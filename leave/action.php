<?php
/**
 * Approve / Reject / Cancel leave — requires leave edit (Payroll Head / HR Head)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

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

$row = null;
if ($id > 0) {
    $stmt = $conn->prepare(
        "SELECT lr.id, e.department_id
         FROM leave_requests lr
         INNER JOIN employees e ON e.id = lr.employee_id
         WHERE lr.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$row) {
    $conn->close();
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode('Leave request not found.'));
    exit;
}

$requestDeptId = (int) ($row['department_id'] ?? 0);
requireAccess('leave', 'edit', $requestDeptId);

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
