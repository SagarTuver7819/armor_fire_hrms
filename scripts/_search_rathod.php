<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli('127.0.0.1', 'root', '', '', 3306);
foreach (['jrosvllq_demo_armorhrms', 'jrosvllq_ocean_hrms_saas', 'jrosvllq_oceanhr_24_9', 'l12_hrms_db', 'armor_hrms'] as $db) {
    if (!$m->select_db($db)) {
        continue;
    }
    $cols = @$m->query('SHOW COLUMNS FROM employees');
    if (!$cols) {
        continue;
    }
    $fields = [];
    while ($c = $cols->fetch_assoc()) {
        $fields[] = $c['Field'];
    }
    $nameCol = null;
    foreach (['employee_name', 'name', 'emp_name', 'full_name'] as $c) {
        if (in_array($c, $fields, true)) {
            $nameCol = $c;
            break;
        }
    }
    $codeCol = null;
    foreach (['employee_code', 'emp_code', 'code'] as $c) {
        if (in_array($c, $fields, true)) {
            $codeCol = $c;
            break;
        }
    }
    if (!$nameCol) {
        echo "$db fields=" . implode(',', array_slice($fields, 0, 15)) . "\n";
        continue;
    }
    $sql = "SELECT " . ($codeCol ?: "''") . " AS code, {$nameCol} AS name FROM employees WHERE {$nameCol} LIKE '%RATHOD%' OR {$nameCol} LIKE '%Rathod%' OR {$nameCol} LIKE '%Arjun%' OR " . ($codeCol ? "{$codeCol} LIKE '%79006%'" : "0") . " LIMIT 20";
    $q = @$m->query($sql);
    echo "$db nameCol={$nameCol} rows=" . ($q ? $q->num_rows : 0) . "\n";
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            echo '  ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
