<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = new mysqli('127.0.0.1', 'root', '', '', 3306);
if ($m->connect_error) {
    die('fail ' . $m->connect_error);
}
$dbs = [];
$r = $m->query('SHOW DATABASES');
while ($row = $r->fetch_row()) {
    $dbs[] = $row[0];
}
foreach ($dbs as $db) {
    if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys', 'phpmyadmin', 'test'], true)) {
        continue;
    }
    $m->select_db($db);
    $t = @$m->query("SHOW TABLES LIKE 'employees'");
    if (!$t || !$t->num_rows) {
        continue;
    }
    $q = @$m->query(
        "SELECT id, employee_code, employee_name, department_id, status, week_off_day, week_off_benefits
         FROM employees
         WHERE LOWER(employee_name) LIKE '%jitesh%'
            OR LOWER(employee_name) LIKE '%jasani%'
            OR LOWER(employee_name) LIKE '%jiten%'
         LIMIT 20"
    );
    if (!$q) {
        continue;
    }
    $found = [];
    while ($row = $q->fetch_assoc()) {
        $found[] = $row;
    }
    if ($found) {
        echo "=== DB {$db} ===\n";
        foreach ($found as $f) {
            echo json_encode($f, JSON_UNESCAPED_UNICODE) . "\n";
        }
        // dept name
        $hasDept = @$m->query("SHOW TABLES LIKE 'departments'");
        if ($hasDept && $hasDept->num_rows) {
            foreach ($found as $f) {
                $did = (int) $f['department_id'];
                $d = $m->query("SELECT department_name FROM departments WHERE id = {$did}");
                $dn = $d && ($rr = $d->fetch_assoc()) ? $rr['department_name'] : '?';
                echo "  dept={$dn}\n";
            }
        }
    } else {
        // count employees + account dept
        $c = $m->query('SELECT COUNT(*) c FROM employees');
        $cnt = $c ? (int) $c->fetch_assoc()['c'] : 0;
        if ($cnt > 20) {
            echo "DB {$db} employees={$cnt} (no jitesh name match)\n";
            $dq = @$m->query(
                "SELECT e.id, e.employee_code, e.employee_name, e.week_off_day, e.week_off_benefits, d.department_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE d.department_name LIKE '%ACCOUNT%' OR d.department_name LIKE '%Finance%'
                 ORDER BY e.employee_name LIMIT 50"
            );
            if ($dq) {
                while ($row = $dq->fetch_assoc()) {
                    echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        }
    }
}
$m->close();
