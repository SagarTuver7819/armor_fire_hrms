<?php
/**
 * Leave Policy PDF API — serve stored PDF as-is (inline for view/print)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/leave_policy.php';

requireLogin();

$path = leavePolicyAbsolutePath();
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Leave Policy PDF not found. Upload it from Leave Master.';
    exit;
}

$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$filename = 'Leave_Policy.pdf';
$size = filesize($path);

header('Content-Type: application/pdf');
header('Content-Length: ' . $size);
header('Accept-Ranges: bytes');
header(
    ($download ? 'Content-Disposition: attachment' : 'Content-Disposition: inline')
    . '; filename="' . $filename . '"'
);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
