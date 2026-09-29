<?php
$f = 'c:/xampp/htdocs/armor_new_hrms/hr/dashboard.php';
$lines = file($f);
file_put_contents($f, implode('', array_slice($lines, 0, 385)));
echo "truncated to " . count(array_slice($lines, 0, 385)) . " lines\n";
