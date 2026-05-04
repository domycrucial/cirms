<?php
// ============================================================
// CIRMS - Application Configuration
// config/config.php
// ============================================================

// ----- Database -----
// Defaults match a typical XAMPP install: MySQL user "root", empty password.
// Production: use a dedicated MySQL user (see database/grants_cirms_user.sql).
// Optional overrides: copy config/config.local.example.php → config/config.local.php
$dbSettings = [
    'host'    => 'localhost',
    'name'    => 'schema',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];
$__cirms_local = __DIR__ . '/config.local.php';
$__local       = [];
if (is_file($__cirms_local)) {
    $loaded = require $__cirms_local;
    if (is_array($loaded)) {
        $__local      = $loaded;
        $dbKeys       = ['host', 'name', 'user', 'pass', 'charset'];
        $dbSettings   = array_replace($dbSettings, array_intersect_key($loaded, array_flip($dbKeys)));
    }
}

// MySQL column on `users` that stores the bcrypt hash (must match your table; CIRMS schema uses `password`).
$__pwCol = $__local['users_password_column'] ?? 'password';
$__pwCol = is_string($__pwCol) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $__pwCol) ? $__pwCol : 'password';
define('USERS_PASSWORD_COLUMN', $__pwCol);

define('DB_HOST', $dbSettings['host']);
define('DB_NAME', $dbSettings['name']);
define('DB_USER', $dbSettings['user']);
define('DB_PASS', $dbSettings['pass']);
define('DB_CHARSET', $dbSettings['charset']);

// ----- App -----
define('APP_NAME', 'CIRMS');
define('APP_FULL_NAME', 'Campus Cyber Incident Reporting & Management System');

/**
 * Base URL with no trailing slash: used for redirects, <link>, and <script src>.
 *
 * - Production: set $app_url_manual below to your full origin (e.g. https://cirms.example.edu).
 * - Local / subfolders: leave $app_url_manual empty so the app derives /your-folder from the
 *   request path (/your-folder/public/...). That way CSS/JS URLs match the real Apache path.
 */
$app_url_manual = ''; // e.g. 'https://cirms.example.edu' — non-empty disables auto-detect
if ($app_url_manual !== '') {
    define('APP_URL', rtrim($app_url_manual, '/'));
} else {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $sn    = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $path  = '';
    if ($sn !== '' && preg_match('#^(.*)/public/#', $sn, $m)) {
        $path = $m[1];
    }
    define('APP_URL', ($https ? 'https' : 'http') . '://' . $host . $path);
}

define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Africa/Dar_es_Salaam');

// ----- Security -----
define('BCRYPT_COST', 12);
define('SESSION_LIFETIME', 1800);                  // 30 minutes
define('CSRF_TOKEN_LENGTH', 32);

// ----- File Uploads -----
define('UPLOAD_DIR', __DIR__ . '/../storage/uploads/');  // outside web root
define('UPLOAD_MAX_MB', 10);
define('UPLOAD_ALLOWED_TYPES', [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// ----- Email (PHPMailer) -----
define('SMTP_HOST', 'smtp.university.ac');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@university.ac');
define('SMTP_FROM_NAME', 'CIRMS Notifications');
define('NOTIFY_IT_EMAIL', 'itsec@university.ac');

// ----- SLA (hours by severity) -----
define('SLA_HOURS', [
    'Low'      => 72,
    'Medium'   => 24,
    'High'     => 8,
    'Critical' => 2,
]);

// ----- Environment -----
define('APP_ENV', 'development');    // 'production' in live deployment

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

date_default_timezone_set(TIMEZONE);
