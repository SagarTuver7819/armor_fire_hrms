<?php
/**
 * Stream Circular PDF (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/circular_helper.php';

requireStaff();
ensureCircularTables();

$id = (int) ($_GET['id'] ?? 0);
$row = getCircularById($id);
if (!$row || !circularFileExists($row['pdf_file'] ?? '')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PDF not found.';
    exit;
}

$abs = circularAbsolutePath($row['pdf_file']);
$download = isset($_GET['download']) && (string) $_GET['download'] === '1';
$filename = $row['original_filename'] ?: ('circular_' . $id . '.pdf');
$filename = preg_replace('/[^\w.\- ()]+/u', '_', $filename);
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    $filename .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($abs));
header(
    ($download ? 'Content-Disposition: attachment' : 'Content-Disposition: inline')
    . '; filename="' . $filename . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($abs);
exit;
