<?php
/**
 * Attendance report Excel export
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$conn = getDBConnection();
ensureAttendanceTables($conn);
$rows = getAttendanceMonthlyReport($conn, $month, $year, $deptId, $employeeId);
$conn->close();

$filename = 'Attendance_Report_' . date('F_Y', mktime(0, 0, 0, $month, 1, $year)) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$headers = ['Sr', 'Employee Code', 'Employee Name', 'Department', 'Present', 'Week Off', 'Half Days', 'Leave', 'Absent', 'Holiday', 'Working Hours'];
echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
echo '<tr><th colspan="' . count($headers) . '">Attendance Report — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))) . '</th></tr><tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($rows as $i => $r) {
    echo '<tr>';
    $cells = [
        $i + 1,
        $r['employee_code'],
        $r['employee_name'],
        $r['department_name'],
        $r['present'],
        $r['week_off'],
        $r['half_days'],
        $r['leave'],
        $r['absent'],
        $r['holiday'],
        $r['working_hours'],
    ];
    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars((string) $c) . '</td>';
    }
    echo '</tr>';
}
echo '</table></body></html>';
