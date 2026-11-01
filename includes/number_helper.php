<?php
/**
 * Indian numbering system (lakhs / crores) for display across HRMS.
 * Example: 6529113 → 65,29,113.00
 */

/**
 * Format a number in Indian comma style.
 *
 * @param mixed $number
 * @param int $decimals
 * @return string
 */
function formatIndianNumber($number, $decimals = 2)
{
    if ($number === null || $number === '') {
        return $decimals > 0 ? number_format(0, $decimals, '.', '') : '0';
    }
    if (!is_numeric($number)) {
        return (string) $number;
    }

    $decimals = max(0, (int) $decimals);
    $negative = ((float) $number) < 0;
    $abs = abs((float) $number);

    if (class_exists('NumberFormatter')) {
        static $formatters = [];
        $key = 'en_IN_' . $decimals;
        if (!isset($formatters[$key])) {
            $fmt = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
            $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(NumberFormatter::GROUPING_USED, 1);
            $formatters[$key] = $fmt;
        }
        $out = $formatters[$key]->format($abs);
        if ($out !== false && $out !== null && $out !== '') {
            return ($negative ? '-' : '') . $out;
        }
    }

    // Fallback without intl: last 3 digits, then groups of 2
    $fixed = number_format($abs, $decimals, '.', '');
    $parts = explode('.', $fixed, 2);
    $intPart = $parts[0];
    $decPart = $parts[1] ?? null;

    $len = strlen($intPart);
    if ($len <= 3) {
        $grouped = $intPart;
    } else {
        $last3 = substr($intPart, -3);
        $rest = substr($intPart, 0, -3);
        $chunks = [];
        while (strlen($rest) > 2) {
            $chunks[] = substr($rest, -2);
            $rest = substr($rest, 0, -2);
        }
        if ($rest !== '') {
            $chunks[] = $rest;
        }
        $grouped = implode(',', array_reverse($chunks)) . ',' . $last3;
    }

    $out = $grouped;
    if ($decimals > 0) {
        $out .= '.' . str_pad((string) ($decPart ?? ''), $decimals, '0');
    }
    return ($negative ? '-' : '') . $out;
}

/**
 * ₹ + Indian number (display money).
 */
function formatInr($number, $decimals = 2, $withSymbol = true)
{
    $n = formatIndianNumber($number, $decimals);
    return $withSymbol ? ('₹ ' . $n) : $n;
}

/**
 * Alias used in older templates.
 */
function indian_number_format($number, $decimals = 2)
{
    return formatIndianNumber($number, $decimals);
}
