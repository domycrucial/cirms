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

// ── ICT services available at the institute (categorised) ─────
// These populate the "ICT Service" dropdown that replaces the old
// free-text Incident Title field. Services are grouped into categories
// (rendered as <optgroup>s). The submitted title MUST be one of these
// exact values (validated server-side below).
$ictServiceGroups = [
    'Academic Systems' => [
        'ISMS (Student Management System)',
        'eLearning Platform (Moodle)',
        'Online Examination System',
        'Student Portal',
        'Library Information System',
    ],
    'Network & Connectivity' => [
        'Campus Wi-Fi / Network',
        'VPN / Remote Access',
    ],
    'Communication & Web' => [
        'Email Service',
        'Institute Website',
    ],
    'Administration & Finance' => [
        'Fee Payment / Financial System',
        'Staff Portal',
    ],
];
// Flat list of every service — used for server-side validation.
$ictServices = array_merge(...array_values($ictServiceGroups));

// Options for the "Affected System / Service" dropdown — the same ICT
// services (grouped) plus an "Other / Not listed" catch-all.
$affectedOptions = array_merge($ictServices, ['Other / Not listed']);

// ── Service → relevant incident types ─────────────────────────
// Drives the cascading Category dropdown: when a user picks an ICT
// service, only the incident types related to that service (matched by
// name) stay visible. Names MUST match the rows seeded in the categories
// table (see database/migrate_incident_types.php).
$serviceCategoryMap = [
    'ISMS (Student Management System)' => [
        'Unable to log in to ISMS',
        'ISMS overloading',
        'ISMS portal slow loading',
        'Result problem',
        'Timetable not visible',
        'Wrong programme details',
        'Student account not activated',
        'Enrolled in wrong course',
        'Account locked',
        'Password recovering',
    ],
    'eLearning Platform (Moodle)' => [
        'Unable to log in to eLearning',
        'eLearning portal inaccessible',
        'Moodle delaying',
        'Quiz not loading',
        'Assignment upload failure',
        'File upload failing',
        'File format rejected',
        'Missing joining group link',
        'Unable to access course material',
        'Mobile upload failure',
        'Account locked',
        'Password recovering',
    ],
    'Online Examination System' => [
        'Examination ticket not appearing',
        'Quiz not loading',
        '500 Internal Server Error',
    ],
    'Student Portal' => [
        'Poor interface',
        'Poor responsiveness',
        'Timetable not visible',
        'Result problem',
        'Wrong programme details',
    ],
    'Library Information System' => [
        'Poor interface',
        '500 Internal Server Error',
        'Account locked',
    ],
    'Campus Wi-Fi / Network' => [
        'Wi-Fi problem',
        'Wi-Fi slow speed',
        'Internet connection problem',
    ],
    'VPN / Remote Access' => [
        'Internet connection problem',
        'Account locked',
    ],
    'Email Service' => [
        'Password reset email not received',
        'Password recovering',
    ],
    'Institute Website' => [
        '500 Internal Server Error',
        'Poor interface',
        'Poor responsiveness',
    ],
    'Fee Payment / Financial System' => [
        'Payment not appearing',
    ],
    'Staff Portal' => [
        'Account locked',
        'Poor interface',
        '500 Internal Server Error',
        'Password recovering',
    ],
];

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

    // Title is now a dropdown of ICT services — no length limitation.
    // It only has to be one of the predefined services in $ictServices.
    if (!in_array($title, $ictServices, true))
        $errors[] = 'Please select a valid ICT service.';

    if (!in_array($cat_id, $validCatIds, true))
        $errors[] = 'Please select a valid incident category.';

    if (!in_array($severity, $validSeverities, true))
        $errors[] = 'Please select a valid severity level.';

    // Affected system is optional, but if chosen it must be one of the
    // predefined dropdown options.
    if ($affected !== '' && !in_array($affected, $affectedOptions, true))
        $errors[] = 'Please select a valid affected system.';

    // Description has no minimum-length limitation, but special characters
    // are not allowed — only letters, numbers, spaces and basic punctuation
    // (. , ' - ? !). This keeps the description to plain readable text.
    if ($description === '')
        $errors[] = 'Description is required.';
    elseif (!preg_match("/^[a-zA-Z0-9\s.,'\-?!]+$/u", $description))
        $errors[] = 'Description may contain only letters, numbers, spaces and basic punctuation (. , \' - ? !) — no special characters.';

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
        //   — a button to track their report in the IRS portal
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
                "report in the IRS portal."
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

    <!-- Incident title — now a dropdown of the institute's ICT services.
         The list comes from $ictServices (defined at the top of this file).
         There is no length limitation: the value is one of a fixed set. -->
    <div class="mb-3">
        <label for="title" class="form-label">ICT Service *</label>
        <select id="title" name="title" class="form-select" required>
            <option value="">— Select the ICT service —</option>
            <?php foreach ($ictServiceGroups as $groupLabel => $services): ?>
            <optgroup label="<?= e($groupLabel) ?>">
                <?php foreach ($services as $service): ?>
                <option value="<?= e($service) ?>"
                    <?= (($_POST['title'] ?? '') === $service) ? 'selected' : '' ?>>
                    <?= e($service) ?>
                </option>
                <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
        </select>
        <div class="form-hint">Choose the ICT service the incident relates to.</div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Category — loaded from categories table -->
        <div class="col-md-6">
            <label for="category_id" class="form-label">Incident Category *</label>
            <!-- Categories carry data-name so the cascade JS can match them to
                 the selected ICT service (see $serviceCategoryMap below). -->
            <select id="category_id" name="category_id" class="form-select" required>
                <option value="">— Select an ICT service first —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>"
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
                    Low — Minor, no immediate risk
                </option>
                <option value="Medium"
                    <?= (($_POST['severity'] ?? '') === 'Medium')   ? 'selected' : '' ?>>
                    Medium — Moderate concern
                </option>
                <option value="High"
                    <?= (($_POST['severity'] ?? '') === 'High')     ? 'selected' : '' ?>>
                    High — Significant threat
                </option>
                <option value="Critical"
                    <?= (($_POST['severity'] ?? '') === 'Critical') ? 'selected' : '' ?>>
                    Critical — Immediate danger
                </option>
            </select>
            <div class="form-hint">If unsure, select Medium — the IT team will reassess.</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Affected system — dropdown of the same ICT services, plus an
             "Other / Not listed" option for systems outside the list. -->
        <div class="col-md-6">
            <label for="affected_system" class="form-label">Affected System / Service</label>
            <select id="affected_system" name="affected_system" class="form-select">
                <option value="">— Select affected system —</option>
                <?php foreach ($ictServiceGroups as $groupLabel => $services): ?>
                <optgroup label="<?= e($groupLabel) ?>">
                    <?php foreach ($services as $opt): ?>
                    <option value="<?= e($opt) ?>"
                        <?= (($_POST['affected_system'] ?? '') === $opt) ? 'selected' : '' ?>>
                        <?= e($opt) ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
                <option value="Other / Not listed"
                    <?= (($_POST['affected_system'] ?? '') === 'Other / Not listed') ? 'selected' : '' ?>>
                    Other / Not listed
                </option>
            </select>
        </div>

        <!-- When the incident happened — defaults to now -->
        <div class="col-md-6">
            <label for="incident_time" class="form-label">Date &amp; Time of Incident *</label>
            <input type="datetime-local" id="incident_time" name="incident_time"
                   class="form-control"
                   value="<?= e($_POST['incident_time'] ?? date('Y-m-d\TH:i')) ?>" required>
        </div>
    </div>

    <!-- Detailed description — no length limitation. Only letters, numbers,
         spaces and basic punctuation are allowed (special characters blocked). -->
    <div class="mb-3">
        <label for="description" class="form-label">Detailed Description *</label>
        <textarea id="description" name="description" class="form-control description-field"
                  rows="6" required
                  placeholder="Describe what happened, when you noticed it, what actions you took, and any other details that will help the IT team investigate."
        ><?= e($_POST['description'] ?? '') ?></textarea>
        <div class="d-flex justify-content-between align-items-center mt-1">
            <div class="form-hint mb-0" id="descCounter">Letters and basic punctuation only — no special characters.</div>
            <div class="form-hint mb-0" id="descCharCount" style="font-variant-numeric:tabular-nums;"></div>
        </div>
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
    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms" id="submitReportBtn">
            <i class="bi bi-send-fill me-1"></i> Submit Incident Report
        </button>
        <a href="<?= APP_URL ?>/public/dashboard.php" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>

</form>
</div>

<style>
/* ── Description field responsive fix ─────────────────────── */
.description-field {
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: pre-wrap;
    resize: vertical;
    min-height: 150px;
    width: 100%;
    box-sizing: border-box;
}
@media (max-width: 576px) {
    .description-field { min-height: 120px; font-size: .85rem; }
    .cirms-card { padding: 1rem !important; }
}
</style>

<script>
(function () {
    /* ── Cascading Category dropdown ──────────────────────────────
       When the user picks an ICT Service (title), filter the Category
       dropdown to only the categories related to that service. The
       service→category map mirrors $serviceCategoryMap in PHP. */
    var SVC_CAT = <?= json_encode($serviceCategoryMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var titleSel = document.getElementById('title');
    var catSel   = document.getElementById('category_id');

    if (titleSel && catSel) {
        // Snapshot every real category option once (skip the placeholder)
        var allCatOptions = Array.prototype.slice
            .call(catSel.options)
            .filter(function (o) { return o.value !== ''; });
        var placeholder = catSel.options[0];

        function refreshCategories() {
            var service = titleSel.value;
            var allowed = SVC_CAT[service] || [];
            var keepVal = catSel.value;

            // Rebuild: placeholder first, then only matching categories
            catSel.innerHTML = '';
            placeholder.textContent = service
                ? '— Select category —'
                : '— Select an ICT service first —';
            catSel.appendChild(placeholder);

            allCatOptions.forEach(function (opt) {
                if (allowed.indexOf(opt.getAttribute('data-name')) !== -1) {
                    catSel.appendChild(opt);
                }
            });

            // Keep the previous choice only if it is still available
            catSel.value = keepVal;
            if (catSel.value !== keepVal) catSel.value = '';
        }

        titleSel.addEventListener('change', refreshCategories);
        refreshCategories(); // run on load (handles re-render after errors)
    }

    /* ── Description: block special characters + plain char count ──
       Only letters, numbers, spaces and basic punctuation (. , ' - ? !)
       are allowed. Anything else is stripped as the user types. There is
       no minimum-length limitation. */
    var desc    = document.getElementById('description');
    var counter = document.getElementById('descCharCount');
    var DESC_ALLOWED = /[^a-zA-Z0-9\s.,'\-?!]/g;
    if (desc) {
        desc.addEventListener('input', function () {
            // Strip any disallowed special character immediately
            var cleaned = this.value.replace(DESC_ALLOWED, '');
            if (cleaned !== this.value) this.value = cleaned;
            if (counter) counter.textContent = this.value.length + ' chars';
        });
        if (counter) counter.textContent = desc.value.length + ' chars';
    }

    /* ── Submit button spinner ───────────────────────────── */
    var form = document.getElementById('incidentForm');
    var btn  = document.getElementById('submitReportBtn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                + 'Submitting…';
        });
    }
}());
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
