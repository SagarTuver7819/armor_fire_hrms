<?php
/**
 * Save Custom Role (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('roles/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$data = [
    'name' => $_POST['name'] ?? '',
    'code' => $_POST['code'] ?? '',
    'description' => $_POST['description'] ?? '',
    'status' => $_POST['status'] ?? 1,
];

$result = saveRole($data, $id);
if (!$result['ok']) {
    $_SESSION['role_form'] = ['error' => $result['error'], 'data' => $data];
    $q = $id > 0 ? ('?id=' . $id) : '';
    header('Location: ' . app_url('roles/edit.php' . $q));
    exit;
}

$msg = $id > 0 ? 'updated' : 'added';
$newId = (int) ($result['id'] ?? $id);
if ($id <= 0) {
    header('Location: ' . app_url('roles/permissions.php?role_id=' . $newId . '&msg=added'));
    exit;
}
header('Location: ' . app_url('roles/index.php?msg=' . $msg));
exit;
