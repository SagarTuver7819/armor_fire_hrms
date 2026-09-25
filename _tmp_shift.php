<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$r = $c->query("SELECT employee_code, shift_type, shift_time FROM employees WHERE employee_code='AS76002' LIMIT 1");
print_r($r->fetch_assoc());
$r2 = $c->query('SELECT id,name,shift_type,start_time,end_time,status FROM shifts');
while ($x = $r2->fetch_assoc()) {
    print_r($x);
}
$c->close();
