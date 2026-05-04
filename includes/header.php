<?php
// ============================================================
// CIRMS - Header Partial
// includes/header.php
//
// Usage: include this at the top of every page.
// Variables expected:
//   $pageTitle  (string)  – shown in <title> and <h1>
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start_secure();
}
$user  = current_user();
$flash = get_flash();
$role  = $user['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'CIRMS') ?> – CIRMS</title>

    <?php require __DIR__ . '/head_assets.php'; ?>
</head>
<body>
<?php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isActive = static function (string $path) use ($scriptPath): string {
    return str_contains($scriptPath, $path) ? 'active' : '';
};
?>

<div class="cirms-shell">
    <?php if ($user): ?>
    <aside class="cirms-sidebar">
        <button type="button" class="sidebar-toggle sidebar-toggle-desktop" data-sidebar-toggle aria-label="Minimize sidebar" title="Minimize sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <a class="cirms-brand" href="<?= APP_URL ?>/public/dashboard.php">
            <span class="brand-icon"><i class="bi bi-shield-lock-fill"></i></span>
            <span class="brand-text">CIRMS</span>
        </a>

        <div class="sidebar-user">
            <span class="user-badge">
                <i class="bi bi-person-circle"></i>
                <?= e($user['name']) ?>
            </span>
            <span class="role-chip role-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span>
        </div>

        <nav class="sidebar-nav">
            <a class="sidebar-link <?= $isActive('/public/dashboard.php') ?>" href="<?= APP_URL ?>/public/dashboard.php">
                <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
            </a>

            <?php if ($role === 'reporter'): ?>
            <a class="sidebar-link <?= $isActive('/public/incidents/report.php') ?>" href="<?= APP_URL ?>/public/incidents/report.php">
                <i class="bi bi-plus-circle-fill"></i><span>Report Incident</span>
            </a>
            <a class="sidebar-link <?= $isActive('/public/incidents/my-reports.php') ?>" href="<?= APP_URL ?>/public/incidents/my-reports.php">
                <i class="bi bi-file-earmark-text"></i><span>My Reports</span>
            </a>
            <?php endif; ?>

            <?php if (in_array($role, ['officer', 'admin'], true)): ?>
            <a class="sidebar-link <?= $isActive('/public/incidents/list.php') ?>" href="<?= APP_URL ?>/public/incidents/list.php">
                <i class="bi bi-list-ul"></i><span>Incidents</span>
            </a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
            <div class="sidebar-section-title">Analytics</div>
            <a class="sidebar-link <?= $isActive('/public/analytics/overview.php') ?>" href="<?= APP_URL ?>/public/analytics/overview.php">
                <i class="bi bi-bar-chart-fill"></i><span>Overview</span>
            </a>
            <a class="sidebar-link <?= $isActive('/public/analytics/trends.php') ?>" href="<?= APP_URL ?>/public/analytics/trends.php">
                <i class="bi bi-graph-up-arrow"></i><span>Trend Reports</span>
            </a>
            <a class="sidebar-link <?= $isActive('/public/analytics/export.php') ?>" href="<?= APP_URL ?>/public/analytics/export.php">
                <i class="bi bi-download"></i><span>Export Data</span>
            </a>

            <div class="sidebar-section-title">Admin</div>
            <a class="sidebar-link <?= $isActive('/public/users/list.php') ?>" href="<?= APP_URL ?>/public/users/list.php">
                <i class="bi bi-people-fill"></i><span>Manage Users</span>
            </a>
            <a class="sidebar-link <?= $isActive('/public/audit/log.php') ?>" href="<?= APP_URL ?>/public/audit/log.php">
                <i class="bi bi-clipboard-check"></i><span>Audit Log</span>
            </a>
            <a class="sidebar-link <?= $isActive('/public/settings/index.php') ?>" href="<?= APP_URL ?>/public/settings/index.php">
                <i class="bi bi-gear-fill"></i><span>System Settings</span>
            </a>
            <?php endif; ?>
        </nav>

        <a href="<?= APP_URL ?>/public/auth/logout.php" class="btn btn-outline-danger btn-sm sidebar-logout">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </aside>
    <button type="button" class="sidebar-backdrop" data-sidebar-close aria-label="Close sidebar"></button>
    <?php endif; ?>

    <div class="cirms-content-wrap">
        <?php if ($user): ?>
        <div class="content-topbar">
            <button type="button" class="sidebar-toggle sidebar-toggle-mobile" data-sidebar-toggle aria-label="Open navigation menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="content-topbar-title"><?= e($pageTitle ?? 'CIRMS') ?></div>
        </div>
        <?php endif; ?>

<!-- ── Flash Messages ───────────────────────────────────── -->
<?php if ($flash): ?>
<div class="alert-wrapper px-4 pt-3">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- ── Page Content ─────────────────────────────────────── -->
<main class="cirms-main">
