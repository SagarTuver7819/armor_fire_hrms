<?php
/**
 * Save Policy (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('policies/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$editUrl = app_url('policies/edit.php' . ($id > 0 ? ('?id=' . $id) : ''));

$contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLen > 0 && empty($_POST) && empty($_FILES)) {
    $_SESSION['policy_form'] = [
        'error' => 'Upload is too large for the server. Please scan again as a smaller PDF (under 10 MB).',
        'data' => [],
    ];
    header('Location: ' . $editUrl);
    exit;
}

$title = $_POST['policy_title'] ?? ($_POST['title'] ?? '');
$departmentIds = isset($_POST['department_ids']) && is_array($_POST['department_ids'])
    ? $_POST['department_ids']
    : [];

// Select2 may send "all" as a selected value
$applyAll = 0;
foreach ($departmentIds as $v) {
    if ((string) $v === 'all' || (string) $v === '0') {
        $applyAll = 1;
        break;
    }
}
if (!empty($_POST['apply_all_departments'])) {
    $applyAll = 1;
}
if ($applyAll) {
    $departmentIds = [];
}

$flashData = [
    'title' => $title,
    'policy_no' => $_POST['policy_no'] ?? '',
    'policy_date' => $_POST['policy_date'] ?? '',
    'added_date' => $_POST['added_date'] ?? '',
    'remarks' => $_POST['remarks'] ?? '',
    'apply_all_departments' => $applyAll,
    'department_ids' => $departmentIds,
];

$result = savePolicy(
    [
        'id' => $id,
        'title' => $title,
        'policy_no' => $_POST['policy_no'] ?? '',
        'policy_date' => $_POST['policy_date'] ?? '',
        'added_date' => $_POST['added_date'] ?? '',
        'remarks' => $_POST['remarks'] ?? '',
        'apply_all_departments' => $applyAll,
        'department_ids' => $departmentIds,
        'created_by' => (int) ($_SESSION['user_id'] ?? 0),
    ],
    $_FILES['policy_pdf'] ?? null
);

if (!$result['ok']) {
    $_SESSION['policy_form'] = [
        'error' => $result['error'] ?? 'Save failed.',
        'data' => $flashData,
    ];
    header('Location: ' . $editUrl);
    exit;
}

unset($_SESSION['policy_form']);
$msg = $id > 0 ? 'updated' : 'added';
header('Location: ' . app_url('policies/index.php?msg=' . $msg));
exit;


