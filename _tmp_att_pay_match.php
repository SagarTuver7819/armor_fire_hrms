<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/master_helper.php';
require_once __DIR__ . '/includes/attendance_helper.php';
require_once __DIR__ . '/includes/payroll_helper.php';
require_once __DIR__ . '/includes/employee_helper.php';

$conn = getDBConnection();
$deptId = 2;
$map2 = holidayDateMapForWindow($conn, '2026-09-01', '2026-09-30', $deptId);
echo "Dept $deptId holidays: " . count($map2) . "\n";
foreach ($map2 as $d => $h) {
    echo "  $d paid=" . (!empty($h['paid']) ? 'Y' : 'N') . "\n";
}

$grid = getAttendanceExcelMonthGrid(9, 2026, $deptId, 0, $conn);
echo 'Employees: ' . count($grid['employees'] ?? []) . "\n";
$lt = $grid['leave_totals'] ?? [];
$allOk = true;
foreach ($grid['employees'] ?? [] as $e) {
    $id = (int) $e['id'];
    $a = $lt[$id] ?? [];
    $emp = getEmployeeById($id);
    if (!$emp) {
        continue;
    }
    $b = getPayrollAttendanceBundle($emp, 9, 2026, (float) ($emp['decided_salary'] ?? 0));
    $ok = abs((float) ($a['present'] ?? 0) - (float) $b['present']) < 0.01
        && abs((float) ($a['week_off'] ?? 0) - (float) $b['week_off']) < 0.01
        && abs((float) ($a['holiday'] ?? 0) - (float) $b['holiday']) < 0.01
        && abs((float) ($a['total_pay_days'] ?? 0) - (float) $b['total_days']) < 0.01;
    if (!$ok) {
        $allOk = false;
    }
    echo sprintf(
        "%s wo=%-9s att[P=%4s WO=%4s H=%4s PL=%4s Pay=%4s] pay[P=%4s WO=%4s H=%4s PL=%4s TD=%4s] %s\n",
        $e['employee_code'],
        $e['week_off_day'] ?? '',
        $a['present'] ?? 0,
        $a['week_off'] ?? 0,
        $a['holiday'] ?? 0,
        $a['PL'] ?? 0,
        $a['total_pay_days'] ?? 0,
        $b['present'],
        $b['week_off'],
        $b['holiday'],
        $b['pl'],
        $b['total_days'],
        $ok ? 'OK' : 'DIFF'
    );
}
echo $allOk ? "ALL MATCH\n" : "MISMATCH\n";
$conn->close();
