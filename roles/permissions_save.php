<?php
/**
 * Save Role Permission Matrix (Admin)
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

$roleId = (int) ($_POST['role_id'] ?? 0);
$departmentId = (int) ($_POST['department_id'] ?? 0);
$raw = $_POST['perms'] ?? [];
if (!is_array($raw)) {
    $raw = [];
}

$perms = [];
foreach (getPermissionModules() as $key => $mod) {
    $row = is_array($raw[$key] ?? null) ? $raw[$key] : [];
    $perms[$key] = [
        'view'   => !empty($row['view']) ? 1 : 0,
        'add'    => !empty($row['add']) ? 1 : 0,
        'edit'   => !empty($row['edit']) ? 1 : 0,
        'delete' => !empty($row['delete']) ? 1 : 0,
    ];
}

$result = saveRolePermissionMatrix($roleId, $departmentId, $perms);
$q = 'role_id=' . $roleId . '&department_id=' . $departmentId;
if (!$result['ok']) {
    header('Location: ' . app_url('roles/permissions.php?' . $q . '&msg=error&err=' . rawurlencode($result['error'] ?? 'Save failed.')));
    exit;
}
header('Location: ' . app_url('roles/permissions.php?' . $q . '&msg=perms'));
exit;
