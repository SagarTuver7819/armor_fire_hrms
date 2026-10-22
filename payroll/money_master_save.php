<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/advance_loan_helper.php';
require_once __DIR__ . '/../includes/date_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$kind = advanceLoanNormalizeKind($_POST['type'] ?? 'Advance');
$deptId = (int) ($_POST['department_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
$employeeId = (int) ($_POST['employee_id'] ?? 0);
requireAccess('payroll', 'edit', $deptId);

$back = app_url('payroll/money_master_edit.php?type=' . strtolower($kind) . '&department_id=' . $deptId . ($id > 0 ? ('&id=' . $id) : ''));
$emp = $employeeId > 0 ? getEmployeeById($employeeId) : null;
if (!$emp || (int) ($emp['department_id'] ?? 0) !== $deptId) {
    header('Location: ' . $back . '&err=employee');
    exit;
}

$txnDate = (string) (parseDateInput($_POST['txn_date'] ?? '') ?? '');
$amount = round((float) ($_POST['amount'] ?? 0), 2);
$installment = round((float) ($_POST['installment_amount'] ?? 0), 2);
$startMonth = (int) ($_POST['start_month'] ?? 0);
$startYear = (int) ($_POST['start_year'] ?? 0);
if ($kind === 'Advance' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txnDate)) {
    $advanceTs = strtotime($txnDate);
    $startMonth = (int) date('n', $advanceTs);
    $startYear = (int) date('Y', $advanceTs);
    $installment = 0;
}
$status = (($_POST['status'] ?? 'Open') === 'Closed') ? 'Closed' : 'Open';
$remarks = trim((string) ($_POST['remarks'] ?? ''));
if (strlen($remarks) > 255) {
    $remarks = substr($remarks, 0, 255);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txnDate) || $amount <= 0 || $startMonth < 1 || $startMonth > 12 || $startYear < 2000) {
    header('Location: ' . $back . '&err=invalid');
    exit;
}
if ($installment < 0 || ($installment > 0 && $installment > $amount)) {
    header('Location: ' . $back . '&err=installment');
    exit;
}

$conn = getDBConnection();
ensureAdvanceLoanTables($conn);
if ($id > 0) {
    $existing = advanceLoanGet($conn, $id);
    if (!$existing || (int) $existing['department_id'] !== $deptId || $existing['kind'] !== $kind) {
        $conn->close();
        header('Location: ' . app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId));
        exit;
    }
    $st = $conn->prepare(
        'UPDATE employee_recoveries
         SET employee_id=?, department_id=?, txn_date=?, amount=?, installment_amount=?,
             start_month=?, start_year=?, remarks=?, status=?
         WHERE id=? AND kind=?'
    );
    $st->bind_param('iisddiissis', $employeeId, $deptId, $txnDate, $amount, $installment, $startMonth, $startYear, $remarks, $status, $id, $kind);
    $st->execute();
    $st->close();
} else {
    $st = $conn->prepare(
        'INSERT INTO employee_recoveries
            (kind, employee_id, department_id, txn_date, amount, installment_amount, start_month, start_year, remarks, status)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $st->bind_param('siisddiiss', $kind, $employeeId, $deptId, $txnDate, $amount, $installment, $startMonth, $startYear, $remarks, $status);
    $st->execute();
    $st->close();
}

advanceLoanPushDiary($conn, $employeeId, $startMonth, $startYear, true);
advanceLoanPushDiary($conn, $employeeId, 0, 0, true);
$conn->close();

header('Location: ' . app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId . '&msg=' . ($id > 0 ? 'updated' : 'added')));
exit;
