<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

$month = (int) ($argv[1] ?? 10);
$year = (int) ($argv[2] ?? 2026);
$conn = getDBConnection();
$from = sprintf('%04d-%02d-01', $year, $month);
$to = date('Y-m-t', strtotime($from));

$emps = $conn->query(
    "SELECT e.*, d.department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE d.department_name LIKE '%ACCOUNT%' OR d.department_name LIKE '%Finance%'
     ORDER BY e.employee_name"
);

echo "Accounts WO audit {$month}/{$year}\n";
while ($emp = $emps->fetch_assoc()) {
    $eid = (int) $emp['id'];
    $deptId = (int) $emp['department_id'];
    $st = $conn->prepare(
        "SELECT attendance_date, day_status, remarks, punch_in, punch_out, source, working_minutes
         FROM attendance_day_status
         WHERE employee_id=? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date"
    );
    $st->bind_param('iss', $eid, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $dayMap = [];
    $dbWo = 0;
    $dbWoDates = [];
    while ($row = $res->fetch_assoc()) {
        $d = substr($row['attendance_date'], 0, 10);
        $dayMap[$d] = $row;
        if (($row['day_status'] ?? '') === 'Week Off') {
            $dbWo++;
            $dbWoDates[] = $d . '(' . date('D', strtotime($d)) . ')';
        }
    }
    $st->close();
    $hol = attendanceHolidaySet($conn, $year, $month, $deptId);
    $tot = attendanceBuildEmployeeMonthTotals($emp, $dayMap, $month, $year, $hol);
    $hist = employeeWeekOffHistoryRows($eid, $conn);
    $flag = ((float) ($tot['week_off_paid'] ?? 0) >= 6 || (float) ($tot['week_off_raw'] ?? 0) >= 6 || $dbWo >= 6) ? ' ***' : '';
    echo sprintf(
        "%s %s | day=%s ben=%s | DBwo=%d raw=%.1f paid=%.1f show=%.1f sandwich=%.0f pay=%.1f%s\n",
        $emp['employee_code'],
        $emp['employee_name'],
        $emp['week_off_day'],
        $emp['week_off_benefits'],
        $dbWo,
        (float) ($tot['week_off_raw'] ?? 0),
        (float) ($tot['week_off_paid'] ?? 0),
        (float) ($tot['week_off'] ?? 0),
        (float) ($tot['sandwich_wo_removed'] ?? 0),
        (float) ($tot['total_pay_days'] ?? 0),
        $flag
    );
    if ($flag) {
        echo '  DB WO dates: ' . implode(', ', $dbWoDates) . "\n";
        echo '  hist: ' . json_encode($hist) . "\n";
    }
}

// Also scan ALL employees for WO>=6
echo "\n--- ALL employees with paid/raw WO >= 6 ---\n";
$all = $conn->query(
    "SELECT e.*, d.department_name FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status=1 ORDER BY d.department_name, e.employee_name"
);
while ($emp = $all->fetch_assoc()) {
    $eid = (int) $emp['id'];
    $deptId = (int) ($emp['department_id'] ?? 0);
    $st = $conn->prepare(
        "SELECT attendance_date, day_status, remarks, punch_in, punch_out, source, working_minutes
         FROM attendance_day_status
         WHERE employee_id=? AND attendance_date BETWEEN ? AND ?"
    );
    $st->bind_param('iss', $eid, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $dayMap = [];
    while ($row = $res->fetch_assoc()) {
        $dayMap[substr($row['attendance_date'], 0, 10)] = $row;
    }
    $st->close();
    if (!$dayMap) {
        continue;
    }
    $hol = attendanceHolidaySet($conn, $year, $month, $deptId);
    $tot = attendanceBuildEmployeeMonthTotals($emp, $dayMap, $month, $year, $hol);
    $raw = (float) ($tot['week_off_raw'] ?? 0);
    $paid = (float) ($tot['week_off_paid'] ?? 0);
    if ($raw < 6 && $paid < 6) {
        continue;
    }
    echo sprintf(
        "[%s] %s %s | day=%s ben=%s raw=%.1f paid=%.1f\n",
        $emp['department_name'] ?? '',
        $emp['employee_code'],
        $emp['employee_name'],
        $emp['week_off_day'],
        $emp['week_off_benefits'],
        $raw,
        $paid
    );
}
$conn->close();
