<?php
/**
 * Admin-only: update live document root from GitHub main.
 * Open: https://armor-hrms.oceanhub.co.in/tools/live_update.php
 *
 * Prefer git pull when exec() is allowed; otherwise download zip + extract
 * (shared hosts often disable exec/shell_exec).
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
chdir($root);

$buildFile = $root . '/includes/build_meta.php';
$before = is_file($buildFile) ? (string) file_get_contents($buildFile) : '';

echo "cwd={$root}\n";
echo "time=" . date('c') . "\n\n";

$canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);

if ($canExec) {
    $cmds = [
        'git rev-parse --abbrev-ref HEAD',
        'git rev-parse --short HEAD',
        'git fetch origin main',
        'git pull --ff-only origin main',
        'git rev-parse --short HEAD',
        'git log -1 --oneline',
    ];
    foreach ($cmds as $cmd) {
        echo "> {$cmd}\n";
        $out = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        echo implode("\n", $out) . "\n";
        echo "exit={$code}\n\n";
        if ($code !== 0 && strpos($cmd, 'pull') !== false) {
            echo "PULL_FAILED\n";
            exit(1);
        }
    }
} else {
    echo "exec() disabled — using GitHub zip deploy\n\n";
    if (!class_exists('ZipArchive')) {
        echo "ZipArchive missing — cannot deploy\n";
        exit(1);
    }
    $zipUrl = 'https://codeload.github.com/SagarTuver7819/armor_fire_hrms/zip/refs/heads/main';
    $tmpZip = sys_get_temp_dir() . '/armor_hrms_main_' . getmypid() . '.zip';
    $tmpDir = sys_get_temp_dir() . '/armor_hrms_main_' . getmypid();

    echo "> download {$zipUrl}\n";
    $bin = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($zipUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'armor-hrms-live-update',
        ]);
        $bin = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bin === false || $code >= 400) {
            echo "curl failed http={$code} err={$err}\n";
            exit(1);
        }
    } else {
        $bin = @file_get_contents($zipUrl);
        if ($bin === false) {
            echo "file_get_contents download failed\n";
            exit(1);
        }
    }
    if (!file_put_contents($tmpZip, $bin)) {
        echo "cannot write zip\n";
        exit(1);
    }
    echo "zip_bytes=" . strlen($bin) . "\n";

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        echo "zip open failed\n";
        @unlink($tmpZip);
        exit(1);
    }
    if (is_dir($tmpDir)) {
        // best-effort clean
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($tmpDir);
    }
    mkdir($tmpDir, 0755, true);
    $zip->extractTo($tmpDir);
    $zip->close();
    @unlink($tmpZip);

    $dirs = glob($tmpDir . '/armor_fire_hrms-*', GLOB_ONLYDIR);
    if (!$dirs) {
        echo "extract root not found\n";
        exit(1);
    }
    $srcRoot = $dirs[0];
    echo "extract={$srcRoot}\n";

    $skip = [
        '.env' => true,
        '.git' => true,
        'uploads' => true,
    ];
    $copied = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($srcRoot) + 1));
        $top = explode('/', $rel, 2)[0];
        if (isset($skip[$top]) || isset($skip[$rel])) {
            continue;
        }
        $dest = $root . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            echo "mkdir fail {$destDir}\n";
            continue;
        }
        if (!@copy($file->getPathname(), $dest)) {
            echo "copy fail {$rel}\n";
            continue;
        }
        $copied++;
    }
    // cleanup extract
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($tmpDir);

    echo "copied_files={$copied}\n\n";
}

$after = is_file($buildFile) ? (string) file_get_contents($buildFile) : '';
if ($before !== $after) {
    echo "BUILD_META_UPDATED\n";
} else {
    echo "BUILD_META_SAME (already up to date or pull did not change build_meta)\n";
}
echo "DONE — hard refresh Attendance Report (Ctrl+F5) and check Build line under the title.\n";
