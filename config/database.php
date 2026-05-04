<?php
// ============================================================
// CIRMS - Database Connection
// config/database.php
//
// Returns a singleton PDO instance.
// Usage: $pdo = db();
// ============================================================

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());

            // In development, show the driver message (no password is included) so setup issues are obvious.
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                header('Content-Type: text/html; charset=UTF-8');
                exit(
                    '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Database error</title></head>'
                    . '<body style="font-family:system-ui,sans-serif;max-width:42rem;margin:2rem auto;padding:0 1rem;line-height:1.5">'
                    . '<h1 style="font-size:1.25rem">Database connection failed</h1>'
                    . '<p><strong>MySQL says:</strong><br><code style="word-break:break-word;background:#f1f5f9;padding:.35rem .5rem;display:inline-block;border-radius:4px">'
                    . $detail . '</code></p>'
                    . '<p><strong>Typical fixes (XAMPP)</strong></p><ol>'
                    . '<li>Start <strong>MySQL</strong> in the XAMPP Control Panel.</li>'
                    . '<li>Import <code>database/schema.sql</code> in phpMyAdmin so the <code>cirms</code> database exists.</li>'
                    . '<li>Match credentials in <code>config/config.php</code> (defaults are <code>root</code> / empty password) or add <code>config/config.local.php</code> from <code>config.local.example.php</code>.</li>'
                    . '</ol></body></html>'
                );
            }

            die('Database connection error. Please contact the system administrator.');
        }
    }

    return $pdo;
}
