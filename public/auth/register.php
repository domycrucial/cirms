<?php
// ============================================================
// CIRMS – User Registration
// public/auth/register.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();

if (is_logged_in()) redirect('/public/dashboard.php');

$error  = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Validation
    if (strlen($full_name) < 3)       $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8)        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)        $errors[] = 'Passwords do not match.';

    // Check email not already registered (SELECT 1 avoids assuming the PK column is named `id`)
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // Hash column may be `password`, `passwd`, etc. (see USERS_PASSWORD_COLUMN + auto-detect in functions.php)
        $pwCol = users_password_column_sql();
        $stmt  = db()->prepare("
            INSERT INTO `users` (`full_name`, `email`, {$pwCol}, `role`, `department`, `phone`)
            VALUES (?, ?, ?, 'reporter', ?, ?)
        ");
        $stmt->execute([$full_name, $email, $hashed, $department, $phone]);

        $userId = (int)db()->lastInsertId();
        audit_log('auth.register', 'user', $userId, ['email' => $email]);

        flash('success', 'Account created successfully. Please sign in.');
        redirect('/public/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – CIRMS</title>

    <?php require __DIR__ . '/../../includes/head_assets.php'; ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card auth-card-wide">

        <div class="auth-logo">
            <div class="auth-logo-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <div>
                <div class="auth-logo-text">CIRMS</div>
                <span class="auth-logo-sub">Request Account Access</span>
            </div>
        </div>

        <p class="auth-intro text-muted mb-3">
            Use your institutional email. Your account will be assigned the
            <strong>Reporter</strong> role (students &amp; staff).
        </p>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 auth-alert">
            <ul class="mb-0 auth-error-list">
                <?php foreach ($errors as $e): ?>
                <li><?= e($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name *</label>
                <input type="text" id="full_name" name="full_name" class="form-control"
                       value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Institutional Email *</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="you@university.ac.tz"
                       value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="department" class="form-label">Department</label>
                    <input type="text" id="department" name="department" class="form-control"
                           placeholder="e.g. Computer Science"
                           value="<?= e($_POST['department'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone (optional)</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           placeholder="+255 7xx xxx xxx"
                           value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password *</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Minimum 8 characters" required>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control" required>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2 auth-submit">
                <i class="bi bi-person-plus me-1"></i> Create Account
            </button>
        </form>

        <hr class="my-3">
        <p class="text-center text-muted mb-0 auth-footer-text">
            Already have an account?
            <a href="<?= APP_URL ?>/public/login.php" class="auth-register-link">Sign in</a>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
