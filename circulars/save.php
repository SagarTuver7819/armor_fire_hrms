<?php
/**
 * Save Circular (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/circular_helper.php';

requireStaff();
require_once __DIR__ . '/../includes/permission_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('circulars/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
requireAccess('circulars', $id > 0 ? 'edit' : 'add');
$editUrl = app_url('circulars/edit.php' . ($id > 0 ? ('?id=' . $id) : ''));

$contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLen > 0 && empty($_POST) && empty($_FILES)) {
    $_SESSION['circular_form'] = [
        'error' => 'Upload is too large for the server. Please scan again as a smaller PDF (under 10 MB).',
        'data' => [],
    ];
    header('Location: ' . $editUrl);
    exit;
}

$title = $_POST['circular_title'] ?? ($_POST['title'] ?? '');
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
    'circular_no' => $_POST['circular_no'] ?? '',
    'circular_date' => $_POST['circular_date'] ?? '',
    'added_date' => $_POST['added_date'] ?? '',
    'remarks' => $_POST['remarks'] ?? '',
    'apply_all_departments' => $applyAll,
    'department_ids' => $departmentIds,
];

$result = saveCircular(
    [
        'id' => $id,
        'title' => $title,
        'circular_no' => $_POST['circular_no'] ?? '',
        'circular_date' => $_POST['circular_date'] ?? '',
        'added_date' => $_POST['added_date'] ?? '',
        'remarks' => $_POST['remarks'] ?? '',
        'apply_all_departments' => $applyAll,
        'department_ids' => $departmentIds,
        'created_by' => (int) ($_SESSION['user_id'] ?? 0),
    ],
    $_FILES['circular_pdf'] ?? null
);

if (!$result['ok']) {
    $_SESSION['circular_form'] = [
        'error' => $result['error'] ?? 'Save failed.',
        'data' => $flashData,
    ];
    header('Location: ' . $editUrl);
    exit;
}

unset($_SESSION['circular_form']);
$msg = $id > 0 ? 'updated' : 'added';
header('Location: ' . app_url('circulars/index.php?msg=' . $msg));
exit;
