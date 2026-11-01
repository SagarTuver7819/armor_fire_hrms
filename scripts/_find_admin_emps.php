<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli('127.0.0.1', 'root', '', '', 3306);
$codes = ['AS76006', 'AS76038', 'AS79006', 'AS76004'];
foreach (['jrosvllq_demo_armorhrms', 'jrosvllq_ocean_hrms_saas', 'jrosvllq_oceanhr_24_9', 'armor_hrms'] as $db) {
    if (!$m->select_db($db)) {
        continue;
    }
    // detect columns
    $cols = [];
    $c = @$m->query('SHOW COLUMNS FROM employees');
    if (!$c) {
        continue;
    }
    while ($r = $c->fetch_assoc()) {
        $cols[$r['Field']] = true;
    }
    $codeCol = isset($cols['employee_code']) ? 'employee_code' : (isset($cols['emp_code']) ? 'emp_code' : null);
    $nameCol = isset($cols['employee_name']) ? 'employee_name' : (isset($cols['full_name']) ? 'full_name' : null);
    if (!$codeCol || !$nameCol) {
        continue;
    }
    $in = "'" . implode("','", $codes) . "'";
    $q = $m->query("SELECT {$codeCol} AS code, {$nameCol} AS name FROM employees WHERE {$codeCol} IN ({$in})");
    echo "=== $db ===\n";
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    // also LIKE Shukal Shiyal
    $q2 = $m->query("SELECT {$codeCol} AS code, {$nameCol} AS name FROM employees WHERE {$nameCol} LIKE '%SHUKAL%' OR {$nameCol} LIKE '%SHIYAL%' OR {$nameCol} LIKE '%ARJUN RATHOD%' LIMIT 20");
    if ($q2) {
        while ($r = $q2->fetch_assoc()) {
            echo 'namehit ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
