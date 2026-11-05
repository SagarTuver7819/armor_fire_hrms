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
requireAccess('departments', 'delete', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department || !cncSopIsCncDepartment($department)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}
$conn = getDBConnection();
cncSopDelete($conn, $id, $deptId);
$conn->close();
header('Location: ' . app_url('sop/index.php?department_id=' . $deptId . '&msg=deleted'));
exit;
