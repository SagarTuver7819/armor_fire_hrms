<?php
/**
 * Delete recruitment application (Admin / HR / recruitment delete permission)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !canAccess('recruitment', 'delete')) {
    header('Location: ' . app_url('recruitment/index.php?msg=delete_error&err=' . rawurlencode('You do not have permission to delete applications.')));
    exit;
}

$res = deleteRecruitmentApplication((int) ($_GET['id'] ?? 0));
if (empty($res['ok'])) {
    header('Location: ' . app_url('recruitment/index.php?msg=delete_error&err=' . rawurlencode($res['error'] ?? 'Delete failed.')));
    exit;
}

header('Location: ' . app_url('recruitment/index.php?msg=deleted&app=' . rawurlencode($res['application_no'] ?? '')));
exit;
