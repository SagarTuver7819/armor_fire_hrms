<?php
/**
 * Attendance Report Excel export — same format as Attendance Report.xlsx
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$conn = getDBConnection();
ensureAttendanceTables($conn);
$grid = getAttendanceExcelMonthGrid($month, $year, $deptId, $employeeId, $conn);
$conn->close();

$label = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$filename = 'Attendance_Report_' . $label . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$title = 'Attendance Report — ' . date('F Y', mktime(0, 0, 0, $month, 1, $year));
if ($deptId > 0 && !empty($grid['employees'][0]['department_name'])) {
    // optional subtitle from first row dept when filtered
}

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<h3>' . htmlspecialchars($title) . '</h3>';
echo attendanceRenderExcelMonthTableHtml($grid, ['export' => true]);
echo '<p>Dates shown as day cells · Times like 9:00 AM | 6:00 PM · Leave: PL / SL / C-Off / DL / LWP</p>';
echo '</body></html>';
exit;
