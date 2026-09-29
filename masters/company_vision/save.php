<?php
/**
 * Save Vision / Mission / Core Values master
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('masters/company_vision/index.php'));
    exit;
}

$title = trim((string) ($_POST['title'] ?? 'Vision, Mission & Core Values'));
$body = [
    'heading' => $title,
    'leadership' => trim((string) ($_POST['leadership'] ?? '')),
    'vision' => trim((string) ($_POST['vision'] ?? '')),
    'mission' => companyContentLinesToArray($_POST['mission'] ?? ''),
    'core_values' => companyContentLinesToArray($_POST['core_values'] ?? ''),
];

$res = saveCompanyContent('vision', $title, $body, (int) ($_SESSION['user_id'] ?? 0));
if (!$res['ok']) {
    header('Location: ' . app_url('masters/company_vision/index.php?msg=error&err=' . urlencode($res['error'] ?? 'Save failed')));
    exit;
}
header('Location: ' . app_url('masters/company_vision/index.php?msg=saved'));
exit;
