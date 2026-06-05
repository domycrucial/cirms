<?php
// ============================================================
// IRS – Export Incidents (CSV & PDF)
// public/analytics/export.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

$from   = $_GET['from']   ?? date('Y-01-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

// ── CSV download ──────────────────────────────────────────────
if (isset($_GET['download']) && $format === 'csv') {
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

    audit_log('analytics.export', null, null, ['format' => 'csv', 'from' => $from, 'to' => $to]);

    $filename = 'irs_incidents_' . $from . '_to_' . $to . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Reference', 'Title', 'Category', 'Severity', 'Status',
        'Reporter', 'Reporter Email', 'Assigned To', 'Affected System',
        'Incident Time', 'Submitted At', 'SLA Deadline', 'Resolved At', 'Closed At'
    ]);
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['reference'], $row['title'], $row['category'],
            $row['severity'], $row['status'], $row['reporter'],
            $row['reporter_email'], $row['assigned_to'] ?? '',
            $row['affected_system'] ?? '', $row['incident_time'],
            $row['created_at'], $row['sla_deadline'] ?? '',
            $row['resolved_at'] ?? '', $row['closed_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── Fetch preview data (for PDF page or preview table) ───────
$rows = [];
if (isset($_GET['download']) || isset($_GET['preview'])) {
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['download']) && $format === 'pdf') {
        audit_log('analytics.export', null, null, ['format' => 'pdf', 'from' => $from, 'to' => $to]);
    }
}

$pageTitle = 'Export Data';
include __DIR__ . '/../../includes/header.php';

// Severity badge colours for preview table
$sevColors = [
    'Critical' => '#ef4444', 'High' => '#f97316',
    'Medium'   => '#eab308', 'Low'  => '#22c55e',
];
$statusColors = [
    'New'          => '#6366f1', 'Acknowledged' => '#0ea5e9',
    'In Progress'  => '#f97316', 'Resolved'     => '#22c55e',
    'Closed'       => '#64748b',
];
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-download me-2 text-cyan"></i>Export Incident Data</h1>
        <p class="page-subtitle">Download incident reports for a specified date range as CSV or PDF.</p>
    </div>
</div>

<!-- Export Form Card -->
<div class="cirms-card" style="max-width:560px;">
    <form method="GET" action="" id="exportForm">
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label">From Date</label>
                <input type="date" name="from" class="form-control"
                       value="<?= e($from) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6">
                <label class="form-label">To Date</label>
                <input type="date" name="to" class="form-control"
                       value="<?= e($to) ?>" max="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- CSV download -->
            <button type="submit" name="download" value="1"
                    onclick="document.getElementById('exportFmt').value='csv';"
                    class="btn btn-dark btn-cirms">
                <i class="bi bi-filetype-csv me-1"></i> Download CSV
            </button>
            <!-- PDF download -->
            <button type="submit" name="download" value="1"
                    onclick="document.getElementById('exportFmt').value='pdf';"
                    class="btn btn-danger btn-cirms">
                <i class="bi bi-filetype-pdf me-1"></i> Download PDF
            </button>
            <!-- Preview -->
            <button type="submit" name="preview" value="1"
                    class="btn btn-outline-secondary btn-cirms">
                <i class="bi bi-eye me-1"></i> Preview Data
            </button>
        </div>
        <input type="hidden" name="format" id="exportFmt" value="csv">
    </form>
</div>

<?php if (!empty($rows)): ?>
<!-- Data Preview Table -->
<div class="cirms-card mt-4" id="reportPreview">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">
                Preview &mdash; <?= count($rows) ?> incident<?= count($rows) !== 1 ? 's' : '' ?>
                &nbsp;<span class="text-muted small">(<?= e($from) ?> to <?= e($to) ?>)</span>
            </h5>
        </div>
        <button class="btn btn-sm btn-danger" onclick="generatePDF()">
            <i class="bi bi-filetype-pdf me-1"></i> Download PDF
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="incidentsTable">
            <thead class="table-dark">
                <tr>
                    <th>Reference</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Reporter</th>
                    <th>Assigned To</th>
                    <th>Submitted At</th>
                    <th>Resolved At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><code><?= e($r['reference']) ?></code></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['category']) ?></td>
                    <td>
                        <span class="badge" style="background:<?= $sevColors[$r['severity']] ?? '#64748b' ?>">
                            <?= e($r['severity']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge" style="background:<?= $statusColors[$r['status']] ?? '#64748b' ?>">
                            <?= e($r['status']) ?>
                        </span>
                    </td>
                    <td><?= e($r['reporter']) ?></td>
                    <td><?= e($r['assigned_to'] ?? '—') ?></td>
                    <td><?= e(substr($r['created_at'], 0, 16)) ?></td>
                    <td><?= e($r['resolved_at'] ? substr($r['resolved_at'], 0, 16) : '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Embedded data for jsPDF -->
<script>
var IRS_REPORT_DATA = <?= json_encode(array_map(function($r) {
    return [
        $r['reference'],
        mb_substr($r['title'], 0, 40) . (mb_strlen($r['title']) > 40 ? '…' : ''),
        $r['category'],
        $r['severity'],
        $r['status'],
        $r['reporter'],
        $r['assigned_to'] ?? '—',
        substr($r['created_at'], 0, 16),
        $r['resolved_at'] ? substr($r['resolved_at'], 0, 16) : '—',
    ];
}, $rows), JSON_UNESCAPED_UNICODE) ?>;
var IRS_FROM = '<?= e($from) ?>';
var IRS_TO   = '<?= e($to) ?>';
var IRS_TOTAL = <?= count($rows) ?>;
</script>
<?php endif; ?>

<!-- jsPDF + AutoTable for PDF generation -->
<script src="<?= APP_URL ?>/public/js/jspdf.umd.min.js"></script>
<script src="<?= APP_URL ?>/public/js/jspdf.plugin.autotable.min.js"></script>
<script>
function generatePDF() {
    if (typeof window.jspdf === 'undefined') {
        alert('PDF library not loaded. Please check your internet connection and try again.');
        return;
    }
    var jsPDF = window.jspdf.jsPDF;
    var doc   = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    // Header block
    doc.setFillColor(13, 27, 42);
    doc.rect(0, 0, 297, 22, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(13);
    doc.setFont('helvetica', 'bold');
    doc.text('Institute of Accountancy Arusha (IAA)', 14, 9);
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text('IRS - Incident Report Export', 14, 15);
    doc.setFontSize(8);
    doc.text('Period: ' + (typeof IRS_FROM !== 'undefined' ? IRS_FROM + ' to ' + IRS_TO : 'All dates'), 14, 20);

    // Generated timestamp top-right
    var now = new Date();
    var ts  = now.toLocaleString('en-TZ', { timeZone: 'Africa/Dar_es_Salaam' });
    doc.setFontSize(7);
    doc.text('Generated: ' + ts, 283, 20, { align: 'right' });

    // Summary sub-header
    doc.setFillColor(240, 244, 248);
    doc.rect(0, 22, 297, 8, 'F');
    doc.setTextColor(50, 50, 80);
    doc.setFontSize(8);
    doc.setFont('helvetica', 'bold');
    doc.text('Total Incidents: ' + (typeof IRS_TOTAL !== 'undefined' ? IRS_TOTAL : '—'), 14, 27.5);
    doc.text('System: IRS v1.0', 150, 27.5);

    // Table
    doc.setTextColor(0, 0, 0);
    doc.autoTable({
        head: [['Reference', 'Title', 'Category', 'Severity', 'Status', 'Reporter', 'Assigned To', 'Submitted', 'Resolved']],
        body: typeof IRS_REPORT_DATA !== 'undefined' ? IRS_REPORT_DATA : [],
        startY: 32,
        styles: { fontSize: 7, cellPadding: 2 },
        headStyles: {
            fillColor: [13, 27, 42],
            textColor: [255, 255, 255],
            fontStyle: 'bold',
            fontSize: 7.5,
        },
        alternateRowStyles: { fillColor: [248, 250, 252] },
        columnStyles: {
            0: { cellWidth: 22, fontStyle: 'bold' },
            1: { cellWidth: 48 },
            2: { cellWidth: 38 },
            3: { cellWidth: 18 },
            4: { cellWidth: 22 },
            5: { cellWidth: 35 },
            6: { cellWidth: 30 },
            7: { cellWidth: 28 },
            8: { cellWidth: 28 },
        },
        didParseCell: function(data) {
            if (data.column.index === 3 && data.section === 'body') {
                var sev = data.cell.raw;
                if (sev === 'Critical')     { data.cell.styles.textColor = [239,  68,  68]; data.cell.styles.fontStyle = 'bold'; }
                else if (sev === 'High')    { data.cell.styles.textColor = [249, 115,  22]; }
                else if (sev === 'Medium')  { data.cell.styles.textColor = [202, 138,   4]; }
                else if (sev === 'Low')     { data.cell.styles.textColor = [ 34, 197,  94]; }
            }
            if (data.column.index === 4 && data.section === 'body') {
                var st = data.cell.raw;
                if (st === 'Resolved')      { data.cell.styles.textColor = [ 34, 197,  94]; }
                else if (st === 'Closed')   { data.cell.styles.textColor = [100, 116, 139]; }
                else if (st === 'New')      { data.cell.styles.textColor = [ 99, 102, 241]; }
            }
        },
        foot: [['', '', '', '', '', '', '', 'Total: ' + (typeof IRS_TOTAL !== 'undefined' ? IRS_TOTAL : ''), '']],
        footStyles: { fillColor: [13, 27, 42], textColor: [255, 255, 255], fontStyle: 'bold', fontSize: 7 },
    });

    // Footer on every page
    var pageCount = doc.internal.getNumberOfPages();
    for (var i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(6.5);
        doc.setTextColor(150, 150, 150);
        doc.text('IRS – Institute of Accountancy Arusha  |  Confidential', 14, doc.internal.pageSize.height - 5);
        doc.text('Page ' + i + ' of ' + pageCount, 283, doc.internal.pageSize.height - 5, { align: 'right' });
    }

    var fname = 'IRS_Incidents_' + (typeof IRS_FROM !== 'undefined' ? IRS_FROM : 'export') + '_to_' + (typeof IRS_TO !== 'undefined' ? IRS_TO : 'export') + '.pdf';
    doc.save(fname);
}

// If direct PDF download was requested via the form button
<?php if (isset($_GET['download']) && $format === 'pdf' && !empty($rows)): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(generatePDF, 600);
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
