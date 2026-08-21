<?php
/**
 * Save department-wise manual attendance
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
$date = trim((string) ($_POST['attendance_date'] ?? ''));
$shiftId = (int) ($_POST['shift_id'] ?? 0);
$shiftName = trim((string) ($_POST['shift_name'] ?? ''));
$rows = $_POST['rows'] ?? [];

if ($deptId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    header('Location: ' . app_url('attendance/manual.php?msg=error'));
    exit;
}

$conn = getDBConnection();
ensureAttendanceTables($conn);

$saved = 0;
foreach ($rows as $row) {
    if (empty($row['include'])) {
        continue;
    }
    $eid = (int) ($row['employee_id'] ?? 0);
    if ($eid <= 0) {
        continue;
    }
    $status = (string) ($row['status'] ?? 'Present');
    $in = (string) ($row['punch_in'] ?? '');
    $out = (string) ($row['punch_out'] ?? '');
    $remarks = (string) ($row['remarks'] ?? '');
    attendanceSaveManualDay($conn, $eid, $date, $status, $in, $out, $shiftName, $remarks);
    $saved++;
}
$conn->close();

$qs = http_build_query([
    'department_id' => $deptId,
    'attendance_date' => $date,
    'shift_id' => $shiftId,
    'show' => 1,
    'msg' => 'saved',
    'count' => $saved,
]);
header('Location: ' . app_url('attendance/manual.php?' . $qs));
exit;
