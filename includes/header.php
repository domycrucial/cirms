<?php
// ============================================================
// CIRMS - Shared Page Header
// includes/header.php
//
// Included at the top of every authenticated page.
// Sidebar layout (top → bottom):
//   1. Brand / logo row        (fixed, always visible)
//   2. Navigation links        (scrollable — starts immediately)
//   3. User info + Logout      (pinned to sidebar bottom)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start_secure();
}

// Emit HTTP security headers exactly once per request
if (!defined('_CIRMS_HEADERS_SENT')) {
    define('_CIRMS_HEADERS_SENT', true);
    send_security_headers();
}

$user  = current_user();
$flash = get_flash();
$role  = $user['role'] ?? '';

// Helper: mark the active sidebar link
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isActive   = static function (string $path) use ($scriptPath): string {
    return str_contains($scriptPath, $path) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?= APP_URL ?>/public/assets/images/iaa.png">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'IRS') ?> – IRS</title>
    <?php require __DIR__ . '/head_assets.php'; ?>
    <style>
/* ── Sidebar layout ──────────────────────────────────────────
   Three-zone flex column inside a fixed-height sidebar:
     Zone 1 (.sb-brand)  – logo row, never scrolls, always on top
     Zone 2 (.sb-nav)    – nav links, scrolls when content overflows
     Zone 3 (.sb-foot)   – user chip + logout, always on bottom
   ----------------------------------------------------------- */
:root { --sb-w: 252px; --sb-w-min: 58px; }

.cirms-shell      { display:flex; min-height:100vh; }
.cirms-content-wrap { flex:1; min-width:0; display:flex; flex-direction:column; }

/* ── Sidebar base ─────────────────────────────────────── */
.cirms-sidebar {
  width: var(--sb-w);
  min-width: var(--sb-w);
  flex-shrink: 0;
  background: #0d1b2a;
  border-right: 1px solid rgba(255,255,255,.07);
  box-shadow: 3px 0 20px rgba(0,0,0,.25);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow: hidden;
  transition: width .2s ease, min-width .2s ease;
  z-index: 1040;
}

/* ── Zone 1: Brand ─────────────────────────────────────── */
.sb-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  padding: .9rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.07);
  flex-shrink: 0;
}
.sb-brand-link {
  display: flex;
  align-items: center;
  gap: .55rem;
  text-decoration: none;
  color: #fff;
  font-family: 'Space Mono', monospace;
  font-size: 1.1rem;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  min-width: 0;
}
.sb-brand-img {
  width: 38px; height: 38px;
  border-radius: 4px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 0 6px rgba(0,212,255,.25));
}
.sb-brand-text { overflow:hidden; text-overflow:ellipsis; }

/* Collapse toggle (desktop only) */
.sb-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px; height: 28px;
  border: 1px solid rgba(255,255,255,.13);
  background: rgba(255,255,255,.05);
  color: #94a3b8;
  border-radius: 6px;
  cursor: pointer;
  flex-shrink: 0;
  font-size: .85rem;
  transition: background .14s, color .14s;
}
.sb-toggle:hover { background: rgba(0,212,255,.12); color: #00d4ff; border-color: rgba(0,212,255,.3); }

/* ── Zone 2: Scrollable nav ────────────────────────────── */
.sb-nav {
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: hidden;
  padding: .6rem .6rem;
  display: flex;
  flex-direction: column;
  gap: .15rem;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.1) transparent;
}
.sb-nav::-webkit-scrollbar       { width: 3px; }
.sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 3px; }

/* Nav section labels */
.sb-section {
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: #475569;
  padding: .65rem .5rem .25rem;
  white-space: nowrap;
}

/* Nav links */
.sb-link {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .52rem .65rem;
  border-radius: 7px;
  color: #94a3b8;
  font-size: .875rem;
  font-weight: 500;
  text-decoration: none;
  white-space: nowrap;
  transition: background .13s, color .13s;
  flex-shrink: 0;
}
.sb-link i        { font-size: .95rem; flex-shrink: 0; width: 1rem; text-align: center; }
.sb-link span     { overflow: hidden; text-overflow: ellipsis; }
.sb-link:hover    { background: rgba(255,255,255,.07); color: #e2e8f0; }
.sb-link.active   {
  background: rgba(0,212,255,.13);
  color: #00d4ff;
  font-weight: 600;
  border-left: 2px solid #00d4ff;
  padding-left: calc(.65rem - 2px);
}
.sb-link.active i { color: #00d4ff; }

/* ── Zone 3: User + Logout (pinned bottom) ──────────────── */
.sb-foot {
  flex-shrink: 0;
  border-top: 1px solid rgba(255,255,255,.07);
}
.sb-user {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .7rem 1rem;
  overflow: hidden;
}
.sb-user-icon {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: rgba(0,212,255,.12);
  border: 1.5px solid rgba(0,212,255,.25);
  display: flex; align-items: center; justify-content: center;
  font-size: .85rem; color: #00d4ff;
  flex-shrink: 0;
}
.sb-user-info  { min-width: 0; }
.sb-user-name  {
  font-size: .78rem; font-weight: 600; color: #cbd5e1;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sb-user-role  {
  display: inline-block;
  font-size: .62rem; font-weight: 700; font-family: 'Space Mono', monospace;
  letter-spacing: .06em; text-transform: uppercase;
  padding: .1rem .4rem; border-radius: 4px; margin-top: .1rem;
}
.sb-user-role.role-admin    { background: rgba(239,68,68,.2);  color: #fca5a5; }
.sb-user-role.role-officer  { background: rgba(0,212,255,.15); color: #00d4ff; }
.sb-user-role.role-reporter { background: rgba(99,102,241,.2); color: #a5b4fc; }

.sb-logout {
  display: flex;
  align-items: center;
  gap: .6rem;
  padding: .65rem 1rem;
  color: #64748b;
  font-size: .85rem;
  font-weight: 500;
  text-decoration: none;
  border-top: 1px solid rgba(255,255,255,.05);
  transition: background .13s, color .13s;
  white-space: nowrap;
}
.sb-logout:hover { background: rgba(239,68,68,.09); color: #fca5a5; }
.sb-logout i     { font-size: .95rem; flex-shrink: 0; }

/* ── Desktop: icon-only collapsed sidebar ───────────────── */
.sidebar-collapsed .cirms-sidebar {
  width: var(--sb-w-min);
  min-width: var(--sb-w-min);
}
.sidebar-collapsed .sb-brand-text,
.sidebar-collapsed .sb-section,
.sidebar-collapsed .sb-link span,
.sidebar-collapsed .sb-user-info,
.sidebar-collapsed .sb-logout span {
  display: none;
}
.sidebar-collapsed .sb-brand        { justify-content: center; padding: .9rem .5rem; }
.sidebar-collapsed .sb-link         { justify-content: center; padding: .52rem; border-left: none !important; padding-left: .52rem !important; }
.sidebar-collapsed .sb-link.active  { border-left: 2px solid #00d4ff; padding-left: calc(.52rem - 2px) !important; }
.sidebar-collapsed .sb-user         { justify-content: center; padding: .7rem .5rem; }
.sidebar-collapsed .sb-logout       { justify-content: center; padding: .65rem .5rem; }
.sidebar-collapsed .sb-nav          { padding: .6rem .4rem; }
.sidebar-collapsed .sb-toggle       { display: inline-flex; }

/* ── Mobile topbar ──────────────────────────────────────── */
.content-topbar {
  display: none;
  align-items: center;
  gap: .7rem;
  padding: .65rem 1rem;
  background: #0d1b2a;
  border-bottom: 1px solid rgba(255,255,255,.07);
  flex-shrink: 0;
}
.content-topbar-title { font-size: .9rem; font-weight: 600; color: #e2e8f0; }
.topbar-toggle {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.05);
  color: #94a3b8;
  border-radius: 7px; cursor: pointer; font-size: 1.1rem;
}
.topbar-toggle:hover { background: rgba(0,212,255,.1); color: #00d4ff; }

/* ── Mobile: sidebar as off-canvas drawer ───────────────── */
.sidebar-backdrop {
  display: none;
  position: fixed; inset: 0;
  background: rgba(2,6,23,.55);
  z-index: 1035;
}

@media (max-width: 768px) {
  .content-topbar { display: flex; }
  .cirms-sidebar {
    position: fixed; left: 0; top: 0;
    width: var(--sb-w) !important;
    min-width: var(--sb-w) !important;
    transform: translateX(-100%);
    transition: transform .25s ease;
  }
  /* Restore all text in mobile drawer */
  .cirms-sidebar .sb-brand-text,
  .cirms-sidebar .sb-section,
  .cirms-sidebar .sb-link span,
  .cirms-sidebar .sb-user-info,
  .cirms-sidebar .sb-logout span  { display: block !important; }
  .cirms-sidebar .sb-brand        { justify-content: space-between !important; padding: .9rem 1rem !important; }
  .cirms-sidebar .sb-link         { justify-content: flex-start !important; }
  .cirms-sidebar .sb-user         { justify-content: flex-start !important; padding: .7rem 1rem !important; }
  .cirms-sidebar .sb-logout       { justify-content: flex-start !important; padding: .65rem 1rem !important; }
  .sidebar-open .cirms-sidebar    { transform: translateX(0); }
  .sidebar-open .sidebar-backdrop { display: block; }
  .sb-toggle                      { display: none !important; }
}
    </style>
</head>
<body>

<div class="cirms-shell" id="appShell">

<?php if ($user): ?>
<!-- ══════════════════════════════════════════════════════════
     SIDEBAR
     Zone 1: Brand  |  Zone 2: Nav (scrolls)  |  Zone 3: User+Logout
══════════════════════════════════════════════════════════ -->
<aside class="cirms-sidebar" role="navigation" aria-label="Main navigation">

    <!-- Zone 1 – Brand row -->
    <div class="sb-brand">
        <a href="<?= APP_URL ?>/public/dashboard.php" class="sb-brand-link" title="Dashboard">
            <img src="<?= APP_URL ?>/public/assets/images/iaa.png"
                 alt="IRS" class="sb-brand-img">
            <span class="sb-brand-text">IRS</span>
        </a>
        <button class="sb-toggle" data-sidebar-toggle
                aria-label="Collapse sidebar" title="Collapse sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
    </div>

    <!-- Zone 2 – Scrollable navigation (first item = Dashboard) -->
    <nav class="sb-nav" id="sidebarNav">

        <a class="sb-link <?= $isActive('/public/dashboard.php') ?>"
           href="<?= APP_URL ?>/public/dashboard.php">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>

        <?php if ($role === 'reporter'): ?>
        <a class="sb-link <?= $isActive('/public/incidents/report.php') ?>"
           href="<?= APP_URL ?>/public/incidents/report.php">
            <i class="bi bi-plus-circle-fill"></i><span>Report Incident</span>
        </a>
        <a class="sb-link <?= $isActive('/public/incidents/my-reports.php') ?>"
           href="<?= APP_URL ?>/public/incidents/my-reports.php">
            <i class="bi bi-file-earmark-text"></i><span>My Reports</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($role, ['officer','admin'], true)): ?>
        <a class="sb-link <?= $isActive('/public/incidents/list.php') ?>"
           href="<?= APP_URL ?>/public/incidents/list.php">
            <i class="bi bi-list-ul"></i><span>Incidents</span>
        </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="sb-section">Analytics</div>
        <a class="sb-link <?= $isActive('/public/analytics/overview.php') ?>"
           href="<?= APP_URL ?>/public/analytics/overview.php">
            <i class="bi bi-bar-chart-fill"></i><span>Overview</span>
        </a>
        <a class="sb-link <?= $isActive('/public/analytics/trends.php') ?>"
           href="<?= APP_URL ?>/public/analytics/trends.php">
            <i class="bi bi-graph-up-arrow"></i><span>Trend Reports</span>
        </a>
        <a class="sb-link <?= $isActive('/public/analytics/export.php') ?>"
           href="<?= APP_URL ?>/public/analytics/export.php">
            <i class="bi bi-download"></i><span>Export Data</span>
        </a>

        <div class="sb-section">Admin</div>
        <a class="sb-link <?= $isActive('/public/users/list.php') ?>"
           href="<?= APP_URL ?>/public/users/list.php">
            <i class="bi bi-people-fill"></i><span>Manage Users</span>
        </a>
        <a class="sb-link <?= $isActive('/public/audit/log.php') ?>"
           href="<?= APP_URL ?>/public/audit/log.php">
            <i class="bi bi-clipboard-check"></i><span>Audit Log</span>
        </a>
        <a class="sb-link <?= $isActive('/public/settings/index.php') ?>"
           href="<?= APP_URL ?>/public/settings/index.php">
            <i class="bi bi-gear-fill"></i><span>System Settings</span>
        </a>
        <?php endif; ?>

    </nav>

    <!-- Zone 3 – User info + Logout pinned at bottom -->
    <div class="sb-foot">
        <div class="sb-user">
            <div class="sb-user-icon"><i class="bi bi-person-fill"></i></div>
            <div class="sb-user-info">
                <div class="sb-user-name" title="<?= e($user['name']) ?>">
                    <?= e($user['name']) ?>
                </div>
                <span class="sb-user-role role-<?= e($role) ?>">
                    <?= e(ucfirst($role)) ?>
                </span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/public/auth/logout.php" class="sb-logout">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </div>

</aside>

<!-- Overlay backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
<?php endif; ?>

<!-- ── Main content column ─────────────────────────────────── -->
<div class="cirms-content-wrap">

    <?php if ($user): ?>
    <!-- Mobile top bar -->
    <div class="content-topbar">
        <button class="topbar-toggle" data-sidebar-toggle aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="content-topbar-title"><?= e($pageTitle ?? 'IRS') ?></span>
    </div>
    <?php endif; ?>

    <!-- Flash messages -->
    <?php if ($flash): ?>
    <div style="padding:.85rem 1.5rem 0;">
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> alert-dismissible fade show mb-0" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page content injected here -->
    <main class="cirms-main">
