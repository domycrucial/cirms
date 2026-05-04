<?php
// ============================================================
// CIRMS – Audit Log
// public/audit/log.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo   = db();
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPg = 50;
$offs  = ($page - 1) * $perPg;

$total = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$pages = (int)ceil($total / $perPg);

$logs = $pdo->prepare("
    SELECT al.*, u.full_name
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT $perPg OFFSET $offs
");
$logs->execute();
$entries = $logs->fetchAll();

$pageTitle = 'Audit Log';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-journal-text me-2 text-cyan"></i>Audit Log</h1>
        <p class="page-subtitle">Immutable record of all system actions. <?= number_format($total) ?> entries.</p>
    </div>
</div>

<div class="cirms-card">
    <div class="table-responsive">
        <table class="cirms-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $log): ?>
            <tr>
                <td class="text-muted" style="font-size:.78rem;"><?= $log['id'] ?></td>
                <td class="text-muted" style="font-size:.78rem;">
                    <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td style="font-size:.85rem;"><?= e($log['full_name'] ?? 'System') ?></td>
                <td>
                    <code style="font-size:.78rem;background:#f0f4f8;padding:.1rem .35rem;border-radius:4px;">
                        <?= e($log['action']) ?>
                    </code>
                </td>
                <td style="font-size:.8rem;">
                    <?= e($log['target_type'] ?? '') ?>
                    <?= $log['target_id'] ? '#' . $log['target_id'] : '' ?>
                </td>
                <td class="text-muted" style="font-size:.78rem;"><?= e($log['ip_address'] ?? '—') ?></td>
                <td>
                    <?php if ($log['details'] && $log['details'] !== 'null'): ?>
                    <button class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="tooltip"
                            title="<?= e($log['details']) ?>">
                        <i class="bi bi-info-circle"></i>
                    </button>
                    <?php endif; ?>
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
                <?php for ($p = 1; $p <= min($pages, 15); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pages > 15): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
