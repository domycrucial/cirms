<?php
// ============================================================
// CIRMS – Logout
// public/auth/logout.php
// ============================================================

require_once __DIR__ . '/../../includes/functions.php';
session_start_secure();

if (is_logged_in()) {
    audit_log('auth.logout', 'user', $_SESSION['user_id']);
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: ' . APP_URL . '/public/login.php');
exit;
