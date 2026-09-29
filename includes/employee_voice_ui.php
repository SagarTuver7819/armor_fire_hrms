<?php
/**
 * Shared display helpers for Employee Voice ticket views
 */

function evDisp($v, $fallback = '—')
{
    if ($v === null) {
        return $fallback;
    }
    $v = trim((string) $v);
    if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
        return $fallback;
    }
    return $v;
}

function evKv($label, $value, $span = 1, $sub = '')
{
    $cls = 'ev-kv';
    if ($span === 2) {
        $cls .= ' span-2';
    }
    if ($span === 3) {
        $cls .= ' span-3';
    }
    $raw = evDisp($value);
    $isEmpty = ($raw === '—');
    echo '<div class="' . $cls . '">';
    echo '<span class="lbl">' . htmlspecialchars($label) . '</span>';
    echo '<span class="val' . ($isEmpty ? ' muted' : '') . '">' . htmlspecialchars($raw) . '</span>';
    if ($sub !== '') {
        echo '<span class="sub">' . htmlspecialchars($sub) . '</span>';
    }
    echo '</div>';
}

function evBlock($label, $value)
{
    echo '<div class="ev-block">';
    echo '<span class="lbl">' . htmlspecialchars($label) . '</span>';
    echo '<div class="body">' . htmlspecialchars(evDisp($value)) . '</div>';
    echo '</div>';
}
