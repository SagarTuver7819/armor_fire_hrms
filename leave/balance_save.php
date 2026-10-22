<?php
/**
 * Manual leave Add / Adjust (type-wise). Does not overwrite system accrual.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireLogin();

$deptId = (int) ($_POST['department_id'] ?? 0);
$employeeId = (int) ($_POST['employee_id'] ?? 0);
$year = (int) ($_POST['year'] ?? date('Y'));
requireAccess('leave', 'edit', $deptId);

if ($employeeId <= 0 || $year < 2000) {
    header('Location: ' . app_url('leave/balance.php?msg=invalid'));
    exit;
}

$ids = $_POST['balance_id'] ?? [];
$adds = $_POST['add_days'] ?? [];
$adjusts = $_POST['adjust_days'] ?? [];
$notes = $_POST['note'] ?? [];
$userId = (int) ($_SESSION['user_id'] ?? 0);

$conn = getDBConnection();
ensureLeaveTables($conn);

$sel = $conn->prepare(
    'SELECT id, leave_type_id, manual_add_days, adjusted_days
     FROM employee_leave_balances
     WHERE id = ? AND employee_id = ? AND year_no = ? LIMIT 1'
);
$upd = $conn->prepare(
    'UPDATE employee_leave_balances
     SET manual_add_days = ?, adjusted_days = ?
     WHERE id = ? AND employee_id = ? AND year_no = ?'
);
$log = $conn->prepare(
    'INSERT INTO leave_balance_adjustments
        (employee_id, leave_type_id, year_no, action_type, days, note, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$changed = 0;
foreach ($ids as $i => $balId) {
    $balId = (int) $balId;
    $add = round((float) ($adds[$i] ?? 0), 2);
    $adj = round((float) ($adjusts[$i] ?? 0), 2);
    $note = trim((string) ($notes[$i] ?? ''));
    if ($balId <= 0 || ($add == 0.0 && $adj == 0.0)) {
        continue;
    }
    $sel->bind_param('iii', $balId, $employeeId, $year);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) {
        continue;
    }
    $ltId = (int) $row['leave_type_id'];
    $newAdd = round((float) $row['manual_add_days'] + $add, 2);
    $newAdj = round((float) $row['adjusted_days'] + $adj, 2);
    $upd->bind_param('ddiii', $newAdd, $newAdj, $balId, $employeeId, $year);
    $upd->execute();
    if ($add != 0.0) {
        $action = 'Add';
        $log->bind_param('iiisdsi', $employeeId, $ltId, $year, $action, $add, $note, $userId);
        $log->execute();
    }
    if ($adj != 0.0) {
        $action = 'Adjust';
        $log->bind_param('iiisdsi', $employeeId, $ltId, $year, $action, $adj, $note, $userId);
        $log->execute();
    }
    $changed++;
}
$sel->close();
$upd->close();
$log->close();
$conn->close();

header('Location: ' . app_url('leave/balance.php?' . http_build_query([
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'year' => $year,
    'tab' => 'balance',
    'msg' => $changed > 0 ? 'saved' : 'none',
])));
exit;
