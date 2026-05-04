<?php
// ============================================================
// CIRMS – Notification Queue Processor
// modules/notifications/process_queue.php
//
// Run via cron every minute:
//   * * * * * php /var/www/cirms/modules/notifications/process_queue.php
// ============================================================

// CLI only – never run via web
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only.');
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/mailer.php';

$pdo = db();

// Fetch up to 50 pending notifications
$stmt = $pdo->query("
    SELECT n.*, u.email AS user_email, u.full_name,
           i.reference, i.title, i.severity, i.status
    FROM notifications n
    JOIN users u ON u.id = n.user_id
    LEFT JOIN incidents i ON i.id = n.incident_id
    WHERE n.status = 'pending'
    ORDER BY n.id ASC
    LIMIT 50
");
$notifications = $stmt->fetchAll();

if (empty($notifications)) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending notifications.\n";
    exit(0);
}

$sent = 0;
$failed = 0;

foreach ($notifications as $notif) {
    // Build the email body based on notification type
    $body = match($notif['type']) {
        'incident.submitted' => "
            <p>Dear IT Security Team,</p>
            <p>A new cybersecurity incident has been submitted:</p>
            <table>
                <tr><td><strong>Reference:</strong></td><td>{$notif['reference']}</td></tr>
                <tr><td><strong>Severity:</strong></td><td>{$notif['severity']}</td></tr>
                <tr><td><strong>Title:</strong></td><td>{$notif['title']}</td></tr>
            </table>
            <p><a href='" . APP_URL . "/public/incidents/view.php?id={$notif['incident_id']}' class='btn'>
                View Incident →
            </a></p>
        ",
        'status.changed' => "
            <p>Dear {$notif['full_name']},</p>
            <p>Your incident <strong>{$notif['reference']}</strong> status has been updated to
               <strong>{$notif['status']}</strong>.</p>
            <p><a href='" . APP_URL . "/public/incidents/view.php?id={$notif['incident_id']}' class='btn'>
                View Report →
            </a></p>
        ",
        default => "<p>{$notif['subject']}</p>",
    };

    $success = send_email($notif['user_email'], $notif['subject'], $body);

    $pdo->prepare("
        UPDATE notifications
        SET status = ?, sent_at = NOW(), error_msg = NULL
        WHERE id = ?
    ")->execute([$success ? 'sent' : 'failed', $notif['id']]);

    if ($success) {
        $sent++;
        echo "[" . date('H:i:s') . "] SENT   → {$notif['user_email']} ({$notif['type']})\n";
    } else {
        $failed++;
        $pdo->prepare("UPDATE notifications SET error_msg = 'Mail delivery failed' WHERE id = ?")
            ->execute([$notif['id']]);
        echo "[" . date('H:i:s') . "] FAILED → {$notif['user_email']} ({$notif['type']})\n";
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Done. Sent: $sent | Failed: $failed\n";
exit(0);
