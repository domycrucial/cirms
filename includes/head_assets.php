<?php
/**
 * Shared stylesheets for CIRMS (Bootstrap + icons + custom theme).
 * Include this inside <head> after <meta> / <title> so the browser can fetch CSS in order:
 *   1) fonts  2) Bootstrap grid/components  3) icons  4) cirms.css overrides & theme
 *
 * Expects: functions.php already loaded (for asset_url + e).
 */
?>
    <!-- Fonts: DM Sans (UI) + Space Mono (headings) — preconnect speeds first paint -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5: layout, forms, alerts (our theme layers on top in cirms.css) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (navbar + forms) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- CIRMS theme: colours, navbar, cards, auth page — always same path as APP_URL -->
    <link href="<?= e(asset_url('public/css/cirms.css')) ?>" rel="stylesheet">
