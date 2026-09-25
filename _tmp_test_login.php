<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';
require_once __DIR__ . '/includes/auth.php';

ensureRoleTables();
$conn = getDBConnection();
$user = 'EMP0001';
$pass = 'name@123';
$stmt = $conn->prepare(
    "SELECT id, username, password, full_name, role, department_id, custom_role_id, employee_id, status
     FROM users WHERE username = ? AND status = 1 LIMIT 1"
);
$stmt->bind_param('s', $user);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['password'] !== $pass) {
    echo "LOGIN FAIL\n";
    exit(1);
}
$_SESSION['user_id'] = $row['id'];
$_SESSION['username'] = $row['username'];
$_SESSION['full_name'] = $row['full_name'];
$_SESSION['role'] = $row['role'];
$_SESSION['department_id'] = $row['department_id'];
$_SESSION['employee_id'] = (int) $row['employee_id'];
$_SESSION['custom_role_id'] = (int) $row['custom_role_id'];
loadUserPermissionsIntoSession((int) $row['custom_role_id'], true);
refreshHeadedDepartmentsSession();

echo "LOGIN OK {$row['full_name']} (@{$row['username']}) role={$row['role']} code={$_SESSION['role_code']}\n";
echo "leave view=" . (canAccess('leave', 'view') ? 'Y' : 'N') . " add=" . (canAccess('leave', 'add') ? 'Y' : 'N') . " edit=" . (canAccess('leave', 'edit') ? 'Y' : 'N') . "\n";
echo "employees view=" . (canAccess('employees', 'view') ? 'Y' : 'N') . " payroll=" . (canAccess('payroll', 'view') ? 'Y' : 'N') . " masters=" . (canAccess('masters', 'view') ? 'Y' : 'N') . "\n";
echo "isOfficeStaff=" . (isOfficeStaffRole() ? 'Y' : 'N') . "\n";

// Also check Rohan EMP0010 if exists
$stmt = $conn->prepare("SELECT username, custom_role_id, r.code FROM users u LEFT JOIN roles r ON r.id=u.custom_role_id WHERE u.username='EMP0010' OR u.employee_id=(SELECT id FROM employees WHERE employee_code='EMP0010' LIMIT 1) LIMIT 1");
// simpler:
$r2 = $conn->query("SELECT u.username, r.code, r.name FROM users u LEFT JOIN roles r ON r.id=u.custom_role_id WHERE u.username='EMP0010' LIMIT 1");
if ($r2 && ($x = $r2->fetch_assoc())) {
    echo "EMP0010 kept role: {$x['code']} ({$x['name']}) — skipped from Office Staff overwrite\n";
}
$conn->close();
