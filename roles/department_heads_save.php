<?php
/**
 * Save Department Head (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireDepartmentHeadsManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('roles/department_heads.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$empId = (int) ($_POST['employee_id'] ?? 0);
$result = setDepartmentHead($deptId, $empId, (int) ($_SESSION['user_id'] ?? 0));
if (!$result['ok']) {
    header('Location: ' . app_url('roles/department_heads.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Save failed.')));
    exit;
}
header('Location: ' . app_url('roles/department_heads.php?msg=saved'));
exit;
