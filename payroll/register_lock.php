<?php
/**
 * Finalize & lock salary register → creates NEFT sheet
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('payroll/register.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$type = (string) ($_POST['type'] ?? 'salary');
$action = (string) ($_POST['action'] ?? 'lock');

$qs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
]);

if ($action === 'unlock') {
    $res = unlockSalaryRegister($month, $year, $type, $deptId);
    $msg = !empty($res['ok']) ? 'unlocked' : 'error';
    $err = $res['error'] ?? '';
    header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=' . urlencode($msg) . ($err ? '&lock_err=' . urlencode($err) : '')));
    exit;
}

$result = lockSalaryRegister($month, $year, $type, $deptId, (int) ($_SESSION['user_id'] ?? 0));
if (!empty($result['ok'])) {
    header('Location: ' . app_url('payroll/neft.php?' . $qs . '&lock_msg=locked&neft_count=' . (int) ($result['neft_count'] ?? 0)));
    exit;
}

header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=error&lock_err=' . urlencode($result['error'] ?? 'Lock failed')));
exit;
