<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

echo "DB: armor search\n";
$patterns = ['%jitesh%', '%JITESH%', '%jasani%', '%Jasani%', '%JITESH%', '%jasani%', '%Jiten%', '%jatin%'];
$sql = "SELECT e.id, e.employee_code, e.employee_name, e.status, e.department_id, d.department_name,
               e.week_off_day, e.week_off_benefits, e.holiday_benefits, e.pay_type
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE LOWER(e.employee_name) LIKE '%jitesh%'
           OR LOWER(e.employee_name) LIKE '%jasani%'
           OR LOWER(e.employee_name) LIKE '%jiten%'
           OR LOWER(e.employee_code) LIKE '%jitesh%'
        ORDER BY e.id";
$r = $conn->query($sql);
$n = 0;
while ($row = $r->fetch_assoc()) {
    $n++;
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
echo "matches={$n}\n\n";

echo "All ACCOUNTS AND FINANCE (any status):\n";
$r2 = $conn->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.status, e.week_off_day, e.week_off_benefits
     FROM employees e
     JOIN departments d ON d.id = e.department_id
     WHERE d.department_name LIKE '%ACCOUNT%'
     ORDER BY e.status DESC, e.employee_name"
);
while ($row = $r2->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nEmployees with week_off count hint — salary register lock snap?\n";
$t = $conn->query("SHOW TABLES LIKE 'salary_register%'");
while ($x = $t->fetch_array()) {
    echo "table: {$x[0]}\n";
}

// Search locks for Jitesh name in JSON
$locks = $conn->query("SHOW TABLES LIKE '%lock%'");
while ($x = $locks->fetch_array()) {
    echo "lock-ish: {$x[0]}\n";
}

$chk = $conn->query("SHOW TABLES LIKE 'payroll_register_locks'");
if ($chk && $chk->num_rows) {
    $lr = $conn->query("SELECT id, month_no, year_no, register_type, department_id, LENGTH(snapshot_json) len FROM payroll_register_locks ORDER BY id DESC LIMIT 20");
    if ($lr) {
        while ($row = $lr->fetch_assoc()) {
            echo 'lock ' . json_encode($row) . "\n";
        }
    }
    $sr = $conn->query("SELECT id, month_no, year_no, register_type FROM payroll_register_locks WHERE snapshot_json LIKE '%Jitesh%' OR snapshot_json LIKE '%JITESH%' OR snapshot_json LIKE '%Jasani%' LIMIT 5");
    if ($sr) {
        while ($row = $sr->fetch_assoc()) {
            echo 'snap hit ' . json_encode($row) . "\n";
        }
    }
}

// Try common alt DB names
foreach (['armor_new_hrms', 'hrms', 'armor_fire_hrms'] as $db) {
    $q = @$conn->query("SHOW DATABASES LIKE '{$db}'");
    if ($q && $q->num_rows) {
        echo "DB exists: {$db}\n";
    }
}

$conn->close();
