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
$eventType = '';
$leaveReqId = 0;
$empDeptId = 0;

if (!$all && $id > 0) {
    $conn = getDBConnection();
    ensureLeaveTables($conn);
    $st = $conn->prepare(
        "SELECT n.event_type, n.leave_request_id, e.department_id
         FROM leave_notifications n
         LEFT JOIN employees e ON e.id = n.employee_id
         WHERE n.id = ? AND n.user_id = ?
         LIMIT 1"
    );
    $st->bind_param('ii', $id, $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    if ($row) {
        $eventType = (string) ($row['event_type'] ?? '');
        $leaveReqId = (int) ($row['leave_request_id'] ?? 0);
        $empDeptId = (int) ($row['department_id'] ?? 0);
    }
}

if ($eventType === 'Applied') {
    $qs = ['status' => 'Pending'];
    if ($empDeptId > 0) {
        $qs['department_id'] = $empDeptId;
    }
    $dest = app_url('leave/index.php?' . http_build_query($qs));
}

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
