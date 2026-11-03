<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/advance_loan_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireLogin();

$kind = advanceLoanNormalizeKind($_GET['type'] ?? 'Advance');
$deptId = (int) ($_GET['department_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
requireAccess('payroll', 'edit', $deptId);

$conn = getDBConnection();
$row = $id > 0 ? advanceLoanGet($conn, $id) : null;
if ($row && (int) $row['department_id'] === $deptId && $row['kind'] === $kind) {
    $employeeId = (int) $row['employee_id'];
    $startMonth = (int) ($row['start_month'] ?? 0);
    $startYear = (int) ($row['start_year'] ?? 0);
    $txnTs = strtotime((string) ($row['txn_date'] ?? ''));
    $txnMonth = $txnTs ? (int) date('n', $txnTs) : 0;
    $txnYear = $txnTs ? (int) date('Y', $txnTs) : 0;
    $st = $conn->prepare('DELETE FROM employee_recoveries WHERE id = ? AND department_id = ? AND kind = ?');
    $st->bind_param('iis', $id, $deptId, $kind);
    $st->execute();
    $st->close();
    // Force diary clear for salary month (start + txn) so register does not keep stale Adv.
    if ($startMonth > 0 && $startYear > 0) {
        advanceLoanPushDiary($conn, $employeeId, $startMonth, $startYear, true);
    }
    if ($txnMonth > 0 && $txnYear > 0 && ($txnMonth !== $startMonth || $txnYear !== $startYear)) {
        advanceLoanPushDiary($conn, $employeeId, $txnMonth, $txnYear, true);
    }
    advanceLoanPushDiary($conn, $employeeId, 0, 0, true);
}
$conn->close();

header('Location: ' . app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId . '&msg=deleted'));
exit;
