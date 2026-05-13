<?php
// ============================================================
// FILE:    public/incidents/view.php
// PURPOSE: View and manage a single incident.
//          Shows incident details, notes, attachments, timeline.
//          Officers/admins can update status, assign officers,
//          and add notes. Three emails are triggered here:
//
//   EMAIL 1 → Reporter (on status change)
//             Function: notify_status_change()
//             Trigger:  Officer/admin saves a new status value
//             Contains: new status + personalised guidance text
//
//   EMAIL 2 → Assigned IT Officer (on assignment change)
//             Function: notify_officer_assigned()
//             Trigger:  Admin selects an officer in the dropdown
//                       AND the assigned_to value actually changes
//             Contains: severity, SLA deadline, Open button
//
//   EMAIL 3 → Reporter (on public note)
//             Function: notify_note_added()
//             Trigger:  Officer adds a note with is_internal = 0
//             NOTE:     Internal notes (is_internal = 1) send NO email
//             Contains: reference + "log in to read" message
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';

// Load mailer.php — required for all three notify_* calls below
require_once __DIR__ . '/../../modules/notifications/mailer.php';

session_start_secure();
require_login();

$pdo  = db();
$user = current_user();
$id   = (int)($_GET['id'] ?? 0); // incident ID from URL ?id=

// ── Fetch the incident with all related data ──────────────────
// Joins categories, reporter (user), and assigned officer tables
$stmt = $pdo->prepare("
    SELECT i.*,
           c.name      AS category_name,
           u.full_name AS reporter_name,
           u.email     AS reporter_email,
           a.full_name AS assigned_name,
           a.email     AS assigned_email
    FROM   incidents i
    JOIN   categories c ON c.id  = i.category_id
    JOIN   users u      ON u.id  = i.reporter_id
    LEFT JOIN users a   ON a.id  = i.assigned_to
    WHERE  i.id = ?
");
$stmt->execute([$id]);
$inc = $stmt->fetch();

// Return 404 if the incident ID doesn't exist in DB
if (!$inc) {
    http_response_code(404);
    die(render_error(404, 'Incident not found.'));
}

// Reporters can only view their own incidents — block access to others
if ($user['role'] === 'reporter' && $inc['reporter_id'] != $user['id']) {
    http_response_code(403);
    die(render_error(403, 'Access denied.'));
}

// ── Handle POST actions (officers and admins only) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();                        // reject invalid CSRF tokens
    require_login(['officer', 'admin']);  // reporters cannot POST here

    $action = $_POST['action'] ?? '';

    // ══════════════════════════════════════════════════════════
    //  ACTION 1: update_status
    //  Handles both status change AND officer assignment in one
    //  POST because both controls are on the same form.
    // ══════════════════════════════════════════════════════════
    if ($action === 'update_status') {

        $newStatus     = $_POST['status']             ?? '';
        $assignedTo    = (int)($_POST['assigned_to']  ?? 0) ?: null;

        // Only allow these exact status values — reject anything else
        $validStatuses = ['New', 'Acknowledged', 'In Progress', 'Resolved', 'Closed'];

        if (in_array($newStatus, $validStatuses, true)) {

            // Remember who was assigned BEFORE this update
            // so we can detect if the assignment actually changed
            $previousAssignedTo = $inc['assigned_to'] ?? null;

            // Set resolved_at timestamp when status moves to Resolved
            $resolvedAt = ($newStatus === 'Resolved') ? date('Y-m-d H:i:s') : null;

            // Set closed_at timestamp when status moves to Closed
            $closedAt   = ($newStatus === 'Closed')   ? date('Y-m-d H:i:s') : null;

            // ── Update the incident row in the database ────────
            // COALESCE keeps existing timestamps if not setting new ones
            $pdo->prepare("
                UPDATE incidents
                SET    status      = ?,
                       assigned_to = ?,
                       resolved_at = COALESCE(?, resolved_at),
                       closed_at   = COALESCE(?, closed_at)
                WHERE  id = ?
            ")->execute([
                $newStatus,
                $assignedTo,
                $resolvedAt,
                $closedAt,
                $id,
            ]);

            // Record every status transition in the immutable audit log
            audit_log('incident.status_changed', 'incident', $id, [
                'from' => $inc['status'],
                'to'   => $newStatus,
            ]);

            // ── EMAIL 1: Notify reporter of status change ──────
            //
            // Only fires when the status VALUE actually changed.
            // If the admin only changed the assigned officer without
            // changing the status, no email goes to the reporter.
            //
            // notify_status_change() is in mailer.php.
            // It builds a status-specific guidance message so the
            // reporter knows what the new status means for them.
            if ($newStatus !== $inc['status']) {
                notify_status_change(
                    $inc['reporter_email'],   // reporter's email from DB
                    $inc['reporter_name'],    // reporter's name from DB
                    [
                        'id'        => $id,
                        'reference' => $inc['reference'],
                        'title'     => $inc['title'],
                    ],
                    $newStatus                // the new status string
                );
            }

            // ── EMAIL 2: Notify assigned IT officer ────────────
            //
            // Only fires when the assigned_to value actually changed
            // AND a real officer was selected (not "Unassigned" / null).
            //
            // notify_officer_assigned() is in mailer.php.
            // It tells the officer they own this incident and
            // shows their SLA deadline.
            if ($assignedTo && $assignedTo !== (int)$previousAssignedTo) {

                // Fetch the newly assigned officer's name and email
                $officerRow = $pdo->prepare(
                    "SELECT full_name, email FROM users WHERE id = ? LIMIT 1"
                );
                $officerRow->execute([$assignedTo]);
                $officer = $officerRow->fetch();

                if ($officer) {
                    notify_officer_assigned(
                        $officer['email'],     // assigned officer's email
                        $officer['full_name'], // assigned officer's name
                        [
                            'id'        => $id,
                            'reference' => $inc['reference'],
                            'title'     => $inc['title'],
                            'severity'  => $inc['severity'],
                            'category'  => $inc['category_name'],
                        ]
                    );
                }
            }

            flash('success', "Status updated to \"{$newStatus}\". Reporter has been notified by email.");
        }
    }

    // ══════════════════════════════════════════════════════════
    //  ACTION 2: add_note
    //  Saves a note to the notes table.
    //  PUBLIC notes  (is_internal = 0) → email sent to reporter
    //  INTERNAL notes (is_internal = 1) → no email, officers only
    // ══════════════════════════════════════════════════════════
    if ($action === 'add_note') {

        $noteBody   = trim($_POST['note_body'] ?? '');
        // is_internal = 1 if checkbox is checked, 0 if not checked
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;

        // Require at least 5 characters — reject blank/very short notes
        if (strlen($noteBody) >= 5) {

            // Insert the note into the notes table
            $pdo->prepare("
                INSERT INTO notes (incident_id, author_id, body, is_internal)
                VALUES (?, ?, ?, ?)
            ")->execute([$id, $user['id'], $noteBody, $isInternal]);

            audit_log('incident.note_added', 'incident', $id);

            // ── EMAIL 3: Notify reporter about PUBLIC notes only ──
            //
            // is_internal = 0 means the reporter CAN see this note
            // in their incident view. We email them to tell them
            // to log in and read it.
            //
            // is_internal = 1 means the note is for IT staff eyes only.
            // The reporter cannot see it and no email is sent.
            //
            // notify_note_added() is in mailer.php.
            // It tells the reporter a note was posted but does NOT
            // include the note text in the email — the reporter
            // must log in to CIRMS to read it.
            if ($isInternal === 0) {
                notify_note_added(
                    $inc['reporter_email'],
                    $inc['reporter_name'],
                    [
                        'id'        => $id,
                        'reference' => $inc['reference'],
                    ]
                );
                flash('success', 'Note added. Reporter has been notified by email.');
            } else {
                // Internal note — no email, just a confirmation
                flash('success', 'Internal note added — not visible to reporter, no email sent.');
            }
        }
    }

    // Redirect back to this page (PRG pattern — prevents double-submit on refresh)
    redirect("/public/incidents/view.php?id={$id}");
}

// ── Fetch notes ───────────────────────────────────────────────
// Reporters only see public notes (is_internal = 0)
// Officers and admins see ALL notes including internal ones
$notesQuery = $user['role'] === 'reporter'
    ? "SELECT n.*, u.full_name FROM notes n
       JOIN users u ON u.id = n.author_id
       WHERE n.incident_id = ? AND n.is_internal = 0
       ORDER BY n.created_at ASC"
    : "SELECT n.*, u.full_name FROM notes n
       JOIN users u ON u.id = n.author_id
       WHERE n.incident_id = ?
       ORDER BY n.created_at ASC";

$notesStmt = $pdo->prepare($notesQuery);
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();

// Fetch evidence attachments for this incident
$attStmt = $pdo->prepare("SELECT * FROM attachments WHERE incident_id = ?");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// Fetch officers and admins for the assignment dropdown (admin only)
$officers = [];
if ($user['role'] === 'admin') {
    $officers = $pdo->query(
        "SELECT id, full_name FROM users
         WHERE  role IN ('officer','admin')
         ORDER  BY full_name"
    )->fetchAll();
}

$pageTitle = 'Incident ' . $inc['reference'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-file-earmark-text me-2 text-cyan"></i>
            <?= e($inc['reference']) ?>
        </h1>
        <p class="page-subtitle"><?= e($inc['title']) ?></p>
    </div>
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<!-- Flash message from POST redirect -->
<?php $flash = get_flash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3">
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Left column: incident details, attachments, notes ─── -->
    <div class="col-lg-8">

        <!-- Incident detail card -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Incident Details</h2>
                <div class="d-flex gap-2">
                    <!-- Severity badge -->
                    <span class="badge <?= severity_class($inc['severity']) ?>">
                        <?= e($inc['severity']) ?>
                    </span>
                    <!-- Status badge -->
                    <span class="status-badge <?= status_class($inc['status']) ?>">
                        <?= e($inc['status']) ?>
                    </span>
                </div>
            </div>

            <dl class="row mb-0" style="font-size:.9rem;">
                <dt class="col-sm-4 text-muted">Category</dt>
                <dd class="col-sm-8"><?= e($inc['category_name']) ?></dd>

                <dt class="col-sm-4 text-muted">Affected System</dt>
                <dd class="col-sm-8"><?= e($inc['affected_system'] ?: '—') ?></dd>

                <dt class="col-sm-4 text-muted">Incident Time</dt>
                <dd class="col-sm-8"><?= date('d M Y H:i', strtotime($inc['incident_time'])) ?></dd>

                <dt class="col-sm-4 text-muted">Reported By</dt>
                <dd class="col-sm-8"><?= e($inc['reporter_name']) ?></dd>

                <dt class="col-sm-4 text-muted">Assigned To</dt>
                <dd class="col-sm-8"><?= e($inc['assigned_name'] ?? 'Unassigned') ?></dd>

                <dt class="col-sm-4 text-muted">SLA Deadline</dt>
                <dd class="col-sm-8">
                    <?php if ($inc['sla_deadline']): ?>
                        <?php
                        // Calculate time remaining; negative = SLA breach
                        $diff = strtotime($inc['sla_deadline']) - time();
                        $cls  = $diff < 0 ? 'sla-breach' : ($diff < 3600 ? 'sla-warning' : 'sla-ok');
                        ?>
                        <span class="<?= $cls ?>">
                            <?= date('d M Y H:i', strtotime($inc['sla_deadline'])) ?>
                        </span>
                    <?php else: ?>—<?php endif; ?>
                </dd>

                <dt class="col-sm-4 text-muted">Ongoing?</dt>
                <dd class="col-sm-8">
                    <?= $inc['is_ongoing'] ? '⚠️ Yes — still ongoing' : 'No — contained' ?>
                </dd>
            </dl>

            <hr>
            <h6 class="fw-bold mb-2">Description</h6>
            <!-- pre-wrap preserves line breaks in the description text -->
            <p style="white-space:pre-wrap;font-size:.9rem;"><?= e($inc['description']) ?></p>
        </div>

        <!-- Evidence attachments card — only shown if attachments exist -->
        <?php if ($attachments): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-paperclip me-1"></i>Evidence &amp; Attachments
                </h2>
            </div>

            <?php
            // Separate image files for thumbnail preview section
            $images = array_filter($attachments, fn($a) => str_starts_with($a['mime_type'], 'image/'));
            ?>

            <!-- Image thumbnails — only visible to officers and admins -->
            <?php if ($images && in_array($user['role'], ['officer', 'admin'])): ?>
            <div class="mb-4">
                <h6 class="fw-bold mb-3"
                    style="font-size:.85rem;color:var(--muted);text-transform:uppercase;">
                    Image Evidence
                </h6>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($images as $img): ?>
                    <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $img['id'] ?>&action=view"
                       target="_blank" class="d-block border rounded p-1"
                       style="background:#f8fafc;" title="Click to view full image">
                        <img src="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $img['id'] ?>&action=view"
                             alt="<?= e($img['original']) ?>"
                             style="max-height:120px;max-width:200px;object-fit:contain;border-radius:4px;">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Full file list -->
            <h6 class="fw-bold mb-2"
                style="font-size:.85rem;color:var(--muted);text-transform:uppercase;">
                All Files
            </h6>
            <ul class="list-unstyled mb-0">
                <?php foreach ($attachments as $att): ?>
                <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                    <i class="bi bi-file-earmark-<?= str_starts_with($att['mime_type'], 'image/')
                        ? 'image' : 'text' ?> text-muted"></i>
                    <span style="font-size:.875rem;"><?= e($att['original']) ?></span>
                    <span class="text-muted" style="font-size:.78rem;">
                        (<?= format_bytes($att['size_bytes']) ?>)
                    </span>
                    <?php if (in_array($user['role'], ['officer', 'admin'])): ?>
                    <div class="ms-auto d-flex gap-1">
                        <!-- View button opens file in new tab -->
                        <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $att['id'] ?>&action=view"
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <!-- Download button triggers file download -->
                        <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $att['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Notes / Activity log card -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-chat-left-text me-1"></i>Activity Log
                </h2>
            </div>

            <?php if (empty($notes)): ?>
                <p class="text-muted" style="font-size:.875rem;">No notes yet.</p>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                <!-- Internal notes get amber background, public notes get grey -->
                <div class="mb-3 p-3 rounded"
                     style="background:<?= $note['is_internal'] ? '#fff8e7' : '#f8fafc' ?>;
                            border:1px solid <?= $note['is_internal'] ? '#fde68a' : '#dde3ea' ?>;">
                    <div class="d-flex justify-content-between mb-1">
                        <strong style="font-size:.875rem;"><?= e($note['full_name']) ?></strong>
                        <span class="text-muted" style="font-size:.78rem;">
                            <?= date('d M Y H:i', strtotime($note['created_at'])) ?>
                        </span>
                    </div>
                    <!-- Show internal badge so officers know this is confidential -->
                    <?php if ($note['is_internal']): ?>
                        <span class="badge bg-warning text-dark mb-1" style="font-size:.7rem;">
                            Internal Note
                        </span>
                    <?php endif; ?>
                    <p class="mb-0" style="font-size:.875rem;white-space:pre-wrap;">
                        <?= e($note['body']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add note form — shown only to officers and admins -->
            <?php if (in_array($user['role'], ['officer', 'admin'])): ?>
            <hr>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_note">

                <div class="mb-2">
                    <label for="note_body" class="form-label">Add Note</label>
                    <textarea id="note_body" name="note_body" class="form-control" rows="3"
                              placeholder="Write an update or message for the reporter…"
                              required></textarea>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="form-check">
                        <!-- Internal note checkbox — checked by default for safety -->
                        <input type="checkbox" id="is_internal" name="is_internal"
                               class="form-check-input" checked>
                        <label for="is_internal" class="form-check-label" style="font-size:.85rem;">
                            Internal note
                            <span class="text-muted" style="font-size:.78rem;">
                                (hidden from reporter — no email sent)
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-dark ms-auto">
                        <i class="bi bi-send me-1"></i> Add Note
                    </button>
                </div>
                <!-- Reminder to officer about email behaviour -->
                <p class="text-muted mt-1" style="font-size:.75rem;">
                    <i class="bi bi-envelope me-1"></i>
                    Uncheck "Internal note" to send the reporter an email notification.
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right column: update panel + timeline ─────────────── -->
    <div class="col-lg-4">

        <!-- Status/assignment update form — officers and admins only -->
        <?php if (in_array($user['role'], ['officer', 'admin'])): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Update Incident</h2>
            </div>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">

                <!-- Status dropdown — current status pre-selected -->
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['New', 'Acknowledged', 'In Progress', 'Resolved', 'Closed'] as $s): ?>
                        <option value="<?= $s ?>"
                            <?= $inc['status'] === $s ? 'selected' : '' ?>>
                            <?= $s ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Officer assignment dropdown — admin only -->
                <?php if ($user['role'] === 'admin' && $officers): ?>
                <div class="mb-3">
                    <label class="form-label">Assign To IT Officer</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($officers as $o): ?>
                        <option value="<?= $o['id'] ?>"
                            <?= $inc['assigned_to'] == $o['id'] ? 'selected' : '' ?>>
                            <?= e($o['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" style="font-size:.75rem;">
                        <i class="bi bi-envelope me-1"></i>
                        The assigned officer will receive an email notification.
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-arrow-repeat me-1"></i> Save Changes
                </button>

                <!-- Reminder about automatic reporter email -->
                <p class="text-muted mt-2" style="font-size:.75rem;">
                    <i class="bi bi-envelope me-1"></i>
                    Status changes automatically email the reporter.
                </p>
            </form>
        </div>
        <?php endif; ?>

        <!-- Incident timeline card -->
        <div class="cirms-card">
            <h2 class="cirms-card-title mb-3">Timeline</h2>
            <ul class="list-unstyled mb-0" style="font-size:.83rem;">
                <li class="mb-2">
                    <span class="text-muted">Submitted:</span><br>
                    <strong><?= date('d M Y H:i', strtotime($inc['created_at'])) ?></strong>
                </li>
                <?php if ($inc['resolved_at']): ?>
                <li class="mb-2">
                    <span class="text-muted">Resolved:</span><br>
                    <strong class="sla-ok">
                        <?= date('d M Y H:i', strtotime($inc['resolved_at'])) ?>
                    </strong>
                </li>
                <?php endif; ?>
                <?php if ($inc['closed_at']): ?>
                <li class="mb-2">
                    <span class="text-muted">Closed:</span><br>
                    <strong><?= date('d M Y H:i', strtotime($inc['closed_at'])) ?></strong>
                </li>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
