<?php
/**
 * Approve / Reject / Cancel leave
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$do = strtolower(trim((string) ($_GET['do'] ?? '')));
$deptId = (int) ($_GET['department_id'] ?? 0);
$status = trim((string) ($_GET['status'] ?? 'All'));
$year = (int) ($_GET['year'] ?? date('Y'));

$qs = http_build_query([
    'department_id' => $deptId,
    'status' => $status,
    'year' => $year,
]);
$redirect = app_url('leave/index.php?' . $qs);

$conn = getDBConnection();
ensureLeaveTables($conn);
$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    if ($do === 'approve') {
        leaveApproveRequest($conn, $id, $userId, 'Approved');
        $msg = 'approved';
    } elseif ($do === 'reject') {
        leaveRejectRequest($conn, $id, $userId, 'Rejected');
        $msg = 'rejected';
    } elseif ($do === 'cancel') {
        leaveCancelApproved($conn, $id, $userId, 'Cancelled');
        $msg = 'cancelled';
    } else {
        throw new RuntimeException('Invalid action.');
    }
    $conn->close();
    header('Location: ' . $redirect . '&msg=' . $msg);
    exit;
} catch (Throwable $e) {
    $conn->close();
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode($e->getMessage()));
    exit;
}
