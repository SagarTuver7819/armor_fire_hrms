<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/permission_helper.php';
require __DIR__ . '/includes/department_head_helper.php';

ensureRoleTables();
ensureDepartmentHeadTables();

$conn = getDBConnection();
$emp = $conn->query("SELECT id, employee_code, department_id FROM employees WHERE employee_code = 'EMP0012' LIMIT 1")->fetch_assoc();
$user = $conn->query("SELECT id, username, custom_role_id, employee_id FROM users WHERE role='employee' AND (username='EMP0012' OR employee_id=" . (int)($emp['id']??0) . ") LIMIT 1")->fetch_assoc();
$role = $conn->query("SELECT id FROM roles WHERE code='DEPT_HEAD' LIMIT 1")->fetch_assoc();
$conn->close();

echo "EMP: "; print_r($emp);
echo "USER: "; print_r($user);
echo "DEPT_HEAD role: "; print_r($role);

if (!$emp || !$role) {
    echo "SKIP test — missing emp/role\n";
    exit(0);
}

$result = assignEmployeePortalLogin([
    'user_id' => 0, // simulate fresh form (bug case)
    'employee_id' => (int) $emp['id'],
    'custom_role_id' => (int) $role['id'],
    'username' => (string) ($emp['employee_code'] ?? 'EMP0012'),
    'password' => '', // optional on update
    'status' => 1,
]);
echo "RESULT: ";
print_r($result);

$conn = getDBConnection();
$after = $conn->query("SELECT id, username, custom_role_id, employee_id, status FROM users WHERE employee_id=" . (int)$emp['id'] . " AND role='employee' LIMIT 1")->fetch_assoc();
$dh = $conn->query("SELECT * FROM department_heads WHERE employee_id=" . (int)$emp['id'] . " AND status=1 LIMIT 1")->fetch_assoc();
$conn->close();
echo "AFTER USER: "; print_r($after);
echo "DEPT HEAD ROW: "; print_r($dh ?: ['none' => true]);
