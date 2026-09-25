<?php
/**
 * Text case helpers — details UPPERCASE, emails lowercase.
 */

if (!function_exists('forceDetailUpper')) {
    function forceDetailUpper($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }
        return strtoupper($value);
    }
}

if (!function_exists('forceEmailLower')) {
    function forceEmailLower($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }
}
