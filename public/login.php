<?php
// ============================================================
// CIRMS – Login Page
// public/login.php
//
// Features:
//   – Bcrypt password verification
//   – Account lockout after 3 failed attempts (30-minute window)
//   – Admin-clearable lockout via audit_log marker
//   – CSRF token on every form submission
//   – Animated particle-network canvas background
//   – Page-transition loading overlay on navigation
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
send_security_headers(); // X-Frame-Options, nosniff, etc.
session_start_secure();

if (is_logged_in()) redirect('/public/dashboard.php');

$error   = '';
$locked  = false;
$timeout = isset($_GET['timeout']); // redirect from require_login() after idle timeout

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo = db();

        // ── Lockout check ─────────────────────────────────────────
        // Count failures in the last 30 min that occurred AFTER the
        // most recent admin-issued lockout_cleared event.
        // This allows admins to unlock accounts instantly.
        $emailJson = '%' . json_encode($email) . '%';
        $failStmt  = $pdo->prepare("
            SELECT COUNT(*) FROM audit_log
            WHERE  action = 'auth.login_failed'
              AND  details LIKE ?
              AND  created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
              AND  created_at > COALESCE(
                       (SELECT MAX(created_at) FROM audit_log
                        WHERE  action = 'auth.lockout_cleared'
                          AND  details LIKE ?),
                       '1970-01-01 00:00:00'
                   )
        ");
        $failStmt->execute([$emailJson, $emailJson]);
        $failCount = (int) $failStmt->fetchColumn();

        if ($failCount >= 3) {
            $locked = true;
            $error  = 'Account temporarily locked after 3 failed attempts. Contact the IT Security team to regain access.';
            audit_log('auth.login_locked', null, null, ['email' => $email]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $hash = $user ? user_password_hash_from_row($user) : null;

            if ($user && is_string($hash) && password_verify($password, $hash)) {
                if (array_key_exists('is_active', $user) && !(int) $user['is_active']) {
                    $error = 'This account has been deactivated. Please contact the IT Security team.';
                    audit_log('auth.login_inactive', 'user', (int) $user['id'], ['email' => $email]);
                } else {
                    // Successful login — regenerate session to prevent fixation
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
                audit_log('auth.login_failed', null, null, ['email' => $email]);
            }
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
    <title>Sign In – CIRMS</title>
    <?php require __DIR__ . '/../includes/head_assets.php'; ?>
    <style>
        /* Canvas replaces the default auth-wrapper gradient */
        html, body { margin:0; padding:0; background:#060f1a; overflow-x:hidden; }
        body { min-height:100vh; }

        #bg-canvas { position:fixed; inset:0; z-index:0; display:block; }

        /* Override the cirms.css gradient — canvas is the background */
        .auth-wrapper {
            background: transparent !important;
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .auth-card {
            background: #ffffff;
            border: 1px solid rgba(0,212,255,.22);
            box-shadow: 0 24px 64px rgba(0,0,0,.5), 0 0 0 1px rgba(0,212,255,.08),
                        0 0 80px rgba(0,212,255,.07);
            border-radius: 18px;
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 420px;
            animation: cardIn .5s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }

        /* ── Pulsing shield icon above logo ──────────────────── */
        .auth-shield {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px; height: 52px;
            background: linear-gradient(135deg, #0d1b2a, #1b2d3f);
            border-radius: 14px;
            border: 1.5px solid rgba(0, 212, 255, 0.3);
            box-shadow: 0 0 20px rgba(0, 212, 255, 0.15);
            animation: shieldPulse 3s ease-in-out infinite;
            flex-shrink: 0;
        }
        @keyframes shieldPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(0,212,255,.15); }
            50%       { box-shadow: 0 0 35px rgba(0,212,255,.35); }
        }

        /* ── Floating security tags below form ───────────────── */
        .auth-security-tags {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            justify-content: center;
        }
        .auth-security-tag {
            font-size: .68rem;
            color: #64748b;
            background: #f0f4f8;
            border: 1px solid #dde3ea;
            border-radius: 20px;
            padding: .15rem .55rem;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }

        /* ── Timeout warning ─────────────────────────────────── */
        .timeout-banner {
            background: #fff8e1;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: .6rem .9rem;
            font-size: .82rem;
            color: #92400e;
            margin-bottom: 1rem;
        }

        /* ── Loading overlay ─────────────────────────────────── */
        #pageLoader {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(6, 15, 26, .8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: .9rem;
        }
        .pl-ring {
            width: 48px; height: 48px;
            border: 4px solid rgba(0,212,255,.2);
            border-top-color: #00d4ff;
            border-radius: 50%;
            animation: plSpin .75s linear infinite;
        }
        @keyframes plSpin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .auth-card { padding: 1.5rem 1.25rem; }
        }
    </style>
</head>
<body>

<!-- ── Animated particle-network background canvas ───────────── -->
<canvas id="bg-canvas" aria-hidden="true"></canvas>

<!-- ── Auth Card ────────────────────────────────────────────── -->
<div class="auth-wrapper">
    <div class="auth-card">

        <!-- Logo row -->
        <div class="auth-logo">
            <div class="auth-shield">
                <img src="<?= APP_URL ?>/public/assets/images/cirms_logo.png"
                     style="width:32px;height:32px;object-fit:cover;border-radius:8px;" alt="CIRMS">
            </div>
            <div>
                <div class="auth-logo-text">CIRMS</div>
                <span class="auth-logo-sub">Campus Cyber Incident Reporting System</span>
            </div>
        </div>

        <h2 class="auth-heading mb-1">Welcome back</h2>
        <p class="auth-lead text-muted mb-3">Sign in with your institutional email address.</p>

        <!-- Session timeout notice -->
        <?php if ($timeout && !$error): ?>
        <div class="timeout-banner">
            <i class="bi bi-clock me-1"></i>
            Your session expired due to inactivity. Please sign in again.
        </div>
        <?php endif; ?>

        <!-- Error / lockout alert -->
        <?php if ($error): ?>
        <div class="alert alert-<?= $locked ? 'warning' : 'danger' ?> py-2 auth-alert">
            <i class="bi bi-<?= $locked ? 'lock-fill' : 'exclamation-triangle-fill' ?> me-1"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <!-- Sign-in form -->
        <form method="POST" action="" id="loginForm" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="email" class="form-label">Institutional Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-envelope text-muted"></i>
                    </span>
                    <input type="email" id="email" name="email" class="form-control border-start-0"
                           placeholder="you@university.ac.tz" required autofocus
                           value="<?= e($_POST['email'] ?? '') ?>"
                           autocomplete="email"
                           <?= $locked ? 'disabled' : '' ?>>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="<?= APP_URL ?>/public/auth/forgot_password.php"
                       class="text-muted" style="font-size:.78rem;">
                        Forgot password?
                    </a>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-lock text-muted"></i>
                    </span>
                    <input type="password" id="password" name="password"
                           class="form-control border-start-0" placeholder="••••••••" required
                           autocomplete="current-password"
                           <?= $locked ? 'disabled' : '' ?>>
                    <button type="button" id="togglePwd"
                            class="btn btn-outline-secondary border-start-0"
                            title="Show / hide password"
                            aria-label="Toggle password visibility">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2 auth-submit" id="loginBtn"
                    <?= $locked ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <hr class="my-3">

        <p class="text-center" style="font-size:.85rem;color:#64748b;">
            Don't have an account?
            <a href="<?= APP_URL ?>/public/auth/register.php" class="auth-register-link fw-semibold">
                Request access
            </a>
        </p>

        <!-- Trust / security tags -->
        <div class="auth-security-tags">
            <span class="auth-security-tag"><i class="bi bi-shield-lock-fill text-success"></i> Encrypted</span>
            <span class="auth-security-tag"><i class="bi bi-lock-fill" style="color:#0ea5e9;"></i> CSRF Protected</span>
            <span class="auth-security-tag"><i class="bi bi-eye-slash-fill text-muted"></i> Bcrypt Passwords</span>
        </div>

    </div><!-- /auth-card -->
</div><!-- /auth-wrapper -->

<!-- Page-transition loading overlay -->
<div id="pageLoader" role="status" aria-label="Loading">
    <div class="pl-ring" aria-hidden="true"></div>
    <span id="pageLoaderLabel" style="color:#94a3b8;font-size:.9rem;font-weight:500;">Loading…</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    /* ══════════════════════════════════════════════════════════
       PARTICLE NETWORK BACKGROUND
       Floating nodes connected by lines when within snap distance.
       Cyan / navy colour palette to match CIRMS branding.
    ══════════════════════════════════════════════════════════ */
    var canvas = document.getElementById('bg-canvas');
    var ctx    = canvas.getContext('2d');
    var W, H, particles;
    var PARTICLE_COUNT  = 55;
    var CONNECT_DIST    = 130;
    var SPEED           = 0.35;
    var COLORS_NODE     = ['rgba(0,212,255,', 'rgba(0,170,204,', 'rgba(99,102,241,'];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function rand(min, max) { return Math.random() * (max - min) + min; }

    function makeParticle() {
        var colorBase = COLORS_NODE[Math.floor(Math.random() * COLORS_NODE.length)];
        return {
            x:     rand(0, W),
            y:     rand(0, H),
            vx:    rand(-SPEED, SPEED),
            vy:    rand(-SPEED, SPEED),
            r:     rand(1.5, 3.5),
            alpha: rand(0.35, 0.9),
            color: colorBase,
        };
    }

    function initParticles() {
        particles = [];
        for (var i = 0; i < PARTICLE_COUNT; i++) particles.push(makeParticle());
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);

        /* Subtle radial gradient overlay */
        var g = ctx.createRadialGradient(W/2, H/2, 0, W/2, H/2, Math.max(W, H) * .7);
        g.addColorStop(0, 'rgba(13,27,42,.92)');
        g.addColorStop(1, 'rgba(6,10,20,.98)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        /* Draw connecting lines */
        for (var i = 0; i < particles.length; i++) {
            for (var j = i + 1; j < particles.length; j++) {
                var dx   = particles[i].x - particles[j].x;
                var dy   = particles[i].y - particles[j].y;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < CONNECT_DIST) {
                    var opacity = (1 - dist / CONNECT_DIST) * 0.4;
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = 'rgba(0,212,255,' + opacity + ')';
                    ctx.lineWidth   = 0.8;
                    ctx.stroke();
                }
            }
        }

        /* Draw particles */
        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.color + p.alpha + ')';
            ctx.fill();

            /* Move */
            p.x += p.vx;
            p.y += p.vy;

            /* Bounce off edges */
            if (p.x < 0 || p.x > W) p.vx *= -1;
            if (p.y < 0 || p.y > H) p.vy *= -1;
        }

        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', function () { resize(); initParticles(); });
    resize();
    initParticles();
    draw();

    /* ══════════════════════════════════════════════════════════
       PASSWORD VISIBILITY TOGGLE
    ══════════════════════════════════════════════════════════ */
    var pwdInput = document.getElementById('password');
    var eyeIcon  = document.getElementById('eyeIcon');
    document.getElementById('togglePwd').addEventListener('click', function () {
        var shown = pwdInput.type === 'text';
        pwdInput.type = shown ? 'password' : 'text';
        eyeIcon.className = shown ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    /* ══════════════════════════════════════════════════════════
       SIGN-IN FORM — button spinner + loading overlay
    ══════════════════════════════════════════════════════════ */
    document.getElementById('loginForm').addEventListener('submit', function () {
        var btn   = document.getElementById('loginBtn');
        var email = document.getElementById('email').value.trim();
        var pass  = document.getElementById('password').value;
        if (!email || !pass) return;
        btn.disabled = true;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
            + 'Signing in…';
        showPageLoader('Signing in…');
    });

    /* ══════════════════════════════════════════════════════════
       PAGE-TRANSITION OVERLAY (navigation links)
    ══════════════════════════════════════════════════════════ */
    function showPageLoader(label) {
        var el = document.getElementById('pageLoader');
        var lb = document.getElementById('pageLoaderLabel');
        if (!el) return;
        if (lb && label) lb.textContent = label;
        el.style.display = 'flex';
    }

    document.querySelectorAll('a[href]').forEach(function (link) {
        var href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
        var label = link.textContent.trim().length ? link.textContent.trim() + '…' : 'Loading…';
        link.addEventListener('click', function (e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            showPageLoader(label.length > 30 ? 'Loading…' : label);
        });
    });

}());
</script>
</body>
</html>
