<?php
/**
 * Secure attachment download — own ticket or admin/HR
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();

$attId = (int) ($_GET['id'] ?? 0);
if ($attId <= 0) {
    http_response_code(404);
    exit('Not found');
}

$conn = getDBConnection();
ensureEmployeeVoiceTables($conn);
$st = $conn->prepare(
    'SELECT a.*, t.employee_id
     FROM ev_attachments a
     INNER JOIN ev_ticket t ON t.id = a.ticket_id
     WHERE a.id = ? AND t.is_deleted = 0 LIMIT 1'
);
$st->bind_param('i', $attId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$empId = (int) ($_SESSION['employee_id'] ?? 0);
$allowed = canManageEmployeeVoice() || ($empId > 0 && (int) $row['employee_id'] === $empId);
if (!$allowed) {
    http_response_code(403);
    exit('Denied');
}

$full = __DIR__ . '/../../' . ltrim((string) $row['file_path'], '/');
if (!is_file($full)) {
    http_response_code(404);
    exit('File missing');
}

$mime = $row['file_mime'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename((string) $row['file_name']) . '"');
header('Content-Length: ' . filesize($full));
readfile($full);
exit;
