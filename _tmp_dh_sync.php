<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/department_head_helper.php';
$conn = getDBConnection();
ensureDepartmentHeadTables($conn);
$r = $conn->query("SHOW TABLES LIKE 'department_heads'");
echo 'department_heads: ' . ($r && $r->num_rows ? 'OK' : 'MISSING') . PHP_EOL;
$roles = $conn->query("SELECT code, name FROM roles WHERE code IN ('HR_HEAD','PAYROLL_HEAD','DEPT_HEAD','OFFICE_STAFF') ORDER BY code");
while ($row = $roles->fetch_assoc()) {
    echo $row['code'] . ' => ' . $row['name'] . PHP_EOL;
}
$conn->close();
