<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/permission_helper.php';

ensureRoleTables();
$conn = getDBConnection();
$row = $conn->query(
    "SELECT u.id, u.username, u.password, u.custom_role_id, u.employee_id, u.status
     FROM users u
     INNER JOIN employees e ON e.id = u.employee_id
     WHERE u.role='employee' AND e.employee_code='EMP0170' LIMIT 1"
)->fetch_assoc();
$conn->close();

if (!$row) {
    echo "EMP0170 login not found\n";
    exit(1);
}
$uid = (int) $row['id'];
echo "Before revoke id=$uid\n";
$r = revokeEmployeePortalLogin($uid);
print_r($r);
echo getEmployeePortalUserById($uid) ? "FAIL still there\n" : "Revoke OK\n";

$r2 = assignEmployeePortalLogin([
    'user_id' => 0,
    'employee_id' => (int) $row['employee_id'],
    'custom_role_id' => (int) $row['custom_role_id'],
    'username' => (string) $row['username'],
    'password' => (string) $row['password'], // restore same hash as plaintext store? may be hashed
    'status' => (int) $row['status'],
]);
// password might already be hashed in DB - assign may double-hash. Restore via SQL instead.
$conn = getDBConnection();
if (!empty($r2['ok'])) {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param('si', $row['password'], $r2['id']);
    $stmt->execute();
    $stmt->close();
    echo "Restored EMP0170 login id=" . $r2['id'] . "\n";
} else {
    // fallback direct insert
    $stmt = $conn->prepare(
        "INSERT INTO users (username, password, full_name, role, custom_role_id, employee_id, status)
         SELECT ?, ?, employee_name, 'employee', ?, id, ? FROM employees WHERE id = ?"
    );
    $un = $row['username'];
    $pw = $row['password'];
    $cr = (int) $row['custom_role_id'];
    $st = (int) $row['status'];
    $eid = (int) $row['employee_id'];
    $stmt->bind_param('ssiii', $un, $pw, $cr, $st, $eid);
    $stmt->execute();
    echo "Restored via insert: " . ($stmt->affected_rows > 0 ? 'OK' : 'FAIL') . " err=" . ($r2['error'] ?? '') . "\n";
    $stmt->close();
}
$conn->close();
echo "DONE\n";
