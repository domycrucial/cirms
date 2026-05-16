<?php
// ============================================================
// CIRMS – System Settings
// public/settings/index.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();
require_login(['admin']);

$pdo = db();

// ── POST handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['_action'] ?? 'save_settings';

    // ── Test SMTP ─────────────────────────────────────────────
    if ($postAction === 'test_smtp') {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")
                    ->fetchAll(\PDO::FETCH_KEY_PAIR);
        $to = trim($rows['notify_it_email'] ?? '');
        if (empty($to)) {
            flash('error', 'Set an IT Security Team email address before testing.');
        } else {
            require_once __DIR__ . '/../../modules/notifications/mailer.php';
            $body = "
                <p>This is a test message from CIRMS System Settings.</p>
                <table class='info-table'>
                    <tr><td>Sent At:</td><td>" . date('d M Y, H:i:s') . "</td></tr>
                    <tr><td>SMTP Host:</td><td>" . htmlspecialchars(SMTP_HOST) . ":" . SMTP_PORT . "</td></tr>
                    <tr><td>Sender:</td><td>" . htmlspecialchars(SMTP_FROM) . "</td></tr>
                </table>
                <p>If you received this, your email configuration is working correctly.</p>
            ";
            $ok = send_email($to, 'IT Security Team', '[CIRMS] SMTP Test — Configuration Verified', $body);
            if ($ok) {
                audit_log('settings.smtp_test');
                flash('success', 'Test email sent successfully to ' . $to . '. Check your inbox.');
            } else {
                flash('error', 'Test email failed. Check your SMTP credentials in config/config.php and the PHP error log.');
            }
        }
        redirect('/public/settings/index.php?tab=email');
    }

    // ── Toggle incident category ──────────────────────────────
    if ($postAction === 'toggle_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $pdo->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?")
                ->execute([$catId]);
            audit_log('settings.category_toggled', 'category', $catId);
        }
        redirect('/public/settings/index.php?tab=categories');
    }

    // ── Save category name/description ───────────────────────
    if ($postAction === 'save_category') {
        $catId   = (int)($_POST['category_id'] ?? 0);
        $name    = trim($_POST['cat_name'] ?? '');
        $desc    = trim($_POST['cat_desc'] ?? '');
        if ($catId > 0 && $name !== '') {
            $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")
                ->execute([$name, $desc ?: null, $catId]);
            audit_log('settings.category_updated', 'category', $catId);
            flash('success', 'Category updated successfully.');
        }
        redirect('/public/settings/index.php?tab=categories');
    }

    // ── Add new category ──────────────────────────────────────
    if ($postAction === 'add_category') {
        $name = trim($_POST['new_cat_name'] ?? '');
        $desc = trim($_POST['new_cat_desc'] ?? '');
        if ($name !== '') {
            $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)")
                ->execute([$name, $desc ?: null]);
            audit_log('settings.category_added');
            flash('success', 'New category "' . $name . '" added.');
        }
        redirect('/public/settings/index.php?tab=categories');
    }

    // ── Save main settings ────────────────────────────────────
    $textKeys = [
        'sla_low_hours', 'sla_medium_hours', 'sla_high_hours', 'sla_critical_hours',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from', 'smtp_from_name',
        'notify_it_email', 'max_upload_mb', 'session_timeout',
    ];
    foreach ($textKeys as $key) {
        if (isset($_POST[$key])) {
            $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute([$key, trim($_POST[$key])]);
        }
    }
    // SMTP password — only update if provided
    if (!empty($_POST['smtp_pass'])) {
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_pass',?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([trim($_POST['smtp_pass'])]);
    }
    // Notification toggles (checkboxes — absent = 0)
    $toggleKeys = [
        'notify_on_submission', 'notify_confirmation',
        'notify_status_change', 'notify_officer_assigned', 'notify_note_added',
    ];
    foreach ($toggleKeys as $key) {
        $val = isset($_POST[$key]) ? '1' : '0';
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $val]);
    }

    audit_log('settings.updated');
    flash('success', 'Settings saved successfully.');
    $returnTab = in_array($_POST['_tab'] ?? '', ['general','email','categories','system'], true)
               ? $_POST['_tab'] : 'general';
    redirect('/public/settings/index.php?tab=' . $returnTab);
}

// ── Load data ─────────────────────────────────────────────────
$rows       = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(\PDO::FETCH_KEY_PAIR);
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$activeTab  = $_GET['tab'] ?? 'general';

// ── System status checks ──────────────────────────────────────
$phpVersion     = PHP_VERSION;
$phpOk          = version_compare($phpVersion, '8.0', '>=');
$phpMailerOk    = file_exists(__DIR__ . '/../../vendor/autoload.php');
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalIncidents = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
$openIncidents  = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN ('Resolved','Closed')")->fetchColumn();
$dbOk           = true; // we already connected above

// Defaults for notification toggles
$notifyDefaults = [
    'notify_on_submission'    => '1',
    'notify_confirmation'     => '1',
    'notify_status_change'    => '1',
    'notify_officer_assigned' => '1',
    'notify_note_added'       => '1',
];
foreach ($notifyDefaults as $k => $def) {
    if (!array_key_exists($k, $rows)) $rows[$k] = $def;
}

$pageTitle = 'System Settings';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-gear-fill me-2 text-cyan"></i>System Settings</h1>
        <p class="page-subtitle">SLA timeframes, notifications, email configuration, and incident categories</p>
    </div>
</div>

<!-- ── Tab navigation ────────────────────────────────────────── -->
<div class="mb-3">
    <nav class="d-flex gap-1 flex-wrap" style="border-bottom:2px solid var(--border);padding-bottom:0;">
        <?php foreach ([
            ['general',     'bi-sliders',        'General'],
            ['email',       'bi-envelope-fill',  'Email & Notifications'],
            ['categories',  'bi-tags-fill',      'Categories'],
            ['system',      'bi-info-circle-fill','System Info'],
        ] as [$tab, $icon, $label]): ?>
        <a href="?tab=<?= $tab ?>"
           style="display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;
                  border-radius:6px 6px 0 0;font-size:.875rem;font-weight:600;text-decoration:none;
                  color:<?= $activeTab === $tab ? 'var(--navy)' : 'var(--muted)' ?>;
                  background:<?= $activeTab === $tab ? '#fff' : 'transparent' ?>;
                  border:<?= $activeTab === $tab ? '1px solid var(--border)' : '1px solid transparent' ?>;
                  border-bottom:<?= $activeTab === $tab ? '2px solid #fff' : '1px solid transparent' ?>;
                  margin-bottom:-2px;transition:color .15s;">
            <i class="bi <?= $icon ?>"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- Tab: General (SLA + System Limits)                        -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'general'): ?>
<form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="save_settings">
    <input type="hidden" name="_tab" value="general">

    <div class="row g-3">

        <!-- ── SLA Timeframes ────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="cirms-card h-100">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-alarm me-2 text-cyan"></i>SLA Timeframes</h2>
                </div>
                <p style="font-size:.84rem;color:var(--muted);margin-bottom:1.25rem;">
                    Maximum hours allowed to resolve an incident before an SLA breach is flagged.
                    Lower values = stricter enforcement.
                </p>

                <?php foreach ([
                    ['sla_critical_hours', 'Critical', 'badge-critical', '2',  'Requires immediate response (e.g. active attack)'],
                    ['sla_high_hours',     'High',     'badge-high',     '8',  'Requires same-day response'],
                    ['sla_medium_hours',   'Medium',   'badge-medium',   '24', 'Requires next-business-day response'],
                    ['sla_low_hours',      'Low',      'badge-low',      '72', 'Requires response within 3 days'],
                ] as [$key, $label, $cls, $default, $hint]):
                    $val = $rows[$key] ?? $default;
                ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge <?= $cls ?>" style="min-width:70px;text-align:center;"><?= $label ?></span>
                        <span style="font-size:.78rem;color:var(--muted);"><?= $hint ?></span>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="number" name="<?= $key ?>" class="form-control"
                               value="<?= e($val) ?>" min="1" max="720" required
                               style="max-width:100px;">
                        <span class="input-group-text" style="background:var(--bg);border-color:var(--border);">hours</span>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Visual SLA hierarchy -->
                <div style="background:var(--bg);border-radius:8px;padding:.85rem 1rem;margin-top:1rem;font-size:.78rem;color:var(--muted);">
                    <i class="bi bi-info-circle me-1"></i>
                    Recommended hierarchy: Critical &lt; High &lt; Medium &lt; Low hours
                </div>
            </div>
        </div>

        <!-- ── System Limits ─────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-hdd-fill me-2 text-cyan"></i>System Limits</h2>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Maximum File Upload Size</label>
                    <div class="input-group">
                        <input type="number" name="max_upload_mb" class="form-control"
                               value="<?= e($rows['max_upload_mb'] ?? '10') ?>" min="1" max="50">
                        <span class="input-group-text" style="background:var(--bg);border-color:var(--border);">MB</span>
                    </div>
                    <div class="form-hint">Applies to evidence attachments on incident reports. Max 50 MB.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Session Timeout</label>
                    <div class="input-group">
                        <input type="number" name="session_timeout" class="form-control"
                               value="<?= e($rows['session_timeout'] ?? '1800') ?>" min="300" max="86400">
                        <span class="input-group-text" style="background:var(--bg);border-color:var(--border);">seconds</span>
                    </div>
                    <div class="form-hint">
                        300 = 5 min &nbsp;|&nbsp; 1800 = 30 min &nbsp;|&nbsp; 3600 = 1 hour.
                        Users are logged out after this period of inactivity.
                    </div>
                </div>

                <!-- Quick reference -->
                <div style="background:var(--bg);border-radius:8px;padding:.85rem 1rem;font-size:.8rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total users in system</span>
                        <strong><?= number_format($totalUsers) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total incidents</span>
                        <strong><?= number_format($totalIncidents) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Currently open</span>
                        <strong><?= number_format($openIncidents) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms px-4">
                <i class="bi bi-check-lg me-1"></i> Save Settings
            </button>
        </div>
    </div>
</form>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════ -->
<!-- Tab: Email & Notifications                                 -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'email'): ?>
<form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="save_settings">
    <input type="hidden" name="_tab" value="email">

    <div class="row g-3">

        <!-- ── SMTP Configuration ────────────────────────────── -->
        <div class="col-lg-7">
            <div class="cirms-card">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-envelope-fill me-2 text-cyan"></i>SMTP Configuration</h2>
                    <span style="font-size:.75rem;color:var(--muted);background:var(--bg);padding:.2rem .6rem;border-radius:4px;border:1px solid var(--border);">
                        Gmail / STARTTLS
                    </span>
                </div>

                <!-- IT notification email -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        IT Security Team Email <span class="text-danger">*</span>
                    </label>
                    <input type="email" name="notify_it_email" class="form-control"
                           value="<?= e($rows['notify_it_email'] ?? '') ?>"
                           placeholder="itsecurity@university.ac.tz">
                    <div class="form-hint">
                        New incident alerts are dispatched to this address immediately upon submission.
                    </div>
                </div>

                <hr style="border-color:var(--border);">

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
                        <select name="smtp_port" class="form-select">
                            <?php foreach (['587' => '587 (STARTTLS)', '465' => '465 (SSL)', '25' => '25 (Plain)'] as $p => $lbl): ?>
                            <option value="<?= $p ?>" <?= ($rows['smtp_port'] ?? '587') == $p ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- SMTP Username -->
                <div class="mb-3">
                    <label class="form-label">SMTP Username</label>
                    <input type="email" name="smtp_user" class="form-control"
                           value="<?= e($rows['smtp_user'] ?? '') ?>"
                           placeholder="youremail@gmail.com"
                           autocomplete="username">
                </div>

                <!-- SMTP Password -->
                <div class="mb-3">
                    <label class="form-label">SMTP App Password</label>
                    <div class="input-group">
                        <input type="password" name="smtp_pass" id="smtpPassInput"
                               class="form-control"
                               placeholder="Leave blank to keep current"
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="var i=document.getElementById('smtpPassInput');i.type=i.type==='password'?'text':'password';">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-hint">
                        Use a Gmail App Password (16 chars) — not your regular Gmail password.
                        Generate one at <strong>myaccount.google.com/apppasswords</strong>.
                    </div>
                </div>

                <!-- From Email + Name -->
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

                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms px-4 me-2">
                        <i class="bi bi-check-lg me-1"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Right column: toggles + test ─────────────────── -->
        <div class="col-lg-5 d-flex flex-column gap-3">

            <!-- Notification Toggles -->
            <div class="cirms-card flex-grow-1">
                <div class="cirms-card-header">
                    <h2 class="cirms-card-title"><i class="bi bi-bell-fill me-2 text-cyan"></i>Email Notification Events</h2>
                </div>
                <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem;">
                    Control which automated emails are sent by the system.
                </p>

                <?php $notifItems = [
                    ['notify_on_submission',    'New Incident Alert',     'Sent to IT officer when a user submits a new incident.',        'bi-envelope-plus text-danger'],
                    ['notify_confirmation',     'Submission Receipt',     'Sent to the reporter confirming their incident was received.',  'bi-check-circle text-success'],
                    ['notify_status_change',    'Status Update',          'Sent to reporter when an officer changes the incident status.', 'bi-arrow-repeat text-warning'],
                    ['notify_officer_assigned', 'Assignment Alert',       'Sent to the officer when they are assigned to an incident.',    'bi-person-badge text-cyan'],
                    ['notify_note_added',       'Public Note Posted',     'Sent to reporter when a public note is added by an officer.',   'bi-chat-left-text text-info'],
                ]; ?>

                <?php foreach ($notifItems as [$key, $title, $desc, $icon]): ?>
                <div class="d-flex align-items-start gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border);">
                    <i class="bi <?= $icon ?>" style="font-size:1.1rem;margin-top:2px;flex-shrink:0;"></i>
                    <div class="flex-grow-1">
                        <div style="font-size:.85rem;font-weight:600;color:var(--navy);"><?= $title ?></div>
                        <div style="font-size:.77rem;color:var(--muted);"><?= $desc ?></div>
                    </div>
                    <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input class="form-check-input" type="checkbox"
                               name="<?= $key ?>" id="tog_<?= $key ?>"
                               role="switch"
                               <?= ($rows[$key] ?? '1') === '1' ? 'checked' : '' ?>>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Note about config.php -->
                <div style="background:var(--bg);border-radius:6px;padding:.7rem .9rem;font-size:.78rem;color:var(--muted);">
                    <i class="bi bi-info-circle me-1"></i>
                    SMTP credentials are read from <code>config/config.php</code>. Update that file to change the sending account.
                </div>
            </div>

            <!-- Test Email -->
            <div class="cirms-card" style="border-left:3px solid #22c55e;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-send-check text-success" style="font-size:1.1rem;"></i>
                    <strong style="font-size:.9rem;color:var(--navy);">Test Email Delivery</strong>
                </div>
                <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem;">
                    Sends a test message to the IT Security Team email using the current
                    <code>config/config.php</code> SMTP credentials.
                </p>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="test_smtp">
                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-envelope-check me-1"></i> Send Test Email Now
                    </button>
                </form>
            </div>

        </div><!-- /col-5 -->
    </div><!-- /row -->
</form>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════ -->
<!-- Tab: Categories                                            -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'categories'): ?>
<div class="row g-3">

    <!-- Existing categories -->
    <div class="col-lg-8">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-tags-fill me-2 text-cyan"></i>Incident Categories</h2>
                <span style="font-size:.78rem;color:var(--muted);">
                    <?= count(array_filter($categories, fn($c) => $c['is_active'])) ?> active
                    / <?= count($categories) ?> total
                </span>
            </div>
            <p style="font-size:.83rem;color:var(--muted);margin-bottom:1rem;">
                Active categories appear in the incident report form. Deactivating a category
                hides it from new reports but preserves all existing incidents that used it.
            </p>

            <div class="table-responsive">
                <table class="cirms-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-size:.78rem;color:var(--muted);"><?= $cat['id'] ?></td>
                        <td>
                            <span style="font-size:.875rem;font-weight:600;color:<?= $cat['is_active'] ? 'var(--navy)' : 'var(--muted)' ?>;">
                                <?= e($cat['name']) ?>
                            </span>
                        </td>
                        <td style="font-size:.8rem;color:var(--muted);max-width:260px;">
                            <?= $cat['description'] ? e(mb_strimwidth($cat['description'], 0, 80, '…')) : '<span style="opacity:.45;">—</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($cat['is_active']): ?>
                            <span class="badge" style="background:rgba(34,197,94,.1);color:#16a34a;border:1px solid rgba(34,197,94,.25);">
                                <i class="bi bi-check-circle-fill me-1"></i>Active
                            </span>
                            <?php else: ?>
                            <span class="badge" style="background:rgba(148,163,184,.1);color:#64748b;border:1px solid rgba(148,163,184,.3);">
                                <i class="bi bi-dash-circle me-1"></i>Inactive
                            </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <!-- Toggle active -->
                            <form method="POST" action="" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_action" value="toggle_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $cat['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                        title="<?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi <?= $cat['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                </button>
                            </form>
                            <!-- Edit -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary ms-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editCatModal"
                                    data-id="<?= $cat['id'] ?>"
                                    data-name="<?= e($cat['name']) ?>"
                                    data-desc="<?= e($cat['description'] ?? '') ?>"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add new category -->
    <div class="col-lg-4">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-plus-circle-fill me-2 text-cyan"></i>Add Category</h2>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="add_category">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="new_cat_name" class="form-control"
                           placeholder="e.g. Insider Threat" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="new_cat_desc" class="form-control" rows="3"
                              placeholder="Brief description of incident type…"
                              maxlength="500"></textarea>
                </div>
                <button type="submit" class="btn btn-dark btn-cirms btn-primary-cirms w-100">
                    <i class="bi bi-plus-lg me-1"></i> Add Category
                </button>
            </form>
        </div>

        <!-- Usage note -->
        <div class="cirms-card mt-0" style="border-left:3px solid var(--medium);">
            <div class="d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill" style="color:var(--medium);flex-shrink:0;margin-top:2px;"></i>
                <div style="font-size:.82rem;color:#92400e;">
                    <strong>Do not delete categories</strong> that have existing incidents
                    associated with them — deactivate them instead to preserve data integrity.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit category modal -->
<div class="modal fade" id="editCatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;border:1px solid var(--border);">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="save_category">
                <input type="hidden" name="category_id" id="editCatId">
                <div class="modal-header" style="border-bottom:1px solid var(--border);">
                    <h5 class="modal-title" style="font-family:'Space Mono',monospace;font-size:.95rem;color:var(--navy);">
                        <i class="bi bi-pencil-fill me-2 text-cyan"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:1.25rem;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="cat_name" id="editCatName" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="cat_desc" id="editCatDesc" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-dark">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('editCatModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            document.getElementById('editCatId').value   = btn.getAttribute('data-id');
            document.getElementById('editCatName').value  = btn.getAttribute('data-name');
            document.getElementById('editCatDesc').value  = btn.getAttribute('data-desc');
        });
    }
});
</script>
<?php endif; ?>


<!-- ══════════════════════════════════════════════════════════ -->
<!-- Tab: System Info                                           -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'system'): ?>
<div class="row g-3">

    <!-- System Status -->
    <div class="col-lg-6">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-activity me-2 text-cyan"></i>System Status</h2>
            </div>

            <?php
            $checks = [
                ['PHP Version', $phpVersion, $phpOk, $phpOk ? 'PHP 8.0+ — supported' : 'PHP 7.x — upgrade recommended'],
                ['Database',    'MariaDB / MySQL', $dbOk, 'Connected successfully'],
                ['PHPMailer',   $phpMailerOk ? 'Installed' : 'Not installed', $phpMailerOk, $phpMailerOk ? 'vendor/autoload.php found' : 'Run: composer require phpmailer/phpmailer'],
                ['App Version', APP_VERSION,  true,  'CIRMS v' . APP_VERSION],
                ['Environment', APP_ENV,      APP_ENV !== 'development', APP_ENV === 'production' ? 'Production mode' : 'Switch to production before going live'],
                ['Timezone',    TIMEZONE,     true,  date('d M Y, H:i:s')],
            ];
            ?>

            <table class="cirms-table">
                <tbody>
                <?php foreach ($checks as [$label, $value, $ok, $hint]): ?>
                <tr>
                    <td style="font-weight:600;font-size:.85rem;color:var(--muted);width:38%;"><?= $label ?></td>
                    <td style="font-size:.85rem;">
                        <span style="color:<?= $ok ? '#16a34a' : '#b91c1c' ?>;">
                            <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                            <?= e($value) ?>
                        </span>
                    </td>
                    <td style="font-size:.77rem;color:var(--muted);"><?= $hint ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database Summary -->
    <div class="col-lg-6">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-database-fill me-2 text-cyan"></i>Database Summary</h2>
            </div>

            <?php
            $dbName      = DB_NAME;
            $resolvedCnt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Resolved','Closed')")->fetchColumn();
            $critOpenCnt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE severity='Critical' AND status NOT IN ('Resolved','Closed')")->fetchColumn();
            $auditCnt    = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
            $notifCnt    = $pdo->query("SELECT COUNT(*) FROM notifications WHERE status='pending'")->fetchColumn();
            $activeCats  = count(array_filter($categories, fn($c) => $c['is_active']));
            ?>

            <table class="cirms-table">
                <tbody>
                <?php foreach ([
                    ['Database Name',        $dbName,                         'bi-server'],
                    ['Total Incidents',       number_format($totalIncidents),  'bi-collection-fill'],
                    ['Open Incidents',        number_format($openIncidents),   'bi-hourglass-split'],
                    ['Critical & Open',       number_format($critOpenCnt),     'bi-exclamation-octagon-fill'],
                    ['Resolved / Closed',     number_format($resolvedCnt),     'bi-check2-circle'],
                    ['Total Users',           number_format($totalUsers),      'bi-people-fill'],
                    ['Active Categories',     $activeCats . ' / ' . count($categories), 'bi-tags-fill'],
                    ['Pending Notifications', number_format($notifCnt),        'bi-bell-fill'],
                    ['Audit Log Entries',     number_format($auditCnt),        'bi-journal-text'],
                ] as [$label, $value, $icon]): ?>
                <tr>
                    <td style="font-size:.85rem;color:var(--muted);font-weight:600;width:55%;">
                        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                    </td>
                    <td style="font-size:.875rem;font-weight:700;color:var(--navy);"><?= e($value) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Current Settings Snapshot -->
    <div class="col-12">
        <div class="cirms-card">
            <div class="cirms-card-header">
                <h2 class="cirms-card-title"><i class="bi bi-sliders me-2 text-cyan"></i>Active Configuration Snapshot</h2>
            </div>
            <div class="row g-3" style="font-size:.84rem;">
                <div class="col-md-3">
                    <div style="font-weight:700;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">SLA Hours</div>
                    <?php foreach (['Critical','High','Medium','Low'] as $sev): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="badge <?= severity_class($sev) ?>"><?= $sev ?></span>
                        <span><?= e($rows['sla_' . strtolower($sev) . '_hours'] ?? '—') ?>h</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-3">
                    <div style="font-weight:700;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">Email</div>
                    <?php foreach ([
                        ['SMTP Host',  $rows['smtp_host']  ?? '—'],
                        ['Port',       $rows['smtp_port']  ?? '—'],
                        ['From',       $rows['smtp_from']  ?? '—'],
                        ['Notify To',  $rows['notify_it_email'] ?? '—'],
                    ] as [$l, $v]): ?>
                    <div class="d-flex justify-content-between mb-1 gap-2">
                        <span class="text-muted"><?= $l ?></span>
                        <span style="color:var(--navy);font-weight:600;word-break:break-all;text-align:right;"><?= e($v) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-3">
                    <div style="font-weight:700;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">Notifications On</div>
                    <?php foreach ([
                        ['New Incident Alert',  'notify_on_submission'],
                        ['Submission Receipt',  'notify_confirmation'],
                        ['Status Update',       'notify_status_change'],
                        ['Assignment Alert',    'notify_officer_assigned'],
                        ['Note Posted',         'notify_note_added'],
                    ] as [$l, $k]): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted"><?= $l ?></span>
                        <?php if (($rows[$k] ?? '1') === '1'): ?>
                        <span style="color:#16a34a;font-size:.75rem;"><i class="bi bi-check-circle-fill"></i> On</span>
                        <?php else: ?>
                        <span style="color:#64748b;font-size:.75rem;"><i class="bi bi-dash-circle"></i> Off</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-3">
                    <div style="font-weight:700;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">Limits</div>
                    <?php foreach ([
                        ['Upload max',        ($rows['max_upload_mb'] ?? '10') . ' MB'],
                        ['Session timeout',   ($rows['session_timeout'] ?? '1800') . 's'],
                        ['PHP version',       PHP_VERSION],
                        ['PHPMailer',         $phpMailerOk ? 'Installed' : 'Missing'],
                    ] as [$l, $v]): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted"><?= $l ?></span>
                        <span style="color:var(--navy);font-weight:600;"><?= e($v) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
