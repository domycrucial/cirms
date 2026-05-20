<?php
// ============================================================
// CIRMS - Core Helper Functions
// includes/functions.php
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Absolute URL to a file under the app (e.g. CSS/JS). Keeps one place for path rules.
 *
 * @param string $path Path after the site root, e.g. 'public/css/cirms.css' (leading slash optional)
 */
function asset_url(string $path): string
{
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    return APP_URL . $path;
}

/**
 * Actual column name on `users` for the bcrypt hash: uses USERS_PASSWORD_COLUMN if that column
 * exists; otherwise picks the first known alias (password, passwd, …) from information_schema.
 * Result is cached for the request.
 */
function users_password_column_name(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $preferred = USERS_PASSWORD_COLUMN;
    try {
        $pdo = db();
        $stmt = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND LOWER(TABLE_NAME) = 'users'"
        );
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $byLower = [];
        foreach ($cols as $c) {
            $byLower[strtolower((string) $c)] = $c;
        }
        if ($byLower !== []) {
            $prefLower = strtolower($preferred);
            if (isset($byLower[$prefLower])) {
                $resolved = (string) $byLower[$prefLower];
                return $resolved;
            }
            foreach (['password', 'passwd', 'password_hash', 'user_password', 'pass_hash', 'hashed_password', 'pass', 'pwd'] as $cand) {
                if (isset($byLower[$cand])) {
                    $resolved = (string) $byLower[$cand];
                    return $resolved;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('users_password_column_name: ' . $e->getMessage());
    }

    $resolved = $preferred;
    return $resolved;
}

/**
 * Backtick-wrapped identifier for INSERT/UPDATE SQL.
 */
function users_password_column_sql(): string
{
    $n = str_replace('`', '', users_password_column_name());

    return '`' . $n . '`';
}

/**
 * Stored bcrypt/hash column name varies across databases. Returns a non-empty string or null.
 */
function user_password_hash_from_row(array $row): ?string
{
    $primary = users_password_column_name();
    if (isset($row[$primary]) && is_string($row[$primary]) && $row[$primary] !== '') {
        return $row[$primary];
    }

    $lower = array_change_key_case($row, CASE_LOWER);
    $lk    = strtolower($primary);
    if (isset($lower[$lk]) && is_string($lower[$lk]) && $lower[$lk] !== '') {
        return $lower[$lk];
    }

    foreach (['password', 'password_hash', 'pass_hash', 'passwd', 'pass', 'hashed_password', 'user_password', 'pwd'] as $key) {
        if (isset($lower[$key]) && is_string($lower[$key]) && $lower[$key] !== '') {
            return $lower[$key];
        }
    }

    return null;
}

// ---- Session -----------------------------------------------

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // lifetime=0 makes this a browser-session cookie (no absolute expiry).
        // Idle timeout is enforced server-side inside require_login() using
        // SESSION_LIFETIME so the timer resets on every page load (sliding window).
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (APP_ENV === 'production'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ---- Authentication ----------------------------------------

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Emit defensive HTTP security headers.
 * Call once per page, before any output.
 * Prevents clickjacking, MIME-sniffing, and leaking the referrer.
 */
function send_security_headers(): void
{
    // Deny framing completely — blocks clickjacking attacks (OWASP A05)
    header('X-Frame-Options: DENY');

    // Prevent MIME-type sniffing — stops content-type confusion attacks
    header('X-Content-Type-Options: nosniff');

    // Send only the origin (not the full URL) for cross-origin requests
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Restrict browser features not needed by this app
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Strict Transport Security — 1 year HSTS (only active on production HTTPS)
    if ((APP_ENV ?? 'development') === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Require login – redirect to login page if not authenticated.
 * Also enforces server-side session lifetime so an idle session
 * is invalidated server-side (not just via cookie expiry).
 *
 * @param array $roles  e.g. ['admin','officer']  (empty = any logged-in user)
 */
function require_login(array $roles = []): void
{
    if (!is_logged_in()) {
        redirect('/public/login.php');
    }

    // ── Server-side idle session expiry ──────────────────────
    // login_time is set on successful auth and refreshed on every
    // page load. If more than SESSION_LIFETIME seconds have passed
    // without activity, destroy the session and redirect to login.
    $idleSeconds = time() - ($_SESSION['login_time'] ?? time());
    if ($idleSeconds > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        redirect('/public/login.php?timeout=1');
    }
    // Refresh the activity timestamp so it resets on each request
    $_SESSION['login_time'] = time();

    if ($roles && !in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        die(render_error(403, 'You do not have permission to access this page.'));
    }
}

function current_user(): ?array
{
    if (!is_logged_in()) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
        'email'=> $_SESSION['user_email'],
    ];
}

// ---- CSRF --------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

// ---- Output Escaping ---------------------------------------

/**
 * Escape a value for safe HTML output.
 * Always use this when printing user data.
 */
function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- Redirects ---------------------------------------------

function redirect(string $path): never
{
    header('Location: ' . APP_URL . $path);
    exit;
}

// ---- Incident Reference Numbers ----------------------------

function generate_reference(): string
{
    $year = date('Y');
    $pdo  = db();
    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('INC-%s-%04d', $year, $count);
}

// ---- SLA Deadline Calculation ------------------------------

function sla_deadline(string $severity): string
{
    $hours = SLA_HOURS[$severity] ?? 72;
    return date('Y-m-d H:i:s', time() + $hours * 3600);
}

// ---- Severity Badge Color ----------------------------------

function severity_class(string $severity): string
{
    return match ($severity) {
        'Low'      => 'badge-low',
        'Medium'   => 'badge-medium',
        'High'     => 'badge-high',
        'Critical' => 'badge-critical',
        default    => 'badge-low',
    };
}

function status_class(string $status): string
{
    return match ($status) {
        'New'         => 'status-new',
        'Acknowledged'=> 'status-ack',
        'In Progress' => 'status-progress',
        'Resolved'    => 'status-resolved',
        'Closed'      => 'status-closed',
        default       => 'status-new',
    };
}

// ---- Audit Logging -----------------------------------------

function audit_log(
    string  $action,
    ?string $targetType = null,
    ?int    $targetId   = null,
    array   $details    = []
): void {
    $user = current_user();
    try {
        db()->prepare("
            INSERT INTO audit_log
                (user_id, action, target_type, target_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $user['id'] ?? null,
            $action,
            $targetType,
            $targetId,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (PDOException $e) {
        // Installations that have not imported the full schema (no audit_log table) must not break auth flows.
        error_log('audit_log: ' . $e->getMessage());
    }
}

// ---- Flash Messages ----------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = compact('type', 'message');
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---- Formatting Helpers ------------------------------------

/**
 * Format a byte count into a human-readable string (KB, MB, GB).
 * Used in file-attachment lists throughout the incident view page.
 */
function format_bytes(int $bytes, int $decimals = 1): string
{
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, $decimals) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, $decimals) . ' MB';
    return round($bytes / 1073741824, $decimals) . ' GB';
}

// ---- Error Page --------------------------------------------

function render_error(int $code, string $message): string
{
    http_response_code($code);
    return "<html><body><h2>Error $code</h2><p>" . e($message) . "</p>
            <a href='" . APP_URL . "'>Go to homepage</a></body></html>";
}

