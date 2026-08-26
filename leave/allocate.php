<?php
/**
 * Allocate yearly leave balances from Leave Master
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();

$deptId = (int) ($_POST['department_id'] ?? 0);
$year = (int) ($_POST['year'] ?? date('Y'));

$result = leaveAllocateYearlyBalances($year, $deptId, true);

header('Location: ' . app_url('leave/balance.php?' . http_build_query([
    'department_id' => $deptId,
    'year' => $year,
    'msg' => 'allocated',
    'created' => $result['created'],
    'updated' => $result['updated'],
])));
exit;
