<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));

$conn = getDBConnection();
ensureEmployeesTable($conn);
ensurePayrollTables($conn);
$stmt = $conn->prepare("SELECT * FROM employees WHERE status = 1 AND department_id = ?");
$stmt->bind_param('i', $deptId);
$stmt->execute();
$emps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

foreach ($emps as $emp) {
    $calc = calculateEmployeeSalary($emp, $month, $year);
    savePayslip((int) $emp['id'], $month, $year, $calc);
}

header('Location: ' . app_url('payroll/generate.php?department_id=' . $deptId . '&month=' . $month . '&year=' . $year . '&msg=generated'));
exit;
