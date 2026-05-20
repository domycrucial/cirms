<?php
// ============================================================
// CIRMS – Incident Detail View
// public/incidents/view.php
// Accessed via ?ref=INC-YYYY-NNNN (reference number, not raw ID)
// ============================================================

set_time_limit(90);
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/notifications/mailer.php';
session_start_secure();
require_login();

$pdo  = db();
$user = current_user();

// ── Resolve incident by reference (hides sequential DB id) ───
$ref = strtoupper(trim($_GET['ref'] ?? ''));
if (!preg_match('/^INC-\d{4}-\d{4}$/', $ref)) {
    http_response_code(404);
    die(render_error(404, 'Incident not found.'));
}

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
    WHERE  i.reference = ?
");
$stmt->execute([$ref]);
$inc = $stmt->fetch();

if (!$inc) {
    http_response_code(404);
    die(render_error(404, 'Incident not found.'));
}

$id = (int)$inc['id']; // internal ID — never exposed in URLs

// Reporters can only view their own incidents
if ($user['role'] === 'reporter' && $inc['reporter_id'] != $user['id']) {
    http_response_code(403);
    die(render_error(403, 'Access denied.'));
}

// ── POST actions (officers and admins only) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_login(['officer', 'admin']);

    $action = $_POST['action'] ?? '';

    // Action 1: update status + assignment
    if ($action === 'update_status') {
        $newStatus  = $_POST['status'] ?? '';
        $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $validStatuses = ['New', 'Acknowledged', 'In Progress', 'Resolved', 'Closed'];

        if (in_array($newStatus, $validStatuses, true)) {
            $previousAssignedTo = $inc['assigned_to'] ?? null;
            $resolvedAt = ($newStatus === 'Resolved') ? date('Y-m-d H:i:s') : null;
            $closedAt   = ($newStatus === 'Closed')   ? date('Y-m-d H:i:s') : null;

            $pdo->prepare("
                UPDATE incidents
                SET    status      = ?,
                       assigned_to = ?,
                       resolved_at = COALESCE(?, resolved_at),
                       closed_at   = COALESCE(?, closed_at)
                WHERE  id = ?
            ")->execute([$newStatus, $assignedTo, $resolvedAt, $closedAt, $id]);

            audit_log('incident.status_changed', 'incident', $id, [
                'from' => $inc['status'],
                'to'   => $newStatus,
            ]);

            if ($newStatus !== $inc['status']) {
                notify_status_change(
                    $inc['reporter_email'], $inc['reporter_name'],
                    ['id' => $id, 'reference' => $inc['reference'], 'title' => $inc['title']],
                    $newStatus
                );
            }

            if ($assignedTo && $assignedTo !== (int)$previousAssignedTo) {
                $off = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? LIMIT 1");
                $off->execute([$assignedTo]);
                $officer = $off->fetch();
                if ($officer) {
                    notify_officer_assigned(
                        $officer['email'], $officer['full_name'],
                        ['id' => $id, 'reference' => $inc['reference'],
                         'title' => $inc['title'], 'severity' => $inc['severity'],
                         'category' => $inc['category_name']]
                    );
                }
            }

            flash('success', "Status updated to \"{$newStatus}\".");
        }
    }

    // Action 2: add note
    if ($action === 'add_note') {
        $noteBody   = trim($_POST['note_body'] ?? '');
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;

        if (strlen($noteBody) >= 5) {
            // Sanitise — strip any HTML tags
            $noteBody = strip_tags($noteBody);
            $pdo->prepare("
                INSERT INTO notes (incident_id, author_id, body, is_internal)
                VALUES (?, ?, ?, ?)
            ")->execute([$id, $user['id'], $noteBody, $isInternal]);

            audit_log('incident.note_added', 'incident', $id);

            if ($isInternal === 0) {
                notify_note_added(
                    $inc['reporter_email'], $inc['reporter_name'],
                    ['id' => $id, 'reference' => $inc['reference']]
                );
                flash('success', 'Note added. Reporter notified by email.');
            } else {
                flash('success', 'Internal note saved — not visible to reporter.');
            }
        }
    }

    // PRG redirect — use reference, not raw ID
    redirect("/public/incidents/view.php?ref=" . urlencode($inc['reference']));
}

// ── Fetch related data ────────────────────────────────────────
$notesQuery = $user['role'] === 'reporter'
    ? "SELECT n.*, u.full_name FROM notes n JOIN users u ON u.id = n.author_id
       WHERE n.incident_id = ? AND n.is_internal = 0 ORDER BY n.created_at ASC"
    : "SELECT n.*, u.full_name FROM notes n JOIN users u ON u.id = n.author_id
       WHERE n.incident_id = ? ORDER BY n.created_at ASC";
$notesStmt = $pdo->prepare($notesQuery);
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();

$attStmt = $pdo->prepare("SELECT * FROM attachments WHERE incident_id = ?");
$attStmt->execute([$id]);
$attachments = $attStmt->fetchAll();

// Officers with open-incident workload for assignment dropdown
$officers = [];
if ($user['role'] === 'admin') {
    $officers = $pdo->query("
        SELECT u.id, u.full_name,
               COALESCE(oc.open_count, 0) AS open_count
        FROM   users u
        LEFT JOIN (
            SELECT assigned_to, COUNT(*) AS open_count
            FROM   incidents
            WHERE  status NOT IN ('Resolved','Closed')
              AND  assigned_to IS NOT NULL
            GROUP  BY assigned_to
        ) oc ON oc.assigned_to = u.id
        WHERE  u.role IN ('officer','admin')
        ORDER  BY open_count ASC, u.full_name ASC
    ")->fetchAll();
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

    <!-- ── Left column ─────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Incident detail card -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Incident Details</h2>
                <div class="d-flex gap-2">
                    <span class="badge <?= severity_class($inc['severity']) ?>"><?= e($inc['severity']) ?></span>
                    <span class="status-badge <?= status_class($inc['status']) ?>"><?= e($inc['status']) ?></span>
                </div>
            </div>

            <dl class="row mb-0" style="font-size:.9rem;">
                <dt class="col-sm-4 text-muted">Reference</dt>
                <dd class="col-sm-8"><span class="ref-number"><?= e($inc['reference']) ?></span></dd>

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
                    <?php if ($inc['sla_deadline']):
                        $diff = strtotime($inc['sla_deadline']) - time();
                        $cls  = $diff < 0 ? 'sla-breach' : ($diff < 3600 ? 'sla-warning' : 'sla-ok');
                    ?>
                    <span class="<?= $cls ?>"><?= date('d M Y H:i', strtotime($inc['sla_deadline'])) ?></span>
                    <?php else: ?>—<?php endif; ?>
                </dd>

                <dt class="col-sm-4 text-muted">Ongoing?</dt>
                <dd class="col-sm-8"><?= $inc['is_ongoing'] ? '⚠️ Yes — still ongoing' : 'No — contained' ?></dd>
            </dl>

            <hr>
            <h6 class="fw-bold mb-2">Description</h6>
            <p style="white-space:pre-wrap;font-size:.9rem;"><?= e($inc['description']) ?></p>
        </div>

        <!-- Attachments card -->
        <?php if ($attachments): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-paperclip me-1"></i>Evidence &amp; Attachments</h2>
            </div>
            <?php $images = array_filter($attachments, fn($a) => str_starts_with($a['mime_type'], 'image/')); ?>
            <?php if ($images && in_array($user['role'], ['officer','admin'])): ?>
            <div class="mb-4">
                <h6 class="fw-bold mb-3" style="font-size:.85rem;color:var(--muted);text-transform:uppercase;">Image Evidence</h6>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($images as $img): ?>
                    <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $img['id'] ?>&action=view"
                       target="_blank" class="d-block border rounded p-1" style="background:#f8fafc;">
                        <img src="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $img['id'] ?>&action=view"
                             alt="<?= e($img['original']) ?>"
                             style="max-height:120px;max-width:200px;object-fit:contain;border-radius:4px;">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <h6 class="fw-bold mb-2" style="font-size:.85rem;color:var(--muted);text-transform:uppercase;">All Files</h6>
            <ul class="list-unstyled mb-0">
                <?php foreach ($attachments as $att): ?>
                <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                    <i class="bi bi-file-earmark-<?= str_starts_with($att['mime_type'], 'image/') ? 'image' : 'text' ?> text-muted"></i>
                    <span style="font-size:.875rem;"><?= e($att['original']) ?></span>
                    <span class="text-muted" style="font-size:.78rem;">(<?= format_bytes($att['size_bytes']) ?>)</span>
                    <?php if (in_array($user['role'], ['officer','admin'])): ?>
                    <div class="ms-auto d-flex gap-1">
                        <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $att['id'] ?>&action=view"
                           target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                        <a href="<?= APP_URL ?>/modules/incidents/download.php?id=<?= $att['id'] ?>"
                           class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Notes / Activity log -->
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-chat-left-text me-1"></i>Activity Log</h2>
            </div>

            <?php if (empty($notes)): ?>
            <p class="text-muted" style="font-size:.875rem;">No notes yet.</p>
            <?php else: ?>
            <?php foreach ($notes as $note): ?>
            <div class="mb-3 p-3 rounded"
                 style="background:<?= $note['is_internal'] ? '#fff8e7' : '#f8fafc' ?>;
                        border:1px solid <?= $note['is_internal'] ? '#fde68a' : '#dde3ea' ?>;">
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

            <?php if (in_array($user['role'], ['officer','admin'])): ?>
            <hr>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_note">
                <div class="mb-2">
                    <label for="note_body" class="form-label">Add Note</label>
                    <textarea id="note_body" name="note_body" class="form-control" rows="3"
                              placeholder="Write an update or message for the reporter…" required
                              maxlength="2000"></textarea>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check">
                        <input type="checkbox" id="is_internal" name="is_internal" class="form-check-input" checked>
                        <label for="is_internal" class="form-check-label" style="font-size:.85rem;">
                            Internal note <span class="text-muted" style="font-size:.78rem;">(hidden from reporter)</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-dark ms-auto" id="noteBtn">
                        <i class="bi bi-send me-1"></i> Add Note
                    </button>
                </div>
                <p class="text-muted mt-1" style="font-size:.75rem;">
                    <i class="bi bi-envelope me-1"></i> Uncheck "Internal note" to email the reporter.
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right column ────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Update panel — officers and admins only -->
        <?php if (in_array($user['role'], ['officer','admin'])): ?>
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Update Incident</h2>
            </div>
            <form method="POST" action="" id="updateForm">
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

                <!-- Officer assignment with workload indicator (admin only) -->
                <?php if ($user['role'] === 'admin' && $officers): ?>
                <div class="mb-3">
                    <label class="form-label">Assign To IT Officer</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($officers as $o):
                            $open = (int)$o['open_count'];
                            if ($open === 0) {
                                $badge = '🟢 Free';
                                $style = 'color:#16a34a;';
                            } elseif ($open <= 3) {
                                $badge = '🟡 ' . $open . ' open';
                                $style = 'color:#b45309;';
                            } else {
                                $badge = '🔴 ' . $open . ' open';
                                $style = 'color:#b91c1c;';
                            }
                        ?>
                        <option value="<?= $o['id'] ?>"
                                <?= $inc['assigned_to'] == $o['id'] ? 'selected' : '' ?>>
                            <?= e($o['full_name']) ?> — <?= $badge ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Workload summary chips -->
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <?php foreach ($officers as $o):
                            $open = (int)$o['open_count'];
                            $chipColor = $open === 0 ? '#22c55e' : ($open <= 3 ? '#f59e0b' : '#ef4444');
                            $chipBg    = $open === 0 ? 'rgba(34,197,94,.1)' : ($open <= 3 ? 'rgba(245,158,11,.1)' : 'rgba(239,68,68,.1)');
                        ?>
                        <span style="font-size:.72rem;padding:.15rem .45rem;border-radius:5px;
                                     background:<?= $chipBg ?>;color:<?= $chipColor ?>;
                                     border:1px solid <?= $chipColor ?>33;white-space:nowrap;">
                            <?= e(explode(' ', $o['full_name'])[0]) ?>: <?= $open ?> open
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint mt-1" style="font-size:.75rem;">
                        <i class="bi bi-envelope me-1"></i> Assigned officer receives an email alert.
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-dark w-100" id="updateBtn">
                    <i class="bi bi-arrow-repeat me-1"></i> Save Changes
                </button>
                <p class="text-muted mt-2" style="font-size:.75rem;">
                    <i class="bi bi-envelope me-1"></i> Status changes automatically email the reporter.
                </p>
            </form>
        </div>
        <?php endif; ?>

        <!-- Timeline -->
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

<script>
/* Spinner on form submit buttons */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
