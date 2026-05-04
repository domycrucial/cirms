<?php
// ============================================================
// CIRMS – Email Notification Module
// modules/notifications/mailer.php
//
// Uses PHPMailer (install via: composer require phpmailer/phpmailer)
// Fallback: basic mail() if PHPMailer not available
// ============================================================

require_once __DIR__ . '/../../config/config.php';

/**
 * Send an email notification.
 *
 * @param string $to       Recipient email
 * @param string $subject  Email subject
 * @param string $bodyHtml HTML body
 * @return bool
 */
function send_email(string $to, string $subject, string $bodyHtml): bool
{
    // ── If PHPMailer is available (via Composer) ────────────
    $phpmailerPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = email_wrap_html($bodyHtml, $subject);
            $mail->AltBody = strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }

    // ── Fallback: PHP mail() ─────────────────────────────────
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";

    return mail($to, $subject, email_wrap_html($bodyHtml, $subject), $headers);
}

/**
 * Wrap email body in a branded HTML template.
 */
function email_wrap_html(string $body, string $title): string
{
    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
body{font-family:DM Sans,Arial,sans-serif;background:#f0f4f8;margin:0;padding:20px;}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1);}
.hdr{background:#0d1b2a;padding:20px 28px;border-bottom:3px solid #00d4ff;}
.hdr h1{color:#fff;font-size:1.1rem;margin:0;letter-spacing:.05em;}
.hdr p{color:#8899aa;font-size:.8rem;margin:4px 0 0;}
.body{padding:28px;color:#1e293b;font-size:.9rem;line-height:1.6;}
.ftr{background:#f8fafc;padding:14px 28px;font-size:.75rem;color:#94a3b8;border-top:1px solid #e2e8f0;}
.btn{display:inline-block;background:#0d1b2a;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;margin-top:12px;}
</style></head>
<body>
<div class="wrap">
    <div class="hdr">
        <h1>🛡 CIRMS – Campus Cyber Incident System</h1>
        <p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
    </div>
    <div class="body">' . $body . '</div>
    <div class="ftr">This is an automated message from CIRMS. Do not reply to this email directly.
    Contact your IT Security team at <a href="mailto:' . NOTIFY_IT_EMAIL . '">' . NOTIFY_IT_EMAIL . '</a>.</div>
</div>
</body></html>';
}

/**
 * Notify IT security team of a new incident submission.
 */
function notify_new_incident(array $incident, string $reporterEmail): void
{
    $ref  = htmlspecialchars($incident['reference'], ENT_QUOTES, 'UTF-8');
    $sev  = htmlspecialchars($incident['severity'],  ENT_QUOTES, 'UTF-8');
    $cat  = htmlspecialchars($incident['category'],  ENT_QUOTES, 'UTF-8');
    $title= htmlspecialchars($incident['title'],     ENT_QUOTES, 'UTF-8');
    $url  = APP_URL . '/public/incidents/view.php?id=' . (int)$incident['id'];

    $body = "
        <p>A new cybersecurity incident has been submitted and requires your attention.</p>
        <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:6px 0;color:#64748b;width:35%'>Reference:</td><td><strong>$ref</strong></td></tr>
            <tr><td style='padding:6px 0;color:#64748b;'>Severity:</td><td><strong style='color:{'Critical':'#ef4444','High':'#f97316','Medium':'#f59e0b','Low':'#22c55e'}[$sev] ?? '#333''>$sev</strong></td></tr>
            <tr><td style='padding:6px 0;color:#64748b;'>Category:</td><td>$cat</td></tr>
            <tr><td style='padding:6px 0;color:#64748b;'>Title:</td><td>$title</td></tr>
        </table>
        <p><a href='$url' class='btn'>View Incident →</a></p>
    ";

    send_email(NOTIFY_IT_EMAIL, "[$sev] New Incident: $ref", $body);
}

/**
 * Notify reporter when their incident status changes.
 */
function notify_status_change(string $reporterEmail, array $incident, string $newStatus): void
{
    $ref  = htmlspecialchars($incident['reference'], ENT_QUOTES, 'UTF-8');
    $url  = APP_URL . '/public/incidents/view.php?id=' . (int)$incident['id'];

    $body = "
        <p>The status of your incident report <strong>$ref</strong> has been updated.</p>
        <p><strong>New Status:</strong> $newStatus</p>
        <p>Log in to CIRMS to view full details and any notes from the IT team.</p>
        <p><a href='$url' class='btn'>View Your Report →</a></p>
    ";

    send_email($reporterEmail, "Incident $ref Status Update: $newStatus", $body);
}
