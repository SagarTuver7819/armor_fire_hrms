<?php
/**
 * Database Configuration
 * Values come from .env (local + live same code)
 */

require_once __DIR__ . '/env.php';

if (!defined('DB_HOST')) {
    define('DB_HOST', env('DB_HOST', 'localhost'));
    define('DB_PORT', env('DB_PORT', '3306'));
    define('DB_USER', env('DB_USER', 'root'));
    define('DB_PASS', env('DB_PASS', ''));
    define('DB_NAME', env('DB_NAME', 'armor_hrms'));
}
if (!defined('DB_PERSISTENT')) {
    // .env DB_PERSISTENT=1 → reuse pooled MySQL connections (each getDBConnection() is ~0.3 ms instead of ~10 ms)
    define('DB_PERSISTENT', in_array(strtolower((string) env('DB_PERSISTENT', '0')), ['1', 'true', 'yes', 'on'], true));
}

/**
 * Create MySQL connection
 */
function getDBConnection()
{
    $port = (int) DB_PORT;
    $host = DB_HOST;
    if (DB_PERSISTENT && strpos($host, 'p:') !== 0) {
        $host = 'p:' . $host;
    }
    $conn = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port > 0 ? $port : 3306);

    if ($conn->connect_error) {
        if (isProduction()) {
            die('Database connection failed. Please contact administrator.');
        }
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Persistent (p:) connections can keep another DB selected — force configured DB every time
    if (!$conn->select_db(DB_NAME)) {
        if (isProduction()) {
            die('Database connection failed. Please contact administrator.');
        }
        die('Database select failed: ' . $conn->error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Armor ERP MySQL (Production Planning KRA / Master part names).
 * .env: ERP_DB_HOST, ERP_DB_PORT, ERP_DB_NAME, ERP_DB_USER, ERP_DB_PASS
 * Falls back to common local/live DB names on the same MySQL host.
 *
 * @return mysqli|null
 */
function getErpDBConnection()
{
    static $failed = false;
    if ($failed) {
        return null;
    }

    $host = (string) env('ERP_DB_HOST', env('DB_HOST', 'localhost'));
    $user = (string) env('ERP_DB_USER', env('DB_USER', 'root'));
    $pass = (string) env('ERP_DB_PASS', env('DB_PASS', ''));
    $port = (int) env('ERP_DB_PORT', env('DB_PORT', '3306'));
    if ($port <= 0) {
        $port = 3306;
    }
    $configured = trim((string) env('ERP_DB_NAME', ''));
    $candidates = [];
    if ($configured !== '') {
        $candidates[] = $configured;
    }
    foreach (['armor_erp', 'jrosvllq_armor_fire_erp', 'armor_fire_erp'] as $try) {
        if (!in_array($try, $candidates, true)) {
            $candidates[] = $try;
        }
    }

    foreach ($candidates as $dbName) {
        $conn = @new mysqli($host, $user, $pass, $dbName, $port);
        if ($conn instanceof mysqli && !$conn->connect_error) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
        if ($conn instanceof mysqli) {
            @$conn->close();
        }
    }

    $failed = true;
    return null;
}
