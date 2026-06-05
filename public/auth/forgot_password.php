<?php
// ============================================================
// CIRMS – Forgot Password
// public/auth/forgot_password.php
//
// Step 1 (?step not set): Email input form
// Step 2 (?step=reset&token=XYZ): New password form
// Tokens stored in settings table with 60-minute expiry.
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/notifications/mailer.php';
session_start_secure();

if (is_logged_in()) redirect('/public/dashboard.php');

$pdo    = db();
$step   = $_GET['step'] ?? '';
$token  = trim($_GET['token'] ?? '');
$errors = [];
$info   = '';

// ── Step 2: Handle new-password form submission ───────────────
if ($step === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $submittedToken = trim($_POST['token'] ?? '');
    $newPass  = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validate password strength
    if (strlen($newPass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        if (!preg_match('/[A-Z]/', $newPass)) $errors[] = 'Must contain an uppercase letter.';
        if (!preg_match('/[a-z]/', $newPass)) $errors[] = 'Must contain a lowercase letter.';
        if (!preg_match('/[0-9]/', $newPass)) $errors[] = 'Must contain a number.';
        if (!preg_match('/[^A-Za-z0-9]/', $newPass)) $errors[] = 'Must contain a special character.';
    }
    if ($newPass !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // Look up token in settings table
        $key = 'pwd_reset_' . hash('sha256', $submittedToken);
        $rowStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $rowStmt->execute([$key]);
        $row = $rowStmt->fetchColumn();

        if (!$row) {
            $errors[] = 'This reset link is invalid or has already been used.';
        } else {
            [$userId, $expiry] = explode('|', $row, 2);
            $userId = (int)$userId;

            if (time() > (int)$expiry) {
                $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$key]);
                $errors[] = 'This reset link has expired (valid for 60 minutes). Please request a new one.';
            } else {
                // Update password
                $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $pwCol  = users_password_column_sql();
                $pdo->prepare("UPDATE users SET {$pwCol} = ? WHERE id = ?")->execute([$hashed, $userId]);

                // Invalidate token
                $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$key]);

                audit_log('auth.password_reset', 'user', $userId);
                flash('success', 'Password updated successfully. You can now sign in with your new password.');
                redirect('/public/login.php');
            }
        }
    }
}

// ── Step 1: Handle email submission ──────────────────────────
if ($step === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $userStmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        // Always show same message to prevent user enumeration
        $info = 'If that email is registered, a password reset link has been sent. Check your inbox.';

        if ($user) {
            // Generate a cryptographically secure token
            $rawToken = bin2hex(random_bytes(32));
            $key      = 'pwd_reset_' . hash('sha256', $rawToken);
            $expiry   = time() + 3600; // 60 minutes

            // Remove any existing token for this user first
            $pdo->prepare("DELETE FROM settings WHERE setting_key LIKE 'pwd_reset_%' AND setting_value LIKE ?")->execute([$user['id'] . '|%']);

            // Store the new token
            $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, description)
                VALUES (?, ?, 'Password reset token')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute([$key, $user['id'] . '|' . $expiry]);

            // Build reset URL
            $resetUrl = APP_URL . '/public/auth/forgot_password.php?step=reset&token=' . urlencode($rawToken);

            // Send email
            $safeName = htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8');
            $body = "
                <p>Dear {$safeName},</p>
                <p>We received a request to reset your IRS portal password. Click the button below to set a new password.
                   This link is valid for <strong>60 minutes</strong>.</p>
                <a href='" . htmlspecialchars($resetUrl, ENT_QUOTES) . "' class='btn'>&#128274; Reset My Password &rarr;</a>
                <hr class='divider'>
                <p style='color:#64748b;font-size:.82rem;'>
                    If you did not request this, you can safely ignore this email — your password will not change.
                </p>
            ";
            send_email($email, $user['full_name'], '[IRS] Password Reset Request', $body);
            audit_log('auth.password_reset_requested', 'user', (int)$user['id']);
        }
    }
}

// ── Validate token for step 2 display ────────────────────────
$tokenValid = false;
if ($step === 'reset' && $token && empty($_POST)) {
    $key = 'pwd_reset_' . hash('sha256', $token);
    $rowStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $rowStmt->execute([$key]);
    $row = $rowStmt->fetchColumn();
    if ($row) {
        [, $expiry] = explode('|', $row, 2);
        $tokenValid = time() <= (int)$expiry;
    }
    if (!$tokenValid) {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/iaa.png">
    <title>Forgot Password – IRS</title>
    <?php require __DIR__ . '/../../includes/head_assets.php'; ?>
    <style>
        html, body { margin:0; padding:0; background:#060f1a; overflow-x:hidden; }
        #bg-canvas  { position:fixed; inset:0; z-index:0; display:block; pointer-events:none; }
        .auth-wrapper {
            background: transparent !important;
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .auth-card {
            border: 1px solid rgba(0,212,255,.2);
            box-shadow: 0 24px 64px rgba(0,0,0,.5), 0 0 60px rgba(0,212,255,.06);
        }
    </style>
</head>
<body>
<canvas id="bg-canvas" aria-hidden="true"></canvas>
<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-logo">
            <div class="auth-logo-icon">
                <img src="<?= APP_URL ?>/public/assets/images/iaa.png"
                     style="width:100%;height:100%;object-fit:contain;border-radius:4px;" alt="IAA">
            </div>
            <div>
                <div class="auth-logo-text">IRS</div>
                <span class="auth-logo-sub">Password Recovery</span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><div><i class="bi bi-x-circle me-1"></i><?= e($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($info): ?>
        <div class="alert alert-info">
            <i class="bi bi-envelope-check me-1"></i><?= e($info) ?>
        </div>
        <?php endif; ?>

        <!-- ── Step 1: Email form ─────────────────────────── -->
        <?php if ($step === '' && !$info): ?>
        <h2 class="auth-heading mb-1">Forgot Password</h2>
        <p class="auth-lead text-muted mb-3">Enter your registered email to receive a reset link.</p>

        <form method="POST" action="" id="forgotForm" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Registered Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" id="emailField" class="form-control"
                           placeholder="you@university.ac.tz" required autofocus
                           pattern="[a-zA-Z0-9@.]+"
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-dark w-100 py-2" id="forgotBtn">
                <i class="bi bi-send me-1"></i> Send Reset Link
            </button>
        </form>

        <?php elseif ($step === '' && $info): ?>
        <!-- Sent confirmation — no further form -->
        <h2 class="auth-heading mb-1">Check Your Inbox</h2>
        <p class="text-muted" style="font-size:.875rem;">
            A reset link has been sent if your email is registered. Check your spam folder if you don't see it within a few minutes.
        </p>

        <!-- ── Step 2: New password form ─────────────────── -->
        <?php elseif ($step === 'reset' && ($tokenValid || !empty($errors))): ?>
        <?php if ($tokenValid): ?>
        <h2 class="auth-heading mb-1">Set New Password</h2>
        <p class="auth-lead text-muted mb-3">Choose a strong password for your account.</p>

        <form method="POST" action="?step=reset" id="resetForm" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="newPwd" class="form-control"
                           placeholder="Min 8 chars" required autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="var p=document.getElementById('newPwd');p.type=p.type==='password'?'text':'password';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div style="background:#e5e7eb;border-radius:2px;margin-top:.4rem;overflow:hidden;">
                    <div id="pwdBar" style="height:4px;border-radius:2px;width:0;background:#ef4444;transition:width .3s,background .3s;"></div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confPwd" class="form-control"
                           placeholder="Re-enter password" required autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="var p=document.getElementById('confPwd');p.type=p.type==='password'?'text':'password';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="matchMsg" style="font-size:.75rem;margin-top:.25rem;"></div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2" id="resetBtn">
                <i class="bi bi-shield-check me-1"></i> Update Password
            </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>

        <hr class="my-3">
        <p class="text-center" style="font-size:.85rem;">
            <a href="<?= APP_URL ?>/public/login.php" class="text-muted">
                <i class="bi bi-arrow-left me-1"></i>Back to Sign In
            </a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    /* Email field: allow only letters, numbers and the @ and . characters */
    var emailField = document.getElementById('emailField');
    emailField && emailField.addEventListener('input', function () {
        this.value = this.value.replace(/[^a-zA-Z0-9@.]/g, '');
    });

    /* Spinner on email form */
    var ff = document.getElementById('forgotForm');
    ff && ff.addEventListener('submit', function () {
        var b = document.getElementById('forgotBtn');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending…';
    });

    /* Password strength + match on reset form */
    var np = document.getElementById('newPwd');
    var cp = document.getElementById('confPwd');
    var bar = document.getElementById('pwdBar');
    var mm  = document.getElementById('matchMsg');

    function score(p) {
        var s = 0;
        if (p.length >= 8)      s++;
        if (/[A-Z]/.test(p))    s++;
        if (/[a-z]/.test(p))    s++;
        if (/[0-9]/.test(p))    s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        return s;
    }

    np && np.addEventListener('input', function () {
        var s = score(this.value);
        var c = ['#ef4444','#ef4444','#f59e0b','#f59e0b','#22c55e','#16a34a'][s];
        if (bar) { bar.style.width = (s*20)+'%'; bar.style.background = c; }
        checkMatch();
    });

    cp && cp.addEventListener('input', checkMatch);

    function checkMatch() {
        if (!cp || !np || !mm || !cp.value) { if(mm) mm.textContent=''; return; }
        if (np.value === cp.value) { mm.textContent='✓ Passwords match'; mm.style.color='#16a34a'; }
        else                       { mm.textContent='✗ Passwords do not match'; mm.style.color='#ef4444'; }
    }

    var rf = document.getElementById('resetForm');
    rf && rf.addEventListener('submit', function () {
        var b = document.getElementById('resetBtn');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Updating…';
    });
}());
</script>

<!-- Particle network background -->
<script>
(function () {
    var canvas = document.getElementById('bg-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var W, H, particles;
    var COUNT = 45, SNAP = 115, SPD = 0.3;
    var COLS = ['rgba(0,212,255,','rgba(0,170,204,','rgba(99,102,241,'];
    function resize(){ W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    function rand(a,b){ return Math.random()*(b-a)+a; }
    function init(){
        particles=[];
        for(var i=0;i<COUNT;i++) particles.push({x:rand(0,W),y:rand(0,H),vx:rand(-SPD,SPD),vy:rand(-SPD,SPD),r:rand(1.5,3),a:rand(.35,.8),c:COLS[Math.floor(Math.random()*COLS.length)]});
    }
    function draw(){
        ctx.clearRect(0,0,W,H);
        var g=ctx.createRadialGradient(W/2,H/2,0,W/2,H/2,Math.max(W,H)*.75);
        g.addColorStop(0,'rgba(13,27,42,.93)');g.addColorStop(1,'rgba(6,10,20,.98)');
        ctx.fillStyle=g;ctx.fillRect(0,0,W,H);
        for(var i=0;i<particles.length;i++) for(var j=i+1;j<particles.length;j++){
            var dx=particles[i].x-particles[j].x,dy=particles[i].y-particles[j].y,d=Math.sqrt(dx*dx+dy*dy);
            if(d<SNAP){ctx.beginPath();ctx.moveTo(particles[i].x,particles[i].y);ctx.lineTo(particles[j].x,particles[j].y);ctx.strokeStyle='rgba(0,212,255,'+(1-d/SNAP)*.3+')';ctx.lineWidth=.8;ctx.stroke();}
        }
        for(var i=0;i<particles.length;i++){var p=particles[i];ctx.beginPath();ctx.arc(p.x,p.y,p.r,0,Math.PI*2);ctx.fillStyle=p.c+p.a+')';ctx.fill();p.x+=p.vx;p.y+=p.vy;if(p.x<0||p.x>W)p.vx*=-1;if(p.y<0||p.y>H)p.vy*=-1;}
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize',function(){resize();init();});
    resize();init();draw();
}());
</script>
</body>
</html>
