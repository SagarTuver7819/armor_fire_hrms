<?php
/**
 * Mark leave notification(s) as read → My Leave
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireStaff();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$id = (int) ($_GET['id'] ?? 0);

$dest = app_url('leave/index.php');

if ($all) {
    markAllLeaveNotificationsRead($userId);
    header('Location: ' . $dest);
    exit;
}

if ($id > 0) {
    markLeaveNotificationRead($id, $userId);
}

header('Location: ' . $dest);
exit;
