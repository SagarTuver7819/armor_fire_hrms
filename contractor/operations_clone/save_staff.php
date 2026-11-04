<?php
/**
 * Clone Ops — Box 2 only: CNC Normal Salary + month attendance (1–monthDays).
 * Does not touch product qty grid.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/attendance_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}

@set_time_limit(300);

$sheetId = (int) ($_POST['sheet_id'] ?? 0);
$operation = trim((string) ($_POST['operation'] ?? 'CNC'));
$excludeEmp = (int) ($_POST['employee_id'] ?? 0);
$month = (int) ($_POST['month_no'] ?? 0);
$year = (int) ($_POST['year_no'] ?? 0);

$backQs = http_build_query(array_filter([
    'id' => $sheetId > 0 ? $sheetId : null,
    'operation' => $operation,
    'month' => $month,
    'year' => $year,
    'employee_id' => $excludeEmp > 0 ? $excludeEmp : null,
]));

if (!in_array($operation, opsCloneStaffSalaryOperations(), true) || $month < 1 || $month > 12 || $year < 2000) {
    header('Location: ' . app_url('contractor/operations_clone/edit.php?' . $backQs));
    exit;
}

$attIn = [];
$jsonRaw = trim((string) ($_POST['staff_att_json'] ?? ''));
if ($jsonRaw !== '') {
    $decoded = json_decode($jsonRaw, true);
    if (is_array($decoded)) {
        $attIn = $decoded;
    }
}

$conn = getDBConnection();
ensureContractorTables($conn);
ensureAttendanceTables($conn);

$staffBundle = getOpsCloneDepartmentStaff($operation, $excludeEmp, $conn);
$allowed = [];
foreach ($staffBundle['rows'] as $r) {
    $allowed[(int) $r['id']] = true;
}
$deptId = resolveDepartmentIdByName($staffBundle['department_name'] ?: opsCloneStaffDepartmentName($operation));
$monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

// 1) Salaries
if (isset($_POST['staff_salary']) && is_array($_POST['staff_salary']) && $deptId > 0) {
    $up = $conn->prepare(
        "UPDATE employees
         SET decided_salary = ?
         WHERE id = ? AND department_id = ? AND pay_type = 'Salary' AND status = 1"
    );
    foreach ($_POST['staff_salary'] as $empKey => $amtRaw) {
        $eid = (int) $empKey;
        if ($eid <= 0 || empty($allowed[$eid])) {
            continue;
        }
        $amt = round(max(0, (float) $amtRaw), 2);
        $up->bind_param('dii', $amt, $eid, $deptId);
        $up->execute();
    }
    $up->close();
}

// 2) Attendance day boxes → attendance_day_status (+ diary sync once per emp)
$touched = [];
foreach ($attIn as $empKey => $days) {
    $eid = (int) $empKey;
    if ($eid <= 0 || empty($allowed[$eid]) || !is_array($days)) {
        continue;
    }
    foreach ($days as $dayKey => $codeRaw) {
        $d = (int) $dayKey;
        if ($d < 1 || $d > $monthDays) {
            continue;
        }
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $parsed = opsCloneAttParseCode($codeRaw);
        if ($parsed === null) {
            continue;
        }
        if (!empty($parsed['clear'])) {
            $del = $conn->prepare('DELETE FROM attendance_day_status WHERE employee_id = ? AND attendance_date = ?');
            $del->bind_param('is', $eid, $date);
            $del->execute();
            $del->close();
            $del2 = $conn->prepare('DELETE FROM attendance_punches WHERE employee_id = ? AND attendance_date = ?');
            $del2->bind_param('is', $eid, $date);
            $del2->execute();
            $del2->close();
            $touched[$eid] = true;
            continue;
        }
        $status = (string) $parsed['status'];
        $leaveType = (string) $parsed['leave_type'];
        $leaveHalf = (string) $parsed['leave_half'];
        $punchIn = '';
        $punchOut = '';
        if ($status === 'Present' || $status === 'Half Day') {
            $punchIn = '09:00';
            $punchOut = '18:00';
        }
        attendanceSaveManualDay(
            $conn,
            $eid,
            $date,
            $status,
            $punchIn,
            $punchOut,
            null,
            null,
            $leaveType !== '' ? $leaveType : null,
            $leaveHalf !== '' ? $leaveHalf : null,
            false
        );
        $touched[$eid] = true;
    }
}

if ($touched && function_exists('ensurePayrollDiaryFromAttendance')) {
    foreach (array_keys($touched) as $eid) {
        ensurePayrollDiaryFromAttendance((int) $eid, $month, $year, $conn);
    }
}

$conn->close();

header('Location: ' . app_url('contractor/operations_clone/edit.php?' . $backQs . '&msg=staff_saved'));
exit;
