<?php
// ============================================================
// CIRMS – Real-Time Stats API Endpoint
// public/api/stats.php
//
// Returns JSON with live incident statistics.
// Called via AJAX by dashboard.php and analytics pages to
// refresh KPI counters and chart data without a full page reload.
//
// Access: any logged-in user (reporters see own stats only,
//         officers and admins see system-wide stats).
//
// Response: application/json  { stats:{}, charts:{} }
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(); // enforces auth + session timeout

// Always respond as JSON; never cache this endpoint
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$pdo  = db();
$user = current_user();
$uid  = (int) $user['id'];
$role = $user['role'];

// ── Helper: parameterised WHERE clause based on role ─────────
// Reporters only see their own incidents; officers and admins see all.
$ownWhere  = $role === 'reporter' ? 'AND reporter_id = ?' : '';
$ownParams = $role === 'reporter' ? [$uid] : [];

// ── KPI counts ───────────────────────────────────────────────
function qCount(PDO $pdo, string $sql, array $p = []): int
{
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return (int) $s->fetchColumn();
}

$total    = qCount($pdo, "SELECT COUNT(*) FROM incidents WHERE 1=1 $ownWhere", $ownParams);
$open     = qCount($pdo, "SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed') $ownWhere", $ownParams);
$resolved = qCount($pdo, "SELECT COUNT(*) FROM incidents WHERE status IN ('Resolved','Closed') $ownWhere", $ownParams);
$critical = qCount($pdo, "SELECT COUNT(*) FROM incidents WHERE severity='Critical' AND status NOT IN ('Resolved','Closed') $ownWhere", $ownParams);
$slaBreached = qCount($pdo,
    "SELECT COUNT(*) FROM incidents WHERE sla_deadline < NOW() AND status NOT IN ('Resolved','Closed') $ownWhere",
    $ownParams
);

// ── 7-day daily trend (for dashboard mini-chart) ──────────────
$trendStmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%a') AS day_label,
           DATE_FORMAT(created_at, '%Y-%m-%d') AS day_key,
           COUNT(*) AS total
    FROM   incidents
    WHERE  created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    " . ($role === 'reporter' ? 'AND reporter_id = ?' : '') . "
    GROUP  BY day_key, day_label
    ORDER  BY day_key ASC
");
$trendStmt->execute($role === 'reporter' ? [$uid] : []);
$trendRows = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// Fill missing days with 0 so the chart always has 7 data points
$trendMap = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i days"));
    $trendMap[$key] = 0;
}
foreach ($trendRows as $row) {
    if (isset($trendMap[$row['day_key']])) {
        $trendMap[$row['day_key']] = (int) $row['total'];
    }
}
$trend7Days = [
    'labels' => array_map(
        fn($d) => date('D', strtotime($d)), // Mon, Tue …
        array_keys($trendMap)
    ),
    'data'   => array_values($trendMap),
];

// ── Overview chart data (for analytics pages) ─────────────────
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month,
           COUNT(*) AS total
    FROM   incidents
    WHERE  created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP  BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER  BY DATE_FORMAT(created_at, '%Y-%m') ASC
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

$avgRes = (float) ($pdo->query(
    "SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)),0) FROM incidents WHERE resolved_at IS NOT NULL"
)->fetchColumn() ?? 0);

// ── Assemble response ─────────────────────────────────────────
echo json_encode([
    'ok'        => true,
    'generated' => date('Y-m-d H:i:s'),
    'stats' => [
        'total'       => $total,
        'open'        => $open,
        'resolved'    => $resolved,
        'critical'    => $critical,
        'slaBreached' => $slaBreached,
        'avgResHours' => round($avgRes, 1),
        'resolvedRate'=> $total > 0 ? round(($resolved / $total) * 100) : 0,
    ],
    'trend7'  => $trend7Days,
    'monthly' => [
        'labels' => array_column($monthly, 'month'),
        'data'   => array_map('intval', array_column($monthly, 'total')),
    ],
    'byCategory' => [
        'labels' => array_column($byCat, 'name'),
        'data'   => array_map('intval', array_column($byCat, 'total')),
    ],
    'bySeverity' => [
        'labels' => array_column($bySev, 'severity'),
        'data'   => array_map('intval', array_column($bySev, 'total')),
    ],
    'byStatus' => [
        'labels' => array_column($byStatus, 'status'),
        'data'   => array_map('intval', array_column($byStatus, 'total')),
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
