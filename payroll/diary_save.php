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
$weekOff = $_POST['week_off'] ?? [];
$pl = $_POST['pl'] ?? [];
$sl = $_POST['sl'] ?? [];
$dl = $_POST['dl'] ?? [];
$ot = $_POST['ot'] ?? [];
$loan = $_POST['loan'] ?? [];
$advance = $_POST['advance'] ?? [];
$arrears = $_POST['arrears'] ?? [];

$conn = getDBConnection();
ensurePayrollTables($conn);
$stmt = $conn->prepare(
    "INSERT INTO salary_diary
        (employee_id, month_no, year_no, working_days, present_days, week_off_days, pl_days, sl_days, dl_days,
         overtime_hours, loan_amount, advance_amount, arrears_amount)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
        working_days=VALUES(working_days), present_days=VALUES(present_days),
        week_off_days=VALUES(week_off_days), pl_days=VALUES(pl_days), sl_days=VALUES(sl_days), dl_days=VALUES(dl_days),
        overtime_hours=VALUES(overtime_hours), loan_amount=VALUES(loan_amount),
        advance_amount=VALUES(advance_amount), arrears_amount=VALUES(arrears_amount)"
);
foreach ($ids as $i => $eid) {
    $eid = (int) $eid;
    $w = (float) ($working[$i] ?? 26);
    $p = (float) ($present[$i] ?? 0);
    $wo = (float) ($weekOff[$i] ?? 0);
    $pld = (float) ($pl[$i] ?? 0);
    $sld = (float) ($sl[$i] ?? 0);
    $dld = (float) ($dl[$i] ?? 0);
    $o = (float) ($ot[$i] ?? 0);
    $ln = (float) ($loan[$i] ?? 0);
    $adv = (float) ($advance[$i] ?? 0);
    $arr = (float) ($arrears[$i] ?? 0);
    $stmt->bind_param('iiidddddddddd', $eid, $month, $year, $w, $p, $wo, $pld, $sld, $dld, $o, $ln, $adv, $arr);
    $stmt->execute();
}
$stmt->close();
$conn->close();

header('Location: ' . app_url('payroll/diary.php?department_id=' . $deptId . '&month=' . $month . '&year=' . $year . '&msg=saved'));
exit;
