<?php
/**
 * Save leave request
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = (int) ($_POST['department_id'] ?? 0);
$redirect = app_url('leave/index.php?department_id=' . $deptId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

requireAccess('leave', 'add', $deptId);

if (empty($_SESSION['role_code'])) {
    refreshHeadedDepartmentsSession();
}

$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$selfApply = function_exists('isEmployee') && isEmployee() && $sessionEmpId > 0
    && !canAccess('leave', 'edit', 0) && !isDeptHeadRole();

$employeeId = (int) ($_POST['employee_id'] ?? 0);
if ($selfApply) {
    $employeeId = $sessionEmpId;
}

if ($employeeId <= 0) {
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode('Select employee.'));
    exit;
}

$emp = getEmployeeById($employeeId);
if (!$emp) {
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode('Employee not found.'));
    exit;
}
$empDept = (int) ($emp['department_id'] ?? 0);
if (!canAccess('leave', 'add', $empDept) && !$selfApply) {
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode('Not allowed for this department.'));
    exit;
}
if (isDeptHeadRole()) {
    $headed = array_map('intval', $_SESSION['headed_department_ids'] ?? []);
    if (!in_array($empDept, $headed, true)) {
        header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode('Not allowed for this department.'));
        exit;
    }
}

$conn = getDBConnection();
ensureLeaveTables($conn);

try {
    $attachmentPath = leaveUploadAttachment($_FILES['attachment'] ?? null, $employeeId);
    leaveApplyRequest($conn, [
        'employee_id' => $employeeId,
        'leave_type_id' => (int) ($_POST['leave_type_id'] ?? 0),
        'from_date' => (string) (parseDateInput($_POST['from_date'] ?? '') ?? ''),
        'to_date' => (string) (parseDateInput($_POST['to_date'] ?? '') ?? ''),
        'leave_half' => (string) ($_POST['leave_half'] ?? 'FULL'),
        'reason' => (string) ($_POST['reason'] ?? ''),
        'attachment_path' => $attachmentPath,
        'applied_by' => (int) ($_SESSION['user_id'] ?? 0),
    ]);
    $conn->close();
    header('Location: ' . app_url('leave/index.php?department_id=' . $empDept . '&msg=applied'));
    exit;
} catch (Throwable $e) {
    $conn->close();
    header('Location: ' . $redirect . '&msg=error&err=' . rawurlencode($e->getMessage()));
    exit;
}
