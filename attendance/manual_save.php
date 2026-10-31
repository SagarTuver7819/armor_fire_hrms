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

// #region agent log
$__dbgLog = static function ($hypothesisId, $location, $message, array $data = []) {
    $line = json_encode([
        'sessionId' => '219c73',
        'runId' => 'wo-pre',
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE);
    if ($line) {
        @file_put_contents('c:\\xampp\\htdocs\\armor_new_hrms\\.cursor\\debug-219c73.log', $line . "\n", FILE_APPEND);
    }
};
$__dbgLog('H5', 'manual_save.php:start', 'save_request', [
    'dept' => $deptId,
    'month' => $month,
    'year' => $year,
    'emp_count' => count($cells),
    'cell_count' => array_sum(array_map(static function ($d) {
        return is_array($d) ? count($d) : 0;
    }, $cells)),
]);
// #endregion

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

        // #region agent log
        $fbWo = '';
        $hist = [];
        $resolvedWo = '';
        $isWoDay = false;
        $stEmp = $conn->prepare('SELECT week_off_day, employee_code FROM employees WHERE id = ? LIMIT 1');
        if ($stEmp) {
            $stEmp->bind_param('i', $employeeId);
            $stEmp->execute();
            $er = $stEmp->get_result()->fetch_assoc();
            $stEmp->close();
            $fbWo = trim((string) ($er['week_off_day'] ?? ''));
            $empCode = (string) ($er['employee_code'] ?? '');
        } else {
            $empCode = '';
        }
        if (function_exists('employeeWeekOffHistoryRows')) {
            $hist = employeeWeekOffHistoryRows($employeeId, $conn);
        }
        if (function_exists('employeeWeekOffDayFromHistory')) {
            $resolvedWo = employeeWeekOffDayFromHistory($hist, $dateNorm, $fbWo !== '' ? $fbWo : 'Sunday');
        } else {
            $resolvedWo = $fbWo;
        }
        $isWoDay = function_exists('attendanceDateIsEmployeeWeekOff')
            ? attendanceDateIsEmployeeWeekOff($conn, $employeeId, $dateNorm)
            : false;
        $weekday = date('l', strtotime($dateNorm));
        $__dbgLog('H2', 'manual_save.php:cell', 'cell_before_save', [
            'emp_id' => $employeeId,
            'emp_code' => $empCode,
            'date' => $dateNorm,
            'weekday' => $weekday,
            'in_status' => $status,
            'has_punches' => ($in !== '' || $out !== ''),
            'emp_week_off' => $fbWo,
            'resolved_wo' => $resolvedWo,
            'is_wo_day' => $isWoDay,
            'history' => $hist,
        ]);
        // #endregion

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

        // #region agent log
        $stAfter = $conn->prepare('SELECT day_status, remarks, punch_in, punch_out FROM attendance_day_status WHERE employee_id = ? AND attendance_date = ? LIMIT 1');
        $after = null;
        if ($stAfter) {
            $stAfter->bind_param('is', $employeeId, $dateNorm);
            $stAfter->execute();
            $after = $stAfter->get_result()->fetch_assoc();
            $stAfter->close();
        }
        $__dbgLog('H2', 'manual_save.php:after', 'cell_after_save', [
            'emp_id' => $employeeId,
            'date' => $dateNorm,
            'in_status' => $status,
            'out_status' => $after['day_status'] ?? null,
            'out_remarks' => $after['remarks'] ?? null,
            'forced_wo_working' => ($status === 'Present' && (($after['day_status'] ?? '') === 'Week Off')),
        ]);
        // #endregion

        $saved++;
        $touchedEmployees[$employeeId] = true;
    }

    // #region agent log
    // Existing WO rows in month (detect stale old weekday WOs — H1/H5)
    $ym = sprintf('%04d-%02d', $year, $month);
    $stWo = $conn->prepare(
        "SELECT attendance_date, day_status, remarks
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date LIKE CONCAT(?, '%')
           AND day_status IN ('Week Off','Holiday')
         ORDER BY attendance_date ASC"
    );
    if ($stWo) {
        $stWo->bind_param('is', $employeeId, $ym);
        $stWo->execute();
        $woRows = [];
        $resWo = $stWo->get_result();
        while ($wr = $resWo->fetch_assoc()) {
            $d = (string) $wr['attendance_date'];
            $woRows[] = [
                'date' => $d,
                'weekday' => date('l', strtotime($d)),
                'status' => $wr['day_status'],
                'remarks' => $wr['remarks'],
            ];
        }
        $stWo->close();
        $__dbgLog('H1', 'manual_save.php:month_wo', 'month_weekoff_rows', [
            'emp_id' => $employeeId,
            'month' => $ym,
            'wo_count' => count($woRows),
            'rows' => $woRows,
        ]);
    }
    // #endregion
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
