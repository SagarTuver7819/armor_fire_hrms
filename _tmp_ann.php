<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$c = getDBConnection();
$today = new DateTime('today');
$limit = (clone $today)->modify('+30 days');
$res = $c->query(
    "SELECT employee_code, employee_name, date_of_joining,
            DATE_FORMAT(date_of_joining, '%m-%d') AS md
     FROM employees
     WHERE status=1 AND date_of_joining IS NOT NULL AND date_of_joining!='' AND date_of_joining!='0000-00-00'
       AND date_of_joining <= DATE_SUB(CURDATE(), INTERVAL 335 DAY)
     LIMIT 800"
);
$n = 0;
while ($r = $res->fetch_assoc()) {
    $md = $r['md'];
    if ($md === '02-29') {
        $y = (int)$today->format('Y');
        $md = checkdate(2,29,$y) ? '02-29' : '02-28';
    }
    $cand = DateTime::createFromFormat('Y-m-d', $today->format('Y').'-'.$md);
    if (!$cand) continue;
    if ($cand < $today) $cand->modify('+1 year');
    if ($cand > $limit) continue;
    $years = (int)$cand->format('Y') - (int)substr($r['date_of_joining'],0,4);
    if ($years < 1) continue;
    $n++;
    if ($n <= 8) {
        echo $r['employee_code'].' | '.$r['employee_name'].' | join='.$r['date_of_joining'].' | next='.$cand->format('Y-m-d').' | '.$years."yr\n";
    }
}
echo "TOTAL=$n\n";
$c->close();
