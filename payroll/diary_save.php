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
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$ids = $_POST['emp_id'] ?? [];
$working = $_POST['working'] ?? [];
$present = $_POST['present'] ?? [];
$ot = $_POST['ot'] ?? [];

$conn = getDBConnection();
ensurePayrollTables($conn);
$stmt = $conn->prepare(
    "INSERT INTO salary_diary (employee_id, month_no, year_no, working_days, present_days, overtime_hours)
     VALUES (?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE working_days=VALUES(working_days), present_days=VALUES(present_days), overtime_hours=VALUES(overtime_hours)"
);
foreach ($ids as $i => $eid) {
    $eid = (int) $eid;
    $w = (float) ($working[$i] ?? 26);
    $p = (float) ($present[$i] ?? 26);
    $o = (float) ($ot[$i] ?? 0);
    $stmt->bind_param('iiiddd', $eid, $month, $year, $w, $p, $o);
    $stmt->execute();
}
$stmt->close();
$conn->close();

header('Location: ' . app_url('payroll/diary.php?department_id=' . $deptId . '&month=' . $month . '&year=' . $year . '&msg=saved'));
exit;
