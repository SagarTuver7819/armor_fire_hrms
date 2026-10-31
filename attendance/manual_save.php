<?php
/**
 * Save Excel-style monthly manual attendance grid
 * Accepts cells_json (preferred) to avoid PHP max_input_vars truncation on large grids.
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

@set_time_limit(300);
@ini_set('memory_limit', '512M');

$deptId = (int) ($_POST['department_id'] ?? 0);
$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$shiftId = (int) ($_POST['shift_id'] ?? 0);
$shiftName = trim((string) ($_POST['shift_name'] ?? ''));

$cells = [];
$jsonRaw = trim((string) ($_POST['cells_json'] ?? ''));
if ($jsonRaw !== '') {
    $decoded = json_decode($jsonRaw, true);
    if (is_array($decoded)) {
        $cells = $decoded;
    }
}
if (!$cells && isset($_POST['cells']) && is_array($_POST['cells'])) {
    $cells = $_POST['cells'];
}

if ($month < 1 || $month > 12 || $year < 2000) {
    header('Location: ' . app_url('attendance/manual.php?msg=error'));
    exit;
}

$redirectQs = static function ($extra = []) use ($deptId, $month, $year, $shiftId) {
    return http_build_query(array_merge([
        'department_id' => $deptId,
        'month' => $month,
        'year' => $year,
        'shift_id' => $shiftId,
        'show' => 1,
    ], $extra));
};

if (!$cells) {
    header('Location: ' . app_url('attendance/manual.php?' . $redirectQs([
        'msg' => 'error',
        'err' => 'nocells',
    ])));
    exit;
}

$conn = getDBConnection();
ensureAttendanceTables($conn);

$saved = 0;
$touchedEmployees = [];

foreach ($cells as $employeeId => $dates) {
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0 || !is_array($dates)) {
        continue;
    }
    foreach ($dates as $date => $cell) {
        if (!is_array($cell)) {
            continue;
        }
        // JSON payload always means touched; array payload needs touched=1
        $touched = isset($cell['touched']) ? (string) $cell['touched'] : '1';
        if ($touched === '' || $touched === '0') {
            continue;
        }

        $dateKey = (string) $date;
        $dateNorm = parseDateInput($dateKey);
        if (!$dateNorm && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey)) {
            $dateNorm = $dateKey;
        }
        if (!$dateNorm) {
            continue;
        }

        $status = trim((string) ($cell['status'] ?? ''));
        $in = (string) ($cell['punch_in'] ?? '');
        $out = (string) ($cell['punch_out'] ?? '');
        $leaveType = (string) ($cell['leave_type'] ?? '');
        $leaveHalf = (string) ($cell['leave_half'] ?? '');

        if ($status === '') {
            $del = $conn->prepare('DELETE FROM attendance_day_status WHERE employee_id = ? AND attendance_date = ?');
            $del->bind_param('is', $employeeId, $dateNorm);
            $del->execute();
            $del->close();
            $del2 = $conn->prepare('DELETE FROM attendance_punches WHERE employee_id = ? AND attendance_date = ?');
            $del2->bind_param('is', $employeeId, $dateNorm);
            $del2->execute();
            $del2->close();
            $saved++;
            $touchedEmployees[$employeeId] = true;
            continue;
        }

        attendanceSaveManualDay(
            $conn,
            $employeeId,
            $dateNorm,
            $status,
            $in,
            $out,
            $shiftName,
            '',
            $leaveType,
            $leaveHalf,
            false // skip per-cell diary sync; do once per employee below
        );
        $saved++;
        $touchedEmployees[$employeeId] = true;
    }
}

if (function_exists('ensurePayrollDiaryFromAttendance')) {
    foreach (array_keys($touchedEmployees) as $eid) {
        ensurePayrollDiaryFromAttendance((int) $eid, $month, $year, $conn);
    }
}

$conn->close();

header('Location: ' . app_url('attendance/manual.php?' . $redirectQs([
    'msg' => 'saved',
    'count' => $saved,
])));
exit;
