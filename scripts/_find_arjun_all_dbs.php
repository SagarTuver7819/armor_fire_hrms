<?php
mysqli_report(MYSQLI_REPORT_OFF);
$hosts = ['127.0.0.1', 'localhost'];
$ports = [3306, 3307, 3308];
foreach ($hosts as $host) {
    foreach ($ports as $port) {
        $m = @new mysqli($host, 'root', '', '', $port);
        if ($m->connect_error) {
            echo "SKIP {$host}:{$port} {$m->connect_error}\n";
            continue;
        }
        echo "OK {$host}:{$port}\n";
        $dbs = $m->query('SHOW DATABASES');
        while ($db = $dbs->fetch_row()) {
            $name = $db[0];
            if (in_array($name, ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'], true)) {
                continue;
            }
            if (!$m->select_db($name)) {
                continue;
            }
            $t = @$m->query("SHOW TABLES LIKE 'employees'");
            if (!$t || !$t->num_rows) {
                continue;
            }
            $q = @$m->query(
                "SELECT id, employee_code, employee_name, department_id, week_off_day, week_off_benefits, date_of_joining
                 FROM employees
                 WHERE employee_code='AS79006' OR employee_name LIKE '%Arjun%Rathod%' OR employee_name LIKE '%ARJUN RATHOD%'
                 LIMIT 5"
            );
            if ($q && $q->num_rows) {
                while ($r = $q->fetch_assoc()) {
                    echo "FOUND in {$name} @{$host}:{$port} => " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            $c = @$m->query("SELECT COUNT(*) c FROM employees WHERE employee_code LIKE 'AS%'");
            if ($c) {
                $n = (int) $c->fetch_assoc()['c'];
                if ($n > 0) {
                    echo "  {$name}: AS* employees={$n}\n";
                }
            }
        }
        $m->close();
    }
}
