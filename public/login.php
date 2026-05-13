<?php
// ============================================================
// CIRMS – Login Page
// public/login.php
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
session_start_secure();

// Already logged in → go to dashboard
if (is_logged_in()) redirect('/public/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        // Do not reference `is_active` in SQL: some installs use a slimmer `users` table without that column.
        // When the column exists, we block inactive accounts after verifying the password.
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $hash = $user ? user_password_hash_from_row($user) : null;

        if ($user && is_string($hash) && password_verify($password, $hash)) {
            if (array_key_exists('is_active', $user) && !(int) $user['is_active']) {
                $error    = 'This account has been deactivated. Please contact support.';
                $targetId = isset($user['id']) ? (int) $user['id'] : null;
                audit_log('auth.login_inactive', 'user', $targetId, ['email' => $email]);
            } else {
                // Regenerate session ID on login (prevents session fixation)
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['login_time'] = time();

                audit_log('auth.login', 'user', (int) $user['id']);
                redirect('/public/dashboard.php');
            }
        } else {
            $error = 'Invalid email or password.';
            // Log failed attempt for security monitoring
            audit_log('auth.login_failed', null, null, ['email' => $email]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/cirms.png">
    <title>Login – CIRMS</title>

    <?php require __DIR__ . '/../includes/head_assets.php'; ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <div class="auth-logo-icon"><img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;" alt="Logo"></div>
            <div>
                <div class="auth-logo-text">CIRMS</div>
                <span class="auth-logo-sub">Campus Cyber Incident Reporting System</span>
            </div>
        </div>

        <h2 class="auth-heading mb-1">Sign In</h2>
        <p class="auth-lead text-muted mb-3">Use your institutional email address.</p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 auth-alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Institutional Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@university.ac.tz" required autofocus
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password"
                           class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2 mt-1 auth-submit">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center text-muted mb-0 auth-footer-text">
            No account?
            <a href="<?= APP_URL ?>/public/auth/register.php" class="auth-register-link">Request access</a>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
