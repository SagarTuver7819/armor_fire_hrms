<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$empId = (int) ($_POST['employee_id'] ?? 0);
$date = trim($_POST['work_date'] ?? '');
$item = trim($_POST['item_name'] ?? '');
$qty = (float) ($_POST['quantity'] ?? 0);
$rate = (float) ($_POST['rate'] ?? 0);
$amount = round($qty * $rate, 2);
$remarks = trim($_POST['remarks'] ?? '');

if ($empId <= 0 || $date === '') {
    die('Employee and date are required. <a href="javascript:history.back()">Back</a>');
}

$conn = getDBConnection();
ensurePayrollTables($conn);
$stmt = $conn->prepare(
    "INSERT INTO jobwork_entries (employee_id, work_date, item_name, quantity, rate, amount, remarks)
     VALUES (?,?,?,?,?,?,?)"
);
$stmt->bind_param('issddds', $empId, $date, $item, $qty, $rate, $amount, $remarks);
$stmt->execute();
$stmt->close();
$conn->close();

header('Location: ' . app_url('payroll/jobwork.php?department_id=' . $deptId . '&msg=saved'));
exit;
