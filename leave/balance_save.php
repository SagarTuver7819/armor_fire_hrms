<?php
/**
 * Save manual leave balance adjustments
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();

$deptId = (int) ($_POST['department_id'] ?? 0);
$employeeId = (int) ($_POST['employee_id'] ?? 0);
$year = (int) ($_POST['year'] ?? date('Y'));
$ids = $_POST['balance_id'] ?? [];
$opening = $_POST['opening_days'] ?? [];
$credited = $_POST['credited_days'] ?? [];
$adjusted = $_POST['adjusted_days'] ?? [];

$conn = getDBConnection();
ensureLeaveTables($conn);
$stmt = $conn->prepare(
    'UPDATE employee_leave_balances
     SET opening_days = ?, credited_days = ?, adjusted_days = ?
     WHERE id = ? AND employee_id = ? AND year_no = ?'
);

foreach ($ids as $i => $balId) {
    $balId = (int) $balId;
    $op = (float) ($opening[$i] ?? 0);
    $cr = (float) ($credited[$i] ?? 0);
    $ad = (float) ($adjusted[$i] ?? 0);
    $stmt->bind_param('dddiii', $op, $cr, $ad, $balId, $employeeId, $year);
    $stmt->execute();
}
$stmt->close();
$conn->close();

header('Location: ' . app_url('leave/balance.php?' . http_build_query([
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'year' => $year,
    'msg' => 'saved',
])));
exit;
