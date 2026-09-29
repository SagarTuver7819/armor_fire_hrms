<?php
/**
 * Update recruitment application status
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'edit')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('recruitment/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
$remarks = trim((string) ($_POST['hr_remarks'] ?? ''));

$res = updateRecruitmentStatus($id, $status, $remarks);
if (empty($res['ok'])) {
    header('Location: ' . app_url('recruitment/view.php?id=' . $id . '&msg=error'));
    exit;
}
header('Location: ' . app_url('recruitment/view.php?id=' . $id . '&msg=saved'));
exit;
