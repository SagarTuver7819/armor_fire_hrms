<?php
/**
 * Save Role Assignment + Employee Portal Login (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('roles/assign.php'));
    exit;
}

$data = [
    'user_id' => $_POST['user_id'] ?? 0,
    'employee_id' => $_POST['employee_id'] ?? 0,
    'custom_role_id' => $_POST['custom_role_id'] ?? 0,
    'username' => $_POST['username'] ?? '',
    'password' => $_POST['password'] ?? '',
    'status' => $_POST['status'] ?? 1,
];

$result = assignEmployeePortalLogin($data);
if (!$result['ok']) {
    $_SESSION['role_assign_form'] = ['error' => $result['error'], 'data' => $data];
    $q = ((int) $data['user_id'] > 0) ? ('?id=' . (int) $data['user_id']) : ('?role_id=' . (int) $data['custom_role_id']);
    header('Location: ' . app_url('roles/assign.php' . $q));
    exit;
}

$roleId = (int) $data['custom_role_id'];
$msg = !empty($result['updated']) ? 'updated' : 'assigned';
header('Location: ' . app_url('roles/assign.php?role_id=' . $roleId . '&msg=' . $msg));
exit;
