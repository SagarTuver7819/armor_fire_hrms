<?php
/**
 * Save follow-up call log
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
$res = saveRecruitmentFollowup($id, [
    'call_at' => $_POST['call_at'] ?? '',
    'next_followup_at' => $_POST['next_followup_at'] ?? '',
    'notes' => $_POST['notes'] ?? '',
    'outcome' => $_POST['outcome'] ?? '',
], (int) ($_SESSION['user_id'] ?? 0));

if (empty($res['ok'])) {
    header('Location: ' . app_url(
        'recruitment/interview.php?id=' . $id . '&msg=error&err=' . urlencode($res['error'] ?? 'Save failed')
    ));
    exit;
}

header('Location: ' . app_url('recruitment/interview.php?id=' . $id . '&msg=followup'));
exit;
