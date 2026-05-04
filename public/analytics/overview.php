<?php
// ============================================================
// CIRMS – Analytics Overview
// public/analytics/overview.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// ── Chart Data Queries ────────────────────────────────────────

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
    FROM incidents GROUP BY severity
")->fetchAll();

// By status
$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM incidents GROUP BY status
")->fetchAll();

// Average resolution time (hours)
$avgRes = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_hours
    FROM incidents WHERE resolved_at IS NOT NULL
")->fetchColumn();

// Total stats
$totalInc   = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$openInc    = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();
$breachedSLA= $pdo->query("SELECT COUNT(*) FROM incidents WHERE sla_deadline < NOW() AND status NOT IN ('Resolved','Closed')")->fetchColumn();

$pageTitle = 'Analytics Overview';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-bar-chart-fill me-2 text-cyan"></i>Analytics Overview</h1>
        <p class="page-subtitle">Campus cybersecurity incident statistics and trends</p>
    </div>
    <a href="<?= APP_URL ?>/public/analytics/export.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i> Export CSV
    </a>
</div>

<!-- ── KPI Strip ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="stat-value"><?= $totalInc ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value"><?= $openInc ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-alarm-fill"></i></div>
            <div>
                <div class="stat-value"><?= $breachedSLA ?></div>
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

<!-- ── Charts Row ────────────────────────────────────────── -->
<div class="row g-3 mb-3">

    <!-- Monthly trend -->
    <div class="col-lg-7">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Monthly Incident Trend (Last 6 Months)</h2>
            </div>
            <canvas id="monthlyChart" height="100"></canvas>
        </div>
    </div>

    <!-- By category -->
    <div class="col-lg-5">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Incidents by Category</h2>
            </div>
            <canvas id="catChart" height="140"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- By severity -->
    <div class="col-md-6">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Severity Distribution</h2>
            </div>
            <canvas id="sevChart" height="160"></canvas>
        </div>
    </div>
    <!-- By status -->
    <div class="col-md-6">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">Status Breakdown</h2>
            </div>
            <canvas id="statusChart" height="160"></canvas>
        </div>
    </div>
</div>

<script>
// ── Chart data from PHP ───────────────────────────────────────
const monthlyLabels = <?= json_encode(array_column($monthly, 'month')) ?>;
const monthlyData   = <?= json_encode(array_column($monthly, 'total')) ?>;

const catLabels = <?= json_encode(array_column($byCat, 'name')) ?>;
const catData   = <?= json_encode(array_column($byCat, 'total')) ?>;

const sevLabels = <?= json_encode(array_column($bySev, 'severity')) ?>;
const sevData   = <?= json_encode(array_column($bySev, 'total')) ?>;

const statusLabels = <?= json_encode(array_column($byStatus, 'status')) ?>;
const statusData   = <?= json_encode(array_column($byStatus, 'total')) ?>;

const SEVERITY_COLORS = { Low: '#22c55e', Medium: '#f59e0b', High: '#f97316', Critical: '#ef4444' };
const STATUS_COLORS   = {
    New: '#6366f1', Acknowledged: '#0ea5e9',
    'In Progress': '#f59e0b', Resolved: '#22c55e', Closed: '#94a3b8'
};

Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#64748b';

// Monthly Bar
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Incidents',
            data: monthlyData,
            backgroundColor: 'rgba(0,170,204,.7)',
            borderColor: '#00aacc',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Category Doughnut
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catData,
            backgroundColor: ['#0d1b2a','#00aacc','#6366f1','#f59e0b','#f97316','#ef4444','#22c55e'],
            borderWidth: 2,
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } }
});

// Severity Doughnut
new Chart(document.getElementById('sevChart'), {
    type: 'doughnut',
    data: {
        labels: sevLabels,
        datasets: [{
            data: sevData,
            backgroundColor: sevLabels.map(s => SEVERITY_COLORS[s] || '#94a3b8'),
            borderWidth: 2,
        }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } }
});

// Status Bar
new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData,
            backgroundColor: statusLabels.map(s => STATUS_COLORS[s] || '#94a3b8'),
            borderRadius: 4,
        }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
