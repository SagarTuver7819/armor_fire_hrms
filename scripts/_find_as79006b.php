<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli('127.0.0.1', 'root', '', '', 3306);
foreach (['jrosvllq_demo_armorhrms', 'jrosvllq_ocean_hrms_saas', 'jrosvllq_oceanhr_24_9', 'l12_hrms_db', 'armor_hrms'] as $db) {
    if (!$m->select_db($db)) {
        echo "$db select fail\n";
        continue;
    }
    $q = @$m->query("SHOW TABLES LIKE 'employees'");
    if (!$q || !$q->num_rows) {
        echo "$db no employees table\n";
        continue;
    }
    $r = $m->query(
        "SELECT COUNT(*) c FROM employees WHERE employee_code LIKE 'AS%'"
    );
    $n = $r ? (int) $r->fetch_assoc()['c'] : 0;
    $q2 = $m->query(
        "SELECT employee_code, employee_name FROM employees
         WHERE employee_code IN ('AS79006','AS76006','AS76038')
            OR employee_name LIKE '%RATHOD%'
            OR employee_name LIKE '%SHIYAL%'
            OR employee_name LIKE '%SHUKAL%'
         LIMIT 20"
    );
    echo "$db AS_count=$n matches=" . ($q2 ? $q2->num_rows : 0) . "\n";
    if ($q2) {
        while ($row = $q2->fetch_assoc()) {
            echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
