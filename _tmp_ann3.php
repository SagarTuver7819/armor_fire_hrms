<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query("SELECT employee_code, employee_name, date_of_joining
 FROM employees WHERE status=1 AND MONTH(date_of_joining) IN (9,10)
 AND date_of_joining <= DATE_SUB(CURDATE(), INTERVAL 335 DAY)
 ORDER BY MONTH(date_of_joining), DAY(date_of_joining)");
$n=0;
while ($x=$r->fetch_assoc()) { $n++; print_r($x); }
echo "sep_oct=$n today=".date('Y-m-d')."\n";
$c->close();
