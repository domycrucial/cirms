<?php
// ============================================================
// CIRMS – Dashboard
// public/dashboard.php
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
session_start_secure();
require_login();

$user = current_user();
$pdo  = db();

// ── Stat Counts ──────────────────────────────────────────────
if ($user['role'] === 'reporter') {
    // Reporter sees only their own incidents
    $uid   = $user['id'];
    $total = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ?");
    $total->execute([$uid]);

    $open  = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ? AND status NOT IN ('Resolved','Closed')");
    $open->execute([$uid]);

    $resolved = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ? AND status IN ('Resolved','Closed')");
    $resolved->execute([$uid]);

    $critical = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporter_id = ? AND severity='Critical' AND status NOT IN ('Resolved','Closed')");
    $critical->execute([$uid]);

    $recent = $pdo->prepare("
        SELECT i.*, c.name AS category_name
        FROM incidents i
        JOIN categories c ON c.id = i.category_id
        WHERE i.reporter_id = ?
        ORDER BY i.created_at DESC LIMIT 10
    ");
    $recent->execute([$uid]);
} else {
    // Officers & admins see all incidents
    $total    = $pdo->query("SELECT COUNT(*) FROM incidents");
    $open     = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')");
    $resolved = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Resolved','Closed')");
    $critical = $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity='Critical' AND status NOT IN ('Resolved','Closed')");

    $recent = $pdo->query("
        SELECT i.*, c.name AS category_name, u.full_name AS reporter_name
        FROM incidents i
        JOIN categories c ON c.id = i.category_id
        JOIN users u ON u.id = i.reporter_id
        ORDER BY i.created_at DESC LIMIT 10
    ");
}

$stats = [
    'total'    => $total->fetchColumn(),
    'open'     => $open->fetchColumn(),
    'resolved' => $resolved->fetchColumn(),
    'critical' => $critical->fetchColumn(),
];

$incidents = $recent->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-grid-1x2-fill me-2 text-cyan"></i>Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?= e($user['name']) ?></p>
    </div>
    <?php if ($user['role'] === 'reporter'): ?>
    <a href="<?= APP_URL ?>/public/incidents/report.php" class="btn btn-dark btn-cirms btn-primary-cirms">
        <i class="bi bi-plus-circle me-1"></i> Report Incident
    </a>
    <?php endif; ?>
</div>

<!-- ── Stats Row ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $stats['open'] ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="stat-value"><?= $stats['resolved'] ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div>
                <div class="stat-value"><?= $stats['critical'] ?></div>
                <div class="stat-label">Critical Open</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Incidents Table ────────────────────────────── -->
<div class="cirms-card">
    <div class="cirms-card-header">
        <h2 class="cirms-card-title">
            <i class="bi bi-clock-history me-1"></i>
            Recent Incidents
        </h2>
        <a href="<?= APP_URL ?>/public/incidents/list.php" class="btn btn-outline-secondary btn-sm">View All</a>
    </div>

    <?php if (empty($incidents)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
        <p class="mt-2 mb-0">No incidents reported yet.</p>
        <?php if ($user['role'] === 'reporter'): ?>
        <a href="<?= APP_URL ?>/public/incidents/report.php" class="btn btn-sm btn-dark mt-3">
            Report your first incident
        </a>
        <?php endif; ?>
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
                    <?php if ($user['role'] !== 'reporter'): ?>
                    <th>Reporter</th>
                    <?php endif; ?>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incidents as $inc): ?>
                <tr>
                    <td><span class="ref-number"><?= e($inc['reference']) ?></span></td>
                    <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($inc['title']) ?>
                    </td>
                    <td><?= e($inc['category_name']) ?></td>
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
                    <?php if ($user['role'] !== 'reporter'): ?>
                    <td><?= e($inc['reporter_name'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td class="text-muted" style="font-size:.8rem;">
                        <?= date('d M Y H:i', strtotime($inc['created_at'])) ?>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/public/incidents/view.php?id=<?= $inc['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
