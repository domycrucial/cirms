<?php
// ============================================================
// FILE:    public/incidents/report.php
// PURPOSE: Incident submission form for logged-in users.
//          On POST: validates, saves to DB, moves files,
//          then fires TWO emails simultaneously:
//
//   EMAIL 1 → IT Security Team
//             Fires: immediately on submit
//             Function: notify_new_incident()
//             Contains: full incident details + Open button
//
//   EMAIL 2 → The reporter (the user who just submitted)
//             Fires: same moment as EMAIL 1
//             Function: notify_submission_confirmation()
//             Contains: reference number + SLA time + Track button
//
// Both emails use PHPMailer SMTP via mailer.php.
// The incident ALWAYS saves even if email fails.
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';

// Load mailer.php — makes notify_new_incident() and
// notify_submission_confirmation() available in this file
require_once __DIR__ . '/../../modules/notifications/mailer.php';

session_start_secure();
require_login(); // any authenticated user (student or staff) can report

$pdo = db();

// Fetch all active incident categories for the dropdown.
// PHP-side filter avoids needing is_active column in all DB setups.
$__cats     = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$categories = array_values(array_filter($__cats, static function (array $c): bool {
    return !array_key_exists('is_active', $c) || (int) $c['is_active'] === 1;
}));

// ── Handle POST submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Reject requests missing a valid CSRF token (security check)
    verify_csrf();

    // ── Collect and sanitise all POST fields ──────────────────
    $errors      = [];
    $title       = trim($_POST['title']           ?? '');
    $cat_id      = (int)($_POST['category_id']    ?? 0);
    $severity    = $_POST['severity']             ?? '';
    $description = trim($_POST['description']     ?? '');
    $affected    = trim($_POST['affected_system'] ?? '');
    $is_ongoing  = isset($_POST['is_ongoing']) ? 1 : 0;
    $inc_time    = $_POST['incident_time']        ?? date('Y-m-d\TH:i');
    $consent     = isset($_POST['consent']);

    // ── Server-side validation ─────────────────────────────────
    // JavaScript validation can be bypassed — always validate in PHP
    $validSeverities = ['Low', 'Medium', 'High', 'Critical'];
    $validCatIds     = array_column($categories, 'id');

    if (!$title || strlen($title) < 5)
        $errors[] = 'Title is required (minimum 5 characters).';

    if (!in_array($cat_id, $validCatIds, true))
        $errors[] = 'Please select a valid incident category.';

    if (!in_array($severity, $validSeverities, true))
        $errors[] = 'Please select a valid severity level.';

    if (strlen($description) < 50)
        $errors[] = 'Description must be at least 50 characters.';

    if (!$consent)
        $errors[] = 'You must acknowledge the data processing consent.';

    // ── File upload validation ─────────────────────────────────
    $savedFiles = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024; // convert MB to bytes

        foreach ($_FILES['attachments']['tmp_name'] as $i => $tmpName) {

            // Skip any file that the browser reported as a failed upload
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;

            // Reject files that exceed the size limit set in config.php
            if ($_FILES['attachments']['size'][$i] > $maxBytes) {
                $errors[] = "File '{$_FILES['attachments']['name'][$i]}' exceeds " . UPLOAD_MAX_MB . " MB.";
                continue;
            }

            // Validate MIME type against the whitelist in config.php
            $mime = mime_content_type($tmpName);
            if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true)) {
                $errors[] = "File type '{$mime}' is not allowed.";
                continue;
            }

            // Generate a random storage filename — prevents directory traversal
            // and filename collision; original name is kept in the DB
            $ext  = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
            $safe = bin2hex(random_bytes(12)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);

            // Queue this file for moving after DB insert succeeds
            $savedFiles[] = [
                'tmp'      => $tmpName,
                'stored'   => $safe,
                'original' => basename($_FILES['attachments']['name'][$i]),
                'mime'     => $mime,
                'size'     => $_FILES['attachments']['size'][$i],
            ];
        }
    }

    // ── Only save to DB when there are no validation errors ────
    if (empty($errors)) {

        // Generate unique reference number e.g. INC-2025-0042
        $ref = generate_reference();

        // Calculate SLA deadline timestamp based on severity hours
        // defined in config.php as SLA_HOURS array
        $deadline = sla_deadline($severity);

        // Convert HTML datetime-local value "2025-06-01T14:30"
        // to MySQL DATETIME format "2025-06-01 14:30:00"
        $incTime = date('Y-m-d H:i:s', strtotime($inc_time));

        // ── INSERT incident row into the database ──────────────
        $stmt = $pdo->prepare("
            INSERT INTO incidents
                (reference, reporter_id, category_id, severity, title,
                 description, affected_system, is_ongoing, incident_time, sla_deadline)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ref,
            $_SESSION['user_id'],   // the logged-in user ID from session
            $cat_id,
            $severity,
            $title,
            $description,
            $affected,
            $is_ongoing,
            $incTime,
            $deadline,
        ]);

        // Capture the auto-generated primary key of the new row
        $incidentId = (int) $pdo->lastInsertId();

        // ── Move uploaded files from PHP temp dir to storage ───
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0750, true);

        foreach ($savedFiles as $file) {
            // Move from PHP temp to permanent storage directory
            move_uploaded_file($file['tmp'], UPLOAD_DIR . $file['stored']);

            // Save attachment record to the attachments table
            $pdo->prepare("
                INSERT INTO attachments
                    (incident_id, filename, original, mime_type, size_bytes, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $incidentId,
                $file['stored'],    // randomised on-disk filename
                $file['original'],  // original name shown in UI
                $file['mime'],
                $file['size'],
                $_SESSION['user_id'],
            ]);
        }

        // Write to immutable audit log — who submitted what and when
        audit_log('incident.submitted', 'incident', $incidentId, ['ref' => $ref]);

        // ── EMAIL 1: Instant alert to IT Security Team ────────
        //
        // notify_new_incident() is in mailer.php.
        // It queries the DB for full incident details, builds a
        // branded HTML email, and sends it via Gmail SMTP to
        // NOTIFY_IT_EMAIL defined in config.php.
        //
        // The IT officer receives this in their Gmail inbox
        // the instant the user clicks Submit — no queue, no delay.
        //
        // $_SESSION['user_name']  — set during login in login.php
        // $_SESSION['user_email'] — set during login in login.php
        $emailToIT = notify_new_incident(
            $incidentId,
            $_SESSION['user_name'],
            $_SESSION['user_email']
        );

        // ── EMAIL 2: Confirmation receipt to the reporter ─────
        //
        // notify_submission_confirmation() is in mailer.php.
        // Sends the reporter a receipt showing:
        //   — their reference number (for tracking and follow-up)
        //   — expected response time based on severity SLA
        //   — a button to track their report in CIRMS
        $emailToReporter = notify_submission_confirmation(
            $_SESSION['user_email'], // reporter's own email address
            $_SESSION['user_name'],  // reporter's full name for salutation
            $ref,                    // reference number e.g. INC-2025-0042
            $severity,               // used to calculate SLA display text
            $title,                  // shown in the confirmation email
            $incidentId              // used to build the track link URL
        );

        // ── Flash message reflects email delivery outcome ─────
        if ($emailToIT && $emailToReporter) {
            // Both emails delivered — best case
            flash('success',
                "Incident {$ref} submitted. The IT team has been alerted " .
                "and a confirmation has been sent to your email."
            );
        } elseif ($emailToIT) {
            // Only IT email delivered — reporter email failed
            flash('success',
                "Incident {$ref} submitted. The IT team has been alerted."
            );
        } else {
            // Both emails failed — incident is still saved
            flash('success',
                "Incident {$ref} submitted. The IT team will see your " .
                "report in the CIRMS dashboard."
            );
        }

        // Redirect reporter to view their newly submitted incident
        redirect("/public/incidents/view.php?id={$incidentId}");
    }
    // If $errors not empty, fall through to re-render the form
    // with error messages shown at the top
}

$pageTitle = 'Report Incident';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-plus-circle-fill me-2 text-cyan"></i>Report an Incident
        </h1>
        <p class="page-subtitle">
            Complete the form below. All fields marked * are required.
        </p>
    </div>
</div>

<!-- Show validation errors if any -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>
        <i class="bi bi-exclamation-triangle-fill me-1"></i>Please fix the following errors:
    </strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="cirms-card" style="max-width:860px;">
<form method="POST" action="" enctype="multipart/form-data" id="incidentForm">

    <!-- CSRF hidden token — required on every POST form -->
    <?= csrf_field() ?>

    <!-- Incident title -->
    <div class="mb-3">
        <label for="title" class="form-label">Incident Title *</label>
        <input type="text" id="title" name="title" class="form-control"
               placeholder="Brief summary — e.g. Phishing email from fake IT department"
               value="<?= e($_POST['title'] ?? '') ?>" required>
        <div class="form-hint">Minimum 5 characters. Be specific and clear.</div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Category — loaded from categories table -->
        <div class="col-md-6">
            <label for="category_id" class="form-label">Incident Category *</label>
            <select id="category_id" name="category_id" class="form-select" required>
                <option value="">— Select category —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"
                    <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Severity — affects SLA deadline and email subject priority -->
        <div class="col-md-6">
            <label for="severity" class="form-label">Severity Level *</label>
            <select id="severity" name="severity" class="form-select" required>
                <option value="">— Select severity —</option>
                <option value="Low"
                    <?= (($_POST['severity'] ?? '') === 'Low')      ? 'selected' : '' ?>>
                    🟢 Low — Minor, no immediate risk
                </option>
                <option value="Medium"
                    <?= (($_POST['severity'] ?? '') === 'Medium')   ? 'selected' : '' ?>>
                    🟡 Medium — Moderate concern
                </option>
                <option value="High"
                    <?= (($_POST['severity'] ?? '') === 'High')     ? 'selected' : '' ?>>
                    🟠 High — Significant threat
                </option>
                <option value="Critical"
                    <?= (($_POST['severity'] ?? '') === 'Critical') ? 'selected' : '' ?>>
                    🔴 Critical — Immediate danger
                </option>
            </select>
            <div class="form-hint">If unsure, select Medium — the IT team will reassess.</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Affected system -->
        <div class="col-md-6">
            <label for="affected_system" class="form-label">Affected System / Service</label>
            <input type="text" id="affected_system" name="affected_system" class="form-control"
                   placeholder="e.g. Student portal, Campus Wi-Fi, Email system"
                   value="<?= e($_POST['affected_system'] ?? '') ?>">
        </div>

        <!-- When the incident happened — defaults to now -->
        <div class="col-md-6">
            <label for="incident_time" class="form-label">Date &amp; Time of Incident *</label>
            <input type="datetime-local" id="incident_time" name="incident_time"
                   class="form-control"
                   value="<?= e($_POST['incident_time'] ?? date('Y-m-d\TH:i')) ?>" required>
        </div>
    </div>

    <!-- Detailed description — minimum 50 chars enforced server-side -->
    <div class="mb-3">
        <label for="description" class="form-label">Detailed Description *</label>
        <textarea id="description" name="description" class="form-control"
                  rows="6" required
                  placeholder="Describe what happened, when you noticed it, what actions you took, and any other details that will help the IT team investigate."
        ><?= e($_POST['description'] ?? '') ?></textarea>
        <div class="form-hint" id="descCounter">Minimum 50 characters required.</div>
    </div>

    <!-- Ongoing incident flag -->
    <div class="mb-3 form-check">
        <input type="checkbox" id="is_ongoing" name="is_ongoing" class="form-check-input"
               <?= isset($_POST['is_ongoing']) ? 'checked' : '' ?>>
        <label for="is_ongoing" class="form-check-label">
            The incident is <strong>still ongoing</strong> (not yet contained)
        </label>
    </div>

    <!-- Evidence file attachments -->
    <div class="mb-3">
        <label for="attachments" class="form-label">Evidence Attachments</label>
        <input type="file" id="attachments" name="attachments[]" class="form-control"
               multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx">
        <div class="form-hint">
            Screenshots, documents, or other evidence.
            Max <?= UPLOAD_MAX_MB ?> MB per file. Allowed: JPG, PNG, GIF, PDF, TXT, DOC, DOCX.
        </div>
    </div>

    <!-- Data processing consent — required before submission -->
    <div class="mb-4 p-3 rounded" style="background:#f8fafc;border:1px solid #dde3ea;">
        <div class="form-check">
            <input type="checkbox" id="consent" name="consent" class="form-check-input"
                   required <?= isset($_POST['consent']) ? 'checked' : '' ?>>
            <label for="consent" class="form-check-label" style="font-size:.875rem;">
                I consent to the university storing and processing the information I have
                provided for the purpose of investigating and resolving this cybersecurity
                incident, in accordance with the institution's data protection policy.
            </label>
        </div>
    </div>

    <!-- Submit and Cancel buttons -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms">
            <i class="bi bi-send-fill me-1"></i> Submit Incident Report
        </button>
        <a href="<?= APP_URL ?>/public/dashboard.php" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>

</form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
