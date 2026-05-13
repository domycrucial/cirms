<?php
// ============================================================
// CIRMS – Secure Attachment Download
// modules/incidents/download.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['officer', 'admin']);

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
$stmt->execute([$id]);
$att = $stmt->fetch();

if (!$att) {
    http_response_code(404);
    die(render_error(404, 'File not found.'));
}

$filePath = UPLOAD_DIR . $att['filename'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die(render_error(404, 'File does not exist on disk.'));
}

audit_log('attachment.downloaded', 'attachment', $id);

// Serve the file safely
$action = $_GET['action'] ?? 'download';
$disposition = ($action === 'view') ? 'inline' : 'attachment';

header('Content-Type: ' . $att['mime_type']);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($att['original']) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
