<?php
// ============================================================
// CIRMS – System Settings
// public/settings/index.php
//
// CHANGES: Added smtp_from and notify_it_email to saved keys.
//          Added SMTP test button that calls test_mail.php.
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $allowed = [
        'sla_low_hours', 'sla_medium_hours', 'sla_high_hours', 'sla_critical_hours',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from', 'smtp_from_name',
        'notify_it_email', 'max_upload_mb', 'session_timeout',
    ];

    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $val = trim($_POST[$key]);
            $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?,?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute([$key, $val]);
        }
    }

    // SMTP password — only update if provided (never overwrite with blank)
    if (!empty($_POST['smtp_pass'])) {
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_pass',?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([trim($_POST['smtp_pass'])]);
    }

    audit_log('settings.updated');
    flash('success', 'Settings saved successfully. Email notifications will use the new SMTP settings.');
    redirect('/public/settings/index.php');
}

// Load current values from settings table
$rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(\PDO::FETCH_KEY_PAIR);

$pageTitle = 'System Settings';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-gear-fill me-2 text-cyan"></i>System Settings</h1>
        <p class="page-subtitle">Configure SLA timeframes, email notifications, and system behaviour.</p>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3">
    <?= e($flash['message']) ?>
</div>
<?php endif; ?>

<form method="POST" action="">
    <?= csrf_field() ?>

    <div class="row g-3">

        <!-- ── SLA Configuration ─────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-alarm me-1"></i>SLA Timeframes</h2>
                </div>
                <p class="text-muted mb-3" style="font-size:.85rem;">
                    Maximum hours to resolve an incident before SLA breach is flagged.
                </p>
                <?php foreach ([
                    ['sla_low_hours',      'Low',      'badge-low',      '72'],
                    ['sla_medium_hours',   'Medium',   'badge-medium',   '24'],
                    ['sla_high_hours',     'High',     'badge-high',     '8'],
                    ['sla_critical_hours', 'Critical', 'badge-critical', '2'],
                ] as [$key, $label, $badgeClass, $default]):
                    $val = $rows[$key] ?? $default;
                ?>
                <div class="row align-items-center mb-3">
                    <div class="col-5"><span class="badge <?= $badgeClass ?>"><?= $label ?></span></div>
                    <div class="col-5">
                        <input type="number" name="<?= $key ?>" class="form-control form-control-sm"
                               value="<?= e($val) ?>" min="1" max="720" required>
                    </div>
                    <div class="col-2 text-muted" style="font-size:.8rem;">hours</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Email / SMTP Configuration ───────────────────────── -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-envelope-fill me-1"></i>Email Notifications (SMTP)</h2>
                </div>

                <p class="text-muted mb-3" style="font-size:.82rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    For Gmail: use <strong>smtp.gmail.com</strong>, port <strong>587</strong>,
                    and a <strong>16-character App Password</strong>
                    (not your real Gmail password).
                    <a href="https://myaccount.google.com/apppasswords" target="_blank">Generate App Password →</a>
                </p>

                <!-- IT Security Notification Email -->
                <div class="mb-3">
                    <label class="form-label fw-bold">IT Security Team Email <span class="text-danger">*</span></label>
                    <input type="email" name="notify_it_email" class="form-control"
                           value="<?= e($rows['notify_it_email'] ?? '') ?>"
                           placeholder="itsecurity@university.ac.tz">
                    <div class="form-hint">
                        <i class="bi bi-envelope me-1"></i>
                        All new incident alerts are sent to this address when a user submits a report.
                    </div>
                </div>

                <hr>

                <!-- SMTP Host + Port -->
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control"
                               value="<?= e($rows['smtp_host'] ?? 'smtp.gmail.com') ?>"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control"
                               value="<?= e($rows['smtp_port'] ?? '587') ?>"
                               placeholder="587">
                    </div>
                </div>

                <!-- SMTP Username -->
                <div class="mb-3">
                    <label class="form-label">SMTP Username (Gmail address)</label>
                    <input type="text" name="smtp_user" class="form-control"
                           value="<?= e($rows['smtp_user'] ?? '') ?>"
                           placeholder="youremail@gmail.com">
                </div>

                <!-- SMTP Password -->
                <div class="mb-3">
                    <label class="form-label">SMTP Password (App Password)</label>
                    <input type="password" name="smtp_pass" class="form-control"
                           placeholder="Leave blank to keep current password"
                           autocomplete="new-password">
                    <div class="form-hint">
                        Use a Gmail App Password (16 characters). Leave blank to keep current.
                    </div>
                </div>

                <!-- From Email + From Name -->
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label">From Email</label>
                        <input type="email" name="smtp_from" class="form-control"
                               value="<?= e($rows['smtp_from'] ?? '') ?>"
                               placeholder="youremail@gmail.com">
                    </div>
                    <div class="col-5">
                        <label class="form-label">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control"
                               value="<?= e($rows['smtp_from_name'] ?? 'CIRMS Notifications') ?>">
                    </div>
                </div>

                <!-- Test Button -->
                <div class="mt-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <p class="mb-2" style="font-size:.85rem;font-weight:600;color:#166534;">
                        <i class="bi bi-send-check me-1"></i>Test SMTP Configuration
                    </p>
                    <p class="mb-2" style="font-size:.8rem;color:#166534;">
                        After saving settings, open this link in your browser to send a test email:
                    </p>
                    <a href="<?= APP_URL ?>/modules/notifications/test_mail.php" target="_blank"
                       class="btn btn-sm btn-outline-success">
                        <i class="bi bi-envelope-check me-1"></i>
                        Open SMTP Test →
                    </a>
                </div>
            </div>
        </div>

        <!-- ── System Limits ─────────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-sliders me-1"></i>System Limits</h2>
                </div>
                <div class="mb-3">
                    <label class="form-label">Max File Upload Size (MB)</label>
                    <input type="number" name="max_upload_mb" class="form-control"
                           value="<?= e($rows['max_upload_mb'] ?? '10') ?>" min="1" max="50">
                </div>
                <div class="mb-3">
                    <label class="form-label">Session Timeout (seconds)</label>
                    <input type="number" name="session_timeout" class="form-control"
                           value="<?= e($rows['session_timeout'] ?? '1800') ?>" min="300">
                    <div class="form-hint">1800 = 30 minutes. 3600 = 1 hour.</div>
                </div>
            </div>
        </div>

        <!-- ── How Notifications Work ──── info panel ───────────── -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-bell me-1"></i>Notification Events</h2>
                </div>
                <p class="text-muted mb-3" style="font-size:.82rem;">
                    CIRMS sends automated emails for these three events:
                </p>
                <ul class="list-unstyled" style="font-size:.85rem;">
                    <li class="mb-3 d-flex gap-2">
                        <i class="bi bi-envelope-plus text-danger" style="font-size:18px;margin-top:1px;flex-shrink:0;"></i>
                        <div>
                            <strong>New Incident Submitted</strong><br>
                            <span class="text-muted">Email sent to IT Security Team email above.</span>
                        </div>
                    </li>
                    <li class="mb-3 d-flex gap-2">
                        <i class="bi bi-arrow-repeat text-warning" style="font-size:18px;margin-top:1px;flex-shrink:0;"></i>
                        <div>
                            <strong>Incident Status Changed</strong><br>
                            <span class="text-muted">Email sent to the original reporter when status is updated by an officer.</span>
                        </div>
                    </li>
                    <li class="mb-3 d-flex gap-2">
                        <i class="bi bi-chat-left-text text-info" style="font-size:18px;margin-top:1px;flex-shrink:0;"></i>
                        <div>
                            <strong>Public Note Added</strong><br>
                            <span class="text-muted">Email sent to reporter when an officer adds a note visible to them (internal notes do not trigger emails).</span>
                        </div>
                    </li>
                </ul>
                <div class="p-2 rounded" style="background:#fef9c3;font-size:.8rem;color:#92400e;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Emails are queued in the database. On Windows/XAMPP, run
                    <code>php modules/notifications/process_queue.php</code>
                    in PowerShell to send them.
                </div>
            </div>
        </div>

        <!-- Save button -->
        <div class="col-12">
            <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms px-4">
                <i class="bi bi-check-lg me-1"></i> Save Settings
            </button>
        </div>

    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
