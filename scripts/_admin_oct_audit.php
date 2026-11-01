<?php
/**
 * Audit Admin dept Oct 2026 attendance on the DB that holds Armor AS* employees.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_OFF);

$dbName = $argv[1] ?? 'jrosvllq_demo_armorhrms';
$m = new mysqli('127.0.0.1', 'root', '', $dbName, 3306);
if ($m->connect_error) {
    die($m->connect_error);
}

// Map schema
$cols = [];
$c = $m->query('SHOW COLUMNS FROM employees');
while ($r = $c->fetch_assoc()) {
    $cols[$r['Field']] = true;
}
$codeCol = isset($cols['employee_code']) ? 'employee_code' : 'emp_code';
$nameCol = isset($cols['employee_name']) ? 'employee_name' : 'full_name';
$woCol = isset($cols['week_off_day']) ? 'week_off_day' : null;
$woBen = isset($cols['week_off_benefits']) ? 'week_off_benefits' : null;
$holBen = isset($cols['holiday_benefits']) ? 'holiday_benefits' : null;
$dojCol = isset($cols['date_of_joining']) ? 'date_of_joining' : (isset($cols['joining_date']) ? 'joining_date' : null);
$deptCol = isset($cols['department_id']) ? 'department_id' : null;

echo "DB={$dbName}\n";
echo "cols code={$codeCol} name={$nameCol} wo={$woCol} doj={$dojCol}\n";

// Find admin dept id
$deptId = 0;
$dq = @$m->query("SELECT id, department_name FROM departments WHERE department_name LIKE '%ADMIN%' OR id=1");
while ($d = $dq->fetch_assoc()) {
    echo 'dept ' . json_encode($d) . "\n";
    if ($deptId === 0) {
        $deptId = (int) $d['id'];
    }
}

$sql = "SELECT id, {$codeCol} AS employee_code, {$nameCol} AS employee_name";
if ($woCol) {
    $sql .= ", {$woCol} AS week_off_day";
}
if ($woBen) {
    $sql .= ", {$woBen} AS week_off_benefits";
}
if ($holBen) {
    $sql .= ", {$holBen} AS holiday_benefits";
}
if ($dojCol) {
    $sql .= ", {$dojCol} AS date_of_joining";
}
if ($deptCol) {
    $sql .= ", {$deptCol} AS department_id";
}
$sql .= " FROM employees WHERE {$codeCol} IN ('AS76006','AS76038','AS79006') OR {$nameCol} LIKE '%ARJUN%RATHOD%'";
if ($deptCol && $deptId) {
    $sql .= " OR {$deptCol}={$deptId}";
}
$sql .= " ORDER BY {$codeCol} LIMIT 50";

$q = $m->query($sql);
echo "employees_found=" . ($q ? $q->num_rows : 0) . "\n";
$targets = [];
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $code = $r['employee_code'] ?? '';
        if (in_array($code, ['AS76006', 'AS76038', 'AS79006'], true) || stripos($r['employee_name'] ?? '', 'ARJUN') !== false) {
            $targets[] = $r;
            echo 'TARGET ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

// attendance table name?
$attTable = null;
foreach (['attendance_day_status', 'attendance', 'employee_attendance'] as $t) {
    $x = @$m->query("SHOW TABLES LIKE '{$t}'");
    if ($x && $x->num_rows) {
        $attTable = $t;
        break;
    }
}
echo "att_table={$attTable}\n";
if (!$attTable || !$targets) {
    // list all admin-like names
    if ($q) {
        // already consumed
    }
    $q2 = $m->query("SELECT {$codeCol} AS c, {$nameCol} AS n FROM employees " . ($deptCol && $deptId ? "WHERE {$deptCol}={$deptId}" : "") . " ORDER BY n LIMIT 30");
    echo "sample:\n";
    if ($q2) {
        while ($r = $q2->fetch_assoc()) {
            echo '  ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    exit(0);
}

// For targets, dump Oct 2026 status counts from raw SQL (this DB may not match armor schema helpers)
foreach ($targets as $emp) {
    $eid = (int) $emp['id'];
    echo "\n==== {$emp['employee_code']} {$emp['employee_name']} doj=" . ($emp['date_of_joining'] ?? '') . " wo=" . ($emp['week_off_day'] ?? '') . " ====\n";
    $attCols = [];
    $ac = $m->query("SHOW COLUMNS FROM {$attTable}");
    while ($r = $ac->fetch_assoc()) {
        $attCols[] = $r['Field'];
    }
    echo 'att_cols=' . implode(',', $attCols) . "\n";
    $dateCol = in_array('attendance_date', $attCols, true) ? 'attendance_date' : (in_array('date', $attCols, true) ? 'date' : null);
    $statusCol = in_array('day_status', $attCols, true) ? 'day_status' : (in_array('status', $attCols, true) ? 'status' : null);
    $empCol = in_array('employee_id', $attCols, true) ? 'employee_id' : null;
    if (!$dateCol || !$statusCol || !$empCol) {
        echo "cannot map attendance columns\n";
        continue;
    }
    $res = $m->query(
        "SELECT {$dateCol} AS d, {$statusCol} AS s, COUNT(*) c
         FROM {$attTable}
         WHERE {$empCol}={$eid} AND {$dateCol} BETWEEN '2026-10-01' AND '2026-10-31'
         GROUP BY s"
    );
    echo "status_counts:\n";
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo '  ' . json_encode($r) . "\n";
        }
    }
    $res2 = $m->query(
        "SELECT {$dateCol} AS d, {$statusCol} AS s
         FROM {$attTable}
         WHERE {$empCol}={$eid} AND {$dateCol} BETWEEN '2026-10-01' AND '2026-10-31'
         ORDER BY d"
    );
    $have = [];
    if ($res2) {
        while ($r = $res2->fetch_assoc()) {
            $have[substr($r['d'], 0, 10)] = $r['s'];
        }
    }
    echo 'rows=' . count($have) . "\n";
    for ($d = 1; $d <= 31; $d++) {
        $date = sprintf('2026-10-%02d', $d);
        $s = $have[$date] ?? 'BLANK';
        if ($s !== 'Present' && $s !== 'Week Off') {
            echo "  {$date} " . date('D', strtotime($date)) . " => {$s}\n";
        }
    }
}
