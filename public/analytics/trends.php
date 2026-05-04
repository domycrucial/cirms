<?php
// ============================================================
// CIRMS – Trend Report
// public/analytics/trends.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// Monthly data — last 12 months, broken down by severity
$monthly = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS ym,
        DATE_FORMAT(created_at, '%b %Y') AS label,
        severity,
        COUNT(*) AS total
    FROM incidents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym, severity
    ORDER BY ym ASC
")->fetchAll();

// Build pivot: month → severity totals
$months   = [];
$pivoted  = [];
foreach ($monthly as $r) {
    $months[$r['ym']] = $r['label'];
    $pivoted[$r['ym']][$r['severity']] = (int)$r['total'];
}
$monthLabels = array_values($months);
$ymKeys      = array_keys($months);

$series = [
    'Critical' => [], 'High' => [], 'Medium' => [], 'Low' => []
];
foreach ($ymKeys as $ym) {
    foreach ($series as $sev => &$arr) {
        $arr[] = $pivoted[$ym][$sev] ?? 0;
    }
}
unset($arr);

// Top affected systems
$topSystems = $pdo->query("
    SELECT affected_system, COUNT(*) AS total
    FROM incidents
    WHERE affected_system IS NOT NULL AND affected_system != ''
    GROUP BY affected_system
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

// Response time by severity (resolved incidents only)
$responseTimes = $pdo->query("
    SELECT severity,
           ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)), 1) AS avg_hours,
           COUNT(*) AS resolved_count
    FROM incidents
    WHERE resolved_at IS NOT NULL
    GROUP BY severity
")->fetchAll();

$pageTitle = 'Trend Reports';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-graph-up-arrow me-2 text-cyan"></i>Trend Reports</h1>
        <p class="page-subtitle">12-month incident trends, affected systems, and response time analysis.</p>
    </div>
    <a href="<?= APP_URL ?>/public/analytics/export.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i> Export CSV
    </a>
</div>

<!-- Stacked bar: monthly by severity -->
<div class="cirms-card mb-3">
    <div class="cirms-card-header">
        <h2 class="cirms-card-title">Monthly Incidents by Severity (Last 12 Months)</h2>
    </div>
    <canvas id="stackedChart" height="80"></canvas>
</div>

<div class="row g-3 mb-3">

    <!-- Top affected systems -->
    <div class="col-lg-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Top Affected Systems</h2>
            </div>
            <?php if (empty($topSystems)): ?>
            <p class="text-muted">No data yet.</p>
            <?php else: ?>
            <?php
            $maxCount = max(array_column($topSystems, 'total'));
            foreach ($topSystems as $sys):
                $pct = $maxCount > 0 ? round(($sys['total'] / $maxCount) * 100) : 0;
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1" style="font-size:.875rem;">
                    <span><?= e($sys['affected_system']) ?></span>
                    <strong><?= $sys['total'] ?></strong>
                </div>
                <div class="progress" style="height:8px;border-radius:4px;">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:#0d1b2a;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Response time table -->
    <div class="col-lg-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Average Resolution Time by Severity</h2>
            </div>
            <?php if (empty($responseTimes)): ?>
            <p class="text-muted">No resolved incidents yet.</p>
            <?php else: ?>
            <table class="cirms-table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Avg. Hours to Resolve</th>
                        <th>SLA Target</th>
                        <th>Resolved Count</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($responseTimes as $rt): ?>
                <tr>
                    <td><span class="badge <?= severity_class($rt['severity']) ?>"><?= e($rt['severity']) ?></span></td>
                    <td>
                        <?php
                        $target = SLA_HOURS[$rt['severity']] ?? 72;
                        $avg    = (float)$rt['avg_hours'];
                        $cls    = $avg <= $target ? 'sla-ok' : 'sla-breach';
                        ?>
                        <span class="<?= $cls ?>"><?= $avg ?>h</span>
                    </td>
                    <td class="text-muted" style="font-size:.85rem;"><?= $target ?>h</td>
                    <td><?= $rt['resolved_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#64748b';

const labels = <?= json_encode($monthLabels) ?>;

new Chart(document.getElementById('stackedChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'Critical', data: <?= json_encode($series['Critical']) ?>, backgroundColor: '#ef4444', borderRadius: 3 },
            { label: 'High',     data: <?= json_encode($series['High']) ?>,     backgroundColor: '#f97316', borderRadius: 3 },
            { label: 'Medium',   data: <?= json_encode($series['Medium']) ?>,   backgroundColor: '#f59e0b', borderRadius: 3 },
            { label: 'Low',      data: <?= json_encode($series['Low']) ?>,       backgroundColor: '#22c55e', borderRadius: 3 },
        ]
    },
    options: {
        plugins: { legend: { position: 'top' } },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
