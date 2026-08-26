<?php
/**
 * Save leave request
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();

$deptId = (int) ($_POST['department_id'] ?? 0);
$redirect = app_url('leave/index.php?department_id=' . $deptId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$conn = getDBConnection();
ensureLeaveTables($conn);

try {
    leaveApplyRequest($conn, [
        'employee_id' => (int) ($_POST['employee_id'] ?? 0),
        'leave_type_id' => (int) ($_POST['leave_type_id'] ?? 0),
        'from_date' => (string) ($_POST['from_date'] ?? ''),
        'to_date' => (string) ($_POST['to_date'] ?? ''),
        'reason' => (string) ($_POST['reason'] ?? ''),
        'applied_by' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    $conn->close();
    header('Location: ' . $redirect . '&msg=applied');
    exit;
} catch (Throwable $e) {
    $conn->close();
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode($e->getMessage()));
    exit;
}
