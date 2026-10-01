<?php
/**
 * One-off: verify employee login flow (CLI). Delete after use if desired.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/department_head_helper.php';

$conn = getDBConnection();
$res = $conn->query(
    "SELECT u.id, u.username, u.password, u.role, u.status, u.employee_id, u.custom_role_id,
            r.code AS role_code, r.name AS role_name, r.status AS role_status,
            e.employee_code, e.employee_name, e.status AS emp_status
     FROM users u
     LEFT JOIN roles r ON r.id = u.custom_role_id
     LEFT JOIN employees e ON e.id = u.employee_id
     WHERE u.role = 'employee' AND u.status = 1
     ORDER BY u.id ASC
     LIMIT 12"
);

$ok = 0;
$fail = 0;
echo "=== Employee portal login checks ===\n";
while ($user = $res->fetch_assoc()) {
    $loginAs = 'employee';
    $userRole = strtolower((string) ($user['role'] ?? ''));
    $roleName = (string) ($user['role_name'] ?? '');
    $roleOk = ($userRole === 'employee');
    $pass = (string) $user['password'];
    $issues = [];

    if (!$roleOk) {
        $issues[] = 'role not employee';
    }
    if (empty($user['custom_role_id']) || $roleName === '') {
        $issues[] = 'no active role assigned';
    }
    if ((int) ($user['role_status'] ?? 1) !== 1 && $roleName !== '') {
        $issues[] = 'role inactive';
    }
    if ($pass === '') {
        $issues[] = 'empty password';
    }

    // Simulate credential match
    $match = ($pass !== '');
    $dest = 'blocked';
    if ($issues === [] && $match) {
        $roleCode = strtoupper((string) ($user['role_code'] ?? ''));
        if ($roleCode === 'DEPT_HEAD') {
            $dest = 'dept employees list / hr dashboard';
        } elseif ($userRole === 'employee' || $roleCode === 'OFFICE_STAFF') {
            $dest = 'employee/dashboard.php';
        } else {
            $dest = 'hr/dashboard.php';
        }
        $ok++;
        echo "[OK] {$user['username']} / {$pass} → {$dest} ({$roleName}) · {$user['employee_name']}\n";
    } else {
        $fail++;
        echo "[FAIL] {$user['username']} · " . implode('; ', $issues ?: ['unknown']) . "\n";
    }
}
echo "---\nOK={$ok} FAIL={$fail}\n";

// Password pattern check for Office Staff samples
$sample = $conn->query(
    "SELECT e.employee_name, u.username, u.password
     FROM users u
     INNER JOIN employees e ON e.id = u.employee_id
     INNER JOIN roles r ON r.id = u.custom_role_id AND r.code = 'OFFICE_STAFF'
     WHERE u.role = 'employee' AND u.status = 1
     LIMIT 5"
);
echo "\n=== Office Staff password vs FirstName@123 pattern ===\n";
while ($s = $sample->fetch_assoc()) {
    $expected = employeePortalPasswordFromName($s['employee_name']);
    $same = hash_equals((string) $s['password'], $expected) ? 'MATCH' : 'DIFF';
    echo "{$s['username']}: stored={$s['password']} expected={$expected} [{$same}]\n";
}
$conn->close();
