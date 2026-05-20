<?php
// ============================================================
// CIRMS – Analytics Overview
// public/analytics/overview.php
//
// Displays 4 pro charts (ApexCharts) + KPI strip:
//   1. Monthly incident trend   – area chart (last 6 months)
//   2. Incidents by category    – horizontal bar with gradient
//   3. Severity distribution    – animated donut
//   4. Status breakdown         – radial bar chart
//
// Charts auto-refresh every 60 s via the /api/stats.php endpoint.
// Admin-only access.
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// ── Data queries ──────────────────────────────────────────────
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           COUNT(*) AS total
    FROM   incidents
    WHERE  created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP  BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER  BY DATE_FORMAT(created_at,'%Y-%m') ASC
")->fetchAll(PDO::FETCH_ASSOC);

$byCat = $pdo->query("
    SELECT c.name, COUNT(*) AS total
    FROM   incidents i JOIN categories c ON c.id = i.category_id
    GROUP  BY c.id ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$bySev = $pdo->query("
    SELECT severity, COUNT(*) AS total
    FROM   incidents
    GROUP  BY severity
    ORDER  BY FIELD(severity,'Critical','High','Medium','Low')
")->fetchAll(PDO::FETCH_ASSOC);

$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM   incidents
    GROUP  BY status
    ORDER  BY FIELD(status,'New','Acknowledged','In Progress','Resolved','Closed')
")->fetchAll(PDO::FETCH_ASSOC);

// ── KPI aggregates ────────────────────────────────────────────
$totalInc    = (int) $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$openInc     = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();
$breachedSLA = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE sla_deadline < NOW() AND status NOT IN ('Resolved','Closed')")->fetchColumn();
$avgRes      = (float) ($pdo->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR,created_at,resolved_at)),0) FROM incidents WHERE resolved_at IS NOT NULL")->fetchColumn());
$resolvedInc = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Resolved','Closed')")->fetchColumn();
$resolvedRate= $totalInc > 0 ? round(($resolvedInc / $totalInc) * 100) : 0;

// ── JSON blob passed to ApexCharts (zero PHP inside <script>) ─
$chartData = json_encode([
    'monthly'  => ['labels' => array_column($monthly,'month'),   'data' => array_map('intval',array_column($monthly,'total'))],
    'category' => ['labels' => array_column($byCat,'name'),      'data' => array_map('intval',array_column($byCat,'total'))],
    'severity' => ['labels' => array_column($bySev,'severity'),  'data' => array_map('intval',array_column($bySev,'total'))],
    'status'   => ['labels' => array_column($byStatus,'status'), 'data' => array_map('intval',array_column($byStatus,'total'))],
], JSON_HEX_TAG | JSON_HEX_AMP);

$pageTitle = 'Analytics Overview';
include __DIR__ . '/../../includes/header.php';
?>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>

<!-- ── Page Header ────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-bar-chart-fill me-2 text-cyan"></i>Analytics Overview
        </h1>
        <p class="page-subtitle">
            Campus cybersecurity incident statistics &nbsp;·&nbsp;
            <span id="ovLastUpdated" style="font-size:.78rem;color:#94a3b8;">Loading…</span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/public/analytics/trends.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-graph-up-arrow me-1"></i> Trends
        </a>
        <a href="<?= APP_URL ?>/public/analytics/export.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-collection-fill"></i></div>
            <div>
                <div class="stat-value" id="kpi-total"><?= number_format($totalInc) ?></div>
                <div class="stat-label">Total Incidents</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value" id="kpi-open"><?= number_format($openInc) ?></div>
                <div class="stat-label">Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card <?= $breachedSLA ? 'stat-card-alert' : '' ?>">
            <div class="stat-icon red"><i class="bi bi-alarm-fill"></i></div>
            <div>
                <div class="stat-value" id="kpi-sla"><?= number_format($breachedSLA) ?></div>
                <div class="stat-label">SLA Breaches</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-value" id="kpi-avg"><?= $avgRes ? round($avgRes,1).'h' : '—' ?></div>
                <div class="stat-label">Avg Resolution</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Row 1: Monthly trend + Category bar ────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-graph-up me-1 text-cyan"></i>Monthly Incident Trend
                </h2>
                <span class="badge-pill-muted">Last 6 months · auto-refreshes</span>
            </div>
            <div id="chartMonthly" style="min-height:240px;"></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-tag-fill me-1 text-cyan"></i>By Category
                </h2>
            </div>
            <div id="chartCategory" style="min-height:240px;"></div>
        </div>
    </div>
</div>

<!-- ── Row 2: Severity donut + Status radial ─────────────── -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-exclamation-triangle-fill me-1 text-cyan"></i>Severity Distribution
                </h2>
            </div>
            <div id="chartSeverity" style="min-height:280px;"></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-layers-fill me-1 text-cyan"></i>Status Breakdown
                </h2>
            </div>
            <div id="chartStatus" style="min-height:280px;"></div>
        </div>
    </div>
</div>

<!-- ── Resolution rate progress bar ──────────────────────── -->
<div class="cirms-card">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="stat-icon green flex-shrink-0"><i class="bi bi-check2-all"></i></div>
        <div class="flex-grow-1" style="min-width:200px;">
            <div class="d-flex justify-content-between mb-1">
                <span style="font-size:.875rem;font-weight:600;color:var(--navy);">
                    Overall Resolution Rate
                </span>
                <strong id="resolvedRateTxt" style="font-size:.875rem;color:var(--navy);">
                    <?= $resolvedRate ?>%
                </strong>
            </div>
            <div class="progress" style="height:10px;border-radius:5px;background:var(--bg);">
                <div id="resolvedRateBar" class="progress-bar"
                     style="width:<?= $resolvedRate ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:5px;"
                     role="progressbar"></div>
            </div>
            <div class="text-muted mt-1" style="font-size:.78rem;">
                <span id="resolvedRateText">
                    <?= number_format($resolvedInc) ?> of <?= number_format($totalInc) ?> incidents resolved or closed
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.badge-pill-muted {
    font-size:.72rem; background:#f0f4f8; color:#64748b;
    border:1px solid #dde3ea; border-radius:20px; padding:.2rem .6rem;
}
.stat-card-alert {
    animation: alertPulse 2.5s ease-in-out 1s infinite;
}
@keyframes alertPulse {
    0%,100% { box-shadow: var(--shadow); }
    50%      { box-shadow: 0 0 0 3px rgba(239,68,68,.18), var(--shadow); }
}
</style>

<!-- ── Chart data passed from PHP → JS ──────────────────── -->
<script>var OV = <?= $chartData ?>;</script>

<!-- ── ApexCharts initialisation (zero PHP inside) ──────── -->
<script>
(function () {
    'use strict';

    /* Shared palette maps */
    var SEV_COLORS    = { Critical:'#ef4444', High:'#f97316', Medium:'#f59e0b', Low:'#22c55e' };
    var STATUS_COLORS = { New:'#6366f1', Acknowledged:'#0ea5e9', 'In Progress':'#f59e0b', Resolved:'#22c55e', Closed:'#94a3b8' };
    var CAT_PALETTE   = ['#0ea5e9','#6366f1','#f59e0b','#ef4444','#22c55e','#f97316','#a78bfa','#fb7185','#00d4ff'];

    var FONT = "'DM Sans', sans-serif";

    /* ── 1. Monthly trend – gradient area chart ───────────── */
    new ApexCharts(document.getElementById('chartMonthly'), {
        chart  : { type:'area', height:240, toolbar:{ show:false }, fontFamily:FONT, animations:{ enabled:true, speed:800 } },
        series : [{ name:'Incidents', data: OV.monthly.data }],
        xaxis  : { categories: OV.monthly.labels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } }, axisBorder:{ show:false }, axisTicks:{ show:false } },
        yaxis  : { min:0, tickAmount:4, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
        stroke : { curve:'smooth', width:2.5 },
        fill   : { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:.45, opacityTo:.02, stops:[0,95] } },
        colors : ['#00aacc'],
        markers: { size:5, colors:['#00aacc'], strokeColors:'#fff', strokeWidth:2, hover:{ size:7 } },
        dataLabels : { enabled: false },
        grid   : { borderColor:'#f0f4f8', strokeDashArray:4, xaxis:{ lines:{ show:false } } },
        tooltip: { theme:'light', y:{ formatter: function(v){ return v+' incident'+(v!==1?'s':''); } } },
        noData : { text:'No data for this period', style:{ color:'#94a3b8', fontSize:'13px' } },
    }).render();

    /* ── 2. By category – horizontal bar with gradient ────── */
    new ApexCharts(document.getElementById('chartCategory'), {
        chart  : { type:'bar', height:240, toolbar:{ show:false }, fontFamily:FONT, animations:{ enabled:true, speed:700 } },
        series : [{ name:'Incidents', data: OV.category.data }],
        xaxis  : { categories: OV.category.labels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
        yaxis  : { labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
        plotOptions: { bar:{ horizontal:true, borderRadius:5, barHeight:'60%',
            distributed:true,
        } },
        colors : CAT_PALETTE,
        dataLabels: { enabled:true, style:{ fontSize:'11px' }, formatter: function(v){ return v > 0 ? v : ''; } },
        legend : { show:false },
        grid   : { borderColor:'#f0f4f8' },
        tooltip: { theme:'light' },
        noData : { text:'No data yet', style:{ color:'#94a3b8' } },
    }).render();

    /* ── 3. Severity – animated donut ────────────────────── */
    new ApexCharts(document.getElementById('chartSeverity'), {
        chart  : { type:'donut', height:280, fontFamily:FONT, animations:{ enabled:true, speed:900, animateGradually:{ enabled:true, delay:150 } } },
        series : OV.severity.data,
        labels : OV.severity.labels,
        colors : OV.severity.labels.map(function(l){ return SEV_COLORS[l]||'#94a3b8'; }),
        plotOptions: { pie:{ donut:{ size:'65%', labels:{ show:true,
            total:{ show:true, showAlways:true, label:'Total', fontSize:'13px', color:'#64748b',
                formatter: function(w){ return w.globals.seriesTotals.reduce(function(a,b){return a+b;},0).toLocaleString(); }
            },
            value:{ fontSize:'22px', fontWeight:700, color:'#0d1b2a' }
        } } } },
        dataLabels: { enabled:true, formatter: function(val){ return Math.round(val)+'%'; }, style:{ fontSize:'11px' } },
        legend : { position:'bottom', fontSize:'12px', markers:{ size:8 } },
        tooltip: { theme:'light', y:{ formatter: function(v){ return v+' incident'+(v!==1?'s':''); } } },
        noData : { text:'No data yet', style:{ color:'#94a3b8' } },
    }).render();

    /* ── 4. Status – horizontal bar ──────────────────────── */
    new ApexCharts(document.getElementById('chartStatus'), {
        chart  : { type:'bar', height:280, toolbar:{ show:false }, fontFamily:FONT, animations:{ enabled:true, speed:700 } },
        series : [{ name:'Count', data: OV.status.data }],
        xaxis  : { categories: OV.status.labels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
        yaxis  : { labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
        plotOptions: { bar:{ horizontal:true, borderRadius:5, barHeight:'55%', distributed:true } },
        colors : OV.status.labels.map(function(l){ return STATUS_COLORS[l]||'#94a3b8'; }),
        dataLabels: { enabled:true, style:{ fontSize:'11px' }, formatter: function(v){ return v > 0 ? v : ''; } },
        legend : { show:false },
        grid   : { borderColor:'#f0f4f8' },
        tooltip: { theme:'light' },
        noData : { text:'No data yet', style:{ color:'#94a3b8' } },
    }).render();

    /* ── Auto-refresh KPIs every 60 s ────────────────────── */
    var API = '<?= APP_URL ?>/public/api/stats.php';
    function refreshKPIs() {
        fetch(API, { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(json){
                if (!json.ok) return;
                var s = json.stats;
                document.getElementById('kpi-total').textContent = s.total.toLocaleString();
                document.getElementById('kpi-open').textContent  = s.open.toLocaleString();
                document.getElementById('kpi-sla').textContent   = s.slaBreached.toLocaleString();
                // avg resolution
                var avgEl = document.getElementById('kpi-avg');
                if (avgEl) avgEl.textContent = s.avgResHours ? s.avgResHours + 'h' : '—';
                // resolution rate bar
                document.getElementById('resolvedRateTxt').textContent = s.resolvedRate + '%';
                document.getElementById('resolvedRateBar').style.width = s.resolvedRate + '%';

                var ts = new Date().toLocaleTimeString();
                var el = document.getElementById('ovLastUpdated');
                if (el) el.textContent = 'Updated ' + ts;
            })
            .catch(function(){});
    }
    refreshKPIs(); // run immediately
    setInterval(refreshKPIs, 60000);

}());
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
