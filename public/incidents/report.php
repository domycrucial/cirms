<?php
// ============================================================
// CIRMS – Submit Incident Report
// public/incidents/report.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login();   // any logged-in user can report

$pdo = db();
// Load categories without requiring `is_active` in SQL (optional column on some DBs).
$__cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$categories = array_values(array_filter($__cats, static function (array $c): bool {
    return !array_key_exists('is_active', $c) || (int) $c['is_active'] === 1;
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ── Server-side validation ───────────────────────────────
    $errors = [];

    $title       = trim($_POST['title'] ?? '');
    $cat_id      = (int)($_POST['category_id'] ?? 0);
    $severity    = $_POST['severity'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $affected    = trim($_POST['affected_system'] ?? '');
    $is_ongoing  = isset($_POST['is_ongoing']) ? 1 : 0;
    $inc_time    = $_POST['incident_time'] ?? date('Y-m-d\TH:i');
    $consent     = isset($_POST['consent']);

    $validSeverities = ['Low','Medium','High','Critical'];
    $validCatIds     = array_column($categories, 'id');

    if (!$title || strlen($title) < 5)              $errors[] = 'Title is required (min 5 characters).';
    if (!in_array($cat_id, $validCatIds, true))     $errors[] = 'Please select a valid incident category.';
    if (!in_array($severity, $validSeverities,true))$errors[] = 'Please select a valid severity level.';
    if (strlen($description) < 50)                  $errors[] = 'Description must be at least 50 characters.';
    if (!$consent)                                  $errors[] = 'You must acknowledge the data processing consent.';

    // ── File Upload Validation ───────────────────────────────
    $savedFiles = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
        foreach ($_FILES['attachments']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['attachments']['size'][$i] > $maxBytes) {
                $errors[] = "File '{$_FILES['attachments']['name'][$i]}' exceeds " . UPLOAD_MAX_MB . " MB.";
                continue;
            }
            $mime = mime_content_type($tmpName);
            if (!in_array($mime, UPLOAD_ALLOWED_TYPES, true)) {
                $errors[] = "File type '$mime' is not allowed.";
                continue;
            }
            // Rename to prevent path traversal
            $ext  = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
            $safe = bin2hex(random_bytes(12)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
            $savedFiles[] = [
                'tmp'      => $tmpName,
                'stored'   => $safe,
                'original' => basename($_FILES['attachments']['name'][$i]),
                'mime'     => $mime,
                'size'     => $_FILES['attachments']['size'][$i],
            ];
        }
    }

    // ── Save to DB ───────────────────────────────────────────
    if (empty($errors)) {
        $ref      = generate_reference();
        $deadline = sla_deadline($severity);
        $incTime  = date('Y-m-d H:i:s', strtotime($inc_time));

        $stmt = $pdo->prepare("
            INSERT INTO incidents
                (reference, reporter_id, category_id, severity, title,
                 description, affected_system, is_ongoing, incident_time, sla_deadline)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $ref, $_SESSION['user_id'], $cat_id, $severity, $title,
            $description, $affected, $is_ongoing, $incTime, $deadline
        ]);
        $incidentId = (int)$pdo->lastInsertId();

        // ── Move uploaded files ──────────────────────────────
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0750, true);
        foreach ($savedFiles as $file) {
            move_uploaded_file($file['tmp'], UPLOAD_DIR . $file['stored']);
            $pdo->prepare("
                INSERT INTO attachments (incident_id, filename, original, mime_type, size_bytes, uploaded_by)
                VALUES (?,?,?,?,?,?)
            ")->execute([
                $incidentId, $file['stored'], $file['original'],
                $file['mime'], $file['size'], $_SESSION['user_id']
            ]);
        }

        audit_log('incident.submitted', 'incident', $incidentId, ['ref' => $ref]);

        // ── Queue notification (simplified) ─────────────────
        $pdo->prepare("
            INSERT INTO notifications (user_id, incident_id, type, subject, status)
            VALUES (?, ?, 'incident.submitted', ?, 'pending')
        ")->execute([
            $_SESSION['user_id'], $incidentId,
            "New Incident Reported: $ref – $severity severity"
        ]);

        flash('success', "Incident $ref submitted successfully. The IT security team has been notified.");
        redirect("/public/incidents/view.php?id=$incidentId");
    }
}

$pageTitle = 'Report Incident';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-plus-circle-fill me-2 text-cyan"></i>Report an Incident</h1>
        <p class="page-subtitle">Complete the form below. All fields marked * are required.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Please fix the following errors:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="cirms-card" style="max-width:860px;">
<form method="POST" action="" enctype="multipart/form-data" id="incidentForm">
    <?= csrf_field() ?>

    <!-- Title -->
    <div class="mb-3">
        <label for="title" class="form-label">Incident Title *</label>
        <input type="text" id="title" name="title" class="form-control"
               placeholder="Brief summary of the incident"
               value="<?= e($_POST['title'] ?? '') ?>" required>
        <div class="form-hint">A short, clear title. Example: "Phishing email from fake IT department"</div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Category -->
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

        <!-- Severity -->
        <div class="col-md-6">
            <label for="severity" class="form-label">Severity Level *</label>
            <select id="severity" name="severity" class="form-select" required>
                <option value="">— Select severity —</option>
                <option value="Low"      <?= (($_POST['severity']??'') === 'Low')      ? 'selected':'' ?>>🟢 Low – Minor inconvenience</option>
                <option value="Medium"   <?= (($_POST['severity']??'') === 'Medium')   ? 'selected':'' ?>>🟡 Medium – Moderate impact</option>
                <option value="High"     <?= (($_POST['severity']??'') === 'High')     ? 'selected':'' ?>>🟠 High – Significant threat</option>
                <option value="Critical" <?= (($_POST['severity']??'') === 'Critical') ? 'selected':'' ?>>🔴 Critical – Immediate danger</option>
            </select>
            <div class="form-hint">If unsure, choose Medium. The IT team will adjust if needed.</div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Affected System -->
        <div class="col-md-6">
            <label for="affected_system" class="form-label">Affected System / Service</label>
            <input type="text" id="affected_system" name="affected_system" class="form-control"
                   placeholder="e.g. Student portal, Campus Wi-Fi, Email"
                   value="<?= e($_POST['affected_system'] ?? '') ?>">
        </div>

        <!-- Incident Time -->
        <div class="col-md-6">
            <label for="incident_time" class="form-label">Date &amp; Time of Incident *</label>
            <input type="datetime-local" id="incident_time" name="incident_time" class="form-control"
                   value="<?= e($_POST['incident_time'] ?? date('Y-m-d\TH:i')) ?>" required>
        </div>
    </div>

    <!-- Description -->
    <div class="mb-3">
        <label for="description" class="form-label">Detailed Description *</label>
        <textarea id="description" name="description" class="form-control"
                  rows="6" required
                  placeholder="Describe what happened, when you noticed it, what actions you took, and any other details that may help the IT team investigate."><?= e($_POST['description'] ?? '') ?></textarea>
        <div class="form-hint" id="descCounter">Minimum 50 characters required.</div>
    </div>

    <!-- Ongoing -->
    <div class="mb-3 form-check">
        <input type="checkbox" id="is_ongoing" name="is_ongoing" class="form-check-input"
               <?= isset($_POST['is_ongoing']) ? 'checked' : '' ?>>
        <label for="is_ongoing" class="form-check-label">
            The incident is <strong>still ongoing</strong> (not yet contained)
        </label>
    </div>

    <!-- Attachments -->
    <div class="mb-3">
        <label for="attachments" class="form-label">Evidence Attachments</label>
        <input type="file" id="attachments" name="attachments[]" class="form-control" multiple
               accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx">
        <div class="form-hint">
            Screenshots, documents, or other evidence. Max 10 MB per file.
            Allowed types: JPG, PNG, GIF, PDF, TXT, DOC, DOCX.
        </div>
    </div>

    <!-- Consent -->
    <div class="mb-4 p-3 rounded" style="background:#f8fafc;border:1px solid #dde3ea;">
        <div class="form-check">
            <input type="checkbox" id="consent" name="consent" class="form-check-input" required
                   <?= isset($_POST['consent']) ? 'checked' : '' ?>>
            <label for="consent" class="form-check-label" style="font-size:.875rem;">
                I consent to the university storing and processing the information I have provided
                for the purpose of investigating and resolving this cybersecurity incident, in accordance
                with the institution's data protection policy.
            </label>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms">
            <i class="bi bi-send-fill me-1"></i> Submit Incident Report
        </button>
        <a href="<?= APP_URL ?>/public/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
