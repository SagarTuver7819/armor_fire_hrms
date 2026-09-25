<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/permission_helper.php';
require __DIR__ . '/includes/department_head_helper.php';

ensureRoleTables();
ensureDepartmentHeadTables();

$conn = getDBConnection();
$emp = $conn->query("SELECT id, employee_code, department_id FROM employees WHERE employee_code='EMP0012' LIMIT 1")->fetch_assoc();
$office = $conn->query("SELECT id FROM roles WHERE code='OFFICE_STAFF' LIMIT 1")->fetch_assoc();
$deptHead = $conn->query("SELECT id FROM roles WHERE code='DEPT_HEAD' LIMIT 1")->fetch_assoc();
$conn->close();

$empId = (int) $emp['id'];
$officeId = (int) $office['id'];
$dhRoleId = (int) $deptHead['id'];

echo "=== UPDATE: Dept Head -> Office Staff ===\n";
$r1 = assignEmployeePortalLogin([
    'user_id' => 0,
    'employee_id' => $empId,
    'custom_role_id' => $officeId,
    'username' => 'EMP0012',
    'password' => '',
    'status' => 1,
]);
print_r($r1);
$conn = getDBConnection();
$u = $conn->query("SELECT custom_role_id FROM users WHERE employee_id=$empId AND role='employee'")->fetch_assoc();
$dh = $conn->query("SELECT status FROM department_heads WHERE employee_id=$empId")->fetch_assoc();
$conn->close();
echo "role_id=" . ($u['custom_role_id'] ?? 'null') . " dh_status=" . ($dh['status'] ?? 'none') . "\n";

echo "=== UPDATE: Office Staff -> Dept Head ===\n";
$r2 = assignEmployeePortalLogin([
    'user_id' => 0,
    'employee_id' => $empId,
    'custom_role_id' => $dhRoleId,
    'username' => 'EMP0012',
    'password' => '',
    'status' => 1,
]);
print_r($r2);
$conn = getDBConnection();
$u = $conn->query("SELECT id, custom_role_id FROM users WHERE employee_id=$empId AND role='employee'")->fetch_assoc();
$dh = $conn->query("SELECT status, department_id FROM department_heads WHERE employee_id=$empId AND status=1")->fetch_assoc();
$conn->close();
echo "role_id=" . ($u['custom_role_id'] ?? 'null') . " dh=" . print_r($dh ?: ['none'=>1], true);

echo "=== EDIT by id (password change optional) ===\n";
$r3 = assignEmployeePortalLogin([
    'user_id' => (int) $u['id'],
    'employee_id' => $empId,
    'custom_role_id' => $dhRoleId,
    'username' => 'EMP0012',
    'password' => '',
    'status' => 1,
]);
print_r($r3);

echo "=== CREATE + REVOKE temp login ===\n";
$conn = getDBConnection();
$free = $conn->query(
    "SELECT e.id, e.employee_code FROM employees e
     LEFT JOIN users u ON u.employee_id = e.id AND u.role='employee'
     WHERE e.status=1 AND u.id IS NULL
     LIMIT 1"
)->fetch_assoc();
$conn->close();
if (!$free) {
    echo "No free employee for create/revoke test\n";
} else {
    $uname = 'TMPCRUD_' . $free['employee_code'];
    $r4 = assignEmployeePortalLogin([
        'user_id' => 0,
        'employee_id' => (int) $free['id'],
        'custom_role_id' => $officeId,
        'username' => $uname,
        'password' => 'Temp@123',
        'status' => 1,
    ]);
    echo "CREATE: "; print_r($r4);
    $newId = (int) ($r4['id'] ?? 0);
    $r5 = revokeEmployeePortalLogin($newId);
    echo "REVOKE: "; print_r($r5);
    $gone = getEmployeePortalUserById($newId);
    echo $gone ? "FAIL still exists\n" : "REVOKE OK gone\n";
}

echo "=== DUPLICATE username should fail ===\n";
$conn = getDBConnection();
$other = $conn->query(
    "SELECT e.id FROM employees e
     INNER JOIN users u ON u.employee_id=e.id AND u.role='employee'
     WHERE e.employee_code <> 'EMP0012' LIMIT 1"
)->fetch_assoc();
$conn->close();
$r6 = assignEmployeePortalLogin([
    'user_id' => 0,
    'employee_id' => (int) $other['id'],
    'custom_role_id' => $officeId,
    'username' => 'EMP0012', // taken by Vikram
    'password' => 'x',
    'status' => 1,
]);
echo ($r6['ok'] ? 'FAIL allowed dup' : 'OK blocked: ' . ($r6['error'] ?? '')) . "\n";

echo "DONE\n";
