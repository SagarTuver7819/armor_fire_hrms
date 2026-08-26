<?php
/**
 * Leave module — employee-wise balance + leave requests + attendance/salary link
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/master_helper.php';
require_once __DIR__ . '/attendance_helper.php';
require_once __DIR__ . '/payroll_helper.php';

function ensureLeaveTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureMasterTables($conn);
    ensureEmployeesTable($conn);
    ensureAttendanceTables($conn);
    ensurePayrollTables($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            year_no INT NOT NULL,
            opening_days DECIMAL(8,2) NOT NULL DEFAULT 0,
            credited_days DECIMAL(8,2) NOT NULL DEFAULT 0,
            used_days DECIMAL(8,2) NOT NULL DEFAULT 0,
            adjusted_days DECIMAL(8,2) NOT NULL DEFAULT 0,
            remarks VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_emp_leave_year (employee_id, leave_type_id, year_no),
            INDEX idx_leave_bal_emp (employee_id),
            INDEX idx_leave_bal_year (year_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type_id INT NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            days DECIMAL(8,2) NOT NULL DEFAULT 0,
            reason TEXT,
            status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
            applied_by INT DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            action_remarks VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_leave_req_emp (employee_id),
            INDEX idx_leave_req_status (status),
            INDEX idx_leave_req_dates (from_date, to_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($closeAfter) {
        $conn->close();
    }
}

function leaveBalanceRemaining(array $row)
{
    return round(
        (float) ($row['opening_days'] ?? 0)
        + (float) ($row['credited_days'] ?? 0)
        + (float) ($row['adjusted_days'] ?? 0)
        - (float) ($row['used_days'] ?? 0),
        2
    );
}

function leaveDiaryBucket($leaveCode, $isPaid = 'Yes')
{
    $code = strtoupper(trim((string) $leaveCode));
    if ($isPaid === 'No' || $code === 'LWP') {
        return 'none'; // unpaid — attendance Leave only, no paid diary days
    }
    if ($code === 'SL') {
        return 'sl';
    }
    if ($code === 'DL') {
        return 'dl';
    }
    return 'pl'; // CL, EL, ML, PL, etc.
}

function getActiveLeaveTypes($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $rows = [];
    $res = $conn->query(
        "SELECT id, code, leave_type, days_allowed, is_paid, description
         FROM leave_types WHERE status = 1
         ORDER BY leave_type ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function getLeaveTypeById($id, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $id = (int) $id;
    $stmt = $conn->prepare(
        'SELECT id, code, leave_type, days_allowed, is_paid, description, status
         FROM leave_types WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $row ?: null;
}

function leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, $opening = null)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    $stmt = $conn->prepare(
        'SELECT id FROM employee_leave_balances
         WHERE employee_id = ? AND leave_type_id = ? AND year_no = ? LIMIT 1'
    );
    $stmt->bind_param('iii', $employeeId, $leaveTypeId, $year);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        return (int) $existing['id'];
    }

    if ($opening === null) {
        $lt = getLeaveTypeById($leaveTypeId, $conn);
        $opening = $lt ? (float) ($lt['days_allowed'] ?? 0) : 0;
    }
    $opening = (float) $opening;
    $ins = $conn->prepare(
        'INSERT INTO employee_leave_balances
            (employee_id, leave_type_id, year_no, opening_days, credited_days, used_days, adjusted_days)
         VALUES (?, ?, ?, ?, 0, 0, 0)'
    );
    $ins->bind_param('iiid', $employeeId, $leaveTypeId, $year, $opening);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}

/**
 * Allocate Leave Master yearly days to all (or one dept) active employees for a year.
 * Does not overwrite used_days. Only creates missing rows or refreshes opening if unused.
 */
function leaveAllocateYearlyBalances($year, $departmentId = 0, $overwriteUnused = true, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureLeaveTables($conn);
    $year = (int) $year;
    $departmentId = (int) $departmentId;

    $types = getActiveLeaveTypes($conn);
    $sql = 'SELECT id FROM employees WHERE status = 1';
    if ($departmentId > 0) {
        $sql .= ' AND department_id = ' . $departmentId;
    }
    $empRes = $conn->query($sql);
    $created = 0;
    $updated = 0;
    while ($emp = $empRes->fetch_assoc()) {
        $empId = (int) $emp['id'];
        foreach ($types as $lt) {
            $ltId = (int) $lt['id'];
            $days = (float) ($lt['days_allowed'] ?? 0);
            $stmt = $conn->prepare(
                'SELECT id, used_days, opening_days, credited_days, adjusted_days
                 FROM employee_leave_balances
                 WHERE employee_id = ? AND leave_type_id = ? AND year_no = ? LIMIT 1'
            );
            $stmt->bind_param('iii', $empId, $ltId, $year);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                leaveEnsureBalanceRow($conn, $empId, $ltId, $year, $days);
                $created++;
                continue;
            }
            if ($overwriteUnused && (float) ($row['used_days'] ?? 0) <= 0) {
                $upd = $conn->prepare(
                    'UPDATE employee_leave_balances SET opening_days = ? WHERE id = ?'
                );
                $balId = (int) $row['id'];
                $upd->bind_param('di', $days, $balId);
                $upd->execute();
                $upd->close();
                $updated++;
            }
        }
    }

    if ($closeAfter) {
        $conn->close();
    }
    return ['created' => $created, 'updated' => $updated];
}

function getEmployeeLeaveBalances($employeeId, $year, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $employeeId = (int) $employeeId;
    $year = (int) $year;
    $rows = [];
    $stmt = $conn->prepare(
        "SELECT b.*, lt.code, lt.leave_type, lt.is_paid, lt.days_allowed
         FROM employee_leave_balances b
         INNER JOIN leave_types lt ON lt.id = b.leave_type_id
         WHERE b.employee_id = ? AND b.year_no = ? AND lt.status = 1
         ORDER BY lt.leave_type ASC"
    );
    $stmt->bind_param('ii', $employeeId, $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['remaining_days'] = leaveBalanceRemaining($row);
        $rows[] = $row;
    }
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function getDepartmentLeaveBalanceRows($departmentId, $year, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $departmentId = (int) $departmentId;
    $year = (int) $year;
    $rows = [];
    $sql = "SELECT e.id AS employee_id, e.employee_code, e.employee_name,
                   d.department_name,
                   lt.id AS leave_type_id, lt.code, lt.leave_type, lt.is_paid,
                   COALESCE(b.opening_days, lt.days_allowed) AS opening_days,
                   COALESCE(b.credited_days, 0) AS credited_days,
                   COALESCE(b.used_days, 0) AS used_days,
                   COALESCE(b.adjusted_days, 0) AS adjusted_days,
                   b.id AS balance_id
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            CROSS JOIN leave_types lt
            LEFT JOIN employee_leave_balances b
              ON b.employee_id = e.id AND b.leave_type_id = lt.id AND b.year_no = ?
            WHERE e.status = 1 AND lt.status = 1";
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ?';
    }
    $sql .= ' ORDER BY e.employee_name ASC, lt.leave_type ASC';

    if ($departmentId > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $year, $departmentId);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $year);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['remaining_days'] = leaveBalanceRemaining($row);
        $rows[] = $row;
    }
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function leaveCountWorkingDays($conn, $employeeId, $fromDate, $toDate)
{
    $employeeId = (int) $employeeId;
    $from = strtotime($fromDate);
    $to = strtotime($toDate);
    if ($from === false || $to === false || $to < $from) {
        return 0.0;
    }

    $emp = getEmployeeById($employeeId);
    $weekOff = strtolower(trim((string) ($emp['week_off_day'] ?? 'Sunday')));
    $holidaySet = [];
    $y1 = (int) date('Y', $from);
    $y2 = (int) date('Y', $to);
    for ($y = $y1; $y <= $y2; $y++) {
        for ($m = 1; $m <= 12; $m++) {
            if ($y === $y1 && $m < (int) date('n', $from)) {
                continue;
            }
            if ($y === $y2 && $m > (int) date('n', $to)) {
                continue;
            }
            foreach (attendanceHolidaySet($conn, $y, $m) as $d => $meta) {
                $holidaySet[$d] = true;
            }
        }
    }

    $days = 0.0;
    for ($ts = $from; $ts <= $to; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $dow = strtolower(date('l', $ts));
        if ($weekOff !== '' && $dow === $weekOff) {
            continue;
        }
        if (isset($holidaySet[$date])) {
            continue;
        }
        $days += 1;
    }
    return $days;
}

function leaveHasOverlap($conn, $employeeId, $fromDate, $toDate, $excludeId = 0)
{
    $employeeId = (int) $employeeId;
    $excludeId = (int) $excludeId;
    $stmt = $conn->prepare(
        "SELECT id FROM leave_requests
         WHERE employee_id = ?
           AND status IN ('Pending','Approved')
           AND from_date <= ? AND to_date >= ?
           AND id <> ?
         LIMIT 1"
    );
    $stmt->bind_param('issi', $employeeId, $toDate, $fromDate, $excludeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

function leaveGetBalance($conn, $employeeId, $leaveTypeId, $year)
{
    leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year);
    $stmt = $conn->prepare(
        'SELECT * FROM employee_leave_balances
         WHERE employee_id = ? AND leave_type_id = ? AND year_no = ? LIMIT 1'
    );
    $stmt->bind_param('iii', $employeeId, $leaveTypeId, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $row['remaining_days'] = leaveBalanceRemaining($row);
    }
    return $row;
}

function leaveApplyRequest($conn, array $data)
{
    ensureLeaveTables($conn);
    $employeeId = (int) ($data['employee_id'] ?? 0);
    $leaveTypeId = (int) ($data['leave_type_id'] ?? 0);
    $fromDate = (string) ($data['from_date'] ?? '');
    $toDate = (string) ($data['to_date'] ?? '');
    $reason = trim((string) ($data['reason'] ?? ''));
    $appliedBy = (int) ($data['applied_by'] ?? 0);

    if ($employeeId <= 0 || $leaveTypeId <= 0) {
        throw new RuntimeException('Employee and leave type are required.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new RuntimeException('Valid from/to dates are required.');
    }
    if (strtotime($toDate) < strtotime($fromDate)) {
        throw new RuntimeException('To date cannot be before from date.');
    }

    $lt = getLeaveTypeById($leaveTypeId, $conn);
    if (!$lt || (int) ($lt['status'] ?? 0) !== 1) {
        throw new RuntimeException('Invalid leave type.');
    }

    $days = leaveCountWorkingDays($conn, $employeeId, $fromDate, $toDate);
    if ($days <= 0) {
        throw new RuntimeException('No working days in selected date range (week-off/holiday excluded).');
    }

    if (leaveHasOverlap($conn, $employeeId, $fromDate, $toDate)) {
        throw new RuntimeException('Overlapping leave already exists for these dates.');
    }

    $year = (int) date('Y', strtotime($fromDate));
    $isUnlimited = strtoupper((string) ($lt['code'] ?? '')) === 'LWP' || (float) ($lt['days_allowed'] ?? 0) <= 0 && ($lt['is_paid'] ?? '') === 'No';
    if (!$isUnlimited) {
        $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
        if (leaveBalanceRemaining($bal) + 0.0001 < $days) {
            throw new RuntimeException(
                'Insufficient leave balance. Remaining: ' . leaveBalanceRemaining($bal) . ' day(s).'
            );
        }
    } else {
        leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
    }

    $stmt = $conn->prepare(
        "INSERT INTO leave_requests
            (employee_id, leave_type_id, from_date, to_date, days, reason, status, applied_by)
         VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)"
    );
    $stmt->bind_param('iissdsi', $employeeId, $leaveTypeId, $fromDate, $toDate, $days, $reason, $appliedBy);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not save leave request: ' . $err);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function leaveMarkAttendanceDays($conn, $employeeId, $fromDate, $toDate)
{
    ensureAttendanceTables($conn);
    $employeeId = (int) $employeeId;
    $from = strtotime($fromDate);
    $to = strtotime($toDate);
    $emp = getEmployeeById($employeeId);
    $weekOff = strtolower(trim((string) ($emp['week_off_day'] ?? 'Sunday')));

    for ($ts = $from; $ts <= $to; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $dow = strtolower(date('l', $ts));
        if ($weekOff !== '' && $dow === $weekOff) {
            continue;
        }
        $y = (int) date('Y', $ts);
        $m = (int) date('n', $ts);
        $holidays = attendanceHolidaySet($conn, $y, $m);
        if (isset($holidays[$date])) {
            continue;
        }
        $status = 'Leave';
        $source = 'leave';
        $zero = 0;
        $stmt = $conn->prepare(
            "INSERT INTO attendance_day_status
                (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source)
             VALUES (?, ?, ?, NULL, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE
                day_status = VALUES(day_status),
                punch_in = NULL,
                punch_out = NULL,
                working_minutes = 0,
                source = VALUES(source)"
        );
        $stmt->bind_param('issis', $employeeId, $date, $status, $zero, $source);
        $stmt->execute();
        $stmt->close();
    }
}

function leaveClearAttendanceDays($conn, $employeeId, $fromDate, $toDate)
{
    $employeeId = (int) $employeeId;
    $stmt = $conn->prepare(
        "UPDATE attendance_day_status
         SET day_status = 'Absent', source = 'leave_cancel'
         WHERE employee_id = ?
           AND attendance_date BETWEEN ? AND ?
           AND source = 'leave'
           AND day_status = 'Leave'"
    );
    $stmt->bind_param('iss', $employeeId, $fromDate, $toDate);
    $stmt->execute();
    $stmt->close();
}

function leaveSyncDiaryBuckets($conn, $employeeId, $fromDate, $toDate)
{
    ensurePayrollTables($conn);
    $employeeId = (int) $employeeId;
    $start = new DateTime($fromDate);
    $end = new DateTime($toDate);
    $cursor = new DateTime($start->format('Y-m-01'));
    $endMonth = new DateTime($end->format('Y-m-01'));

    while ($cursor <= $endMonth) {
        $month = (int) $cursor->format('n');
        $year = (int) $cursor->format('Y');
        $monthFrom = $cursor->format('Y-m-01');
        $monthTo = $cursor->format('Y-m-t');

        $pl = 0.0;
        $sl = 0.0;
        $dl = 0.0;
        $stmt = $conn->prepare(
            "SELECT lr.days, lr.from_date, lr.to_date, lt.code, lt.is_paid
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = ?
               AND lr.status = 'Approved'
               AND lr.from_date <= ? AND lr.to_date >= ?"
        );
        $stmt->bind_param('iss', $employeeId, $monthTo, $monthFrom);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $f = max($row['from_date'], $monthFrom);
            $t = min($row['to_date'], $monthTo);
            $days = leaveCountWorkingDays($conn, $employeeId, $f, $t);
            $bucket = leaveDiaryBucket($row['code'] ?? '', $row['is_paid'] ?? 'Yes');
            if ($bucket === 'sl') {
                $sl += $days;
            } elseif ($bucket === 'dl') {
                $dl += $days;
            } elseif ($bucket === 'pl') {
                $pl += $days;
            }
        }
        $stmt->close();

        // LWP/unpaid (dl) should not count as paid in salary — store but payroll may still include dl.
        // Keep mapping: unpaid goes to dl_days; paid CL/EL → pl; SL → sl.

        $existing = null;
        $st = $conn->prepare(
            'SELECT id, present_days, week_off_days, holiday_days, working_days,
                    overtime_hours, loan_amount, advance_amount, arrears_amount, remarks
             FROM salary_diary WHERE employee_id = ? AND month_no = ? AND year_no = ? LIMIT 1'
        );
        $st->bind_param('iii', $employeeId, $month, $year);
        $st->execute();
        $existing = $st->get_result()->fetch_assoc();
        $st->close();

        $monthDays = (float) date('t', strtotime($monthFrom));
        $present = $existing ? (float) $existing['present_days'] : 0;
        $weekOff = $existing ? (float) $existing['week_off_days'] : 0;
        $holiday = $existing ? (float) ($existing['holiday_days'] ?? 0) : 0;
        $working = $existing ? (float) $existing['working_days'] : $monthDays;
        $ot = $existing ? (float) $existing['overtime_hours'] : 0;
        $loan = $existing ? (float) $existing['loan_amount'] : 0;
        $adv = $existing ? (float) $existing['advance_amount'] : 0;
        $arr = $existing ? (float) $existing['arrears_amount'] : 0;
        $remarks = $existing ? ($existing['remarks'] ?? null) : 'Leave sync';

        if (function_exists('ensurePayrollColumn')) {
            ensurePayrollColumn($conn, 'salary_diary', 'holiday_days', 'holiday_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER week_off_days');
        }

        $ins = $conn->prepare(
            "INSERT INTO salary_diary
                (employee_id, month_no, year_no, working_days, present_days, week_off_days, holiday_days,
                 pl_days, sl_days, dl_days, overtime_hours, loan_amount, advance_amount, arrears_amount, remarks)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                pl_days = VALUES(pl_days),
                sl_days = VALUES(sl_days),
                dl_days = VALUES(dl_days),
                remarks = VALUES(remarks)"
        );
        $ins->bind_param(
            'iiiddddddddddds',
            $employeeId,
            $month,
            $year,
            $working,
            $present,
            $weekOff,
            $holiday,
            $pl,
            $sl,
            $dl,
            $ot,
            $loan,
            $adv,
            $arr,
            $remarks
        );
        $ins->execute();
        $ins->close();

        $cursor->modify('+1 month');
    }
}

function leaveApproveRequest($conn, $requestId, $userId, $remarks = '')
{
    ensureLeaveTables($conn);
    $requestId = (int) $requestId;
    $userId = (int) $userId;

    $stmt = $conn->prepare(
        "SELECT lr.*, lt.code, lt.is_paid, lt.days_allowed
         FROM leave_requests lr
         INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) {
        throw new RuntimeException('Leave request not found.');
    }
    if ($req['status'] !== 'Pending') {
        throw new RuntimeException('Only pending requests can be approved.');
    }

    $employeeId = (int) $req['employee_id'];
    $leaveTypeId = (int) $req['leave_type_id'];
    $days = (float) $req['days'];
    $year = (int) date('Y', strtotime($req['from_date']));
    $code = strtoupper((string) ($req['code'] ?? ''));
    $isUnlimited = $code === 'LWP' || ((float) ($req['days_allowed'] ?? 0) <= 0 && ($req['is_paid'] ?? '') === 'No');

    $conn->begin_transaction();
    try {
        if (!$isUnlimited) {
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            if (leaveBalanceRemaining($bal) + 0.0001 < $days) {
                throw new RuntimeException('Insufficient leave balance at approval time.');
            }
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances SET used_days = used_days + ? WHERE id = ?'
            );
            $balId = (int) $bal['id'];
            $upd->bind_param('di', $days, $balId);
            $upd->execute();
            $upd->close();
        } else {
            leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances SET used_days = used_days + ? WHERE id = ?'
            );
            $balId = (int) $bal['id'];
            $upd->bind_param('di', $days, $balId);
            $upd->execute();
            $upd->close();
        }

        $st = $conn->prepare(
            "UPDATE leave_requests
             SET status = 'Approved', approved_by = ?, approved_at = NOW(), action_remarks = ?
             WHERE id = ?"
        );
        $st->bind_param('isi', $userId, $remarks, $requestId);
        $st->execute();
        $st->close();

        leaveMarkAttendanceDays($conn, $employeeId, $req['from_date'], $req['to_date']);
        // Refresh present / WO / holiday from day-status after marking Leave
        $fromTs = strtotime($req['from_date']);
        $toTs = strtotime($req['to_date']);
        $mStart = (int) date('n', $fromTs);
        $yStart = (int) date('Y', $fromTs);
        $mEnd = (int) date('n', $toTs);
        $yEnd = (int) date('Y', $toTs);
        $y = $yStart;
        $m = $mStart;
        while ($y < $yEnd || ($y === $yEnd && $m <= $mEnd)) {
            ensurePayrollDiaryFromAttendance($employeeId, $m, $y, $conn);
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }
        leaveSyncDiaryBuckets($conn, $employeeId, $req['from_date'], $req['to_date']);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function leaveRejectRequest($conn, $requestId, $userId, $remarks = '')
{
    ensureLeaveTables($conn);
    $requestId = (int) $requestId;
    $userId = (int) $userId;
    $stmt = $conn->prepare('SELECT id, status FROM leave_requests WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) {
        throw new RuntimeException('Leave request not found.');
    }
    if ($req['status'] !== 'Pending') {
        throw new RuntimeException('Only pending requests can be rejected.');
    }
    $st = $conn->prepare(
        "UPDATE leave_requests
         SET status = 'Rejected', approved_by = ?, approved_at = NOW(), action_remarks = ?
         WHERE id = ?"
    );
    $st->bind_param('isi', $userId, $remarks, $requestId);
    $st->execute();
    $st->close();
}

function leaveCancelApproved($conn, $requestId, $userId, $remarks = '')
{
    ensureLeaveTables($conn);
    $requestId = (int) $requestId;
    $userId = (int) $userId;
    $stmt = $conn->prepare(
        "SELECT lr.*, lt.code, lt.is_paid
         FROM leave_requests lr
         INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req || $req['status'] !== 'Approved') {
        throw new RuntimeException('Only approved leave can be cancelled.');
    }

    $employeeId = (int) $req['employee_id'];
    $leaveTypeId = (int) $req['leave_type_id'];
    $days = (float) $req['days'];
    $year = (int) date('Y', strtotime($req['from_date']));

    $conn->begin_transaction();
    try {
        $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
        $upd = $conn->prepare(
            'UPDATE employee_leave_balances
             SET used_days = GREATEST(0, used_days - ?) WHERE id = ?'
        );
        $balId = (int) $bal['id'];
        $upd->bind_param('di', $days, $balId);
        $upd->execute();
        $upd->close();

        $st = $conn->prepare(
            "UPDATE leave_requests
             SET status = 'Cancelled', approved_by = ?, approved_at = NOW(), action_remarks = ?
             WHERE id = ?"
        );
        $st->bind_param('isi', $userId, $remarks, $requestId);
        $st->execute();
        $st->close();

        leaveClearAttendanceDays($conn, $employeeId, $req['from_date'], $req['to_date']);
        $fromTs = strtotime($req['from_date']);
        $toTs = strtotime($req['to_date']);
        $mStart = (int) date('n', $fromTs);
        $yStart = (int) date('Y', $fromTs);
        $mEnd = (int) date('n', $toTs);
        $yEnd = (int) date('Y', $toTs);
        $y = $yStart;
        $m = $mStart;
        while ($y < $yEnd || ($y === $yEnd && $m <= $mEnd)) {
            ensurePayrollDiaryFromAttendance($employeeId, $m, $y, $conn);
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }
        leaveSyncDiaryBuckets($conn, $employeeId, $req['from_date'], $req['to_date']);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function getLeaveRequestById($id, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $id = (int) $id;
    $stmt = $conn->prepare(
        "SELECT lr.*, e.employee_code, e.employee_name, e.department_id,
                d.department_name, lt.code, lt.leave_type, lt.is_paid
         FROM leave_requests lr
         INNER JOIN employees e ON e.id = lr.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE lr.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $row ?: null;
}

function fetchLeaveRequests($departmentId = 0, $status = '', $year = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $departmentId = (int) $departmentId;
    $year = (int) $year;
    $status = trim((string) $status);

    $sql = "SELECT lr.*, e.employee_code, e.employee_name, e.department_id,
                   d.department_name, lt.code, lt.leave_type, lt.is_paid
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE 1=1";
    $types = '';
    $params = [];
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    }
    if ($status !== '' && $status !== 'All') {
        $sql .= ' AND lr.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if ($year > 0) {
        $sql .= ' AND YEAR(lr.from_date) = ?';
        $types .= 'i';
        $params[] = $year;
    }
    $sql .= ' ORDER BY lr.created_at DESC, lr.id DESC LIMIT 500';

    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    if (isset($stmt)) {
        $stmt->close();
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function leaveEmployeesForSelect($departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $departmentId = (int) $departmentId;
    $sql = "SELECT id, employee_code, employee_name, department_id
            FROM employees WHERE status = 1";
    if ($departmentId > 0) {
        $sql .= ' AND department_id = ' . $departmentId;
    }
    $sql .= ' ORDER BY employee_name ASC';
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}
