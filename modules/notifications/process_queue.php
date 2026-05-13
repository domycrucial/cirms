<?php
// ============================================================
// CIRMS – Notification Queue Processor
// modules/notifications/process_queue.php
//
// HOW TO RUN ON WINDOWS (XAMPP):
//   Open PowerShell and run manually:
//   cd C:\xampp\htdocs\cirmsv2
//   php modules/notifications/process_queue.php
//
// HOW TO RUN ON LINUX (production server — auto every minute):
//   Add to crontab with: crontab -e
//   * * * * * php /var/www/cirmsv2/modules/notifications/process_queue.php >> /var/log/cirms_mail.log 2>&1
//
// This script is CLI-only. It will return 403 if opened in a browser.
// ============================================================

// Block web access — this must only run from command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('403 Forbidden — This script runs via CLI only.');
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/mailer.php';

$pdo  = db();
$smtp = get_smtp_settings(); // Load SMTP settings once for use in all bodies

echo "[" . date('Y-m-d H:i:s') . "] CIRMS Notification Queue Processor started.\n";

// ── Fetch up to 50 pending notifications with full context ───
$stmt = $pdo->query("
    SELECT
        n.id            AS notif_id,
        n.type,
        n.subject,
        n.incident_id,
        n.user_id,
        u.email         AS user_email,
        u.full_name     AS user_name,
        i.reference,
        i.title,
        i.severity,
        i.status,
        i.description,
        i.affected_system,
        i.category_id,
        c.name          AS category_name,
        r.full_name     AS reporter_name,
        r.email         AS reporter_email
    FROM notifications n
    JOIN users u ON u.id = n.user_id
    LEFT JOIN incidents i ON i.id = n.incident_id
    LEFT JOIN categories c ON c.id = i.category_id
    LEFT JOIN users r ON r.id = i.reporter_id
    WHERE n.status = 'pending'
    ORDER BY n.id ASC
    LIMIT 50
");
$notifications = $stmt->fetchAll();

if (empty($notifications)) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending notifications. Exiting.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($notifications) . " pending notification(s).\n\n";

$sent   = 0;
$failed = 0;

foreach ($notifications as $n) {

    $incidentUrl = APP_URL . '/public/incidents/view.php?id=' . (int)$n['incident_id'];
    $ref         = htmlspecialchars($n['reference']     ?? '', ENT_QUOTES, 'UTF-8');
    $title       = htmlspecialchars($n['title']         ?? '', ENT_QUOTES, 'UTF-8');
    $sev         = htmlspecialchars($n['severity']      ?? '', ENT_QUOTES, 'UTF-8');
    $status      = htmlspecialchars($n['status']        ?? '', ENT_QUOTES, 'UTF-8');
    $cat         = htmlspecialchars($n['category_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $affected    = htmlspecialchars($n['affected_system']?? 'Not specified', ENT_QUOTES, 'UTF-8');
    $reporterName= htmlspecialchars($n['reporter_name'] ?? $n['user_name'], ENT_QUOTES, 'UTF-8');
    $userName    = htmlspecialchars($n['user_name'],      ENT_QUOTES, 'UTF-8');

    // Severity colour class for the branded HTML template
    $sevClass = match(strtolower($n['severity'] ?? '')) {
        'critical' => 'sev-critical',
        'high'     => 'sev-high',
        'medium'   => 'sev-medium',
        default    => 'sev-low',
    };

    // Status badge class
    $statusClass = match($n['status'] ?? '') {
        'Acknowledged' => 's-ack',
        'In Progress'  => 's-progress',
        'Resolved'     => 's-resolved',
        'Closed'       => 's-closed',
        default        => 's-new',
    };

    // ── Build email body by notification type ─────────────────
    switch ($n['type']) {

        // ══════════════════════════════════════════════════════
        //  TYPE 1: incident.submitted
        //  Sent TO: IT Security Team (admin/officer email)
        //  Triggered by: user submitting a new incident report
        // ══════════════════════════════════════════════════════
        case 'incident.submitted':
            $body = "
                <p>Dear IT Security Team,</p>
                <p>A new cybersecurity incident has been submitted through CIRMS and
                   requires your immediate attention.</p>

                <table class='meta-table'>
                    <tr>
                        <td>Reference:</td>
                        <td><strong>{$ref}</strong></td>
                    </tr>
                    <tr>
                        <td>Severity:</td>
                        <td><strong class='{$sevClass}'>{$sev}</strong></td>
                    </tr>
                    <tr>
                        <td>Category:</td>
                        <td>{$cat}</td>
                    </tr>
                    <tr>
                        <td>Title:</td>
                        <td>{$title}</td>
                    </tr>
                    <tr>
                        <td>Affected System:</td>
                        <td>{$affected}</td>
                    </tr>
                    <tr>
                        <td>Reported By:</td>
                        <td>{$reporterName}</td>
                    </tr>
                    <tr>
                        <td>Submitted At:</td>
                        <td>" . date('d M Y, H:i') . "</td>
                    </tr>
                </table>

                <p>Please log in to CIRMS to review this incident, assign it to the
                   appropriate officer, and set the initial status.</p>

                <a href='{$incidentUrl}' class='btn'>
                    &#128274; Open Incident in CIRMS &rarr;
                </a>

                <hr class='divider'>
                <p style='color:#64748b;font-size:.82rem;'>
                    This notification was triggered automatically when the incident was submitted.
                    SLA clock is now running for this {$sev} severity incident.
                </p>
            ";
            $recipientEmail = $smtp['notify_email'];
            $recipientName  = 'IT Security Team';
            break;

        // ══════════════════════════════════════════════════════
        //  TYPE 2: status.changed
        //  Sent TO: The original reporter (student/staff)
        //  Triggered by: officer/admin updating incident status
        // ══════════════════════════════════════════════════════
        case 'status.changed':
            $guidance = match($n['status'] ?? '') {
                'Acknowledged' =>
                    'Your report has been received and acknowledged by the IT Security team.
                     An officer has been assigned and investigation will begin shortly.',
                'In Progress'  =>
                    'The IT Security team is actively investigating your reported incident.
                     You will receive another update when there is progress to share.',
                'Resolved'     =>
                    'The IT Security team has resolved your incident. Please log in to CIRMS
                     to review the resolution notes and confirm whether the issue is fully addressed.',
                'Closed'       =>
                    'This incident has been closed. If you believe the issue persists,
                     please submit a new incident report through CIRMS.',
                default        =>
                    'Your incident report has been updated. Log in to CIRMS for full details.',
            };

            $body = "
                <p>Dear {$userName},</p>
                <p>The status of your cybersecurity incident report has been updated.</p>

                <table class='meta-table'>
                    <tr>
                        <td>Reference:</td>
                        <td><strong>{$ref}</strong></td>
                    </tr>
                    <tr>
                        <td>Title:</td>
                        <td>{$title}</td>
                    </tr>
                    <tr>
                        <td>New Status:</td>
                        <td>
                            <span class='status-badge {$statusClass}'>{$status}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>Severity:</td>
                        <td><span class='{$sevClass}'>{$sev}</span></td>
                    </tr>
                    <tr>
                        <td>Updated At:</td>
                        <td>" . date('d M Y, H:i') . "</td>
                    </tr>
                </table>

                <p>{$guidance}</p>

                <a href='{$incidentUrl}' class='btn'>
                    &#128269; View Your Incident Report &rarr;
                </a>

                <hr class='divider'>
                <p style='color:#64748b;font-size:.82rem;'>
                    Log in to CIRMS at any time to check your incident status, read IT team notes,
                    and track the progress of your report.
                </p>
            ";
            $recipientEmail = $n['user_email'];
            $recipientName  = $n['user_name'];
            break;

        // ══════════════════════════════════════════════════════
        //  TYPE 3: note.added
        //  Sent TO: The reporter (when a non-internal note is posted)
        //  Triggered by: officer/admin adding a public note
        // ══════════════════════════════════════════════════════
        case 'note.added':
            $body = "
                <p>Dear {$userName},</p>
                <p>The IT Security team has posted a new update on your incident report.</p>

                <table class='meta-table'>
                    <tr>
                        <td>Reference:</td>
                        <td><strong>{$ref}</strong></td>
                    </tr>
                    <tr>
                        <td>Title:</td>
                        <td>{$title}</td>
                    </tr>
                    <tr>
                        <td>Current Status:</td>
                        <td>
                            <span class='status-badge {$statusClass}'>{$status}</span>
                        </td>
                    </tr>
                </table>

                <p>Log in to CIRMS to read the full message from the IT team and
                   respond if you have additional information to provide.</p>

                <a href='{$incidentUrl}' class='btn'>
                    &#128172; Read the Update &rarr;
                </a>

                <hr class='divider'>
                <p style='color:#64748b;font-size:.82rem;'>
                    Please do not reply to this email. All communication about your incident
                    must be done through the CIRMS portal to ensure a complete audit trail.
                </p>
            ";
            $recipientEmail = $n['user_email'];
            $recipientName  = $n['user_name'];
            break;

        // ══════════════════════════════════════════════════════
        //  DEFAULT: unknown notification type
        // ══════════════════════════════════════════════════════
        default:
            $body = "<p>" . htmlspecialchars($n['subject'], ENT_QUOTES, 'UTF-8') . "</p>
                     <a href='{$incidentUrl}' class='btn'>View in CIRMS &rarr;</a>";
            $recipientEmail = $n['user_email'];
            $recipientName  = $n['user_name'];
            break;
    }

    // ── Send the email ────────────────────────────────────────
    $success = send_email(
        $recipientEmail,
        $recipientName,
        $n['subject'],
        $body
    );

    // ── Update notification row in DB ─────────────────────────
    if ($success) {
        $pdo->prepare("
            UPDATE notifications
            SET status = 'sent', sent_at = NOW(), error_msg = NULL
            WHERE id = ?
        ")->execute([$n['notif_id']]);

        $sent++;
        echo "[" . date('H:i:s') . "] SENT    → {$recipientEmail}  ({$n['type']})  Ref: {$ref}\n";

    } else {
        $pdo->prepare("
            UPDATE notifications
            SET status = 'failed', error_msg = 'PHPMailer delivery failed'
            WHERE id = ?
        ")->execute([$n['notif_id']]);

        $failed++;
        echo "[" . date('H:i:s') . "] FAILED  → {$recipientEmail}  ({$n['type']})  Ref: {$ref}\n";
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Queue complete.";
echo " Sent: {$sent} | Failed: {$failed} | Total: " . count($notifications) . "\n";
exit(0);
