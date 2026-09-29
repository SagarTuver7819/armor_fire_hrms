<?php
/**
 * Mark employee-document notification(s) as read → employee Documents tab
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_documents_helper.php';

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
$all = !empty($_GET['all']);

ensureEmployeeRelatedDocumentsTables();

$goEmpId = 0;
if ($all) {
    markAllEmployeeDocumentNotificationsRead($userId);
} elseif ($id > 0) {
    $conn = getDBConnection();
    $st = $conn->prepare(
        'SELECT employee_id FROM employee_document_notifications
         WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $st->bind_param('ii', $id, $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    if ($row) {
        $goEmpId = (int) ($row['employee_id'] ?? 0);
    }
    markEmployeeDocumentNotificationRead($id, $userId);
}

if ($goEmpId > 0) {
    header('Location: ' . app_url('employees/view.php?id=' . $goEmpId . '&tab=documents'));
    exit;
}

header('Location: ' . app_url('hr/dashboard.php'));
exit;
