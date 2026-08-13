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

/**
 * Create MySQL connection
 */
function getDBConnection()
{
    $port = (int) DB_PORT;
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port > 0 ? $port : 3306);

    if ($conn->connect_error) {
        if (isProduction()) {
            die('Database connection failed. Please contact administrator.');
        }
        die('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
