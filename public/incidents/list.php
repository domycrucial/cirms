<?php
// ============================================================
// CIRMS – Incidents List (Officer / Admin view)
// public/incidents/list.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['officer', 'admin']);

$pdo = db();

// ── Filters ──────────────────────────────────────────────────
$status   = $_GET['status']   ?? '';
$severity = $_GET['severity'] ?? '';
$search   = trim($_GET['q']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($status)   { $where[] = 'i.status = ?';   $params[] = $status; }
if ($severity) { $where[] = 'i.severity = ?'; $params[] = $severity; }
if ($search)   {
    $where[]  = '(i.reference LIKE ? OR i.title LIKE ? OR u.full_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM incidents i
    JOIN users u ON u.id = i.reporter_id
    WHERE $whereSQL
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name, u.full_name AS reporter_name,
           a.full_name AS assigned_name
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    JOIN users u ON u.id = i.reporter_id
    LEFT JOIN users a ON a.id = i.assigned_to
    WHERE $whereSQL
    ORDER BY
        FIELD(i.severity,'Critical','High','Medium','Low'),
        i.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$incidents = $stmt->fetchAll();

$pageTitle = 'All Incidents';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-list-ul me-2 text-cyan"></i>Incidents</h1>
        <p class="page-subtitle"><?= $total ?> incident<?= $total !== 1 ? 's' : '' ?> found</p>
    </div>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="cirms-card mb-3">
    <form id="filterForm" method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" placeholder="Ref, title, reporter…"
                   value="<?= e($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['New','Acknowledged','In Progress','Resolved','Closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Severity</label>
            <select name="severity" class="form-select">
                <option value="">All severities</option>
                <?php foreach (['Critical','High','Medium','Low'] as $sv): ?>
                <option value="<?= $sv ?>" <?= $severity === $sv ? 'selected' : '' ?>><?= $sv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-search me-1"></i> Filter
            </button>
        </div>
        <div class="col-md-2">
            <a href="<?= APP_URL ?>/public/incidents/list.php" class="btn btn-outline-secondary w-100">
                Clear
            </a>
        </div>
    </form>
</div>

<!-- ── Table ─────────────────────────────────────────────── -->
<div class="cirms-card">
    <?php if (empty($incidents)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2rem;"></i>
        <p class="mt-2">No incidents match your filters.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="cirms-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Reporter</th>
                    <th>Assigned To</th>
                    <th>Submitted</th>
                    <th>SLA</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incidents as $inc):
                // SLA indicator
                $now = time();
                $deadline = $inc['sla_deadline'] ? strtotime($inc['sla_deadline']) : null;
                $slaClass = 'sla-ok';
                $slaLabel = $deadline ? date('d M H:i', $deadline) : '—';
                if ($deadline && !in_array($inc['status'],['Resolved','Closed'])) {
                    $diff = $deadline - $now;
                    if ($diff < 0)        $slaClass = 'sla-breach';
                    elseif ($diff < 3600) $slaClass = 'sla-warning';
                }
            ?>
                <tr>
                    <td><span class="ref-number"><?= e($inc['reference']) ?></span></td>
                    <td style="max-width:200px;">
                        <span class="d-block text-truncate" title="<?= e($inc['title']) ?>">
                            <?= e($inc['title']) ?>
                        </span>
                    </td>
                    <td style="font-size:.8rem;"><?= e($inc['category_name']) ?></td>
                    <td>
                        <span class="badge <?= severity_class($inc['severity']) ?>">
                            <?= e($inc['severity']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= status_class($inc['status']) ?>">
                            <?= e($inc['status']) ?>
                        </span>
                    </td>
                    <td style="font-size:.85rem;"><?= e($inc['reporter_name']) ?></td>
                    <td style="font-size:.85rem;"><?= e($inc['assigned_name'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.8rem;">
                        <?= date('d M Y', strtotime($inc['created_at'])) ?>
                    </td>
                    <td class="<?= $slaClass ?>" style="font-size:.78rem;"><?= $slaLabel ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/public/incidents/view.php?ref=<?= e($inc['reference']) ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="d-flex justify-content-center mt-3">
        <nav>
            <ul class="pagination pagination-sm">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($status) ?>&severity=<?= urlencode($severity) ?>&q=<?= urlencode($search) ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
