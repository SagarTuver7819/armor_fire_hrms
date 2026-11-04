<?php
/**
 * Armor ERP database (Production Planning KRA sync).
 * Kept in PHP config (not .env) so live/local both work from git.
 */

if (defined('ERP_DB_HOST')) {
    return;
}

$isCli = (PHP_SAPI === 'cli');
$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
$isLocal = $isCli
    || in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true)
    || $hostHeader === 'localhost'
    || strpos($hostHeader, 'localhost:') === 0
    || strpos($hostHeader, '.local') !== false;

if ($isLocal) {
    // Local XAMPP — same MySQL, ERP schema name
    define('ERP_DB_HOST', 'localhost');
    define('ERP_DB_PORT', 3306);
    define('ERP_DB_NAME', 'armor_erp');
    define('ERP_DB_USER', 'root');
    define('ERP_DB_PASS', '');
} else {
    // Live (armor-hrms.oceanhub.co.in) — Armor Fire ERP MySQL
    define('ERP_DB_HOST', 'localhost');
    define('ERP_DB_PORT', 3306);
    define('ERP_DB_NAME', 'jrosvllq_armor_fire_erp');
    define('ERP_DB_USER', 'jrosvllq_armor_fire_erp');
    define('ERP_DB_PASS', 'mP5Alve9X;_DBVOJ');
}
