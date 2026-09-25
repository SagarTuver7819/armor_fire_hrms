<?php
/**
 * Stream Circular PDF (HR / Admin / Employee portal)
 */

@ini_set('display_errors', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/circular_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireStaff();
ensureCircularTables();
requireAccess('circulars', 'view');

$id = (int) ($_GET['id'] ?? 0);
$row = getCircularById($id);
if (!$row || !circularFileExists($row['pdf_file'] ?? '')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PDF not found.';
    exit;
}

$abs = circularAbsolutePath($row['pdf_file']);
$fh = fopen($abs, 'rb');
if ($fh === false) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PDF not found.';
    exit;
}
$magic = fread($fh, 5);
if ($magic === false || strncmp($magic, '%PDF-', 5) !== 0) {
    fclose($fh);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid PDF file.';
    exit;
}
rewind($fh);

$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$filename = $row['original_filename'] ?: ('circular_' . $id . '.pdf');
$filename = preg_replace('/[^\w.\- ()]+/u', '_', $filename);
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    $filename .= '.pdf';
}
$size = filesize($abs);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) $size);
header('Accept-Ranges: none');
header(
    ($download ? 'Content-Disposition: attachment' : 'Content-Disposition: inline')
    . '; filename="' . $filename . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

fpassthru($fh);
fclose($fh);
exit;
