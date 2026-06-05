<?php
// ============================================================
// CIRMS – Self-Registration
// public/auth/register.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../modules/notifications/mailer.php';
session_start_secure();

if (is_logged_in()) redirect('/public/dashboard.php');

$errors = [];
$old    = [];  // preserve form values on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Collect
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    $old = compact('full_name', 'email', 'department', 'phone');

    // ── Validate Full Name ────────────────────────────────────
    if (strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    } elseif (!preg_match("/^[a-zA-Z\s'\-\.]+$/u", $full_name)) {
        $errors[] = 'Full name must contain only letters, spaces, hyphens, apostrophes, or dots.';
    } elseif (strlen($full_name) > 150) {
        $errors[] = 'Full name must not exceed 150 characters.';
    }

    // ── Validate Email ────────────────────────────────────────
    // Only letters, digits and the two special characters @ and . are
    // permitted (e.g. a Gmail address). Any other special character is rejected.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9@.]+$/', $email)) {
        $errors[] = 'Email may contain only letters, numbers, @ and . — no other special characters.';
    } elseif (strlen($email) > 200) {
        $errors[] = 'Email address is too long.';
    }

    // ── Validate Department ────────────────────────────────────
    // Department must contain only letters (and spaces) — no numbers or symbols.
    if ($department !== '') {
        if (!preg_match("/^[a-zA-Z\s]+$/u", $department)) {
            $errors[] = 'Department must contain only letters and spaces.';
        } elseif (strlen($department) > 150) {
            $errors[] = 'Department must not exceed 150 characters.';
        }
    }

    // ── Validate Phone ────────────────────────────────────────
    if ($phone !== '') {
        if (!preg_match('/^\+255[67]\d{8}$/', $phone)) {
            $errors[] = 'Phone must be in +255 format — e.g. +255712345678 (10 digits after +255 starting with 6 or 7).';
        }
    }

    // ── Validate Password Strength ────────────────────────────
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        if (!preg_match('/[A-Z]/', $password))
            $errors[] = 'Password must contain at least one uppercase letter (A–Z).';
        if (!preg_match('/[a-z]/', $password))
            $errors[] = 'Password must contain at least one lowercase letter (a–z).';
        if (!preg_match('/[0-9]/', $password))
            $errors[] = 'Password must contain at least one number (0–9).';
        if (!preg_match('/[^A-Za-z0-9]/', $password))
            $errors[] = 'Password must contain at least one special character (e.g. @, #, !, %).';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Check duplicate email ─────────────────────────────────
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists.';
        }
    }

    // ── Save and notify ───────────────────────────────────────
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $pwCol  = users_password_column_sql();

        $stmt = db()->prepare("
            INSERT INTO users (full_name, email, {$pwCol}, role, department, phone)
            VALUES (?, ?, ?, 'reporter', ?, ?)
        ");
        $stmt->execute([$full_name, $email, $hashed, $department ?: null, $phone ?: null]);

        $userId = (int)db()->lastInsertId();
        audit_log('auth.register', 'user', $userId, ['email' => $email]);

        notify_account_created($email, $full_name, 'reporter', '(the password you set during registration)');
        notify_new_registration_alert($full_name, $email, $department);

        flash('success', 'Account created. A welcome email has been sent. Please sign in.');
        redirect('/public/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/iaa.png">
    <title>Create Account – IRS</title>
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
            align-items: flex-start;
            justify-content: center;
            padding: 1.5rem;
            overflow-y: auto;
        }
        .auth-card {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,212,255,.2);
            box-shadow: 0 24px 64px rgba(0,0,0,.5), 0 0 60px rgba(0,212,255,.06);
        }
        .pwd-strength { height: 4px; border-radius: 2px; transition: width .3s, background .3s; }
        .field-hint   { font-size: .75rem; color: var(--muted, #8899aa); margin-top: .25rem; }
        #pageLoader {
            display:none; position:fixed; inset:0; background:rgba(6,15,26,.8);
            z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:.9rem;
        }
        .pl-ring { width:48px; height:48px; border:4px solid rgba(0,212,255,.2); border-top-color:#00d4ff; border-radius:50%; animation:plSpin .75s linear infinite; }
        @keyframes plSpin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<canvas id="bg-canvas" aria-hidden="true"></canvas>
<div class="auth-wrapper">
    <div class="auth-card auth-card-wide">

        <div class="auth-logo">
            <div class="auth-logo-icon">
                <img src="<?= APP_URL ?>/public/assets/images/iaa.png"
                     style="width:100%;height:100%;object-fit:contain;border-radius:4px;" alt="IAA">
            </div>
            <div>
                <div class="auth-logo-text">IRS</div>
                <span class="auth-logo-sub">Create Your Account</span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="regForm" novalidate>
            <?= csrf_field() ?>

            <!-- Full Name -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" id="fullName" class="form-control"
                       value="<?= e($old['full_name'] ?? '') ?>"
                       placeholder="Your full name" required maxlength="150"
                       pattern="[a-zA-Z\s'\-\.]+" autocomplete="name">
                <div class="field-hint">Letters, spaces, hyphens, apostrophes only — no numbers or symbols.</div>
            </div>

            <!-- Email -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" id="emailField" class="form-control"
                       value="<?= e($old['email'] ?? '') ?>"
                       placeholder="your.email@university.ac.tz" required maxlength="200"
                       pattern="[a-zA-Z0-9@.]+" autocomplete="email">
                <div class="field-hint">Only letters, numbers, @ and . are allowed.</div>
            </div>

            <div class="row g-3 mb-3">
                <!-- Department -->
                <div class="col-6">
                    <label class="form-label fw-semibold">Department</label>
                    <input type="text" name="department" id="deptField" class="form-control"
                           value="<?= e($old['department'] ?? '') ?>"
                           placeholder="e.g. ICT, Finance" maxlength="150"
                           pattern="[a-zA-Z\s]+">
                    <div class="field-hint">Letters and spaces only.</div>
                </div>
                <!-- Phone -->
                <div class="col-6">
                    <label class="form-label fw-semibold">Phone (optional)</label>
                    <input type="tel" name="phone" id="phoneField" class="form-control"
                           value="<?= e($old['phone'] ?? '') ?>"
                           placeholder="+255712345678" maxlength="13"
                           pattern="\+255[67]\d{8}" autocomplete="tel">
                    <div class="field-hint">Format: +255 7xx xxx xxxx</div>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-2">
                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" id="passwordField" class="form-control"
                           placeholder="Min 8 chars — uppercase, lowercase, number, symbol" required
                           autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="var p=document.getElementById('passwordField');p.type=p.type==='password'?'text':'password';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <!-- Strength bar -->
                <div style="background:#e5e7eb;border-radius:2px;margin-top:.4rem;overflow:hidden;">
                    <div id="pwdBar" class="pwd-strength" style="width:0;background:#ef4444;"></div>
                </div>
                <div id="pwdHint" class="field-hint">
                    Must have: uppercase, lowercase, number, special character.
                </div>
            </div>

            <!-- Confirm password -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirmField" class="form-control"
                           placeholder="Re-enter your password" required autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="var p=document.getElementById('confirmField');p.type=p.type==='password'?'text':'password';">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="matchHint" class="field-hint"></div>
            </div>

            <button type="submit" class="btn btn-dark w-100 py-2" id="regBtn">
                <i class="bi bi-person-check me-1"></i> Create Account
            </button>

            <p class="text-center mt-3" style="font-size:.875rem;">
                Already have an account?
                <a href="<?= APP_URL ?>/public/login.php" class="auth-register-link">Sign in here</a>
            </p>
        </form>

    </div>
</div>

<!-- Page-transition loading overlay -->
<div id="pageLoader" role="status" aria-label="Loading"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.58);z-index:9999;
            align-items:center;justify-content:center;flex-direction:column;gap:.9rem;">
    <div class="spinner-border" style="width:3rem;height:3rem;color:#00d4ff;border-width:3px;" aria-hidden="true"></div>
    <span id="pageLoaderLabel" style="color:#f1f5f9;font-size:.9rem;font-weight:500;letter-spacing:.03em;">Loading…</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    /* ── Page-transition overlay helper ─────────────────────── */
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

    /* ── Password strength meter ─────────────────────────── */
    var pwd  = document.getElementById('passwordField');
    var bar  = document.getElementById('pwdBar');
    var hint = document.getElementById('pwdHint');

    function scorePassword(p) {
        var score = 0;
        if (p.length >= 8)  score++;
        if (/[A-Z]/.test(p)) score++;
        if (/[a-z]/.test(p)) score++;
        if (/[0-9]/.test(p)) score++;
        if (/[^A-Za-z0-9]/.test(p)) score++;
        return score; // 0-5
    }

    pwd && pwd.addEventListener('input', function () {
        var s = scorePassword(this.value);
        var colors = ['#ef4444','#ef4444','#f59e0b','#f59e0b','#22c55e','#16a34a'];
        var labels = ['Too weak','Too weak','Fair — add special char','Almost there','Strong','Very strong'];
        bar.style.width  = (s * 20) + '%';
        bar.style.background = colors[s] || '#ef4444';
        hint.textContent = this.value.length ? labels[s] : 'Must have: uppercase, lowercase, number, special character.';
        hint.style.color = colors[s] || '';
    });

    /* ── Password match indicator ────────────────────────── */
    var confirm   = document.getElementById('confirmField');
    var matchHint = document.getElementById('matchHint');

    function checkMatch() {
        if (!confirm.value) { matchHint.textContent = ''; return; }
        if (pwd.value === confirm.value) {
            matchHint.textContent = '✓ Passwords match';
            matchHint.style.color = '#16a34a';
        } else {
            matchHint.textContent = '✗ Passwords do not match';
            matchHint.style.color = '#ef4444';
        }
    }

    confirm && confirm.addEventListener('input', checkMatch);
    pwd     && pwd.addEventListener('input',     checkMatch);

    /* ── Block special chars in name/department/email fields ─ */
    var nameField  = document.getElementById('fullName');
    var deptField  = document.getElementById('deptField');
    var phoneField = document.getElementById('phoneField');
    var emailField = document.getElementById('emailField');

    nameField && nameField.addEventListener('input', function () {
        this.value = this.value.replace(/[^a-zA-Z\s'\-\.]/g, '');
    });
    // Department: letters and spaces only — strip numbers and symbols
    deptField && deptField.addEventListener('input', function () {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
    });
    phoneField && phoneField.addEventListener('input', function () {
        // Allow only +, digits
        this.value = this.value.replace(/[^\+\d]/g, '');
    });
    // Email: allow only letters, numbers and the @ and . special characters
    emailField && emailField.addEventListener('input', function () {
        this.value = this.value.replace(/[^a-zA-Z0-9@.]/g, '');
    });

    /* ── Submit spinner + overlay ───────────────────────── */
    document.getElementById('regForm').addEventListener('submit', function () {
        var btn = document.getElementById('regBtn');
        btn.disabled = true;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
            + 'Creating account…';
        showPageLoader('Creating account…');
    });
}());
</script>

<!-- ── Particle network background (same as login page) ── -->
<script>
(function () {
    var canvas = document.getElementById('bg-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var W, H, particles;
    var COUNT = 50, SNAP = 120, SPD = 0.32;
    var COLS  = ['rgba(0,212,255,', 'rgba(0,170,204,', 'rgba(99,102,241,'];

    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    function rand(a, b) { return Math.random() * (b - a) + a; }
    function init() {
        particles = [];
        for (var i = 0; i < COUNT; i++) {
            particles.push({ x: rand(0,W), y: rand(0,H), vx: rand(-SPD,SPD), vy: rand(-SPD,SPD),
                r: rand(1.5,3), a: rand(.35,.85), c: COLS[Math.floor(Math.random()*COLS.length)] });
        }
    }
    function draw() {
        ctx.clearRect(0, 0, W, H);
        var g = ctx.createRadialGradient(W/2,H/2,0,W/2,H/2,Math.max(W,H)*.75);
        g.addColorStop(0,'rgba(13,27,42,.93)'); g.addColorStop(1,'rgba(6,10,20,.98)');
        ctx.fillStyle = g; ctx.fillRect(0,0,W,H);
        for (var i = 0; i < particles.length; i++) {
            for (var j = i+1; j < particles.length; j++) {
                var dx = particles[i].x-particles[j].x, dy = particles[i].y-particles[j].y;
                var d  = Math.sqrt(dx*dx+dy*dy);
                if (d < SNAP) {
                    ctx.beginPath(); ctx.moveTo(particles[i].x,particles[i].y);
                    ctx.lineTo(particles[j].x,particles[j].y);
                    ctx.strokeStyle = 'rgba(0,212,255,'+(1-d/SNAP)*.35+')';
                    ctx.lineWidth = .8; ctx.stroke();
                }
            }
        }
        for (var i = 0; i < particles.length; i++) {
            var p = particles[i];
            ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle = p.c+p.a+')'; ctx.fill();
            p.x += p.vx; p.y += p.vy;
            if (p.x<0||p.x>W) p.vx*=-1;
            if (p.y<0||p.y>H) p.vy*=-1;
        }
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize', function(){ resize(); init(); });
    resize(); init(); draw();
}());
</script>

<!-- Page-transition loading overlay -->
<div id="pageLoader" role="status" aria-label="Loading"
     style="display:none;position:fixed;inset:0;background:rgba(6,15,26,.8);z-index:9999;
            align-items:center;justify-content:center;flex-direction:column;gap:.9rem;">
    <div class="pl-ring" aria-hidden="true"></div>
    <span style="color:#94a3b8;font-size:.9rem;font-weight:500;">Loading…</span>
</div>
</body>
</html>

<?php
function notify_new_registration_alert(string $name, string $email, string $department): void {
    try {
        $safeName  = htmlspecialchars($name,                             ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email,                            ENT_QUOTES, 'UTF-8');
        $safeDept  = htmlspecialchars($department ?: 'Not specified',    ENT_QUOTES, 'UTF-8');
        $usersUrl  = APP_URL . '/public/users/list.php';

        $body = "
            <p>A new user has self-registered on the IRS portal. No action is required unless you need
               to promote their role or verify their identity.</p>
            <table class='info-table'>
                <tr><td>Name:</td>         <td><strong>{$safeName}</strong></td></tr>
                <tr><td>Email:</td>        <td>{$safeEmail}</td></tr>
                <tr><td>Department:</td>   <td>{$safeDept}</td></tr>
                <tr><td>Role:</td>         <td>Reporter (default)</td></tr>
                <tr><td>Registered At:</td><td>" . date('d M Y, H:i') . "</td></tr>
            </table>
            <a href='{$usersUrl}' class='btn'>&#128100; Manage Users &rarr;</a>
            <hr class='divider'>
            <p style='color:#64748b;font-size:.82rem;'>Automated awareness notification.</p>
        ";

        send_email(
            NOTIFY_IT_EMAIL, 'IT Security Team',
            '[IRS] New User Registration: ' . $safeName,
            email_wrap_html($body, 'New User Self-Registered')
        );
    } catch (\Throwable $e) {
        error_log('notify_new_registration_alert: ' . $e->getMessage());
    }
}
