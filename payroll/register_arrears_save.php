<?php
/**
 * AJAX: save Salary Register Arrears → salary_diary.arrears_amount
 * Net = Gross − Deductions + Arrears (handled in register calculation).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$month = (int) ($_POST['month'] ?? 0);
$year = (int) ($_POST['year'] ?? 0);
$deptId = (int) ($_POST['department_id'] ?? 0);
$type = (string) ($_POST['type'] ?? 'salary');
$arrears = (float) ($_POST['arrears'] ?? 0);

if ($employeeId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee / month.']);
    exit;
}

requirePayrollRegisterAccess($deptId);

if (function_exists('ensurePayrollApprovalTables')) {
    ensurePayrollApprovalTables();
}
$lock = function_exists('getSalaryRegisterLock')
    ? getSalaryRegisterLock($month, $year, $type, $deptId)
    : null;
if ($lock !== null) {
    echo json_encode(['success' => false, 'message' => 'Register is locked. Unlock to edit Arrears.']);
    exit;
}

if ($arrears < 0) {
    $arrears = 0;
}
$arrears = round($arrears, 2);

$conn = getDBConnection();
ensurePayrollTables($conn);

$stmt = $conn->prepare(
    "INSERT INTO salary_diary (employee_id, month_no, year_no, arrears_amount)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE arrears_amount = VALUES(arrears_amount)"
);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
    exit;
}
$stmt->bind_param('iiid', $employeeId, $month, $year, $arrears);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();
$conn->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => $err ?: 'Save failed.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Arrears saved.',
    'arrears' => $arrears,
]);
