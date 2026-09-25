<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query("SELECT MIN(date_of_joining) mn, MAX(date_of_joining) mx,
  SUM(date_of_joining <= DATE_SUB(CURDATE(), INTERVAL 365 DAY)) AS yr1plus,
  SUM(date_of_joining <= DATE_SUB(CURDATE(), INTERVAL 335 DAY)) AS near1yr,
  COUNT(*) AS total
  FROM employees WHERE status=1 AND date_of_joining IS NOT NULL AND date_of_joining!='' AND date_of_joining!='0000-00-00'");
print_r($r->fetch_assoc());
$r2 = $c->query("SELECT employee_code, date_of_joining, DATE_FORMAT(date_of_joining,'%m-%d') md
  FROM employees WHERE status=1 AND date_of_joining <= DATE_SUB(CURDATE(), INTERVAL 335 DAY)
  ORDER BY DATE_FORMAT(date_of_joining,'%m-%d') ASC LIMIT 15");
while ($x = $r2->fetch_assoc()) print_r($x);
$c->close();
