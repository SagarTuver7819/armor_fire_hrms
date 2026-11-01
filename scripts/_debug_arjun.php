<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';

$month = 10;
$year = 2026;
$conn = getDBConnection();

$q = $conn->query(
    "SELECT e.*, d.department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.employee_code IN ('AS79006','AS76006','AS76038')
        OR e.employee_name LIKE '%Arjun%'
        OR e.employee_name LIKE '%RATHOD%'
        OR e.employee_name LIKE '%SHIYAL%'
        OR e.employee_name LIKE '%SHUKAL%'
     ORDER BY e.employee_code"
);
$emps = [];
while ($r = $q->fetch_assoc()) {
    $emps[] = $r;
    echo "EMP " . json_encode([
        'id' => $r['id'],
        'code' => $r['employee_code'],
        'name' => $r['employee_name'],
        'dept' => $r['department_name'] ?? '',
        'wo' => $r['week_off_day'] ?? '',
        'wo_ben' => $r['week_off_benefits'] ?? '',
        'hol_ben' => $r['holiday_benefits'] ?? '',
        'doj' => $r['date_of_joining'] ?? '',
        'doe' => $r['date_of_exit'] ?? '',
        'pay' => $r['pay_type'] ?? '',
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
if (!$emps) {
    echo "NO_EMPLOYEES_FOUND\n";
    // show admin dept sample
    $dq = $conn->query("SELECT id, department_name FROM departments WHERE id=1 OR department_name LIKE '%ADMIN%'");
    while ($d = $dq->fetch_assoc()) {
        echo "DEPT " . json_encode($d) . "\n";
        $eq = $conn->query('SELECT id,employee_code,employee_name FROM employees WHERE department_id=' . (int)$d['id'] . ' LIMIT 20');
        while ($e = $eq->fetch_assoc()) {
            echo '  ' . json_encode($e, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    exit(1);
}

$from = '2026-10-01';
$to = '2026-10-31';

foreach ($emps as $emp) {
    if (stripos($emp['employee_code'] ?? '', '79006') === false
        && stripos($emp['employee_name'] ?? '', 'Arjun') === false) {
        // still print all three for compare; focus dump on Arjun
    }
    $eid = (int) $emp['id'];
    $deptId = (int) ($emp['department_id'] ?? 0);
    echo "\n==== {$emp['employee_code']} {$emp['employee_name']} ====\n";

    $st = $conn->prepare(
        "SELECT attendance_date, day_status, remarks, punch_in, punch_out, working_minutes, source
         FROM attendance_day_status
         WHERE employee_id=? AND attendance_date BETWEEN ? AND ?
         ORDER BY attendance_date"
    );
    $st->bind_param('iss', $eid, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $dayMap = [];
    $statusCount = [];
    while ($row = $res->fetch_assoc()) {
        $d = substr($row['attendance_date'], 0, 10);
        $dayMap[$d] = $row;
        $s = $row['day_status'] ?? '';
        $statusCount[$s] = ($statusCount[$s] ?? 0) + 1;
        $dow = date('D', strtotime($d));
        echo sprintf(
            "%s %s | %-10s | src=%s | rem=%s | in=%s out=%s\n",
            $d,
            $dow,
            $s,
            $row['source'] ?? '',
            $row['remarks'] ?? '',
            $row['punch_in'] ?? '',
            $row['punch_out'] ?? ''
        );
    }
    $st->close();
    echo 'DB rows=' . count($dayMap) . ' statuses=' . json_encode($statusCount) . "\n";

    // missing dates
    $missing = [];
    for ($d = 1; $d <= 31; $d++) {
        $date = sprintf('2026-10-%02d', $d);
        if (!isset($dayMap[$date])) {
            $missing[] = $date . '(' . date('D', strtotime($date)) . ')';
        }
    }
    echo 'Missing DB dates: ' . (count($missing) ? implode(', ', $missing) : 'none') . "\n";

    $hist = employeeWeekOffHistoryRows($eid, $conn);
    echo 'WO history: ' . json_encode($hist) . "\n";

    $hol = attendanceHolidaySet($conn, $year, $month, $deptId);
    $tot = attendanceBuildEmployeeMonthTotals($emp, $dayMap, $month, $year, $hol);
    echo 'Totals: ' . json_encode([
        'P' => $tot['present'] ?? null,
        'WO' => $tot['week_off'] ?? null,
        'WOraw' => $tot['week_off_raw'] ?? null,
        'WOpay' => $tot['week_off_paid'] ?? null,
        'H' => $tot['holiday'] ?? null,
        'PL' => $tot['PL'] ?? null,
        'SL' => $tot['SL'] ?? null,
        'DL' => $tot['DL'] ?? null,
        'CO' => $tot['C-Off'] ?? null,
        'LWP' => $tot['LWP'] ?? null,
        'days' => $tot['total_days'] ?? null,
        'pay' => $tot['total_pay_days'] ?? null,
        'sandwich' => $tot['sandwich_wo_removed'] ?? null,
    ]) . "\n";

    // After build, list days with sandwich unpaid or blank in map for calendar WO
    echo "--- Final day statuses (after build) ---\n";
    ksort($dayMap);
    $counted = 0;
    for ($d = 1; $d <= 31; $d++) {
        $date = sprintf('2026-10-%02d', $d);
        $cell = $dayMap[$date] ?? null;
        $stt = $cell ? trim((string)($cell['day_status'] ?? '')) : '(none)';
        $sand = !empty($cell['_sandwich_unpaid']) ? ' SANDWICH_UNPAID' : '';
        $virt = !empty($cell['_virtual']) ? ' virt' : '';
        $auto = !empty($cell['_auto_lwp']) ? ' autoLWP' : '';
        echo sprintf("%s %s | %s%s%s%s\n", $date, date('D', strtotime($date)), $stt, $sand, $virt, $auto);
    }
}
$conn->close();
