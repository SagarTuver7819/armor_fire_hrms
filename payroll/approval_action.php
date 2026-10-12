<?php
/**
 * HR Head check / Accounts done / Send back for a locked salary register
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('payroll/approvals.php'));
    exit;
}

$lockId = (int) ($_POST['lock_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$remarks = (string) ($_POST['remarks'] ?? '');

$lock = getSalaryRegisterLockById($lockId);
$back = $lock ? payrollLockRegisterUrl($lock) : 'payroll/approvals.php?';
$res = payrollApprovalAct($lockId, $action, $remarks);

$sep = strpos($back, '?') === false ? '?' : '&';
if (!empty($res['ok'])) {
    header('Location: ' . app_url($back . $sep . 'lock_msg=' . urlencode('appr_' . $res['status'])));
} else {
    header('Location: ' . app_url($back . $sep . 'lock_msg=error&lock_err=' . urlencode($res['error'] ?? 'Action failed')));
}
exit;
