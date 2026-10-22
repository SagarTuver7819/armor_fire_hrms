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
    $st = $conn->prepare('DELETE FROM employee_recoveries WHERE id = ? AND department_id = ? AND kind = ?');
    $st->bind_param('iis', $id, $deptId, $kind);
    $st->execute();
    $st->close();
    advanceLoanPushDiary($conn, $employeeId, (int) $row['start_month'], (int) $row['start_year'], true);
    advanceLoanPushDiary($conn, $employeeId, 0, 0, true);
}
$conn->close();

header('Location: ' . app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId . '&msg=deleted'));
exit;
