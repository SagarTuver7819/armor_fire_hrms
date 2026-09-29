<?php
/**
 * Save interview result / status
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
    header('Location: ' . app_url('recruitment/interviews.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$res = saveRecruitmentInterviewUpdate($id, [
    'status' => $_POST['status'] ?? '',
    'interview_mode' => $_POST['interview_mode'] ?? '',
    'interview_date' => $_POST['interview_date'] ?? '',
    'interview_notes' => $_POST['interview_notes'] ?? '',
    'awaited_with' => $_POST['awaited_with'] ?? '',
    'not_selected_reason' => $_POST['not_selected_reason'] ?? '',
    'hr_remarks' => $_POST['hr_remarks'] ?? '',
], (int) ($_SESSION['user_id'] ?? 0));

if (empty($res['ok'])) {
    header('Location: ' . app_url(
        'recruitment/interview.php?id=' . $id . '&msg=error&err=' . urlencode($res['error'] ?? 'Update failed')
    ));
    exit;
}

header('Location: ' . app_url('recruitment/interview.php?id=' . $id . '&msg=saved'));
exit;
