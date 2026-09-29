<?php
/**
 * Mark Employee Voice notification(s) as read → ticket view
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$id = (int) ($_GET['id'] ?? 0);

$isStaff = function_exists('canManageEmployeeVoice') && canManageEmployeeVoice();
$dest = $isStaff
    ? app_url('employee_voice/index.php')
    : app_url('employee/voice/my.php');

if ($all) {
    markAllEvNotificationsRead($userId);
    header('Location: ' . $dest);
    exit;
}

if ($id > 0) {
    $row = getEvNotificationById($id, $userId);
    markEvNotificationRead($id, $userId);
    $ticketId = (int) ($row['ticket_id'] ?? 0);
    if ($ticketId > 0) {
        $dest = $isStaff
            ? app_url('employee_voice/view.php?id=' . $ticketId)
            : app_url('employee/voice/view.php?id=' . $ticketId);
    }
}

header('Location: ' . $dest);
exit;
