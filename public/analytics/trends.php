<?php
// ============================================================
// CIRMS – Trend Reports
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
$months  = [];
$pivoted = [];
foreach ($monthly as $r) {
    $months[$r['ym']] = $r['label'];
    $pivoted[$r['ym']][$r['severity']] = (int)$r['total'];
}
$monthLabels   = array_values($months);
$ymKeys        = array_keys($months);

$series        = ['Critical' => [], 'High' => [], 'Medium' => [], 'Low' => []];
$monthlyTotals = [];
foreach ($ymKeys as $ym) {
    foreach ($series as $sev => &$arr) {
        $arr[] = $pivoted[$ym][$sev] ?? 0;
    }
    $monthlyTotals[] = array_sum($pivoted[$ym] ?? []);
}
unset($arr);

// Summary KPIs
$totalPeriod    = array_sum($monthlyTotals);
$peakMonthLabel = '—';
$peakMonthCount = 0;
if (!empty($monthlyTotals)) {
    $maxIdx         = array_search(max($monthlyTotals), $monthlyTotals);
    $peakMonthLabel = $monthLabels[$maxIdx] ?? '—';
    $peakMonthCount = $monthlyTotals[$maxIdx] ?? 0;
}

// By category (last 12 months)
$byCat = $pdo->query("
    SELECT c.name, COUNT(*) AS total
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY c.id
    ORDER BY total DESC
")->fetchAll();

// By severity (last 12 months)
$bySev = $pdo->query("
    SELECT severity, COUNT(*) AS total
    FROM incidents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY severity
    ORDER BY FIELD(severity,'Critical','High','Medium','Low')
")->fetchAll();

// By status (last 12 months)
$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM incidents
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY status
    ORDER BY FIELD(status,'New','Acknowledged','In Progress','Resolved','Closed')
")->fetchAll();

// Top affected systems (all time)
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
    ORDER BY FIELD(severity,'Critical','High','Medium','Low')
")->fetchAll();

// Open count
$openCount = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();

// Dynamic heights for chart wrappers
$catChartH  = max(count($byCat)      * 46, 180);
$statChartH = max(count($byStatus)   * 52, 180);
$sysChartH  = max(count($topSystems) * 44, 180);

// Single JSON blob — keeps all PHP echos out of the main <script> block
$chartData = json_encode([
    'monthLabels' => $monthLabels,
    'critical'    => $series['Critical'],
    'high'        => $series['High'],
    'medium'      => $series['Medium'],
    'low'         => $series['Low'],
    'catLabels'   => array_column($byCat,      'name'),
    'catData'     => array_map('intval', array_column($byCat,      'total')),
    'sevLabels'   => array_column($bySev,      'severity'),
    'sevData'     => array_map('intval', array_column($bySev,      'total')),
    'statLabels'  => array_column($byStatus,   'status'),
    'statData'    => array_map('intval', array_column($byStatus,   'total')),
    'sysLabels'   => array_column($topSystems, 'affected_system'),
    'sysData'     => array_map('intval', array_column($topSystems, 'total')),
    'rtLabels'    => array_column($responseTimes, 'severity'),
    'rtActual'    => array_map('floatval', array_column($responseTimes, 'avg_hours')),
    'slaMap'      => SLA_HOURS,
], JSON_HEX_TAG | JSON_HEX_AMP);
$chartFlags = json_encode([
    'monthly' => !empty($monthLabels),
    'cat'     => !empty($byCat),
    'sev'     => !empty($bySev),
    'status'  => !empty($byStatus),
    'systems' => !empty($topSystems),
    'rt'      => !empty($responseTimes),
]);

$pageTitle = 'Trend Reports';
include __DIR__ . '/../../includes/header.php';
?>

<!-- ApexCharts replaces Chart.js for all trend charts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-graph-up-arrow me-2 text-cyan"></i>Trend Reports
        </h1>
        <p class="page-subtitle">12-month incident trends, category analysis, and response-time metrics</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/public/analytics/overview.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart-fill me-1"></i> Overview
        </a>
        <a href="<?= APP_URL ?>/public/analytics/export.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- ── KPI Summary ────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-calendar3-range-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalPeriod) ?></div>
                <div class="stat-label">This Period</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-bar-chart-steps"></i></div>
            <div>
                <div class="stat-value"><?= $peakMonthCount ?></div>
                <div class="stat-label">Peak Month</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:.1rem;"><?= e($peakMonthLabel) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($openCount) ?></div>
                <div class="stat-label">Still Open</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-stopwatch-fill"></i></div>
            <div>
                <div class="stat-value"><?= count($responseTimes) ?></div>
                <div class="stat-label">Severities w/ SLA</div>
            </div>
        </div>
    </div>
</div>

<!-- ── 1. Monthly by Severity (stacked bar) ───────────────── -->
<div class="cirms-card mb-3">
    <div class="cirms-card-header">
        <h2 class="cirms-card-title">
            <i class="bi bi-bar-chart-fill me-2 text-cyan"></i>Monthly Incidents by Severity
        </h2>
        <span class="badge-pill-muted">Last 12 months</span>
    </div>
    <?php if (empty($monthLabels)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No incident data in the last 12 months.
    </div>
    <?php else: ?>
    <div id="chartStacked" style="min-height:280px;"></div>
    <?php endif; ?>
</div>

<!-- ── 2. By Category + Severity Distribution ─────────────── -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-tag-fill me-2 text-cyan"></i>Incidents by Category
                </h2>
            </div>
            <?php if (empty($byCat)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div id="chartCat" style="min-height:<?= $catChartH ?>px;"></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-cyan"></i>Severity Distribution
                </h2>
            </div>
            <?php if (empty($bySev)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div id="chartSev" style="min-height:280px;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── 3. Status Breakdown + Top Affected Systems ─────────── -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-layers-fill me-2 text-cyan"></i>Status Breakdown
                </h2>
            </div>
            <?php if (empty($byStatus)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No data yet.
            </div>
            <?php else: ?>
            <div id="chartStatus" style="min-height:<?= $statChartH ?>px;"></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-pc-display me-2 text-cyan"></i>Top Affected Systems
                </h2>
            </div>
            <?php if (empty($topSystems)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No affected systems recorded.
            </div>
            <?php else: ?>
            <div id="chartSystems" style="min-height:<?= $sysChartH ?>px;"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── 4. Resolution Time vs SLA ──────────────────────────── -->
<div class="cirms-card mb-3">
    <div class="cirms-card-header">
        <h2 class="cirms-card-title">
            <i class="bi bi-stopwatch-fill me-2 text-cyan"></i>Average Resolution Time vs SLA Target
        </h2>
    </div>
    <?php if (empty($responseTimes)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No resolved incidents yet.
    </div>
    <?php else: ?>
    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <div id="chartResolution" style="min-height:220px;"></div>
        </div>
        <div class="col-lg-5">
            <table class="cirms-table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Avg Hours</th>
                        <th>SLA Target</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($responseTimes as $rt):
                    $target = SLA_HOURS[$rt['severity']] ?? 72;
                    $avg    = (float) $rt['avg_hours'];
                    $met    = $avg <= $target;
                ?>
                <tr>
                    <td><span class="badge <?= severity_class($rt['severity']) ?>"><?= e($rt['severity']) ?></span></td>
                    <td><span class="<?= $met ? 'sla-ok' : 'sla-breach' ?>"><?= $avg ?>h</span></td>
                    <td class="text-muted" style="font-size:.83rem;"><?= $target ?>h</td>
                    <td>
                        <?php if ($met): ?>
                        <span class="badge" style="background:rgba(34,197,94,.1);color:#16a34a;border:1px solid rgba(34,197,94,.25);">
                            <i class="bi bi-check-circle-fill me-1"></i>Met
                        </span>
                        <?php else: ?>
                        <span class="badge" style="background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.25);">
                            <i class="bi bi-x-circle-fill me-1"></i>Breached
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.badge-pill-muted {
    font-size:.72rem; background:#f0f4f8; color:#64748b;
    border:1px solid #dde3ea; border-radius:20px; padding:.2rem .6rem;
}
</style>

<!-- PHP data → JS (only PHP echos here) -->
<script>var TR = <?= $chartData ?>; var TR_SHOW = <?= $chartFlags ?>;</script>

<!-- ApexCharts initialisation — zero PHP inside -->
<script>
(function () {
    'use strict';

    var FONT = "'DM Sans', sans-serif";
    var SEV_COLORS    = { Critical:'#ef4444', High:'#f97316', Medium:'#f59e0b', Low:'#22c55e' };
    var STATUS_COLORS = { New:'#6366f1', Acknowledged:'#0ea5e9', 'In Progress':'#f59e0b', Resolved:'#22c55e', Closed:'#94a3b8' };
    var CAT_PALETTE   = ['#0ea5e9','#6366f1','#f59e0b','#ef4444','#22c55e','#f97316','#a78bfa','#fb7185'];

    /* ── 1. Monthly stacked bar ───────────────────────────── */
    if (TR_SHOW.monthly && document.getElementById('chartStacked')) {
        new ApexCharts(document.getElementById('chartStacked'), {
            chart  : { type:'bar', height:280, stacked:true, toolbar:{ show:false }, fontFamily:FONT, animations:{ enabled:true, speed:800 } },
            series : [
                { name:'Critical', data: TR.critical },
                { name:'High',     data: TR.high     },
                { name:'Medium',   data: TR.medium   },
                { name:'Low',      data: TR.low      },
            ],
            colors : ['#ef4444','#f97316','#f59e0b','#22c55e'],
            xaxis  : { categories: TR.monthLabels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            yaxis  : { labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            plotOptions: { bar:{ borderRadius:3, columnWidth:'55%' } },
            legend : { position:'top', fontSize:'12px' },
            grid   : { borderColor:'#f0f4f8', strokeDashArray:4 },
            dataLabels: { enabled:false },
            tooltip: { theme:'light', shared:true, intersect:false },
        }).render();
    }

    /* ── 2. By category – horizontal bar ─────────────────── */
    if (TR_SHOW.cat && document.getElementById('chartCat')) {
        new ApexCharts(document.getElementById('chartCat'), {
            chart  : { type:'bar', fontFamily:FONT, toolbar:{ show:false }, animations:{ enabled:true, speed:700 } },
            series : [{ name:'Incidents', data: TR.catData }],
            xaxis  : { categories: TR.catLabels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            plotOptions: { bar:{ horizontal:true, borderRadius:4, distributed:true, barHeight:'60%' } },
            colors : CAT_PALETTE,
            dataLabels: { enabled:true, style:{ fontSize:'11px' } },
            legend : { show:false },
            grid   : { borderColor:'#f0f4f8' },
            tooltip: { theme:'light' },
        }).render();
    }

    /* ── 3. Severity donut ────────────────────────────────── */
    if (TR_SHOW.sev && document.getElementById('chartSev')) {
        new ApexCharts(document.getElementById('chartSev'), {
            chart  : { type:'donut', height:280, fontFamily:FONT, animations:{ enabled:true, speed:900 } },
            series : TR.sevData,
            labels : TR.sevLabels,
            colors : TR.sevLabels.map(function(l){ return SEV_COLORS[l]||'#94a3b8'; }),
            plotOptions: { pie:{ donut:{ size:'65%', labels:{ show:true,
                total:{ show:true, showAlways:true, label:'Total', fontSize:'13px', color:'#64748b',
                    formatter: function(w){ return w.globals.seriesTotals.reduce(function(a,b){return a+b;},0); }
                }
            } } } },
            dataLabels: { enabled:true, formatter: function(v){ return Math.round(v)+'%'; } },
            legend : { position:'bottom', fontSize:'12px' },
            tooltip: { theme:'light', y:{ formatter: function(v){ return v+' incidents'; } } },
        }).render();
    }

    /* ── 4. Status breakdown – horizontal bar ────────────── */
    if (TR_SHOW.status && document.getElementById('chartStatus')) {
        new ApexCharts(document.getElementById('chartStatus'), {
            chart  : { type:'bar', fontFamily:FONT, toolbar:{ show:false }, animations:{ enabled:true, speed:700 } },
            series : [{ name:'Incidents', data: TR.statData }],
            xaxis  : { categories: TR.statLabels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            plotOptions: { bar:{ horizontal:true, borderRadius:4, distributed:true, barHeight:'55%' } },
            colors : TR.statLabels.map(function(l){ return STATUS_COLORS[l]||'#94a3b8'; }),
            dataLabels: { enabled:true, style:{ fontSize:'11px' } },
            legend : { show:false },
            grid   : { borderColor:'#f0f4f8' },
            tooltip: { theme:'light' },
        }).render();
    }

    /* ── 5. Top affected systems ──────────────────────────── */
    if (TR_SHOW.systems && document.getElementById('chartSystems')) {
        new ApexCharts(document.getElementById('chartSystems'), {
            chart  : { type:'bar', fontFamily:FONT, toolbar:{ show:false }, animations:{ enabled:true, speed:700 } },
            series : [{ name:'Incidents', data: TR.sysData }],
            xaxis  : { categories: TR.sysLabels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            plotOptions: { bar:{ horizontal:true, borderRadius:4, barHeight:'55%', distributed:true } },
            colors : CAT_PALETTE,
            dataLabels: { enabled:true, style:{ fontSize:'11px' } },
            legend : { show:false },
            grid   : { borderColor:'#f0f4f8' },
            tooltip: { theme:'light' },
        }).render();
    }

    /* ── 6. Resolution time vs SLA target ────────────────── */
    if (TR_SHOW.rt && document.getElementById('chartResolution')) {
        var slaTargets = TR.rtLabels.map(function(l){ return TR.slaMap[l] || 72; });
        new ApexCharts(document.getElementById('chartResolution'), {
            chart  : { type:'bar', height:220, fontFamily:FONT, toolbar:{ show:false }, animations:{ enabled:true, speed:800 } },
            series : [
                { name:'Actual (h)',     data: TR.rtActual   },
                { name:'SLA Target (h)', data: slaTargets    },
            ],
            colors : ['#6366f1','#22c55e'],
            xaxis  : { categories: TR.rtLabels, labels:{ style:{ fontSize:'11px', colors:'#94a3b8' } } },
            yaxis  : { title:{ text:'Hours', style:{ fontSize:'11px' } }, labels:{ style:{ fontSize:'11px' } } },
            plotOptions: { bar:{ columnWidth:'45%', borderRadius:3 } },
            legend : { position:'top', fontSize:'12px' },
            grid   : { borderColor:'#f0f4f8', strokeDashArray:4 },
            dataLabels: { enabled:true, style:{ fontSize:'10px' }, formatter: function(v){ return v+'h'; } },
            tooltip: { theme:'light', shared:true },
        }).render();
    }

}());
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
