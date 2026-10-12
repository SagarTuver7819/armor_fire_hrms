<?php
/**
 * Mark payroll approval notification read → open its register
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();

$uid = (int) ($_SESSION['user_id'] ?? 0);
if (isset($_GET['all'])) {
    markPayrollNotificationRead(0, $uid);
    header('Location: ' . app_url('payroll/approvals.php'));
    exit;
}

$url = markPayrollNotificationRead((int) ($_GET['id'] ?? 0), $uid);
header('Location: ' . app_url($url ?: 'payroll/approvals.php'));
exit;
