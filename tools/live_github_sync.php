<?php
/**
 * Admin-only: pull selected PHP files from GitHub main (no exec/shell needed).
 * Open once after push: /tools/live_github_sync.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
if (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    echo 'Admin only.';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$repo = 'SagarTuver7819/armor_fire_hrms';
$branch = 'main';
$files = [
    'includes/contractor_split_helper.php',
    'includes/salary_register_helper.php',
    'includes/payroll_reports_helper.php',
    'includes/payroll_helper.php',
    'includes/build_meta.php',
    'contractor/operations/edit.php',
    'payroll/register.php',
    'tools/live_update.php',
    'tools/live_github_sync.php',
];

echo "time=" . date('c') . "\n";
echo "repo={$repo}@{$branch}\n\n";

$ok = 0;
foreach ($files as $rel) {
    $url = "https://raw.githubusercontent.com/{$repo}/{$branch}/{$rel}";
    echo "> {$rel}\n";
    $bin = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'armor-hrms-live-github-sync',
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($bin === false || $code >= 400) {
            echo "  FAIL http={$code} err={$err}\n";
            continue;
        }
    } else {
        $bin = @file_get_contents($url);
        if ($bin === false) {
            echo "  FAIL download\n";
            continue;
        }
    }
    $dest = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo "  FAIL mkdir {$dir}\n";
        continue;
    }
    if (@file_put_contents($dest, $bin) === false) {
        echo "  FAIL write\n";
        continue;
    }
    echo '  OK bytes=' . strlen($bin) . "\n";
    $ok++;
}
echo "\nupdated={$ok}/" . count($files) . "\nDONE\n";
