<?php
/**
 * Global date/time formats
 * Display date: DD-MM-YYYY
 * Display time: 12-hour (h:i A)
 * Database / MySQL still uses Y-m-d / H:i:s.
 */

if (!defined('APP_DATE_DISPLAY')) {
    define('APP_DATE_DISPLAY', 'd-m-Y');
}
if (!defined('APP_TIME_DISPLAY')) {
    define('APP_TIME_DISPLAY', 'h:i A');
}
if (!defined('APP_DATETIME_DISPLAY')) {
    define('APP_DATETIME_DISPLAY', 'd-m-Y h:i A');
}

/**
 * Display date as DD-MM-YYYY (empty-safe).
 */
function formatDateDisplay($date)
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '';
    }
    if ($date instanceof DateTimeInterface) {
        return $date->format(APP_DATE_DISPLAY);
    }
    $raw = trim((string) $date);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return '';
    }
    // Already DD-MM-YYYY
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $raw)) {
        return $raw;
    }
    // Prefer explicit parser (avoids strtotime MM/DD vs DD/MM ambiguity)
    $parsed = parseDateInput($raw);
    if ($parsed) {
        $ts = strtotime($parsed);
        return $ts ? date(APP_DATE_DISPLAY, $ts) : '';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }
    return date(APP_DATE_DISPLAY, $ts);
}

/**
 * Display time as 12-hour (e.g. 02:58 PM).
 */
function formatTimeDisplay($time)
{
    if ($time === null || $time === '' || $time === '00:00:00') {
        return '';
    }
    if ($time instanceof DateTimeInterface) {
        return $time->format(APP_TIME_DISPLAY);
    }
    $raw = trim((string) $time);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }
    return date(APP_TIME_DISPLAY, $ts);
}

/**
 * Display date+time as DD-MM-YYYY hh:mm AM/PM.
 */
function formatDateTimeDisplay($datetime)
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
        return '';
    }
    if ($datetime instanceof DateTimeInterface) {
        return $datetime->format(APP_DATETIME_DISPLAY);
    }
    $raw = trim((string) $datetime);
    if ($raw === '') {
        return '';
    }
    // Date only
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || preg_match('/^\d{2}-\d{2}-\d{4}$/', $raw)) {
        return formatDateDisplay($raw);
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }
    return date(APP_DATETIME_DISPLAY, $ts);
}

/**
 * Value for date text inputs (DD-MM-YYYY).
 */
function dateInputValue($date)
{
    return formatDateDisplay($date);
}

/**
 * Parse user/form date into Y-m-d for DB.
 * Accepts: DD-MM-YYYY, DD/MM/YYYY, YYYY-MM-DD, and common strtotime strings.
 * Returns null for empty/invalid.
 */
function parseDateInput($value)
{
    if ($value === null) {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }

    // YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $d = (int) $m[3];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    // DD-MM-YYYY or DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $value, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

/**
 * Normalize POST/GET date field to Y-m-d or empty string (for NULLIF / optional fields).
 */
function normalizeDatePost($value, $asEmptyString = true)
{
    $parsed = parseDateInput($value);
    if ($parsed === null) {
        return $asEmptyString ? '' : null;
    }
    return $parsed;
}
