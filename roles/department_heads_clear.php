<?php
/**
 * Clear Department Head (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireDepartmentHeadsManager();

$deptId = (int) ($_GET['department_id'] ?? 0);
$result = clearDepartmentHead($deptId);
if (!$result['ok']) {
    header('Location: ' . app_url('roles/department_heads.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Clear failed.')));
    exit;
}
header('Location: ' . app_url('roles/department_heads.php?msg=cleared'));
exit;
