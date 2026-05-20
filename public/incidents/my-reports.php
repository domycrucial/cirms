<?php
// ============================================================
// CIRMS – My Reports (Reporter view)
// public/incidents/my-reports.php
//
// Shows the logged-in reporter's own submitted incidents with:
//   – Pagination (20 per page)
//   – SLA status indicator per row
//   – Status and severity badges
//   – Quick summary stats above the table
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['reporter']);

$pdo  = db();
$uid  = (int) current_user()['id'];

// ── Pagination ────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ── Quick summary counts for header strip ─────────────────────
$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                              AS total,
        SUM(CASE WHEN status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS open,
        SUM(CASE WHEN status IN     ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN sla_deadline < NOW()
                  AND status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS breached
    FROM incidents
    WHERE reporter_id = ?
");
$summaryStmt->execute([$uid]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$summary = array_map('intval', $summary);

// ── Total count for pagination ────────────────────────────────
$total = $summary['total'];
$pages = (int) ceil($total / $perPage);

// ── Incidents for this page ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT i.*, c.name AS category_name
    FROM   incidents i
    JOIN   categories c ON c.id = i.category_id
    WHERE  i.reporter_id = ?
    ORDER  BY i.created_at DESC
    LIMIT  $perPage OFFSET $offset
");
$stmt->execute([$uid]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Reports';
include __DIR__ . '/../../includes/header.php';
?>

<!-- ── Page Header ────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-file-earmark-text me-2 text-cyan"></i>My Reports
        </h1>
        <p class="page-subtitle">
            <?= $total ?> report<?= $total !== 1 ? 's' : '' ?> submitted
        </p>
    </div>
    <a href="<?= APP_URL ?>/public/incidents/report.php"
       class="btn btn-dark btn-cirms btn-primary-cirms">
        <i class="bi bi-plus-circle me-1"></i> New Report
    </a>
</div>

<!-- ── Mini summary strip ─────────────────────────────────── -->
<?php if ($total > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-enter" style="animation-delay:0s">
            <div class="stat-icon cyan"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="stat-value"><?= $summary['total'] ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-enter" style="animation-delay:.07s">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $summary['open'] ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-enter" style="animation-delay:.14s">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="stat-value"><?= $summary['resolved'] ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-enter <?= $summary['breached'] > 0 ? 'stat-card-critical' : '' ?>"
             style="animation-delay:.21s">
            <div class="stat-icon red"><i class="bi bi-alarm-fill"></i></div>
            <div>
                <div class="stat-value"><?= $summary['breached'] ?></div>
                <div class="stat-label">SLA Breached</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Incidents table ────────────────────────────────────── -->
<div class="cirms-card">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.4;"></i>
        <p class="mb-0">You have not submitted any incident reports yet.</p>
        <a href="<?= APP_URL ?>/public/incidents/report.php" class="btn btn-dark btn-sm mt-3">
            <i class="bi bi-plus-circle me-1"></i> Submit your first report
        </a>
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
                    <th>Submitted</th>
                    <th>SLA</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $inc):
                $isClosed = in_array($inc['status'], ['Resolved', 'Closed'], true);
                $deadline = $inc['sla_deadline'] ? strtotime($inc['sla_deadline']) : 0;
                $now      = time();
                $breached = !$isClosed && $deadline && $deadline < $now;
                $warning  = !$isClosed && $deadline && ($deadline - $now) < 3600 && !$breached;
            ?>
            <tr class="<?= $breached ? 'row-sla-breach' : '' ?>">
                <td><span class="ref-number"><?= e($inc['reference']) ?></span></td>
                <td style="max-width:200px;">
                    <span class="d-block text-truncate" title="<?= e($inc['title']) ?>">
                        <?= e($inc['title']) ?>
                    </span>
                </td>
                <td style="font-size:.82rem;"><?= e($inc['category_name']) ?></td>
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
                <td class="text-muted" style="font-size:.78rem;white-space:nowrap;">
                    <?= date('d M Y', strtotime($inc['created_at'])) ?>
                </td>
                <td style="font-size:.75rem;font-weight:600;">
                    <?php if ($isClosed): ?>
                        <span style="color:#16a34a;">✓ Met</span>
                    <?php elseif ($breached): ?>
                        <span style="color:#ef4444;">⚠ Breached</span>
                    <?php elseif ($warning): ?>
                        <span style="color:#f59e0b;">⚡ &lt;1h left</span>
                    <?php else: ?>
                        <span style="color:#94a3b8;">OK</span>
                    <?php endif; ?>
                </td>
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
    <div class="d-flex justify-content-between align-items-center mt-3 px-1 flex-wrap gap-2">
        <span class="text-muted" style="font-size:.8rem;">
            Page <?= $page ?> of <?= $pages ?> &middot; <?= $total ?> incidents
        </span>
        <nav aria-label="My reports pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
                endif;
                for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor;
                if ($end < $pages):
                    if ($end < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pages ?>"><?= $pages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
