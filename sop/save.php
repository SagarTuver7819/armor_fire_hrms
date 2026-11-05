<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/sop_helper.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
requireAccess('departments', $id > 0 ? 'edit' : 'add', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
$listUrl = app_url('sop/index.php?department_id=' . $deptId);
if (!$department || !cncSopIsCncDepartment($department)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$payload = [
    'id' => $id,
    'department_id' => $deptId,
    'title' => $_POST['title'] ?? '',
    'sop_date' => $_POST['sop_date'] ?? '',
    'subject' => $_POST['subject'] ?? '',
    'department_name' => $_POST['department_name'] ?? '',
    'body' => $_POST['body'] ?? '',
    'signer_name' => $_POST['signer_name'] ?? '',
    'signature_data' => $_POST['signature_data'] ?? '',
    'clear_signature' => !empty($_POST['clear_signature']) && (string) $_POST['clear_signature'] === '1',
    'created_by' => (int) ($_SESSION['user_id'] ?? 0),
];

$conn = getDBConnection();
$result = cncSopSave($conn, $payload);
$conn->close();

if (empty($result['ok'])) {
    $_SESSION['sop_form_error'] = $result['error'] ?? 'Could not save SOP.';
    $_SESSION['sop_form_data'] = $payload;
    $back = app_url('sop/edit.php?department_id=' . $deptId . ($id > 0 ? '&id=' . $id : ''));
    header('Location: ' . $back);
    exit;
}

$msg = $id > 0 ? 'updated' : 'added';
header('Location: ' . app_url('sop/print.php?id=' . (int) $result['id'] . '&department_id=' . $deptId . '&msg=' . $msg));
exit;
