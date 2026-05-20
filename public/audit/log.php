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

// Optional server-side search
$search = trim($_GET['q'] ?? '');
$filter = trim($_GET['type'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(al.action LIKE ? OR u.full_name LIKE ? OR al.target_type LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== '') {
    $actionMap = [
        'auth'    => ["al.action LIKE '%login%' OR al.action LIKE '%logout%' OR al.action LIKE '%register%'"],
        'create'  => ["al.action LIKE '%create%' OR al.action LIKE '%report%' OR al.action LIKE '%add%'"],
        'update'  => ["al.action LIKE '%update%' OR al.action LIKE '%edit%' OR al.action LIKE '%assign%' OR al.action LIKE '%resolve%' OR al.action LIKE '%status%'"],
        'delete'  => ["al.action LIKE '%delete%' OR al.action LIKE '%remove%' OR al.action LIKE '%close%'"],
        'view'    => ["al.action LIKE '%view%' OR al.action LIKE '%list%' OR al.action LIKE '%export%'"],
    ];
    if (isset($actionMap[$filter])) {
        $where[] = '(' . $actionMap[$filter][0] . ')';
    }
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSQL
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = (int)ceil($total / $perPg);

$logsStmt = $pdo->prepare("
    SELECT al.id, al.action, al.target_type, al.target_id, al.details,
           al.ip_address, al.created_at, u.full_name
    FROM audit_log al
    LEFT JOIN users u ON u.id = al.user_id
    $whereSQL
    ORDER BY al.created_at DESC
    LIMIT $perPg OFFSET $offs
");
$logsStmt->execute($params);
$entries = $logsStmt->fetchAll();

// Action style helper
function audit_action_style(string $action): array {
    $a = strtolower($action);
    if (str_contains($a, 'login') || str_contains($a, 'logout') || str_contains($a, 'register')) {
        return ['bg' => 'rgba(14,165,233,.1)', 'color' => '#0369a1', 'border' => 'rgba(14,165,233,.25)', 'icon' => 'bi-person-check'];
    }
    if (str_contains($a, 'create') || str_contains($a, 'report') || str_contains($a, 'add')) {
        return ['bg' => 'rgba(34,197,94,.1)', 'color' => '#16a34a', 'border' => 'rgba(34,197,94,.25)', 'icon' => 'bi-plus-circle-fill'];
    }
    if (str_contains($a, 'delete') || str_contains($a, 'remove')) {
        return ['bg' => 'rgba(239,68,68,.1)', 'color' => '#b91c1c', 'border' => 'rgba(239,68,68,.25)', 'icon' => 'bi-trash-fill'];
    }
    if (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'assign')
        || str_contains($a, 'resolve') || str_contains($a, 'status') || str_contains($a, 'close')) {
        return ['bg' => 'rgba(245,158,11,.1)', 'color' => '#b45309', 'border' => 'rgba(245,158,11,.25)', 'icon' => 'bi-pencil-fill'];
    }
    if (str_contains($a, 'view') || str_contains($a, 'list') || str_contains($a, 'export')) {
        return ['bg' => 'rgba(99,102,241,.1)', 'color' => '#4338ca', 'border' => 'rgba(99,102,241,.25)', 'icon' => 'bi-eye-fill'];
    }
    return ['bg' => 'rgba(148,163,184,.1)', 'color' => '#475569', 'border' => 'rgba(148,163,184,.25)', 'icon' => 'bi-activity'];
}

$pageTitle = 'Audit Log';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-journal-text me-2 text-cyan"></i>Audit Log</h1>
        <p class="page-subtitle">Immutable record of all system actions &mdash; <?= number_format($total) ?> entries<?= $search || $filter ? ' (filtered)' : '' ?></p>
    </div>
</div>

<!-- ── Search + Filter Bar ───────────────────────────────── -->
<div class="cirms-card mb-3" style="padding:1rem 1.25rem;">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
        <div class="input-group" style="max-width:320px;">
            <span class="input-group-text" style="border-color:var(--border);background:var(--bg);">
                <i class="bi bi-search text-muted" style="font-size:.85rem;"></i>
            </span>
            <input type="text" name="q" class="form-control"
                   placeholder="Search action, user, or target…"
                   value="<?= e($search) ?>"
                   style="border-left:0;">
        </div>

        <select name="type" class="form-select" style="max-width:180px;">
            <option value="">All action types</option>
            <option value="auth"   <?= $filter === 'auth'   ? 'selected' : '' ?>>Authentication</option>
            <option value="create" <?= $filter === 'create' ? 'selected' : '' ?>>Create / Report</option>
            <option value="update" <?= $filter === 'update' ? 'selected' : '' ?>>Update / Edit</option>
            <option value="delete" <?= $filter === 'delete' ? 'selected' : '' ?>>Delete / Remove</option>
            <option value="view"   <?= $filter === 'view'   ? 'selected' : '' ?>>View / Export</option>
        </select>

        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-funnel me-1"></i>Filter
        </button>

        <?php if ($search || $filter): ?>
        <a href="?" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-x-circle me-1"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Log Table ─────────────────────────────────────────── -->
<div class="cirms-card">
    <?php if (empty($entries)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        No audit entries match your search.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="cirms-table" id="auditTable">
            <thead>
                <tr>
                    <th style="width:1%;">#</th>
                    <th style="white-space:nowrap;">Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th style="width:1%;text-align:center;">Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $log):
                $style = audit_action_style($log['action']);
                $hasDetails = !empty($log['details']) && $log['details'] !== 'null' && $log['details'] !== '[]' && $log['details'] !== '{}';
            ?>
            <tr>
                <!-- Row ID -->
                <td class="text-muted" style="font-size:.75rem;font-family:'Space Mono',monospace;"><?= $log['id'] ?></td>

                <!-- Timestamp -->
                <td style="white-space:nowrap;">
                    <div style="font-size:.82rem;font-weight:600;color:#1e293b;"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                    <div style="font-size:.75rem;color:var(--muted);"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                </td>

                <!-- User -->
                <td>
                    <div style="font-size:.85rem;font-weight:500;"><?= e($log['full_name'] ?? 'System') ?></div>
                </td>

                <!-- Action badge -->
                <td>
                    <span style="
                        display:inline-flex;align-items:center;gap:.35rem;
                        padding:.22rem .6rem;border-radius:5px;
                        font-size:.76rem;font-weight:700;font-family:'Space Mono',monospace;
                        letter-spacing:.02em;white-space:nowrap;
                        background:<?= $style['bg'] ?>;
                        color:<?= $style['color'] ?>;
                        border:1px solid <?= $style['border'] ?>;">
                        <i class="bi <?= $style['icon'] ?>" style="font-size:.75rem;"></i>
                        <?= e($log['action']) ?>
                    </span>
                </td>

                <!-- Target -->
                <td>
                    <?php if ($log['target_type']): ?>
                    <span style="font-size:.82rem;color:#1e293b;"><?= e($log['target_type']) ?></span>
                    <?php if ($log['target_id']): ?>
                    <span style="font-size:.75rem;color:var(--muted);font-family:'Space Mono',monospace;margin-left:.25rem;">#<?= $log['target_id'] ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- IP Address -->
                <td>
                    <?php if (!empty($log['ip_address'])): ?>
                    <span style="font-size:.75rem;font-family:'Space Mono',monospace;color:var(--muted);">
                        <?= e($log['ip_address']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Details modal trigger -->
                <td style="text-align:center;">
                    <?php if ($hasDetails): ?>
                    <button class="btn btn-sm audit-detail-btn"
                            style="border:1px solid var(--border);background:var(--bg);color:var(--muted);border-radius:6px;padding:.2rem .5rem;"
                            data-action="<?= e($log['action']) ?>"
                            data-details='<?= htmlspecialchars($log['details'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'
                            title="View details">
                        <i class="bi bi-info-circle" style="font-size:.85rem;"></i>
                    </button>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Pagination ────────────────────────────────────── -->
    <?php if ($pages > 1): ?>
    <?php
        $startPg = max(1, $page - 4);
        $endPg   = min($pages, $startPg + 8);
        if ($endPg - $startPg < 8) $startPg = max(1, $endPg - 8);
        $qs = http_build_query(['q' => $search, 'type' => $filter]);
        $base = '?' . ($qs ? $qs . '&' : '');
    ?>
    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
        <span class="text-muted" style="font-size:.8rem;">
            Showing <?= number_format(($page - 1) * $perPg + 1) ?>–<?= number_format(min($page * $perPg, $total)) ?> of <?= number_format($total) ?>
        </span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <!-- Prev -->
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $base ?>page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left" style="font-size:.75rem;"></i>
                    </a>
                </li>

                <?php if ($startPg > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= $base ?>page=1">1</a></li>
                <?php if ($startPg > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $startPg; $p <= $endPg; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $base ?>page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($endPg < $pages): ?>
                <?php if ($endPg < $pages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= $base ?>page=<?= $pages ?>"><?= $pages ?></a></li>
                <?php endif; ?>

                <!-- Next -->
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $base ?>page=<?= $page + 1 ?>">
                        <i class="bi bi-chevron-right" style="font-size:.75rem;"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ── Details Modal ─────────────────────────────────────── -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:12px;border:1px solid var(--border);">
            <div class="modal-header" style="border-bottom:1px solid var(--border);padding:.9rem 1.25rem;">
                <div>
                    <h5 class="modal-title mb-0" id="detailsModalLabel" style="font-family:'Space Mono',monospace;font-size:.95rem;color:var(--navy);">
                        <i class="bi bi-info-circle-fill me-2 text-cyan"></i>Action Details
                    </h5>
                    <div id="detailsModalAction" class="mt-1" style="font-size:.78rem;color:var(--muted);"></div>
                </div>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:1.25rem;" id="detailsModalBody">
                <!-- populated by JS -->
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:.75rem 1.25rem;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Details Modal ────────────────────────────────────────
const detailsModal = document.getElementById('detailsModal');
if (detailsModal) {
    let _activeBtn = null;

    // Open modal and store reference to the clicked button
    document.querySelectorAll('.audit-detail-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            _activeBtn = btn;
            bootstrap.Modal.getOrCreateInstance(detailsModal).show();
        });
    });

    // Populate modal once Bootstrap fires show.bs.modal
    detailsModal.addEventListener('show.bs.modal', function () {
        const btn    = _activeBtn;
        _activeBtn   = null;
        const raw    = btn ? btn.getAttribute('data-details') : '';
        const action = btn ? btn.getAttribute('data-action') : '';

        document.getElementById('detailsModalAction').textContent = action || '';

        const body = document.getElementById('detailsModalBody');
        if (!raw || raw === 'null' || raw === '[]' || raw === '{}') {
            body.innerHTML = '<p class="text-muted mb-0" style="font-size:.875rem;">No additional details recorded.</p>';
            return;
        }

        try {
            const data = JSON.parse(raw);
            if (typeof data === 'object' && data !== null && !Array.isArray(data)) {
                const pairs = Object.entries(data).filter(([, v]) => v !== null && v !== '');
                if (pairs.length === 0) {
                    body.innerHTML = '<p class="text-muted mb-0" style="font-size:.875rem;">No additional details recorded.</p>';
                    return;
                }
                let html = '<dl class="row mb-0" style="font-size:.85rem;row-gap:.35rem;">';
                for (const [k, v] of pairs) {
                    const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    const val   = (typeof v === 'object') ? JSON.stringify(v, null, 2) : String(v);
                    html += `<dt class="col-sm-4" style="color:var(--muted);font-weight:600;">${escHtml(label)}</dt>`;
                    html += `<dd class="col-sm-8 mb-0" style="color:#1e293b;word-break:break-word;"><code style="background:var(--bg);padding:.1rem .35rem;border-radius:4px;font-size:.82rem;">${escHtml(val)}</code></dd>`;
                }
                html += '</dl>';
                body.innerHTML = html;
            } else {
                body.innerHTML = `<pre style="font-size:.8rem;background:var(--bg);padding:.75rem;border-radius:6px;overflow-x:auto;margin:0;">${escHtml(JSON.stringify(data, null, 2))}</pre>`;
            }
        } catch (_) {
            body.innerHTML = `<pre style="font-size:.8rem;background:var(--bg);padding:.75rem;border-radius:6px;overflow-x:auto;margin:0;">${escHtml(raw)}</pre>`;
        }
    });
}

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
