<?php
// ============================================================
// CIRMS – Analytics Overview
// public/analytics/overview.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// Incidents per month (last 6 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
           COUNT(*) AS total
    FROM incidents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
")->fetchAll();

// By category
$byCat = $pdo->query("
    SELECT c.name, COUNT(*) AS total
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    GROUP BY c.id
    ORDER BY total DESC
")->fetchAll();

// By severity
$bySev = $pdo->query("
    SELECT severity, COUNT(*) AS total
    FROM incidents
    GROUP BY severity
    ORDER BY FIELD(severity,'Critical','High','Medium','Low')
")->fetchAll();

// By status
$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM incidents
    GROUP BY status
    ORDER BY FIELD(status,'New','Acknowledged','In Progress','Resolved','Closed')
")->fetchAll();

// KPI stats
$avgRes      = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) FROM incidents WHERE resolved_at IS NOT NULL")->fetchColumn();
$totalInc    = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$openInc     = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();
$breachedSLA = $pdo->query("SELECT COUNT(*) FROM incidents WHERE sla_deadline < NOW() AND status NOT IN ('Resolved','Closed')")->fetchColumn();
$resolvedInc = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Resolved','Closed')")->fetchColumn();
$resolvedRate = $totalInc > 0 ? round(($resolvedInc / $totalInc) * 100) : 0;

// Dynamic heights for horizontal-bar wrappers
$catH  = max(count($byCat)    * 44, 180);
$statH = max(count($byStatus) * 52, 180);

// Single JSON blob — no PHP echos needed inside <script> blocks
$chartData = json_encode([
    'monthLabels'  => array_column($monthly,  'month'),
    'monthData'    => array_map('intval', array_column($monthly,  'total')),
    'catLabels'    => array_column($byCat,    'name'),
    'catData'      => array_map('intval', array_column($byCat,    'total')),
    'sevLabels'    => array_column($bySev,    'severity'),
    'sevData'      => array_map('intval', array_column($bySev,    'total')),
    'statusLabels' => array_column($byStatus, 'status'),
    'statusData'   => array_map('intval', array_column($byStatus, 'total')),
], JSON_HEX_TAG | JSON_HEX_AMP);
$chartFlags = json_encode([
    'monthly' => !empty($monthly),
    'cat'     => !empty($byCat),
    'sev'     => !empty($bySev),
    'status'  => !empty($byStatus),
]);

$pageTitle = 'Analytics Overview';
include __DIR__ . '/../../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-bar-chart-fill me-2 text-cyan"></i>Analytics Overview</h1>
        <p class="page-subtitle">Campus cybersecurity incident statistics and trends</p>
    </div>
    <a href="<?= APP_URL ?>/public/analytics/export.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i> Export CSV
    </a>
</div>

<!-- ── KPI Strip ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalInc) ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= number_format($openInc) ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-alarm-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($breachedSLA) ?></div>
                <div class="stat-label">SLA Breaches</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-value"><?= $avgRes ? round($avgRes, 1) . 'h' : '—' ?></div>
                <div class="stat-label">Avg Resolution</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 1: Monthly Trend + By Category ─────────────────── -->
<div class="row g-3 mb-3">

    <div class="col-lg-8">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-graph-up me-2 text-cyan"></i>Monthly Incident Trend</h2>
                <span style="font-size:.75rem;color:var(--muted);background:var(--bg);padding:.2rem .6rem;border-radius:4px;border:1px solid var(--border);">Last 6 months</span>
            </div>
            <?php if (empty($monthly)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No incident data in the last 6 months.
            </div>
            <?php else: ?>
            <canvas id="monthlyChart" height="100"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-tag-fill me-2 text-cyan"></i>Incidents by Category</h2>
            </div>
            <?php if (empty($byCat)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div style="position:relative;height:<?= $catH ?>px;">
                <canvas id="catChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Row 2: Severity Donut + Status Breakdown ───────────── -->
<div class="row g-3 mb-3">

    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-exclamation-triangle-fill me-2 text-cyan"></i>Severity Distribution</h2>
            </div>
            <?php if (empty($bySev)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div style="max-width:300px;margin:0 auto;padding:.5rem 0;">
                <canvas id="sevChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-layers-fill me-2 text-cyan"></i>Status Breakdown</h2>
            </div>
            <?php if (empty($byStatus)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div style="position:relative;height:<?= $statH ?>px;">
                <canvas id="statusChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Resolution Rate ────────────────────────────────────── -->
<div class="cirms-card">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="stat-icon green flex-shrink-0"><i class="bi bi-check2-all"></i></div>
        <div class="flex-grow-1" style="min-width:200px;">
            <div class="d-flex justify-content-between mb-1">
                <span style="font-size:.875rem;font-weight:600;color:var(--navy);">Overall Resolution Rate</span>
                <strong style="font-size:.875rem;color:var(--navy);"><?= $resolvedRate ?>%</strong>
            </div>
            <div class="progress" style="height:10px;border-radius:5px;background:var(--bg);">
                <div class="progress-bar"
                     style="width:<?= $resolvedRate ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:5px;"
                     role="progressbar"></div>
            </div>
            <div class="text-muted mt-1" style="font-size:.78rem;">
                <?= number_format($resolvedInc) ?> of <?= number_format($totalInc) ?> incidents resolved or closed
            </div>
        </div>
    </div>
</div>

<!-- Step 1: PHP data → JS (the only place PHP echos appear near scripts) -->
<script>
var OV = <?= $chartData ?>;
var OV_SHOW = <?= $chartFlags ?>;
</script>

<!-- Step 2: Pure JavaScript — zero PHP inside this block -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.color = '#64748b';

    /* Center-total plugin for doughnut charts */
    Chart.register({
        id: 'centerText',
        afterDraw: function (chart) {
            if (chart.config.type !== 'doughnut') return;
            var ca = chart.chartArea;
            if (!ca) return;
            var total = chart.data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
            var cx = (ca.left + ca.right) / 2;
            var cy = (ca.top  + ca.bottom) / 2;
            var c  = chart.ctx;
            c.save();
            c.textAlign = 'center'; c.textBaseline = 'middle';
            c.fillStyle = '#0d1b2a';
            c.font = 'bold 22px "Space Mono", monospace';
            c.fillText(total, cx, cy - 9);
            c.font = '11px "DM Sans", sans-serif'; c.fillStyle = '#8899aa';
            c.fillText('total', cx, cy + 12);
            c.restore();
        }
    });

    var SEV_COLORS = { Low:'#22c55e', Medium:'#f59e0b', High:'#f97316', Critical:'#ef4444' };
    var STATUS_COLORS = {
        New:'#6366f1', Acknowledged:'#0ea5e9',
        'In Progress':'#f59e0b', Resolved:'#22c55e', Closed:'#94a3b8'
    };
    var CAT_PALETTE = ['#0d1b2a','#00aacc','#6366f1','#f59e0b','#f97316','#ef4444','#22c55e','#a78bfa','#fb7185'];

    function pctLabel(ctx) {
        var vals = ctx.dataset.data;
        var total = vals.reduce(function (a, b) { return a + b; }, 0);
        var val = (ctx.chart.config.type === 'doughnut')
            ? ctx.parsed
            : (ctx.parsed.x !== undefined ? ctx.parsed.x : ctx.parsed.y);
        var pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
        return ' ' + val + ' (' + pct + '%)';
    }

    /* 1. Monthly gradient area line chart */
    if (OV_SHOW.monthly) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: OV.monthLabels,
                datasets: [{
                    label: 'Incidents',
                    data: OV.monthData,
                    borderColor: '#00aacc',
                    backgroundColor: function (context) {
                        var ca = context.chart.chartArea;
                        if (!ca) return 'rgba(0,170,204,.08)';
                        var g = context.chart.ctx.createLinearGradient(0, ca.top, 0, ca.bottom);
                        g.addColorStop(0, 'rgba(0,170,204,.32)');
                        g.addColorStop(1, 'rgba(0,170,204,.02)');
                        return g;
                    },
                    fill: true, tension: 0.4,
                    pointBackgroundColor: '#00aacc', pointBorderColor: '#fff',
                    pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7, borderWidth: 2.5
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { var v = c.parsed.y; return ' ' + v + ' incident' + (v !== 1 ? 's' : ''); } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    /* 2. Category horizontal bar */
    if (OV_SHOW.cat) {
        new Chart(document.getElementById('catChart'), {
            type: 'bar',
            data: {
                labels: OV.catLabels,
                datasets: [{ data: OV.catData, backgroundColor: CAT_PALETTE.slice(0, OV.catData.length), borderRadius: 4, borderSkipped: false }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: pctLabel } } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } }, y: { grid: { display: false } } }
            }
        });
    }

    /* 3. Severity donut with center total */
    if (OV_SHOW.sev) {
        new Chart(document.getElementById('sevChart'), {
            type: 'doughnut',
            data: {
                labels: OV.sevLabels,
                datasets: [{
                    data: OV.sevData,
                    backgroundColor: OV.sevLabels.map(function (s) { return SEV_COLORS[s] || '#94a3b8'; }),
                    borderWidth: 3, borderColor: '#fff', hoverOffset: 8
                }]
            },
            options: {
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 12 } } },
                    tooltip: { callbacks: { label: pctLabel } }
                }
            }
        });
    }

    /* 4. Status horizontal bar */
    if (OV_SHOW.status) {
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: {
                labels: OV.statusLabels,
                datasets: [{ data: OV.statusData, backgroundColor: OV.statusLabels.map(function (s) { return STATUS_COLORS[s] || '#94a3b8'; }), borderRadius: 4, borderSkipped: false }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: pctLabel } } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } }, y: { grid: { display: false } } }
            }
        });
    }

}); /* end DOMContentLoaded */
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
