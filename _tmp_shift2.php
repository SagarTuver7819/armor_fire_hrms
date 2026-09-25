<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/employee_helper.php';
require __DIR__ . '/includes/master_helper.php';
$c = getDBConnection();
$r = $c->query("SELECT employee_code, shift_type, shift_time FROM employees WHERE status=1 LIMIT 5");
while ($e = $r->fetch_assoc()) {
    $lab = resolveEmployeeShiftLabels($e);
    echo $e['employee_code'] . ' type=' . ($e['shift_type'] ?? '') . ' stored=' . ($e['shift_time'] ?? '') . ' => ' . $lab['display'] . PHP_EOL;
}
// empty time Day
print_r(resolveEmployeeShiftLabels(['shift_type' => 'Day', 'shift_time' => '']));
$c->close();
