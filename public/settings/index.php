<?php
// ============================================================
// CIRMS – System Settings
// public/settings/index.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $allowed = [
        'sla_low_hours', 'sla_medium_hours', 'sla_high_hours', 'sla_critical_hours',
        'smtp_host', 'smtp_port', 'smtp_user', 'notify_email',
        'max_upload_mb', 'session_timeout',
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

    // SMTP password only updated if provided (never store blank)
    if (!empty($_POST['smtp_pass'])) {
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_pass',?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$_POST['smtp_pass']]);
    }

    audit_log('settings.updated');
    flash('success', 'Settings saved successfully.');
    redirect('/public/settings/index.php');
}

// Load current settings
$rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'System Settings';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-gear-fill me-2 text-cyan"></i>System Settings</h1>
        <p class="page-subtitle">Configure SLA, email notifications, and system behaviour.</p>
    </div>
</div>

<form method="POST" action="">
    <?= csrf_field() ?>

    <div class="row g-3">

        <!-- SLA Configuration -->
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
                    <div class="col-5">
                        <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                    </div>
                    <div class="col-5">
                        <input type="number" name="<?= $key ?>" class="form-control form-control-sm"
                               value="<?= e($val) ?>" min="1" max="720" required>
                    </div>
                    <div class="col-2 text-muted" style="font-size:.8rem;">hours</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Email / SMTP -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-envelope me-1"></i>Email Notifications</h2>
                </div>

                <div class="mb-3">
                    <label class="form-label">IT Security Notification Email</label>
                    <input type="email" name="notify_email" class="form-control"
                           value="<?= e($rows['notify_email'] ?? '') ?>">
                    <div class="form-hint">All new incident alerts are sent here.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control"
                               value="<?= e($rows['smtp_host'] ?? '') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control"
                               value="<?= e($rows['smtp_port'] ?? '587') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-control"
                           value="<?= e($rows['smtp_user'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control"
                           placeholder="Leave blank to keep current password">
                    <div class="form-hint">Stored in database. Leave blank to keep unchanged.</div>
                </div>
            </div>
        </div>

        <!-- System Limits -->
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

        <!-- Save -->
        <div class="col-12">
            <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms px-4">
                <i class="bi bi-check-lg me-1"></i> Save Settings
            </button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
