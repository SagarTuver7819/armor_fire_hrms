<?php
/**
 * Admin-only: git pull origin main on the live document root.
 * Open: https://armor-hrms.oceanhub.co.in/tools/live_update.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = $_SESSION['user'] ?? [];
$role = strtolower((string) ($user['role'] ?? ''));
if ($role !== 'admin') {
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

$after = is_file($buildFile) ? (string) file_get_contents($buildFile) : '';
if ($before !== $after) {
    echo "BUILD_META_UPDATED\n";
} else {
    echo "BUILD_META_SAME (already up to date or pull did not change build_meta)\n";
}
echo "DONE — hard refresh Attendance Report (Ctrl+F5) and check Build line under the title.\n";
