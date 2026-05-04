<?php
// ============================================================
// CIRMS – My Reports (Reporter view)
// public/incidents/my-reports.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['reporter']);

$pdo = db();
$uid = current_user()['id'];

$incidents = $pdo->prepare("
    SELECT i.*, c.name AS category_name
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    WHERE i.reporter_id = ?
    ORDER BY i.created_at DESC
");
$incidents->execute([$uid]);
$rows = $incidents->fetchAll();

$pageTitle = 'My Reports';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-file-earmark-text me-2 text-cyan"></i>My Reports</h1>
        <p class="page-subtitle"><?= count($rows) ?> incident<?= count($rows) !== 1 ? 's' : '' ?> submitted</p>
    </div>
    <a href="<?= APP_URL ?>/public/incidents/report.php" class="btn btn-dark btn-cirms btn-primary-cirms">
        <i class="bi bi-plus-circle me-1"></i> New Report
    </a>
</div>

<div class="cirms-card">
    <?php if (empty($rows)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
        <p class="mt-2">You have not submitted any incident reports yet.</p>
        <a href="<?= APP_URL ?>/public/incidents/report.php" class="btn btn-dark mt-2">
            Submit your first report
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $inc): ?>
            <tr>
                <td><span class="ref-number"><?= e($inc['reference']) ?></span></td>
                <td><?= e($inc['title']) ?></td>
                <td style="font-size:.85rem;"><?= e($inc['category_name']) ?></td>
                <td>
                    <span class="badge <?= severity_class($inc['severity']) ?>"><?= e($inc['severity']) ?></span>
                </td>
                <td>
                    <span class="status-badge <?= status_class($inc['status']) ?>"><?= e($inc['status']) ?></span>
                </td>
                <td class="text-muted" style="font-size:.8rem;">
                    <?= date('d M Y', strtotime($inc['created_at'])) ?>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
