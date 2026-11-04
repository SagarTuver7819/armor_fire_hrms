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
 * Never throws — failed candidates are skipped (PHP 8+ mysqli exceptions caught).
 *
 * @return mysqli|null
 */
function getErpDBConnection()
{
    static $cached = false;
    static $conn = null;
    if ($cached) {
        return $conn;
    }
    $cached = true;

    $host = (string) env('ERP_DB_HOST', env('DB_HOST', 'localhost'));
    $port = (int) env('ERP_DB_PORT', env('DB_PORT', '3306'));
    if ($port <= 0) {
        $port = 3306;
    }

    // Credential sets: explicit ERP_* first, then HRMS DB_* (local often shares root)
    $credSets = [];
    $erpUser = trim((string) env('ERP_DB_USER', ''));
    $erpPass = (string) env('ERP_DB_PASS', '');
    if ($erpUser !== '') {
        $credSets[] = ['user' => $erpUser, 'pass' => $erpPass];
    }
    $hrmsUser = (string) env('DB_USER', 'root');
    $hrmsPass = (string) env('DB_PASS', '');
    $dup = false;
    foreach ($credSets as $c) {
        if ($c['user'] === $hrmsUser && $c['pass'] === $hrmsPass) {
            $dup = true;
            break;
        }
    }
    if (!$dup) {
        $credSets[] = ['user' => $hrmsUser, 'pass' => $hrmsPass];
    }

    $configured = trim((string) env('ERP_DB_NAME', ''));
    // Live hosting DB first, then local armor_erp — avoids HRMS user fatal on denied armor_erp
    $candidates = [];
    if ($configured !== '') {
        $candidates[] = $configured;
    }
    foreach (['jrosvllq_armor_fire_erp', 'armor_erp', 'armor_fire_erp'] as $try) {
        if (!in_array($try, $candidates, true)) {
            $candidates[] = $try;
        }
    }

    $prevReport = null;
    if (function_exists('mysqli_report')) {
        $prevReport = MYSQLI_REPORT_OFF;
        try {
            // PHP 8+: failed connect throws mysqli_sql_exception unless report mode is off
            mysqli_report(MYSQLI_REPORT_OFF);
        } catch (Throwable $e) {
            $prevReport = null;
        }
    }

    try {
        foreach ($credSets as $cred) {
            foreach ($candidates as $dbName) {
                try {
                    $try = @new mysqli($host, $cred['user'], $cred['pass'], $dbName, $port);
                } catch (Throwable $e) {
                    $try = null;
                }
                if ($try instanceof mysqli && empty($try->connect_errno) && empty($try->connect_error)) {
                    $try->set_charset('utf8mb4');
                    $conn = $try;
                    return $conn;
                }
                if ($try instanceof mysqli) {
                    @$try->close();
                }
            }
        }
    } finally {
        if ($prevReport !== null && function_exists('mysqli_report')) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            } catch (Throwable $e) {
                // ignore restore failures
            }
        }
    }

    $conn = null;
    return null;
}
