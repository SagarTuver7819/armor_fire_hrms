<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/master_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$deptId = (int) ($_GET['department_id'] ?? 0);
$conn = getDBConnection();
ensureMasterTables($conn);
$rows = getSubDepartmentsByDepartment($deptId, $conn);
$conn->close();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r['id'],
        'name' => (string) $r['name'],
    ];
}
echo json_encode($out);
