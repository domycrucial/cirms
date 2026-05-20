<?php
// ============================================================
// FILE:    modules/notifications/mailer.php
// PURPOSE: Every email the CIRMS system sends goes through
//          this file. It contains 11 functions:
//
//  FUNCTION 1  — send_email()                   Core SMTP delivery engine
//  FUNCTION 2  — email_wrap_html()              Branded HTML email template
//  FUNCTION 3  — notify_new_incident()          → IT Officer  (new report submitted)
//  FUNCTION 4  — notify_submission_confirmation()→ Reporter   (receipt + reference)
//  FUNCTION 5  — notify_status_change()         → Reporter   (status moved)
//  FUNCTION 6  — notify_officer_assigned()      → IT Officer  (assigned to incident)
//  FUNCTION 7  — notify_note_added()            → Reporter   (public note posted)
//  FUNCTION 8  — notify_account_created()       → New User   (welcome + login info)
//  FUNCTION 9  — notify_account_activated()     → User       (account re-enabled)
//  FUNCTION 10 — notify_role_changed()          → User       (role changed)
//  FUNCTION 11 — notify_lockout_cleared()       → User       (login lockout removed by admin)
//
// HOW TO INSTALL PHPMAILER (run once in PowerShell):
//   cd C:\xampp\htdocs\cirmsv2
//   composer require phpmailer/phpmailer
//
// GMAIL SMTP SETUP:
//   Step 1 → myaccount.google.com/security → enable 2-Step Verification
//   Step 2 → myaccount.google.com/apppasswords → create App Password "CIRMS"
//   Step 3 → paste the 16-char code into SMTP_PASS in config/config.php
//   Step 4 → test: php modules/notifications/test_mail.php
// ============================================================

// Load SMTP constants (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
// SMTP_FROM, SMTP_FROM_NAME, NOTIFY_IT_EMAIL, APP_URL, SLA_HOURS)
require_once __DIR__ . '/../../config/config.php';

// Load the db() function used inside notify_new_incident()
require_once __DIR__ . '/../../config/database.php';


// ============================================================
//  FUNCTION 1 — send_email()
//
//  The single delivery point for ALL emails in CIRMS.
//  Every notify_* function calls this one function.
//  Uses PHPMailer + Gmail SMTP when vendor/autoload.php exists.
//  Falls back to PHP's built-in mail() on live servers only.
//
//  PARAMETERS:
//    $to        — recipient email address (string)
//    $toName    — recipient name shown in email client (string)
//    $subject   — email subject line (string)
//    $bodyHtml  — inner HTML body content — will be wrapped
//                 inside the branded template by email_wrap_html()
//
//  RETURNS:
//    true  — email was delivered successfully
//    false — delivery failed (error logged to php_error_log)
//
//  NOTE: A false return should NEVER stop the page from working.
//        Incidents and user accounts always save regardless of
//        whether the email succeeded.
// ============================================================
function send_email(string $to, string $toName, string $subject, string $bodyHtml): bool
{
    // Path to PHPMailer's Composer autoloader
    // This file is created when you run: composer require phpmailer/phpmailer
    $autoloader = __DIR__ . '/../../vendor/autoload.php';

    // ── Route A: PHPMailer is installed — preferred path ─────
    if (file_exists($autoloader)) {
        require_once $autoloader;

        try {
            // Instantiate PHPMailer with exceptions enabled (true)
            // This means errors throw exceptions instead of returning false silently
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Use SMTP transport instead of PHP's sendmail
            $mail->isSMTP();

            // Gmail SMTP server — value comes from config.php define('SMTP_HOST', ...)
            $mail->Host = SMTP_HOST;

            // SMTP authentication is always required for Gmail
            $mail->SMTPAuth = true;

            // Your Gmail address — set in config.php define('SMTP_USER', ...)
            // Must match the account the App Password was generated for
            $mail->Username = SMTP_USER;

            // Gmail App Password (16 characters) — NOT your normal Gmail password
            // Get it from: myaccount.google.com/apppasswords
            // Set in config.php define('SMTP_PASS', ...)
            $mail->Password = SMTP_PASS;

            // ENCRYPTION_STARTTLS is the correct mode for port 587
            // Do NOT combine STARTTLS with port 465 — that causes connection failure
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            // Port 587 is the Gmail standard for STARTTLS
            // Value comes from config.php define('SMTP_PORT', 587)
            $mail->Port = SMTP_PORT;

            // ── UNCOMMENT the 3 lines below ONLY if you get SSL
            // ── certificate errors on XAMPP localhost.
            // ── Do NOT use this setting on a production server.
            // $mail->SMTPOptions = [
            //     'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            // ];

            // Set the visible From address — what recipients see as the sender name
            // Values from config.php SMTP_FROM and SMTP_FROM_NAME
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

            // Set Reply-To so any replies go to the CIRMS address
            $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

            // Add the single recipient passed as a parameter
            // NEVER hardcode an email address here
            $mail->addAddress($to, $toName);

            // Tell PHPMailer the body is HTML (not plain text)
            $mail->isHTML(true);

            // Force UTF-8 so special characters (e.g. Swahili) display correctly
            $mail->CharSet = 'UTF-8';

            // Set the subject line
            $mail->Subject = $subject;

            // Wrap the body in the branded CIRMS HTML template
            $mail->Body = email_wrap_html($bodyHtml, $subject);

            // Plain text fallback for email clients that block HTML
            // strip_tags removes all HTML, preserving line breaks
            $mail->AltBody = strip_tags(
                str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)
            );

            // Send the email — throws Exception on failure
            $mail->send();

            // Log success to PHP error log for audit trail
            // View at: C:\xampp\php\logs\php_error_log
            error_log("CIRMS MAIL SENT → {$to} | {$subject}");

            return true; // email delivered

        } catch (Exception $e) {
            // Log the full PHPMailer error for debugging
            error_log("CIRMS MAIL ERROR: " . $e->getMessage() . " | To: {$to} | Subject: {$subject}");

            return false; // email failed — caller decides how to handle
        }
    }

    // ── Route B: PHPMailer not installed — fallback to mail() ─
    // This route only works on live servers with sendmail/postfix.
    // On XAMPP it will fail silently. Install PHPMailer instead.
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM . "\r\n";

    return mail($to, $subject, email_wrap_html($bodyHtml, $subject), $headers);
}


// ============================================================
//  FUNCTION 2 — email_wrap_html()
//
//  Produces the complete HTML email document that wraps every
//  email body. Applies the CIRMS brand: dark navy header with
//  cyan underline, white content area, grey footer.
//
//  All CSS classes used by notify_* functions are defined here:
//    .info-table     — the data table layout
//    .sev-*          — severity colour classes
//    .st-*           — status badge classes
//    .role-*         — role badge classes
//    .btn            — dark call-to-action button
//    .btn-green      — green call-to-action button
//    .alert-box      — amber highlighted notice block
//    .divider        — horizontal rule separator
//
//  PARAMETERS:
//    $body   — inner HTML content (built in each notify_* function)
//    $title  — shown in the email header subtitle area
//
//  RETURNS: complete HTML string ready to use as email body
// ============================================================
function email_wrap_html(string $body, string $title): string
{
    // Escape all dynamic values before placing them in HTML attributes
    $safeTitle = htmlspecialchars($title,          ENT_QUOTES, 'UTF-8');
    $itEmail   = htmlspecialchars(NOTIFY_IT_EMAIL, ENT_QUOTES, 'UTF-8');
    $appUrl    = htmlspecialchars(APP_URL,         ENT_QUOTES, 'UTF-8');
    $year      = date('Y');

    // Return the complete HTML email as a heredoc string
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$safeTitle}</title>
  <style>
    /* ── Reset ── */
    body{margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;}
    /* ── Outer wrapper centres the email in the inbox preview pane ── */
    .outer{padding:28px 16px;}
    /* ── Email card ── */
    .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:10px;
          overflow:hidden;border:1px solid #e2e8f0;}
    /* ── Dark navy header with cyan accent border ── */
    .hdr{background:#0d1b2a;padding:22px 28px;border-bottom:3px solid #00c2ff;}
    .hdr-title{color:#fff;font-size:1.1rem;font-weight:700;margin:0;}
    .hdr-sub{color:#8899aa;font-size:.8rem;margin:5px 0 0;}
    /* ── Email body area ── */
    .body{padding:28px;color:#1e293b;font-size:.9rem;line-height:1.7;}
    .body p{margin:0 0 14px;}
    /* ── Data table used to display incident/user details ── */
    .info-table{width:100%;border-collapse:collapse;margin:16px 0;}
    .info-table td{padding:9px 12px;border-bottom:1px solid #f1f5f9;font-size:.875rem;}
    .info-table td:first-child{color:#64748b;width:36%;font-weight:600;}
    .info-table td:last-child{color:#0f172a;}
    /* ── Severity colours ── */
    .sev-critical{color:#dc2626;font-weight:700;}
    .sev-high{color:#ea580c;font-weight:700;}
    .sev-medium{color:#d97706;font-weight:700;}
    .sev-low{color:#16a34a;font-weight:700;}
    /* ── Status badge pills ── */
    .st-new{background:#dbeafe;color:#1d4ed8;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .st-ack{background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .st-prog{background:#ede9fe;color:#5b21b6;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .st-res{background:#dcfce7;color:#166534;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .st-closed{background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    /* ── Role badge pills ── */
    .role-reporter{background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .role-officer{background:#ede9fe;color:#5b21b6;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    .role-admin{background:#fce7f3;color:#9d174d;padding:3px 10px;border-radius:999px;font-size:.8rem;display:inline-block;}
    /* ── Call-to-action buttons ── */
    .btn{display:inline-block;background:#0d1b2a;color:#fff!important;text-decoration:none;
         padding:11px 24px;border-radius:7px;font-weight:700;font-size:.875rem;margin-top:10px;}
    .btn-green{display:inline-block;background:#166534;color:#fff!important;text-decoration:none;
               padding:11px 24px;border-radius:7px;font-weight:700;font-size:.875rem;margin-top:10px;}
    /* ── Decorative divider line ── */
    .divider{border:none;border-top:1px solid #e2e8f0;margin:20px 0;}
    /* ── Amber alert highlight box ── */
    .alert-box{background:#fef9c3;border-left:4px solid #ca8a04;padding:12px 16px;
               border-radius:0 6px 6px 0;font-size:.85rem;color:#713f12;margin:14px 0;}
    /* ── Footer ── */
    .ftr{background:#f8fafc;padding:16px 28px;font-size:.75rem;color:#94a3b8;
         border-top:1px solid #e2e8f0;text-align:center;line-height:1.8;}
    .ftr a{color:#64748b;}
  </style>
</head>
<body>
<div class="outer">
  <div class="wrap">

    <!-- ── Header ── -->
    <div class="hdr">
      <p class="hdr-title">&#128737; CIRMS &mdash; Campus Cyber Incident System</p>
      <p class="hdr-sub">{$safeTitle}</p>
    </div>

    <!-- ── Body — injected from each notify_* function ── -->
    <div class="body">{$body}</div>

    <!-- ── Footer ── -->
    <div class="ftr">
      Automated message from CIRMS. Do not reply to this email.<br>
      IT Security: <a href="mailto:{$itEmail}">{$itEmail}</a> &nbsp;|&nbsp;
      <a href="{$appUrl}">Open CIRMS</a><br>
      &copy; {$year} CIRMS &mdash; Campus Cyber Incident Reporting &amp; Management System
    </div>

  </div>
</div>
</body>
</html>
HTML;
}


// ============================================================
//  FUNCTION 3 — notify_new_incident()
//
//  RECIPIENT:  IT Security Team (address set in config.php as
//              NOTIFY_IT_EMAIL — e.g. hildakimaro720@gmail.com)
//  TRIGGERED:  Immediately when a user submits an incident report
//  CALLED BY:  public/incidents/report.php (after DB INSERT)
//
//  This function fetches the full incident row from the database,
//  builds a detailed HTML email showing all incident fields, and
//  delivers it directly to the IT officer's inbox in real time.
//  The officer receives a clickable button to open the incident.
//
//  PARAMETERS:
//    $incidentId    — the auto-increment ID of the new incident row
//    $reporterName  — full name of the user who submitted (from session)
//    $reporterEmail — email of the user who submitted (from session)
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_new_incident(int $incidentId, string $reporterName, string $reporterEmail): bool
{
    // Fetch the incident row plus its category name from the database
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("
            SELECT i.reference,
                   i.severity,
                   i.title,
                   i.description,
                   i.affected_system,
                   i.is_ongoing,
                   i.created_at,
                   c.name AS category_name
            FROM   incidents i
            JOIN   categories c ON c.id = i.category_id
            WHERE  i.id = ?
        ");
        $stmt->execute([$incidentId]);
        $inc = $stmt->fetch();

        // If row not found (shouldn't happen but defensive check)
        if (!$inc) {
            error_log("notify_new_incident: incident ID {$incidentId} not found in DB");
            return false;
        }

    } catch (\Throwable $e) {
        error_log("notify_new_incident DB error: " . $e->getMessage());
        return false;
    }

    // Escape every value before placing it inside HTML
    $ref      = htmlspecialchars($inc['reference'],                        ENT_QUOTES, 'UTF-8');
    $sev      = htmlspecialchars($inc['severity'],                         ENT_QUOTES, 'UTF-8');
    $cat      = htmlspecialchars($inc['category_name'],                    ENT_QUOTES, 'UTF-8');
    $title    = htmlspecialchars($inc['title'],                            ENT_QUOTES, 'UTF-8');
    $affected = htmlspecialchars($inc['affected_system'] ?: 'Not specified', ENT_QUOTES, 'UTF-8');
    $reporter = htmlspecialchars($reporterName,                            ENT_QUOTES, 'UTF-8');
    $repEmail = htmlspecialchars($reporterEmail,                           ENT_QUOTES, 'UTF-8');

    // Human-readable ongoing status
    $ongoing  = $inc['is_ongoing'] ? 'Yes — still ongoing' : 'No — appears contained';

    // Format the submission timestamp
    $time     = date('d M Y, H:i', strtotime($inc['created_at']));

    // Pick severity CSS class — defined in email_wrap_html()
    $sevClass = match (strtolower($sev)) {
        'critical' => 'sev-critical',
        'high'     => 'sev-high',
        'medium'   => 'sev-medium',
        default    => 'sev-low',
    };

    // Direct URL to the incident detail page in CIRMS
    $url = APP_URL . '/public/incidents/view.php?id=' . $incidentId;

    // Build the inner HTML body of the email
    $body = "
        <p>A new cybersecurity incident has been submitted through CIRMS
           and requires your immediate attention.</p>

        <table class='info-table'>
            <tr><td>Reference:</td>    <td><strong>{$ref}</strong></td></tr>
            <tr><td>Severity:</td>     <td><span class='{$sevClass}'>{$sev}</span></td></tr>
            <tr><td>Category:</td>     <td>{$cat}</td></tr>
            <tr><td>Title:</td>        <td>{$title}</td></tr>
            <tr><td>Affected:</td>     <td>{$affected}</td></tr>
            <tr><td>Ongoing:</td>      <td>{$ongoing}</td></tr>
            <tr><td>Reported By:</td>  <td>{$reporter} &lt;{$repEmail}&gt;</td></tr>
            <tr><td>Submitted At:</td> <td>{$time}</td></tr>
        </table>

        <p>Log in to CIRMS to acknowledge this incident, assign it to an officer,
           and begin the investigation. The SLA clock is now running.</p>

        <a href='{$url}' class='btn'>&#128274; Open Incident in CIRMS &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            This email was triggered automatically when the incident was submitted.
            All investigation actions must be recorded in CIRMS.
        </p>
    ";

    // Subject line includes severity so officer can prioritise from inbox
    $subject = "[CIRMS][{$sev}] New Incident: {$ref}";

    // Send directly to the IT security team email defined in config.php
    return send_email(NOTIFY_IT_EMAIL, 'IT Security Team', $subject, $body);
}


// ============================================================
//  FUNCTION 4 — notify_submission_confirmation()
//
//  RECIPIENT:  The reporter (student or staff who submitted)
//  TRIGGERED:  Same moment as notify_new_incident() — on submit
//  CALLED BY:  public/incidents/report.php (after DB INSERT)
//
//  Sends the reporter a receipt email so they:
//    - Know their report was successfully received
//    - Have the reference number to track and follow up
//    - Know how long to expect before a response (SLA)
//    - Have a direct link to check their report status
//
//  PARAMETERS:
//    $reporterEmail — reporter's email address
//    $reporterName  — reporter's full name
//    $reference     — incident reference e.g. INC-2025-0042
//    $severity      — severity string (Low/Medium/High/Critical)
//    $title         — incident title
//    $incidentId    — incident DB row ID (for building view URL)
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_submission_confirmation(
    string $reporterEmail,
    string $reporterName,
    string $reference,
    string $severity,
    string $title,
    int    $incidentId
): bool {
    // Escape all values for HTML output
    $name  = htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8');
    $ref   = htmlspecialchars($reference,    ENT_QUOTES, 'UTF-8');
    $sev   = htmlspecialchars($severity,     ENT_QUOTES, 'UTF-8');
    $ttl   = htmlspecialchars($title,        ENT_QUOTES, 'UTF-8');
    $time  = date('d M Y, H:i');

    // Build the link to the incident view page
    $url = APP_URL . '/public/incidents/view.php?id=' . $incidentId;

    // Calculate expected response time from SLA_HOURS array in config.php
    // e.g. Critical = 2 hours, High = 8 hours, Medium = 24 h, Low = 72 h
    $slaHours = SLA_HOURS[$severity] ?? 72;
    $slaText  = $slaHours >= 24
        ? ($slaHours / 24) . ' day' . ($slaHours / 24 > 1 ? 's' : '')
        : $slaHours . ' hour' . ($slaHours > 1 ? 's' : '');

    // Pick severity colour class
    $sevClass = match (strtolower($sev)) {
        'critical' => 'sev-critical',
        'high'     => 'sev-high',
        'medium'   => 'sev-medium',
        default    => 'sev-low',
    };

    $body = "
        <p>Dear {$name},</p>

        <p>Your cybersecurity incident report has been successfully received by CIRMS.
           The IT Security team has been alerted and will respond as soon as possible.</p>

        <table class='info-table'>
            <tr><td>Reference:</td>          <td><strong>{$ref}</strong></td></tr>
            <tr><td>Title:</td>              <td>{$ttl}</td></tr>
            <tr><td>Severity:</td>           <td><span class='{$sevClass}'>{$sev}</span></td></tr>
            <tr><td>Submitted At:</td>       <td>{$time}</td></tr>
            <tr><td>Expected Response:</td>  <td>Within {$slaText}</td></tr>
        </table>

        <div class='alert-box'>
            Your reference number is <strong>{$ref}</strong>. Keep this number —
            use it when following up with the IT Security team.
        </div>

        <p>You will receive email updates whenever the status of your report changes.
           You can also check the current status at any time by logging in to CIRMS.</p>

        <a href='{$url}' class='btn-green'>&#128269; Track Your Report &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            Do not reply to this email. If you have additional urgent information
            to add to your report, please contact the IT Security team directly.
        </p>
    ";

    return send_email($reporterEmail, $reporterName, "[CIRMS] Report Received: {$ref}", $body);
}


// ============================================================
//  FUNCTION 5 — notify_status_change()
//
//  RECIPIENT:  The reporter (student or staff who submitted)
//  TRIGGERED:  Every time an officer/admin updates the status
//  CALLED BY:  public/incidents/view.php (update_status POST action)
//
//  Sends a personalised email at each stage of the incident:
//    New          — already handled by function 4
//    Acknowledged — tells reporter their report is under review
//    In Progress  — tells reporter active investigation has started
//    Resolved     — tells reporter to review the resolution notes
//    Closed       — tells reporter the case is officially closed
//
//  Each status has its own guidance paragraph so the reporter
//  always knows exactly what to do next.
//
//  PARAMETERS:
//    $reporterEmail — reporter's email address (from DB)
//    $reporterName  — reporter's full name (from DB)
//    $incident      — array with keys: id, reference, title
//    $newStatus     — new status string (must be one of the 5 valid values)
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_status_change(
    string $reporterEmail,
    string $reporterName,
    array  $incident,
    string $newStatus
): bool {
    // Escape display values
    $ref   = htmlspecialchars($incident['reference'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($incident['title'],     ENT_QUOTES, 'UTF-8');
    $name  = htmlspecialchars($reporterName,          ENT_QUOTES, 'UTF-8');

    // Build direct link to the incident view page
    $url = APP_URL . '/public/incidents/view.php?id=' . (int)$incident['id'];

    // Pick the correct status badge CSS class
    $stClass = match ($newStatus) {
        'Acknowledged' => 'st-ack',
        'In Progress'  => 'st-prog',
        'Resolved'     => 'st-res',
        'Closed'       => 'st-closed',
        default        => 'st-new',
    };

    // Write a meaningful guidance paragraph for each status
    // so the reporter always understands what the status means
    $guidance = match ($newStatus) {
        'Acknowledged' =>
            'Your report has been acknowledged by the IT Security team. An officer
             has been assigned and investigation will begin shortly. No action is
             required from you at this time.',
        'In Progress'  =>
            'The IT Security team is actively investigating your incident. You may
             receive requests for additional information. Check CIRMS for any
             notes posted by the officer.',
        'Resolved'     =>
            'The IT Security team has resolved your incident. Please log in to CIRMS
             to review the resolution notes. If the problem persists, please reply
             through the CIRMS portal or submit a new incident report.',
        'Closed'       =>
            'This incident has been officially closed and recorded in the system.
             If the issue reoccurs or was not fully resolved, please submit a new
             incident report through CIRMS.',
        default        =>
            'Your incident report has been updated. Log in to CIRMS for full
             details and any notes posted by the IT team.',
    };

    $body = "
        <p>Dear {$name},</p>

        <p>Your cybersecurity incident report has been updated by the IT Security team.</p>

        <table class='info-table'>
            <tr><td>Reference:</td>   <td><strong>{$ref}</strong></td></tr>
            <tr><td>Title:</td>       <td>{$title}</td></tr>
            <tr><td>New Status:</td>  <td><span class='{$stClass}'>{$newStatus}</span></td></tr>
            <tr><td>Updated At:</td>  <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <p>{$guidance}</p>

        <a href='{$url}' class='btn'>&#128269; View Your Incident &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            Do not reply to this email. All communication about your incident
            must go through the CIRMS portal to maintain a complete audit trail.
        </p>
    ";

    return send_email(
        $reporterEmail,
        $reporterName,
        "[CIRMS] Incident {$ref} — Status: {$newStatus}",
        $body
    );
}


// ============================================================
//  FUNCTION 6 — notify_officer_assigned()
//
//  RECIPIENT:  The IT officer who was just assigned
//  TRIGGERED:  When admin selects an officer in the assign dropdown
//              and the assigned_to value actually changes
//  CALLED BY:  public/incidents/view.php (update_status POST action)
//
//  Tells the officer they now own this incident so they can
//  act quickly. Includes severity, SLA deadline, and a button
//  to open the incident directly.
//
//  PARAMETERS:
//    $officerEmail — email of the assigned officer (from DB)
//    $officerName  — full name of the assigned officer (from DB)
//    $incident     — array with: id, reference, title, severity, category
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_officer_assigned(
    string $officerEmail,
    string $officerName,
    array  $incident
): bool {
    // Escape display values
    $ref   = htmlspecialchars($incident['reference'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($incident['title'],     ENT_QUOTES, 'UTF-8');
    $sev   = htmlspecialchars($incident['severity'],  ENT_QUOTES, 'UTF-8');
    $cat   = htmlspecialchars($incident['category'],  ENT_QUOTES, 'UTF-8');
    $name  = htmlspecialchars($officerName,           ENT_QUOTES, 'UTF-8');

    // Direct link to open and manage the incident
    $url = APP_URL . '/public/incidents/view.php?id=' . (int)$incident['id'];

    // Pick severity colour class
    $sevClass = match (strtolower($sev)) {
        'critical' => 'sev-critical',
        'high'     => 'sev-high',
        'medium'   => 'sev-medium',
        default    => 'sev-low',
    };

    // Calculate SLA response time from config.php SLA_HOURS array
    $slaHours = SLA_HOURS[$incident['severity']] ?? 72;
    $slaText  = $slaHours >= 24
        ? ($slaHours / 24) . ' day' . ($slaHours / 24 > 1 ? 's' : '')
        : $slaHours . ' hour' . ($slaHours > 1 ? 's' : '');

    $body = "
        <p>Dear {$name},</p>

        <p>You have been assigned to investigate and resolve the following
           cybersecurity incident in CIRMS. Please acknowledge it as soon as possible.</p>

        <table class='info-table'>
            <tr><td>Reference:</td>    <td><strong>{$ref}</strong></td></tr>
            <tr><td>Severity:</td>     <td><span class='{$sevClass}'>{$sev}</span></td></tr>
            <tr><td>Category:</td>     <td>{$cat}</td></tr>
            <tr><td>Title:</td>        <td>{$title}</td></tr>
            <tr><td>Assigned At:</td>  <td>" . date('d M Y, H:i') . "</td></tr>
            <tr><td>SLA Deadline:</td> <td>Respond within <strong>{$slaText}</strong></td></tr>
        </table>

        <div class='alert-box'>
            Set the status to <strong>Acknowledged</strong> immediately after opening
            this incident. This stops the SLA breach timer and notifies the reporter
            that their incident is now being actively handled.
        </div>

        <p>Open the incident to read the full description, any evidence attachments
           provided by the reporter, and their contact details.</p>

        <a href='{$url}' class='btn'>&#128274; Open Incident in CIRMS &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            Automated assignment notification from CIRMS.
        </p>
    ";

    return send_email(
        $officerEmail,
        $officerName,
        "[CIRMS] Assigned to You: {$ref} — {$sev}",
        $body
    );
}


// ============================================================
//  FUNCTION 7 — notify_note_added()
//
//  RECIPIENT:  The reporter
//  TRIGGERED:  When an officer posts a PUBLIC note (is_internal = 0)
//  CALLED BY:  public/incidents/view.php (add_note POST action)
//
//  Internal notes (is_internal = 1) are for officers only and
//  do NOT trigger this function. Only public notes do.
//
//  The note body itself is NOT included in the email — the reporter
//  must log in to CIRMS to read it. This keeps investigation
//  details off email and ensures all communication stays in CIRMS.
//
//  PARAMETERS:
//    $reporterEmail — reporter's email (from DB)
//    $reporterName  — reporter's name (from DB)
//    $incident      — array with: id, reference
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_note_added(
    string $reporterEmail,
    string $reporterName,
    array  $incident
): bool {
    $ref  = htmlspecialchars($incident['reference'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($reporterName,          ENT_QUOTES, 'UTF-8');

    // Direct link to the incident so reporter clicks straight through
    $url  = APP_URL . '/public/incidents/view.php?id=' . (int)$incident['id'];

    $body = "
        <p>Dear {$name},</p>

        <p>The IT Security team has posted a new update on your incident
           <strong>{$ref}</strong>.</p>

        <p>Log in to CIRMS to read the full message from the officer. If you have
           additional information that could help the investigation, you can add
           it through the portal.</p>

        <table class='info-table'>
            <tr><td>Reference:</td>  <td><strong>{$ref}</strong></td></tr>
            <tr><td>Posted At:</td>  <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <a href='{$url}' class='btn'>&#128172; Read the Update &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            Do not reply to this email. All communication about your incident
            must go through the CIRMS portal to maintain a complete record.
        </p>
    ";

    return send_email(
        $reporterEmail,
        $reporterName,
        "[CIRMS] New Update on Your Incident {$ref}",
        $body
    );
}


// ============================================================
//  FUNCTION 8 — notify_account_created()
//
//  RECIPIENT:  The newly created user
//  TRIGGERED:  When admin creates a user via users/list.php
//              OR when a user self-registers via register.php
//  CALLED BY:  public/users/list.php (create_user POST action)
//              public/auth/register.php (after successful registration)
//
//  For admin-created accounts: sends email, temp password, role, login link
//  For self-registered accounts: sends welcome email and login link
//  (the temp password parameter carries a placeholder message for self-reg)
//
//  PARAMETERS:
//    $userEmail    — new user's email address
//    $userName     — new user's full name
//    $role         — assigned role (reporter / officer / admin)
//    $tempPassword — temporary password to include in email
//                    (for self-reg, pass a descriptive placeholder string)
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_account_created(
    string $userEmail,
    string $userName,
    string $role,
    string $tempPassword
): bool {
    // Escape display values
    $name     = htmlspecialchars($userName,      ENT_QUOTES, 'UTF-8');
    $email    = htmlspecialchars($userEmail,     ENT_QUOTES, 'UTF-8');
    $roleStr  = htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8');
    $loginUrl = APP_URL . '/public/login.php';

    // Pick role badge CSS class
    $roleClass = match ($role) {
        'admin'   => 'role-admin',
        'officer' => 'role-officer',
        default   => 'role-reporter',
    };

    // IT security contact address for the fraud warning at the bottom
    $itEmailSafe = htmlspecialchars(NOTIFY_IT_EMAIL, ENT_QUOTES, 'UTF-8');

    $body = "
        <p>Dear {$name},</p>

        <p>An account has been created for you on the CIRMS Campus Cyber Incident
           Reporting &amp; Management System. You can now log in and start using the system.</p>

        <table class='info-table'>
            <tr><td>Login Email:</td>         <td><strong>{$email}</strong></td></tr>
            <tr><td>Password:</td>            <td><strong>{$tempPassword}</strong></td></tr>
            <tr><td>Your Role:</td>           <td><span class='{$roleClass}'>{$roleStr}</span></td></tr>
            <tr><td>Account Created:</td>     <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <div class='alert-box'>
            Please change your password immediately after your first login.
            Go to your profile settings inside CIRMS to update it.
        </div>

        <p>Click the button below to log in to CIRMS for the first time.</p>

        <a href='{$loginUrl}' class='btn-green'>&#128274; Log In to CIRMS &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            If you did not expect this email, contact the IT Security team immediately
            at <a href='mailto:{$itEmailSafe}'>{$itEmailSafe}</a>.
        </p>
    ";

    return send_email($userEmail, $userName, "[CIRMS] Your Account Has Been Created", $body);
}


// ============================================================
//  FUNCTION 9 — notify_account_activated()
//
//  RECIPIENT:  The user whose account was just re-enabled
//  TRIGGERED:  When admin clicks Activate on a disabled account
//  CALLED BY:  public/users/list.php (toggle_active POST action)
//
//  NOTE: This function fires ONLY when activating (is_active 0→1).
//  When deactivating (is_active 1→0) no email is sent —
//  there is no point emailing an account that is being disabled.
//
//  PARAMETERS:
//    $userEmail — user's email address
//    $userName  — user's full name
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_account_activated(string $userEmail, string $userName): bool
{
    $name     = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $emailSafe= htmlspecialchars($userEmail,ENT_QUOTES, 'UTF-8');
    $loginUrl = APP_URL . '/public/login.php';

    $body = "
        <p>Dear {$name},</p>

        <p>Your CIRMS account has been reactivated by the system administrator.
           You can now log in and access the system.</p>

        <table class='info-table'>
            <tr><td>Account Email:</td>   <td>{$emailSafe}</td></tr>
            <tr><td>Reactivated At:</td>  <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <a href='{$loginUrl}' class='btn-green'>&#128274; Log In to CIRMS &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            If you did not expect this email or did not request reactivation,
            contact the IT Security team immediately.
        </p>
    ";

    return send_email($userEmail, $userName, "[CIRMS] Your Account Has Been Reactivated", $body);
}


// ============================================================
//  FUNCTION 10 — notify_role_changed()
//
//  RECIPIENT:  The user whose role was just changed
//  TRIGGERED:  When admin changes a user's role via the dropdown
//  CALLED BY:  public/users/list.php (change_role POST action)
//
//  Only fires when the role actually changes (old != new).
//  Tells the user what their new role is and what it allows them
//  to do, so they understand their new access level in CIRMS.
//
//  PARAMETERS:
//    $userEmail — user's email address
//    $userName  — user's full name
//    $oldRole   — previous role (reporter / officer / admin)
//    $newRole   — new role (reporter / officer / admin)
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_role_changed(
    string $userEmail,
    string $userName,
    string $oldRole,
    string $newRole
): bool {
    // Escape and capitalise role names for display
    $name    = htmlspecialchars($userName,         ENT_QUOTES, 'UTF-8');
    $old     = htmlspecialchars(ucfirst($oldRole), ENT_QUOTES, 'UTF-8');
    $new     = htmlspecialchars(ucfirst($newRole), ENT_QUOTES, 'UTF-8');
    $loginUrl= APP_URL . '/public/login.php';

    // Pick badge CSS class for the new role
    $newRoleClass = match ($newRole) {
        'admin'   => 'role-admin',
        'officer' => 'role-officer',
        default   => 'role-reporter',
    };

    // Explain what the new role can and cannot do in plain language
    $roleDesc = match ($newRole) {
        'admin'    =>
            'You now have full access to CIRMS: user management, analytics dashboards,
             system settings, audit logs, and all incident reports.',
        'officer'  =>
            'You can now view all reported incidents, update their status, add
             investigation notes, assign incidents, and communicate with reporters.',
        'reporter' =>
            'You can submit cybersecurity incident reports through CIRMS and track
             the status of your own submitted reports.',
        default    =>
            'Your access level in CIRMS has been updated.',
    };

    $body = "
        <p>Dear {$name},</p>

        <p>Your CIRMS access role has been changed by the system administrator.</p>

        <table class='info-table'>
            <tr><td>Previous Role:</td>  <td>{$old}</td></tr>
            <tr><td>New Role:</td>       <td><span class='{$newRoleClass}'>{$new}</span></td></tr>
            <tr><td>Changed At:</td>     <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <p>{$roleDesc}</p>

        <p>Please log out and log back in to CIRMS for your new permissions to take
           full effect.</p>

        <a href='{$loginUrl}' class='btn'>Log In with Your New Role &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            If you did not expect this role change, contact the IT Security team immediately.
        </p>
    ";

    return send_email(
        $userEmail,
        $userName,
        "[CIRMS] Your Account Role Has Changed to {$new}",
        $body
    );
}


// ============================================================
//  FUNCTION 11 — notify_lockout_cleared()
//
//  RECIPIENT:  The user whose login lockout was removed by admin
//  TRIGGERED:  When an admin clicks "Unlock" on the Manage Users
//              page after the user had ≥3 failed login attempts
//  CALLED BY:  public/users/list.php (unlock_user POST action)
//
//  PARAMETERS:
//    $userEmail — user's email address
//    $userName  — user's full name
//
//  RETURNS: true = sent, false = failed
// ============================================================
function notify_lockout_cleared(string $userEmail, string $userName): bool
{
    $name      = htmlspecialchars($userName,  ENT_QUOTES, 'UTF-8');
    $emailSafe = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
    $loginUrl  = APP_URL . '/public/login.php';

    $body = "
        <p>Dear {$name},</p>

        <p>Your CIRMS account was temporarily locked after multiple unsuccessful sign-in
           attempts. The IT Security team has reviewed your account and cleared the lockout
           — you may now sign in again.</p>

        <table class='info-table'>
            <tr><td>Account Email:</td><td>{$emailSafe}</td></tr>
            <tr><td>Unlocked At:</td>  <td>" . date('d M Y, H:i') . "</td></tr>
        </table>

        <a href='{$loginUrl}' class='btn-green'>&#128275; Sign In Now &rarr;</a>

        <hr class='divider'>
        <p style='color:#64748b;font-size:.82rem;'>
            If you believe someone else attempted to access your account, please contact
            the IT Security team immediately and change your password after signing in.
        </p>
    ";

    return send_email(
        $userEmail,
        $userName,
        '[CIRMS] Your Account Lockout Has Been Cleared',
        $body
    );
}
