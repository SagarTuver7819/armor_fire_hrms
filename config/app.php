<?php
/**
 * App Config - Base URL for assets & links
 * Works: local XAMPP folder, live subdomain, nested /employees /masters
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/company.php';

// Timezone
$tz = env('APP_TIMEZONE', 'Asia/Kolkata');
if ($tz) {
    @date_default_timezone_set($tz);
}

if (!defined('APP_ENV')) {
    define('APP_ENV', env('APP_ENV', 'local'));
}

if (!defined('APP_URL')) {
    define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
}

/**
 * Resolve APP_BASE once
 * Priority:
 *  1) .env APP_BASE_PATH (best for live subdomain / fixed folder)
 *  2) Auto-detect from SCRIPT_NAME
 */
if (!defined('APP_BASE')) {
    $base = '';

    // If .env defines APP_BASE_PATH (even empty), prefer it
    if (array_key_exists('APP_BASE_PATH', $_ENV) || getenv('APP_BASE_PATH') !== false) {
        $base = rtrim(str_replace('\\', '/', (string) env('APP_BASE_PATH', '')), '/');
        if ($base === '/' || $base === '\\' || $base === '.') {
            $base = '';
        }
    } else {
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        // Nested masters: /masters or /masters/{slug}
        if (preg_match('#^(.*?)/masters(?:/[^/]+)?$#', $scriptName, $m)) {
            $scriptName = $m[1] === '' ? '/' : $m[1];
        }

        // Nested employees
        if (substr($scriptName, -10) === '/employees') {
            $scriptName = dirname($scriptName);
        }

        $base = rtrim($scriptName, '/');
        if ($base === '' || $base === '\\' || $base === '.') {
            $base = '';
        }
    }

    /**
     * Live subdomain safety:
     * If APP_URL is domain root (no /path), force empty base.
     * Prevents broken CSS/links when local APP_BASE_PATH=/armor_new_hrms is copied to live.
     */
    if (isProduction() && defined('APP_URL') && APP_URL !== '') {
        $urlPath = parse_url(APP_URL, PHP_URL_PATH);
        if ($urlPath === null || $urlPath === '' || $urlPath === '/') {
            $base = '';
        }
    }

    define('APP_BASE', $base);
}

/**
 * Build app URL from project-relative path
 * Example: app_url('employees/index.php?department_id=1')
 */
function app_url($path = '')
{
    $path = ltrim((string) $path, '/');
    if (APP_BASE === '') {
        return '/' . $path;
    }
    return APP_BASE . '/' . $path;
}

/**
 * Absolute URL (for emails / QR later) — optional
 */
function app_full_url($path = '')
{
    $rel = app_url($path);
    if (APP_URL !== '') {
        return APP_URL . '/' . ltrim($path, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $rel;
}
