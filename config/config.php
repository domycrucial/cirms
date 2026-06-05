<?php
// ============================================================
// CIRMS - Application Configuration
// config/config.php
// ============================================================

// ----- Database -----
$dbSettings = [
    'host'    => 'localhost',
    'name'    => 'cirms_db',          // <-- change to your DB name if different
    'user'    => 'root',
    'pass'    => '',                // <-- blank = XAMPP default
    'charset' => 'utf8mb4',
];
$__cirms_local = __DIR__ . '/config.local.php';
$__local       = [];
if (is_file($__cirms_local)) {
    $loaded = require $__cirms_local;
    if (is_array($loaded)) {
        $__local    = $loaded;
        $dbKeys     = ['host', 'name', 'user', 'pass', 'charset'];
        $dbSettings = array_replace($dbSettings, array_intersect_key($loaded, array_flip($dbKeys)));
    }
}

$__pwCol = $__local['users_password_column'] ?? 'password';
$__pwCol = is_string($__pwCol) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $__pwCol) ? $__pwCol : 'password';
define('USERS_PASSWORD_COLUMN', $__pwCol);

define('DB_HOST',    $dbSettings['host']);
define('DB_NAME',    $dbSettings['name']);
define('DB_USER',    $dbSettings['user']);
define('DB_PASS',    $dbSettings['pass']);
define('DB_CHARSET', $dbSettings['charset']);

// ----- App -----
define('APP_NAME',      'IRS');
define('APP_FULL_NAME', 'Institute of Accountancy Arusha Reporting System');

$app_url_manual = ''; // Set to 'http://localhost/cirmsv2' if auto-detect fails
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
define('TIMEZONE',    'Africa/Dar_es_Salaam');

// ----- Security -----
define('BCRYPT_COST',       12);
define('SESSION_LIFETIME',  1800);
define('CSRF_TOKEN_LENGTH', 32);

// ----- File Uploads -----
define('UPLOAD_DIR',          __DIR__ . '/../storage/uploads/');
define('UPLOAD_MAX_MB',       10);
define('UPLOAD_ALLOWED_TYPES', [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf', 'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// ============================================================
// ----- Email / SMTP (PHPMailer) -----
//
// HOW TO CONFIGURE FOR GMAIL (step by step):
//
//   STEP 1 — Enable 2-Step Verification on your Google account:
//            Go to: https://myaccount.google.com/security
//            Click "2-Step Verification" and follow the steps.
//
//   STEP 2 — Generate a Gmail App Password:
//            Go to: https://myaccount.google.com/apppasswords
//            Select App: Mail, Device: Other → type "CIRMS"
//            Click Generate → copy the 16-character code shown.    rugg vgxp edkq lqjf
//            Example: "abcd efgh ijkl mnop"
//
//   STEP 3 — Fill in the values below:
//            SMTP_USER = your full Gmail address
//            SMTP_PASS = the 16-character App Password (with spaces is fine)
//            SMTP_FROM = same as your Gmail address
//            NOTIFY_IT_EMAIL = the IT officer email that receives new incident alerts
//
//   STEP 4 — Install PHPMailer:
//            Open PowerShell in your project folder and run:
//            composer require phpmailer/phpmailer
//
//   STEP 5 — Test it:
//            In PowerShell: php modules/notifications/test_mail.php
// ============================================================

define('SMTP_HOST',      'smtp.gmail.com');     // Gmail SMTP server — do not change
define('SMTP_PORT',      587);                  // 587 = STARTTLS (correct for Gmail)
define('SMTP_USER',      'chuwadominic52@gmail.com');// <-- YOUR Gmail address here
define('SMTP_PASS',      'rugg vgxp edkq lqjf');// <-- YOUR 16-char App Password here
define('SMTP_FROM',      'chuwadominic52@gmail.com');// <-- same as SMTP_USER for Gmail
define('SMTP_FROM_NAME', 'IRS Notifications');
define('NOTIFY_IT_EMAIL','godsplancharity255@gmail.com'); // <-- IT officer/team email

// ----- SLA (hours by severity) -----
define('SLA_HOURS', [
    'Low'      => 72,
    'Medium'   => 24,
    'High'     => 8,
    'Critical' => 2,
]);

// ----- Environment -----
// Override to 'production' via config.local.php: return ['app_env' => 'production'];
define('APP_ENV', $__local['app_env'] ?? 'development');

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

date_default_timezone_set(TIMEZONE);
