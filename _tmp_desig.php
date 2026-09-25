<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/employee_helper.php';
$e = getEmployeeById(2);
var_export($e['designation'] ?? null);
echo PHP_EOL;
