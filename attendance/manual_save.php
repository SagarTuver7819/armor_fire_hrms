<?php
/**
 * Save Excel-style monthly manual attendance grid
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('attendance/manual.php'));
    exit;
}

$deptId = (int) ($_POST['department_id'] ?? 0);
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$shiftId = (int) ($_POST['shift_id'] ?? 0);
$shiftName = trim((string) ($_POST['shift_name'] ?? ''));
$cells = $_POST['cells'] ?? [];

if ($month < 1 || $month > 12 || $year < 2000) {
    header('Location: ' . app_url('attendance/manual.php?msg=error'));
    exit;
}

$conn = getDBConnection();
ensureAttendanceTables($conn);

$saved = 0;
foreach ($cells as $employeeId => $dates) {
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0 || !is_array($dates)) {
        continue;
    }
    foreach ($dates as $date => $cell) {
        if (empty($cell['touched'])) {
            continue;
        }
        $date = parseDateInput($date) ?: (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) ? (string) $date : '');
        if ($date === '') {
            continue;
        }
        $status = trim((string) ($cell['status'] ?? ''));
        $in = (string) ($cell['punch_in'] ?? '');
        $out = (string) ($cell['punch_out'] ?? '');
        $leaveType = (string) ($cell['leave_type'] ?? '');
        $leaveHalf = (string) ($cell['leave_half'] ?? '');

        if ($status === '') {
            // Clear day
            $del = $conn->prepare('DELETE FROM attendance_day_status WHERE employee_id = ? AND attendance_date = ?');
            $del->bind_param('is', $employeeId, $date);
            $del->execute();
            $del->close();
            $del2 = $conn->prepare('DELETE FROM attendance_punches WHERE employee_id = ? AND attendance_date = ?');
            $del2->bind_param('is', $employeeId, $date);
            $del2->execute();
            $del2->close();
            $saved++;
            continue;
        }

        attendanceSaveManualDay(
            $conn,
            $employeeId,
            $date,
            $status,
            $in,
            $out,
            $shiftName,
            '',
            $leaveType,
            $leaveHalf
        );
        $saved++;
    }
}
$conn->close();

$qs = http_build_query([
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
    'shift_id' => $shiftId,
    'show' => 1,
    'msg' => 'saved',
    'count' => $saved,
]);
header('Location: ' . app_url('attendance/manual.php?' . $qs));
exit;
