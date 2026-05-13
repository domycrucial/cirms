<?php
// ============================================================
// CIRMS – Self-Registration
// public/auth/register.php
//
// EMAILS SENT FROM THIS FILE (2 total):
//
//   EMAIL 1 → The new user (welcome email)
//             Function: notify_account_created()
//             Trigger:  Successful self-registration
//             Content:  Welcome message, their email, role (Reporter),
//                       reminder to set a strong password, login link
//             NOTE:     For self-registration we do NOT send the
//                       password — the user just created it themselves.
//                       We send a welcome message only.
//
//   EMAIL 2 → IT Security Team (new registration alert)
//             Function: notify_new_registration_alert()
//             Trigger:  Same moment as EMAIL 1
//             Content:  New user's name, email, department, timestamp
//             PURPOSE:  Admin awareness — they can review and
//                       promote the user from Reporter to Officer
//                       if needed via users/list.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';

// Load mailer.php for both notification functions
require_once __DIR__ . '/../../modules/notifications/mailer.php';

session_start_secure();

// Redirect already-logged-in users away from the register page
if (is_logged_in()) redirect('/public/dashboard.php');

$errors = [];

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    // Collect and sanitise all fields
    $full_name  = trim($_POST['full_name']       ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $department = trim($_POST['department']       ?? '');
    $phone      = trim($_POST['phone']            ?? '');
    $password   = $_POST['password']              ?? '';
    $confirm    = $_POST['confirm_password']      ?? '';

    // ── Validate inputs ───────────────────────────────────────
    if (strlen($full_name) < 3)
        $errors[] = 'Full name must be at least 3 characters.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';

    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';

    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    // Check the email is not already registered
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists.';
        }
    }

    // ── Save and notify if validation passed ──────────────────
    if (empty($errors)) {

        // Hash password with bcrypt — the plain text is never stored
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // pwCol handles different column names (password, passwd etc.)
        $pwCol = users_password_column_sql();

        // All self-registered users get the 'reporter' role by default
        // Admins can promote them to 'officer' from users/list.php
        $stmt = db()->prepare("
            INSERT INTO users (full_name, email, {$pwCol}, role, department, phone)
            VALUES (?, ?, ?, 'reporter', ?, ?)
        ");
        $stmt->execute([$full_name, $email, $hashed, $department, $phone]);

        $userId = (int) db()->lastInsertId();
        audit_log('auth.register', 'user', $userId, ['email' => $email]);

        // ── EMAIL 1: Welcome email to the new user ────────────
        //
        // notify_account_created() is designed for admin-created
        // accounts. For self-registration we call it with a
        // placeholder password string because the user already
        // knows their password — we just send the welcome.
        //
        // The function will show their email, role, and a login link.
        // We pass '(password you chose during registration)' as the
        // temp password so the email makes sense in this context.
        notify_account_created(
            $email,                                // new user's email
            $full_name,                            // new user's name
            'reporter',                            // default role
            '(the password you set during registration)' // not a real temp pass
        );

        // ── EMAIL 2: Alert IT team about new registration ─────
        //
        // notify_new_registration_alert() is defined further below
        // in this file (not in mailer.php) because it is only used
        // here and has a simple single-use body.
        notify_new_registration_alert($full_name, $email, $department);

        flash('success', 'Account created successfully. A welcome email has been sent. Please sign in.');
        redirect('/public/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/cirms.png">
    <title>Register – CIRMS</title>
    <?php require __DIR__ . '/../../includes/head_assets.php'; ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card auth-card-wide">

        <div class="auth-logo">
            <div class="auth-logo-icon">
                <img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png"
                     style="width:100%;height:100%;object-fit:cover;border-radius:10px;" alt="CIRMS Logo">
            </div>
            <div>
                <div class="auth-logo-text">CIRMS</div>
                <span class="auth-logo-sub">Create Your Account</span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= e($_POST['full_name'] ?? '') ?>"
                       placeholder="Your full name" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       placeholder="your.email@university.ac.tz" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control"
                           value="<?= e($_POST['department'] ?? '') ?>"
                           placeholder="e.g. ICT, Finance">
                </div>
                <div class="col-6">
                    <label class="form-label">Phone (optional)</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= e($_POST['phone'] ?? '') ?>"
                           placeholder="+255 7xx xxx xxxx">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Minimum 8 characters" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-control"
                       placeholder="Re-enter your password" required>
            </div>

            <button type="submit" class="btn btn-dark w-100">
                <i class="bi bi-person-check me-1"></i> Create Account
            </button>

            <p class="text-center mt-3" style="font-size:.875rem;">
                Already have an account?
                <a href="<?= APP_URL ?>/public/login.php">Sign in here</a>
            </p>
        </form>

    </div>
</div>
</body>
</html>

<?php
// ============================================================
//  HELPER FUNCTION — notify_new_registration_alert()
//  Defined at the bottom of this file because it is only used
//  here and does not belong in mailer.php (single use).
//
//  Sends the IT Security team a simple alert when any new user
//  self-registers, so admins are aware and can review the account.
// ============================================================
function notify_new_registration_alert(
    string $name,
    string $email,
    string $department
): void {
    // If the send fails we do nothing — it is a low-priority alert
    // and the user's account is already created regardless
    try {
        $safeName  = htmlspecialchars($name,       ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email,      ENT_QUOTES, 'UTF-8');
        $safeDept  = htmlspecialchars($department ?: 'Not specified', ENT_QUOTES, 'UTF-8');
        $usersUrl  = APP_URL . '/public/users/list.php';

        $body = "
            <p>A new user has self-registered on CIRMS. No action is required
               unless you need to promote their role or verify their identity.</p>

            <table class='info-table'>
                <tr><td>Name:</td>        <td><strong>{$safeName}</strong></td></tr>
                <tr><td>Email:</td>       <td>{$safeEmail}</td></tr>
                <tr><td>Department:</td>  <td>{$safeDept}</td></tr>
                <tr><td>Role:</td>        <td>Reporter (default)</td></tr>
                <tr><td>Registered At:</td><td>" . date('d M Y, H:i') . "</td></tr>
            </table>

            <p>To promote this user to IT Officer or Admin, visit the user management page.</p>

            <a href='{$usersUrl}' class='btn'>&#128100; Manage Users &rarr;</a>

            <hr class='divider'>
            <p style='color:#64748b;font-size:.82rem;'>
                This is an automated awareness notification. No action required.
            </p>
        ";

        // Send directly to the IT security team email in config.php
        send_email(
            NOTIFY_IT_EMAIL,
            'IT Security Team',
            '[CIRMS] New User Registration: ' . $safeName,
            email_wrap_html($body, 'New User Self-Registered')
        );

    } catch (\Throwable $e) {
        // Log but don't throw — registration must succeed even if this fails
        error_log('notify_new_registration_alert error: ' . $e->getMessage());
    }
}
