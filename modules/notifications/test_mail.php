<?php
// ============================================================
// CIRMS – SMTP Test Script
// modules/notifications/test_mail.php
//
// HOW TO USE:
//   Option A — Browser:
//     Open: http://localhost/cirmsv2/modules/notifications/test_mail.php
//     ⚠ DELETE THIS FILE BEFORE GOING LIVE
//
//   Option B — PowerShell:
//     cd C:\xampp\htdocs\cirmsv2
//     php modules/notifications/test_mail.php
//
// This script sends ONE test email to NOTIFY_IT_EMAIL and
// shows you exactly what went wrong if it fails.
// ============================================================

// Allow browser access for easy testing (remove in production)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/mailer.php';

function get_smtp_settings() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'user' => SMTP_USER,
        'pass' => SMTP_PASS,
        'from' => SMTP_FROM,
        'from_name' => SMTP_FROM_NAME,
        'notify_email' => NOTIFY_IT_EMAIL,
    ];
}

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "CIRMS SMTP Test\n";
echo str_repeat("=", 50) . "\n\n";

// Show current config (never show password in production!)
$smtp = get_smtp_settings();
echo "SMTP Host:       " . $smtp['host'] . "\n";
echo "SMTP Port:       " . $smtp['port'] . "\n";
echo "SMTP User:       " . $smtp['user'] . "\n";
echo "SMTP Password:   " . (empty($smtp['pass']) ? "❌ NOT SET" : str_repeat('*', strlen($smtp['pass']))) . "\n";
echo "From:            " . $smtp['from'] . " ({$smtp['from_name']})\n";
echo "IT Notify Email: " . $smtp['notify_email'] . "\n\n";

// Check PHPMailer is installed
$vendorPath = __DIR__ . '/../../vendor/autoload.php';
echo "PHPMailer:       " . (file_exists($vendorPath) ? "✅ Installed (vendor/autoload.php found)" : "❌ NOT found — run: composer require phpmailer/phpmailer") . "\n\n";

if (empty($smtp['user']) || empty($smtp['pass'])) {
    echo "❌ SMTP_USER or SMTP_PASS is empty.\n";
    echo "   Edit config/config.php and fill in your Gmail and App Password.\n";
    echo '</pre>';
    exit;
}

if (!file_exists($vendorPath)) {
    echo "❌ PHPMailer not installed.\n";
    echo "   Run in PowerShell: composer require phpmailer/phpmailer\n";
    echo '</pre>';
    exit;
}

// Build a test email body
$testBody = "
    <p>This is a test email from your CIRMS system.</p>
    <p>If you received this, your SMTP configuration is working correctly.</p>

    <table class='meta-table'>
        <tr><td>Test Sent At:</td><td><strong>" . date('d M Y H:i:s') . "</strong></td></tr>
        <tr><td>SMTP Host:</td><td>" . htmlspecialchars($smtp['host']) . ":" . $smtp['port'] . "</td></tr>
        <tr><td>Sender:</td><td>" . htmlspecialchars($smtp['from']) . "</td></tr>
    </table>

    <p>Your notification system is ready. You can now delete the test_mail.php file.</p>
";

echo "Attempting to send test email to: " . $smtp['notify_email'] . " ...\n\n";

$result = send_email(
    $smtp['notify_email'],
    'IT Security Team',
    '[CIRMS] SMTP Test — Configuration Verified',
    $testBody
);

if ($result) {
    echo "✅ SUCCESS — Email sent to " . $smtp['notify_email'] . "\n\n";
    echo "Check your inbox (and spam/junk folder).\n";
    echo "If received, delete this file before going live:\n";
    echo "   modules/notifications/test_mail.php\n";
} else {
    echo "❌ FAILED — Email was not delivered.\n\n";
    echo "Common causes:\n";
    echo "  1. SMTP_PASS is your Gmail password, not an App Password\n";
    echo "     → Go to myaccount.google.com/apppasswords and generate one\n\n";
    echo "  2. 2-Step Verification not enabled on your Gmail\n";
    echo "     → Go to myaccount.google.com/security and enable it first\n\n";
    echo "  3. Port 587 is blocked by your network/firewall\n";
    echo "     → Try from a different network, or ask your IT department\n\n";
    echo "  4. XAMPP SSL certificate issue\n";
    echo "     → Uncomment the SMTPOptions line in mailer.php send_email()\n\n";
    echo "Check error_log for the full PHPMailer error message.\n";
    echo "On XAMPP, logs are at: C:\\xampp\\php\\logs\\php_error_log\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo '</pre>';
