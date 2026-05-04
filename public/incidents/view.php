<?php
// ============================================================
// CIRMS – View Incident Detail
// public/incidents/view.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login();

$pdo  = db();
$user = current_user();
$id   = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name,
           u.full_name AS reporter_name, u.email AS reporter_email,
           a.full_name AS assigned_name
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    JOIN users u ON u.id = i.reporter_id
    LEFT JOIN users a ON a.id = i.assigned_to
    WHERE i.id = ?
");
$stmt->execute([$id]);
$inc = $stmt->fetch();

if (!$inc) {
    http_response_code(404);
    die(render_error(404, 'Incident not found.'));
}

// Reporters may only view their own incidents
if ($user['role'] === 'reporter' && $inc['reporter_id'] != $user['id']) {
    http_response_code(403);
    die(render_error(403, 'Access denied.'));
}

// ── Handle POST: status update or add note ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_login(['officer', 'admin']);

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus     = $_POST['status'] ?? '';
        $assignedTo    = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $validStatuses = ['New','Acknowledged','In Progress','Resolved','Closed'];

        if (in_array($newStatus, $validStatuses, true)) {
            $extra = [];
            if ($newStatus === 'Resolved') $extra['resolved_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'Closed')   $extra['closed_at']   = date('Y-m-d H:i:s');

            $pdo->prepare("
                UPDATE incidents
                SET status = ?, assigned_to = ?,
                    resolved_at = COALESCE(?, resolved_at),
                    closed_at   = COALESCE(?, closed_at)
                WHERE id = ?
            ")->execute([
                $newStatus, $assignedTo,
                $extra['resolved_at'] ?? null,
                $extra['closed_at']   ?? null,
                $id
            ]);

            audit_log('incident.status_changed', 'incident', $id, [
                'from' => $inc['status'], 'to' => $newStatus
            ]);
            flash('success', "Incident status updated to \"$newStatus\".");
        }
    }

    if ($action === 'add_note') {
        $body       = trim($_POST['note_body'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        if (strlen($body) >= 5) {
            $pdo->prepare("
                INSERT INTO notes (incident_id, author_id, body, is_internal)
                VALUES (?,?,?,?)
            ")->execute([$id, $user['id'], $body, $isInternal]);
            audit_log('incident.note_added', 'incident', $id);
            flash('success', 'Note added.');
        }
    }

    redirect("/public/incidents/view.php?id=$id");
}

// ── Fetch notes & attachments ────────────────────────────────
$notesQuery = $user['role'] === 'reporter'
    ? "SELECT n.*, u.full_name FROM notes n JOIN users u ON u.id=n.author_id WHERE n.incident_id=? AND n.is_internal=0 ORDER BY n.created_at ASC"
    : "SELECT n.*, u.full_name FROM notes n JOIN users u ON u.id=n.author_id WHERE n.incident_id=? ORDER BY n.created_at ASC";
$notesStmt = $pdo->prepare($notesQuery);
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();

$attStmt = $pdo->prepare("SELECT * FROM attachments WHERE incident_id = ?");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// For assignment dropdown
$officers = [];
if ($user['role'] === 'admin') {
    // Omit is_active in SQL so older `users` tables without that column still work.
    $officers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('officer','admin') ORDER BY full_name")->fetchAll();
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

<div class="row g-3">

    <!-- ── Left: Main Details ─────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Details card -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Incident Details</h2>
                <div class="d-flex gap-2">
                    <span class="badge <?= severity_class($inc['severity']) ?>"><?= e($inc['severity']) ?></span>
                    <span class="status-badge <?= status_class($inc['status']) ?>"><?= e($inc['status']) ?></span>
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
                        $diff = strtotime($inc['sla_deadline']) - time();
                        $cls  = $diff < 0 ? 'sla-breach' : ($diff < 3600 ? 'sla-warning' : 'sla-ok');
                        ?>
                        <span class="<?= $cls ?>">
                            <?= date('d M Y H:i', strtotime($inc['sla_deadline'])) ?>
                        </span>
                    <?php else: ?>—<?php endif; ?>
                </dd>

                <dt class="col-sm-4 text-muted">Ongoing?</dt>
                <dd class="col-sm-8"><?= $inc['is_ongoing'] ? '⚠️ Yes – still ongoing' : 'No – contained' ?></dd>
            </dl>

            <hr>
            <h6 class="fw-bold mb-2">Description</h6>
            <p style="white-space:pre-wrap;font-size:.9rem;"><?= e($inc['description']) ?></p>
        </div>

        <!-- Attachments -->
        <?php if ($attachments): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-paperclip me-1"></i>Attachments</h2>
            </div>
            <ul class="list-unstyled mb-0">
                <?php foreach ($attachments as $att): ?>
                <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                    <i class="bi bi-file-earmark text-muted"></i>
                    <span style="font-size:.875rem;"><?= e($att['original']) ?></span>
                    <span class="text-muted" style="font-size:.78rem;"><?= format_bytes($att['size_bytes']) ?></span>
                    <?php if (in_array($user['role'], ['officer','admin'])): ?>
                    <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $att['id'] ?>"
                       class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="bi bi-download"></i>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Notes / Timeline -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-chat-left-text me-1"></i>Activity Log</h2>
            </div>

            <?php if (empty($notes)): ?>
            <p class="text-muted" style="font-size:.875rem;">No notes yet.</p>
            <?php else: ?>
            <?php foreach ($notes as $note): ?>
            <div class="mb-3 p-3 rounded" style="background:<?= $note['is_internal'] ? '#fff8e7' : '#f8fafc' ?>;border:1px solid <?= $note['is_internal'] ? '#fde68a' : '#dde3ea' ?>;">
                <div class="d-flex justify-content-between mb-1">
                    <strong style="font-size:.875rem;"><?= e($note['full_name']) ?></strong>
                    <span class="text-muted" style="font-size:.78rem;"><?= date('d M Y H:i', strtotime($note['created_at'])) ?></span>
                </div>
                <?php if ($note['is_internal']): ?>
                <span class="badge bg-warning text-dark mb-1" style="font-size:.7rem;">Internal Note</span>
                <?php endif; ?>
                <p class="mb-0" style="font-size:.875rem;white-space:pre-wrap;"><?= e($note['body']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add note form (officers/admins) -->
            <?php if (in_array($user['role'], ['officer','admin'])): ?>
            <hr>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_note">
                <div class="mb-2">
                    <label for="note_body" class="form-label">Add Note</label>
                    <textarea id="note_body" name="note_body" class="form-control" rows="3"
                              placeholder="Add an update, note, or action taken…" required></textarea>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check">
                        <input type="checkbox" id="is_internal" name="is_internal" class="form-check-input" checked>
                        <label for="is_internal" class="form-check-label" style="font-size:.85rem;">
                            Internal note (not visible to reporter)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-dark ms-auto">
                        <i class="bi bi-send me-1"></i> Add Note
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: Actions ────────────────────────────────── -->
    <div class="col-lg-4">
        <?php if (in_array($user['role'], ['officer','admin'])): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Update Incident</h2>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['New','Acknowledged','In Progress','Resolved','Closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $inc['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($user['role'] === 'admin' && $officers): ?>
                <div class="mb-3">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($officers as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $inc['assigned_to'] == $o['id'] ? 'selected':'' ?>>
                            <?= e($o['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-dark w-100">
                    <i class="bi bi-arrow-repeat me-1"></i> Save Changes
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Metadata summary -->
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
                    <strong class="sla-ok"><?= date('d M Y H:i', strtotime($inc['resolved_at'])) ?></strong>
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
