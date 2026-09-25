<?php
/**
 * Save Staff User (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('users/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$data = [
    'username' => $_POST['username'] ?? '',
    'full_name' => $_POST['full_name'] ?? '',
    'password' => $_POST['password'] ?? '',
    'role' => $_POST['role'] ?? 'hr',
    'department_id' => $_POST['department_id'] ?? 0,
    'custom_role_id' => $_POST['custom_role_id'] ?? 0,
    'status' => $_POST['status'] ?? 1,
];

$result = saveStaffUser($data, $id);
if (!$result['ok']) {
    $_SESSION['staff_user_form'] = ['error' => $result['error'], 'data' => $data];
    $q = $id > 0 ? ('?id=' . $id) : '';
    header('Location: ' . app_url('users/edit.php' . $q));
    exit;
}

header('Location: ' . app_url('users/index.php?msg=' . ($id > 0 ? 'updated' : 'added')));
exit;
