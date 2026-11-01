<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli('127.0.0.1', 'root', '', '', 3306);
$dbs = $m->query('SHOW DATABASES');
while ($db = $dbs->fetch_row()) {
    $name = $db[0];
    if (in_array($name, ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'], true)) {
        continue;
    }
    if (!$m->select_db($name)) {
        continue;
    }
    $t = @$m->query("SHOW TABLES LIKE 'employees'");
    if (!$t || !$t->num_rows) {
        continue;
    }
    $q = @$m->query(
        "SELECT id, employee_code, employee_name, department_id, week_off_day, week_off_benefits, holiday_benefits, date_of_joining, date_of_exit, pay_type
         FROM employees
         WHERE employee_code LIKE '%79006%'
            OR employee_code LIKE '%76006%'
            OR employee_code LIKE '%76038%'
            OR employee_name LIKE '%ARJUN%'
            OR employee_name LIKE '%SHIYAL%'
            OR employee_name LIKE '%SHUKAL%'"
    );
    if ($q && $q->num_rows) {
        echo "=== {$name} ===\n";
        while ($r = $q->fetch_assoc()) {
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
// also armor_hrms AS codes
$m->select_db('armor_hrms');
$q = $m->query("SELECT employee_code, employee_name FROM employees WHERE employee_code LIKE 'AS%' LIMIT 20");
echo "armor_hrms AS*:\n";
if ($q) {
    while ($r = $q->fetch_assoc()) {
        echo json_encode($r) . "\n";
    }
}
