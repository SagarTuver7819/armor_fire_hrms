<?php
require_once __DIR__ . '/../config/database.php';
$c = getDBConnection();
$r = $c->query(
    "SELECT e.employee_code, e.employee_name, d.department_name, e.week_off_day, e.week_off_benefits, e.status
     FROM employees e LEFT JOIN departments d ON d.id=e.department_id ORDER BY d.department_name, e.employee_name"
);
while ($x = $r->fetch_assoc()) {
    echo $x['department_name'] . ' | ' . $x['employee_code'] . ' | ' . $x['employee_name']
        . ' | WO=' . $x['week_off_day'] . ' ben=' . $x['week_off_benefits'] . ' st=' . $x['status'] . "\n";
}
