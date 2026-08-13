<?php
/**
 * Load .env into environment (simple, no Composer needed)
 * Looks for: project_root/.env
 */

if (defined('ARMOR_ENV_LOADED')) {
    return;
}
define('ARMOR_ENV_LOADED', true);

$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip quotes
        if (
            (strlen($value) >= 2) &&
            (($value[0] === '"' && substr($value, -1) === '"') ||
             ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if ($key === '') {
            continue;
        }
        // Always set from file (allows empty values like APP_BASE_PATH=)
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

/**
 * Read env value with default
 * Empty string is a valid value (e.g. APP_BASE_PATH= for subdomain root)
 */
function env($key, $default = null)
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val === false) {
        return $default;
    }
    return $val;
}

/**
 * Is production environment?
 */
function isProduction()
{
    return strtolower((string) env('APP_ENV', 'local')) === 'production';
}
