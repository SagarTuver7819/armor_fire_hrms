<?php
/**
 * Database Configuration
 * Values come from .env (local + live same code)
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/erp_database.php';

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
 * Credentials: config/erp_database.php (not .env).
 * Never throws — failed candidates are skipped (PHP 8+ mysqli exceptions caught).
 *
 * @return mysqli|null
 */
function getErpDBConnection()
{
    static $cached = false;
    static $conn = null;
    if ($cached) {
        // #region agent log
        $alive = false;
        if ($conn instanceof mysqli) {
            try {
                $alive = @$conn->ping() ? true : false;
            } catch (Throwable $e) {
                $alive = false;
                // #region agent log
                @file_put_contents(
                    dirname(__DIR__) . '/debug-219c73.log',
                    json_encode([
                        'sessionId' => '219c73',
                        'hypothesisId' => 'C',
                        'location' => 'database.php:getErpDBConnection:cache',
                        'message' => 'cached ERP conn ping failed',
                        'data' => ['error' => $e->getMessage(), 'alive' => false],
                        'timestamp' => (int) round(microtime(true) * 1000),
                    ]) . "\n",
                    FILE_APPEND
                );
                // #endregion
            }
        }
        // #region agent log
        @file_put_contents(
            dirname(__DIR__) . '/debug-219c73.log',
            json_encode([
                'sessionId' => '219c73',
                'hypothesisId' => 'A',
                'location' => 'database.php:getErpDBConnection:cache-hit',
                'message' => 'returning cached ERP connection',
                'data' => ['alive' => $alive, 'isMysqli' => ($conn instanceof mysqli)],
                'timestamp' => (int) round(microtime(true) * 1000),
            ]) . "\n",
            FILE_APPEND
        );
        // #endregion
        if ($alive) {
            return $conn;
        }
        // Cached handle was closed by a caller — reconnect
        $cached = false;
        $conn = null;
    }
    $cached = true;

    if (!defined('ERP_DB_HOST')) {
        require_once __DIR__ . '/erp_database.php';
    }

    $host = (string) ERP_DB_HOST;
    $port = (int) ERP_DB_PORT;
    if ($port <= 0) {
        $port = 3306;
    }

    // Primary: config/erp_database.php; fallback: HRMS DB user (local shared root)
    $credSets = [
        ['user' => (string) ERP_DB_USER, 'pass' => (string) ERP_DB_PASS],
    ];
    $hrmsUser = defined('DB_USER') ? (string) DB_USER : (string) env('DB_USER', 'root');
    $hrmsPass = defined('DB_PASS') ? (string) DB_PASS : (string) env('DB_PASS', '');
    if ($hrmsUser !== $credSets[0]['user'] || $hrmsPass !== $credSets[0]['pass']) {
        $credSets[] = ['user' => $hrmsUser, 'pass' => $hrmsPass];
    }

    $candidates = [];
    $primary = trim((string) ERP_DB_NAME);
    if ($primary !== '') {
        $candidates[] = $primary;
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
