<?php
/**
 * Finalize & lock salary register → sends it for HR Head check → Accounts → NEFT final
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

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

requirePayrollRegisterAccess($deptId);

$qs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
]);

if ($action === 'unlock') {
    $lockBefore = getSalaryRegisterLock($month, $year, $type, $deptId);
    $res = unlockSalaryRegister($month, $year, $type, $deptId);
    if (!empty($res['ok']) && $lockBefore) {
        payrollApprovalLog($lockBefore, 'unlocked_admin');
    }
    $msg = !empty($res['ok']) ? 'unlocked' : 'error';
    $err = $res['error'] ?? '';
    header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=' . urlencode($msg) . ($err ? '&lock_err=' . urlencode($err) : '')));
    exit;
}

if (!payrollCanLock()) {
    header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=error&lock_err=' . urlencode('Only the assigned Payroll Manager (Maker) can lock the salary register.')));
    exit;
}

$result = lockSalaryRegister($month, $year, $type, $deptId, (int) ($_SESSION['user_id'] ?? 0));
if (!empty($result['ok'])) {
    header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=locked'));
    exit;
}

header('Location: ' . app_url('payroll/register.php?' . $qs . '&lock_msg=error&lock_err=' . urlencode($result['error'] ?? 'Lock failed')));
exit;
