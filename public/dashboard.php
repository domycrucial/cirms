<?php
// ============================================================
// CIRMS – Dashboard
// public/dashboard.php
//
// Role-aware landing page after login.
//   reporter → sees only their own incidents
//   officer / admin → sees all incidents system-wide
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
session_start_secure();
require_login();

$user = current_user();
$pdo  = db();
$uid  = (int) $user['id'];
$role = $user['role'];

// ── KPI stats in a single query ───────────────────────────────
if ($role === 'reporter') {
    $s = $pdo->prepare("
        SELECT
            COUNT(*)                                                                AS total,
            SUM(CASE WHEN status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS open,
            SUM(CASE WHEN status IN     ('Resolved','Closed') THEN 1 ELSE 0 END)   AS resolved,
            SUM(CASE WHEN severity='Critical'
                      AND status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS critical,
            SUM(CASE WHEN sla_deadline < NOW()
                      AND status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS sla_breached
        FROM incidents WHERE reporter_id = ?
    ");
    $s->execute([$uid]);
} else {
    $s = $pdo->query("
        SELECT
            COUNT(*)                                                                AS total,
            SUM(CASE WHEN status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS open,
            SUM(CASE WHEN status IN     ('Resolved','Closed') THEN 1 ELSE 0 END)   AS resolved,
            SUM(CASE WHEN severity='Critical'
                      AND status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS critical,
            SUM(CASE WHEN sla_deadline < NOW()
                      AND status NOT IN ('Resolved','Closed') THEN 1 ELSE 0 END)   AS sla_breached
        FROM incidents
    ");
}
$raw   = $s->fetch(PDO::FETCH_ASSOC);
$stats = [
    'total'        => (int)($raw['total']        ?? 0),
    'open'         => (int)($raw['open']         ?? 0),
    'resolved'     => (int)($raw['resolved']     ?? 0),
    'critical'     => (int)($raw['critical']     ?? 0),
    'sla_breached' => (int)($raw['sla_breached'] ?? 0),
];
$resolvedPct = $stats['total'] > 0
    ? round(($stats['resolved'] / $stats['total']) * 100)
    : 0;

// ── 7-day trend ───────────────────────────────────────────────
$tStmt = $pdo->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS n
    FROM   incidents
    WHERE  created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    " . ($role === 'reporter' ? 'AND reporter_id = ?' : '') . "
    GROUP  BY d ORDER BY d ASC
");
$tStmt->execute($role === 'reporter' ? [$uid] : []);

$trendMap = [];
for ($i = 6; $i >= 0; $i--) {
    $trendMap[date('Y-m-d', strtotime("-{$i} days"))] = 0;
}
foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($trendMap[$r['d']])) $trendMap[$r['d']] = (int) $r['n'];
}
$chartJson = json_encode([
    'labels' => array_map(fn($d) => date('D d', strtotime($d)), array_keys($trendMap)),
    'data'   => array_values($trendMap),
], JSON_HEX_TAG | JSON_HEX_AMP);

// ── SLA breach panel (top 5) ──────────────────────────────────
$slaBreaches = [];
if ($role !== 'reporter' && $stats['sla_breached'] > 0) {
    $slaBreaches = $pdo->query("
        SELECT i.reference, i.title, i.severity, i.sla_deadline,
               u.full_name AS reporter_name
        FROM   incidents i
        JOIN   users u ON u.id = i.reporter_id
        WHERE  i.sla_deadline < NOW()
          AND  i.status NOT IN ('Resolved','Closed')
        ORDER  BY i.sla_deadline ASC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Recent incidents ──────────────────────────────────────────
if ($role === 'reporter') {
    $rStmt = $pdo->prepare("
        SELECT i.*, c.name AS category_name
        FROM   incidents i JOIN categories c ON c.id = i.category_id
        WHERE  i.reporter_id = ?
        ORDER  BY i.created_at DESC LIMIT 8
    ");
    $rStmt->execute([$uid]);
} else {
    $rStmt = $pdo->query("
        SELECT i.*, c.name AS category_name, u.full_name AS reporter_name
        FROM   incidents i
        JOIN   categories c ON c.id  = i.category_id
        JOIN   users u       ON u.id = i.reporter_id
        ORDER  BY i.created_at DESC LIMIT 8
    ");
}
$incidents = $rStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard';

// Extra <style> injected into header's <head> via output buffer trick
// is not needed — styles are in the page <style> block below which
// the browser handles correctly even when placed after content.
// ApexCharts is loaded before first chart render via DOMContentLoaded.

include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>

<!-- ── Page Header ────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-grid-1x2-fill me-2 text-cyan"></i>Dashboard
        </h1>
        <p class="page-subtitle">
            Welcome back, <strong><?= e($user['name']) ?></strong>
            &nbsp;&middot;&nbsp;
            <span id="dash-ts" style="font-size:.76rem;color:#94a3b8;"></span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($role === 'reporter'): ?>
        <a href="<?= APP_URL ?>/public/incidents/report.php"
           class="btn btn-dark btn-cirms btn-primary-cirms">
            <i class="bi bi-plus-circle me-1"></i> Report Incident
        </a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/public/incidents/list.php"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i> All Incidents
        </a>
        <?php if ($role === 'admin'): ?>
        <a href="<?= APP_URL ?>/public/analytics/overview.php"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart-fill me-1"></i> Analytics
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── KPI Cards ──────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-cyan" style="animation-delay:0s">
            <div class="kpi-icon-wrap"><i class="bi bi-collection-fill"></i></div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpi-total"><?= $stats['total'] ?></div>
                <div class="kpi-label">Total Incidents</div>
            </div>
            <div class="kpi-glow"></div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-amber" style="animation-delay:.08s">
            <div class="kpi-icon-wrap"><i class="bi bi-hourglass-split"></i></div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpi-open"><?= $stats['open'] ?></div>
                <div class="kpi-label">Open</div>
            </div>
            <div class="kpi-glow"></div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-green" style="animation-delay:.16s">
            <div class="kpi-icon-wrap"><i class="bi bi-check2-circle"></i></div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpi-resolved"><?= $stats['resolved'] ?></div>
                <div class="kpi-label">Resolved</div>
                <div class="kpi-sub"><?= $resolvedPct ?>% resolution rate</div>
            </div>
            <div class="kpi-glow"></div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="kpi-card <?= $stats['critical'] > 0 ? 'kpi-red kpi-pulse' : 'kpi-red' ?>"
             style="animation-delay:.24s">
            <div class="kpi-icon-wrap"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpi-critical"><?= $stats['critical'] ?></div>
                <div class="kpi-label">Critical Open</div>
                <?php if ($stats['sla_breached'] > 0): ?>
                <div class="kpi-sub kpi-sub-danger"><?= $stats['sla_breached'] ?> SLA breached</div>
                <?php endif; ?>
            </div>
            <div class="kpi-glow"></div>
        </div>
    </div>

</div>

<!-- ── Trend Chart + SLA Panel ────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="<?= $slaBreaches ? 'col-lg-7' : 'col-12' ?>">
        <div class="cirms-card h-100">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title">
                    <i class="bi bi-graph-up me-2 text-cyan"></i>7-Day Incident Trend
                </h2>
                <span class="badge-pill-muted">Auto-refreshes every 60 s</span>
            </div>
            <div id="dash-chart" style="min-height:200px;"></div>
        </div>
    </div>

    <?php if ($slaBreaches): ?>
    <div class="col-lg-5">
        <div class="cirms-card h-100 sla-alert-card">
            <div class="cirms-card-header" style="border-color:#fecaca;">
                <h2 class="cirms-card-title sla-alert-title">
                    <i class="bi bi-alarm-fill me-2"></i>SLA Breached
                </h2>
                <span class="badge bg-danger"><?= $stats['sla_breached'] ?></span>
            </div>
            <div class="d-flex flex-column gap-2">
            <?php foreach ($slaBreaches as $b): ?>
                <div class="sla-breach-row">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div style="min-width:0;">
                            <div class="ref-number" style="font-size:.72rem;"><?= e($b['reference']) ?></div>
                            <div class="text-truncate" style="font-size:.82rem;font-weight:600;max-width:180px;"
                                 title="<?= e($b['title']) ?>"><?= e($b['title']) ?></div>
                        </div>
                        <span class="badge <?= severity_class($b['severity']) ?> flex-shrink-0">
                            <?= e($b['severity']) ?>
                        </span>
                    </div>
                    <div style="font-size:.71rem;color:#ef4444;margin-top:.25rem;">
                        <i class="bi bi-clock me-1"></i>
                        Deadline: <?= date('d M H:i', strtotime($b['sla_deadline'])) ?>
                        &middot; <?= e($b['reporter_name']) ?>
                    </div>
                    <a href="<?= APP_URL ?>/public/incidents/view.php?ref=<?= e($b['reference']) ?>"
                       class="sla-view-link">View &rarr;</a>
                </div>
            <?php endforeach; ?>
            <?php if ($stats['sla_breached'] > 5): ?>
                <a href="<?= APP_URL ?>/public/incidents/list.php"
                   class="btn btn-sm btn-outline-danger w-100 mt-1">
                    View all <?= $stats['sla_breached'] ?> breached
                </a>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ── Resolution Rate Bar ────────────────────────────────── -->
<div class="cirms-card mb-4">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="stat-icon green flex-shrink-0">
            <i class="bi bi-check2-all"></i>
        </div>
        <div class="flex-grow-1" style="min-width:180px;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span style="font-size:.85rem;font-weight:600;color:var(--navy);">
                    Resolution Rate
                </span>
                <strong style="font-family:'Space Mono',monospace;font-size:.9rem;color:var(--navy);">
                    <?= $resolvedPct ?>%
                </strong>
            </div>
            <div style="height:8px;background:var(--bg);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:<?= $resolvedPct ?>%;
                            background:linear-gradient(90deg,#22c55e,#16a34a);
                            border-radius:99px;transition:width 1s ease;"></div>
            </div>
            <div class="text-muted mt-1" style="font-size:.76rem;">
                <?= $stats['resolved'] ?> of <?= $stats['total'] ?> incidents resolved or closed
            </div>
        </div>
    </div>
</div>

<!-- ── Recent Incidents ───────────────────────────────────── -->
<div class="cirms-card">
    <div class="cirms-card-header">
        <h2 class="cirms-card-title">
            <i class="bi bi-clock-history me-2"></i>Recent Incidents
        </h2>
        <a href="<?= APP_URL ?>/public/incidents/<?= $role === 'reporter' ? 'my-reports' : 'list' ?>.php"
           class="btn btn-outline-secondary btn-sm">
            View All <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <?php if (empty($incidents)): ?>
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h5>No incidents yet</h5>
        <?php if ($role === 'reporter'): ?>
        <p>Submit your first incident report to get started.</p>
        <a href="<?= APP_URL ?>/public/incidents/report.php"
           class="btn btn-dark btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Report Incident
        </a>
        <?php else: ?>
        <p>No incidents have been submitted yet.</p>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="table-responsive">
        <table class="cirms-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Title</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <?php if ($role !== 'reporter'): ?><th>Reporter</th><?php endif; ?>
                    <th>Date</th>
                    <th>SLA</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incidents as $inc):
                $isClosed = in_array($inc['status'], ['Resolved','Closed'], true);
                $dl       = $inc['sla_deadline'] ? strtotime($inc['sla_deadline']) : 0;
                $now      = time();
                $breached = !$isClosed && $dl && $dl < $now;
                $warning  = !$isClosed && $dl && ($dl - $now) < 3600 && !$breached;
            ?>
            <tr class="<?= $breached ? 'row-sla-breach' : '' ?>">
                <td>
                    <span class="ref-number"><?= e($inc['reference']) ?></span>
                </td>
                <td style="max-width:220px;">
                    <span class="d-block text-truncate fw-semibold"
                          style="font-size:.875rem;"
                          title="<?= e($inc['title']) ?>">
                        <?= e($inc['title']) ?>
                    </span>
                    <span class="text-muted" style="font-size:.75rem;"><?= e($inc['category_name']) ?></span>
                </td>
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
                <?php if ($role !== 'reporter'): ?>
                <td style="font-size:.82rem;color:#64748b;">
                    <?= e($inc['reporter_name'] ?? '—') ?>
                </td>
                <?php endif; ?>
                <td style="font-size:.78rem;color:#94a3b8;white-space:nowrap;">
                    <?= date('d M Y', strtotime($inc['created_at'])) ?>
                </td>
                <td style="font-size:.75rem;font-weight:700;white-space:nowrap;">
                    <?php if ($isClosed): ?>
                        <span style="color:#16a34a;">✓ Met</span>
                    <?php elseif ($breached): ?>
                        <span style="color:#ef4444;">⚠ Breached</span>
                    <?php elseif ($warning): ?>
                        <span style="color:#f59e0b;">⚡ &lt;1h</span>
                    <?php else: ?>
                        <span style="color:#94a3b8;">OK</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= APP_URL ?>/public/incidents/view.php?ref=<?= e($inc['reference']) ?>"
                       class="btn btn-sm btn-outline-secondary">
                        View
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Page-specific styles ───────────────────────────────── -->
<style>
/* ── KPI Cards ─────────────────────────────────────────────── */
.kpi-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #fff;
    border-radius: 14px;
    border: 1px solid var(--border);
    padding: 1.25rem 1.4rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    animation: kpiIn .45s cubic-bezier(.22,1,.36,1) both;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,.11); }

@keyframes kpiIn {
    from { opacity:0; transform:translateY(18px); }
    to   { opacity:1; transform:translateY(0); }
}

.kpi-icon-wrap {
    width: 52px; height: 52px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.45rem;
    flex-shrink: 0;
    position: relative; z-index: 1;
}
.kpi-cyan  .kpi-icon-wrap { background:rgba(0,212,255,.12); color:#00aacc; }
.kpi-amber .kpi-icon-wrap { background:rgba(245,158,11,.12); color:#d97706; }
.kpi-green .kpi-icon-wrap { background:rgba(34,197,94,.12);  color:#16a34a; }
.kpi-red   .kpi-icon-wrap { background:rgba(239,68,68,.12);  color:#dc2626; }

.kpi-body { flex: 1; position: relative; z-index: 1; }
.kpi-value {
    font-family: 'Space Mono', monospace;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    color: var(--navy);
}
.kpi-label {
    font-size: .78rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: .25rem;
}
.kpi-sub        { font-size: .72rem; color: #94a3b8; margin-top: .15rem; }
.kpi-sub-danger { color: #ef4444; font-weight: 600; }

/* Accent glow corner */
.kpi-glow {
    position: absolute;
    right: -20px; bottom: -20px;
    width: 90px; height: 90px;
    border-radius: 50%;
    opacity: .06;
}
.kpi-cyan  .kpi-glow { background: #00d4ff; }
.kpi-amber .kpi-glow { background: #f59e0b; }
.kpi-green .kpi-glow { background: #22c55e; }
.kpi-red   .kpi-glow { background: #ef4444; }

/* Critical pulse animation */
.kpi-pulse {
    animation: kpiIn .45s cubic-bezier(.22,1,.36,1) .24s both,
               kpiPulse 2.6s ease-in-out 1.5s infinite;
}
@keyframes kpiPulse {
    0%,100% { box-shadow: 0 2px 12px rgba(0,0,0,.06); }
    50%      { box-shadow: 0 0 0 4px rgba(239,68,68,.14), 0 2px 12px rgba(0,0,0,.06); }
}

/* ── SLA breach card ─────────────────────────────────────── */
.sla-alert-card  { background:#fff9f9 !important; border-color:#fca5a5 !important; }
.sla-alert-title { color:#b91c1c; }
.sla-breach-row  {
    background: #fff;
    border: 1px solid #fecaca;
    border-radius: 9px;
    padding: .6rem .75rem;
}
.sla-view-link {
    display: inline-block;
    margin-top: .2rem;
    font-size: .73rem;
    color: #ef4444;
    font-weight: 700;
    text-decoration: none;
}
.sla-view-link:hover { text-decoration: underline; }

/* ── Empty state ─────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 3.5rem 1rem;
    color: #94a3b8;
}
.empty-state i {
    font-size: 2.8rem;
    display: block;
    margin-bottom: .75rem;
    opacity: .45;
}
.empty-state h5 { color: #64748b; font-size: 1rem; margin-bottom: .4rem; }
.empty-state p  { font-size: .875rem; margin-bottom: 1rem; }

@media (max-width: 480px) {
    .kpi-value { font-size: 1.6rem; }
    .kpi-card  { padding: 1rem; }
}
</style>

<!-- ── Chart + Live Refresh ───────────────────────────────── -->
<script>
(function () {
    'use strict';

    var CD = <?= $chartJson ?>;

    /* ── 7-day area sparkline ─────────────────────────────── */
    var chart = new ApexCharts(document.getElementById('dash-chart'), {
        chart: {
            type: 'area', height: 200,
            toolbar: { show: false },
            fontFamily: "'DM Sans', sans-serif",
            animations: { enabled: true, speed: 900 },
        },
        series : [{ name: 'Incidents', data: CD.data }],
        xaxis  : {
            categories: CD.labels,
            labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis  : {
            min: 0, tickAmount: 3,
            labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
        },
        stroke  : { curve: 'smooth', width: 2.5 },
        fill    : {
            type: 'gradient',
            gradient: { shadeIntensity:1, opacityFrom:.4, opacityTo:.02, stops:[0,95] },
        },
        colors      : ['#00aacc'],
        markers     : { size:5, colors:['#00aacc'], strokeColors:'#fff', strokeWidth:2, hover:{size:7} },
        dataLabels  : { enabled: false },
        grid        : { borderColor:'#f0f4f8', strokeDashArray:4, xaxis:{lines:{show:false}} },
        tooltip     : {
            theme: 'light',
            y: { formatter: function(v){ return v+' incident'+(v!==1?'s':''); } },
        },
        noData: { text:'No incidents in this period', style:{ color:'#94a3b8', fontSize:'13px' } },
    });
    chart.render();

    /* ── Live KPI refresh every 60 s ─────────────────────── */
    var API = '<?= APP_URL ?>/public/api/stats.php';
    var ts  = document.getElementById('dash-ts');

    function refresh() {
        fetch(API, { credentials: 'same-origin' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(j){
                if (!j || !j.ok) return;
                var s = j.stats;
                var map = {
                    'kpi-total'   : s.total,
                    'kpi-open'    : s.open,
                    'kpi-resolved': s.resolved,
                    'kpi-critical': s.critical,
                };
                Object.keys(map).forEach(function(id){
                    var el = document.getElementById(id);
                    if (el) el.textContent = map[id].toLocaleString();
                });
                chart.updateSeries([{ name:'Incidents', data: j.trend7.data }]);
                chart.updateOptions({ xaxis:{ categories: j.trend7.labels } });
                if (ts) ts.textContent = 'Updated ' + new Date().toLocaleTimeString();
            })
            .catch(function(){});
    }

    if (ts) ts.textContent = 'Live';
    setInterval(refresh, 60000);

    /* ── Bootstrap tooltips ──────────────────────────────── */
    document.querySelectorAll('[title]').forEach(function(el){
        if (typeof bootstrap !== 'undefined')
            new bootstrap.Tooltip(el, { trigger:'hover', placement:'top' });
    });

}());
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
