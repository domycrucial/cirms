<?php
// ============================================================
// CIRMS – Export Incidents as CSV
// public/analytics/export.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// ── If no form submit yet, show the export form ──────────────
if (!isset($_GET['download'])) {
    $pageTitle = 'Export Data';
    include __DIR__ . '/../../includes/header.php';
    ?>
    <div class="page-header">
        <div>
            <h1 class="page-title"><i class="bi bi-download me-2 text-cyan"></i>Export Incident Data</h1>
            <p class="page-subtitle">Download a CSV report of incidents for a specified date range.</p>
        </div>
    </div>

    <div class="cirms-card" style="max-width:500px;">
        <form method="GET" action="">
            <input type="hidden" name="download" value="1">
            <div class="mb-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from" class="form-control"
                       value="<?= e($from) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to" class="form-control"
                       value="<?= e($to) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-dark btn-cirms">
                <i class="bi bi-filetype-csv me-1"></i> Download CSV
            </button>
        </form>
    </div>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// ── Generate CSV ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT i.reference, i.title, c.name AS category, i.severity, i.status,
           u.full_name AS reporter, u.email AS reporter_email,
           a.full_name AS assigned_to,
           i.affected_system, i.incident_time, i.created_at,
           i.sla_deadline, i.resolved_at, i.closed_at
    FROM incidents i
    JOIN categories c ON c.id = i.category_id
    JOIN users u ON u.id = i.reporter_id
    LEFT JOIN users a ON a.id = i.assigned_to
    WHERE DATE(i.created_at) BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$from, $to]);

audit_log('analytics.export', null, null, ['from' => $from, 'to' => $to]);

$filename = 'cirms_incidents_' . $from . '_to_' . $to . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
// BOM for Excel UTF-8 compatibility
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    'Reference', 'Title', 'Category', 'Severity', 'Status',
    'Reporter', 'Reporter Email', 'Assigned To', 'Affected System',
    'Incident Time', 'Submitted At', 'SLA Deadline', 'Resolved At', 'Closed At'
]);

// Data rows
while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['reference'],
        $row['title'],
        $row['category'],
        $row['severity'],
        $row['status'],
        $row['reporter'],
        $row['reporter_email'],
        $row['assigned_to'] ?? '',
        $row['affected_system'] ?? '',
        $row['incident_time'],
        $row['created_at'],
        $row['sla_deadline'] ?? '',
        $row['resolved_at'] ?? '',
        $row['closed_at'] ?? '',
    ]);
}

fclose($out);
exit;
