<?php
/**
 * Compensatory Off (C-Off)
 * - Work on Week Off / Holiday → credit to ledger
 * - ≥ 4 hours → 0.5 day (half day); ≥ 8 hours → 1.0 day (full day)
 * - Must use within 2 months of credit or wipe out
 * - Separate from PL yearly balance
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/attendance_helper.php';
require_once __DIR__ . '/leave_helper.php';

function ensureCoffTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureLeaveTables($conn);
    ensureAttendanceTables($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS coff_credits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            work_date DATE NOT NULL,
            source_type VARCHAR(20) NOT NULL DEFAULT 'Week Off',
            credit_days DECIMAL(6,2) NOT NULL DEFAULT 0.5,
            used_days DECIMAL(6,2) NOT NULL DEFAULT 0,
            working_minutes INT NOT NULL DEFAULT 0,
            punch_in TIME DEFAULT NULL,
            punch_out TIME DEFAULT NULL,
            earned_at DATE NOT NULL,
            expires_at DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'Open',
            remarks VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_coff_emp_work (employee_id, work_date),
            INDEX idx_coff_emp (employee_id),
            INDEX idx_coff_expires (expires_at),
            INDEX idx_coff_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS coff_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coff_credit_id INT NOT NULL,
            leave_request_id INT NOT NULL,
            employee_id INT NOT NULL,
            days_used DECIMAL(6,2) NOT NULL DEFAULT 0,
            used_on DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_coff_usage_credit (coff_credit_id),
            INDEX idx_coff_usage_leave (leave_request_id),
            INDEX idx_coff_usage_emp (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Seed C-Off leave type (balance driven by ledger, not yearly opening)
    $chk = $conn->query(
        "SELECT id FROM leave_types WHERE UPPER(TRIM(code)) IN ('C-OFF','COFF','C OFF') LIMIT 1"
    );
    if ($chk && $chk->num_rows === 0) {
        $conn->query(
            "INSERT INTO leave_types
                (code, leave_type, days_allowed, is_paid, description, accrual_type,
                 monthly_carry_forward, max_concurrent_applicants, allow_encashment, status)
             VALUES
                ('C-OFF', 'Compensatory Off', 0, 'Yes',
                 'Earned by working on Week Off / Holiday (4 hrs = 0.5 day, 8 hrs = 1 day). Use within 2 months.',
                 'yearly', 0, 0, 0, 1)"
        );
    } else {
        @$conn->query(
            "UPDATE leave_types SET
                leave_type = 'Compensatory Off',
                days_allowed = 0,
                is_paid = 'Yes',
                description = 'Earned by working on Week Off / Holiday (4 hrs = 0.5 day, 8 hrs = 1 day). Use within 2 months.',
                status = 1
             WHERE UPPER(TRIM(code)) IN ('C-OFF','COFF')"
        );
    }

    if ($closeAfter) {
        $conn->close();
    }
}

function getCoffLeaveTypeId($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureCoffTables($conn);
    $res = $conn->query(
        "SELECT id FROM leave_types
         WHERE status = 1 AND UPPER(TRIM(code)) IN ('C-OFF','COFF','C OFF')
         LIMIT 1"
    );
    $row = $res ? $res->fetch_assoc() : null;
    if ($closeAfter) {
        $conn->close();
    }
    return $row ? (int) $row['id'] : 0;
}

if (!function_exists('leaveTypeIsCoff')) {
    function leaveTypeIsCoff($lt)
    {
        if (!$lt || !is_array($lt)) {
            return false;
        }
        $code = strtoupper(trim((string) ($lt['code'] ?? '')));
        return in_array($code, ['C-OFF', 'COFF', 'C OFF'], true);
    }
}

/**
 * Expire open credits past expires_at. Returns count expired.
 */
function coffExpireOverdue($conn, $employeeId = 0)
{
    ensureCoffTables($conn);
    $employeeId = (int) $employeeId;
    $sql = "UPDATE coff_credits
            SET status = 'Expired',
                remarks = TRIM(CONCAT(COALESCE(remarks,''), ' | Auto-expired after 2 months'))
            WHERE status IN ('Open','Partial')
              AND expires_at < CURDATE()
              AND (credit_days - used_days) > 0.001";
    if ($employeeId > 0) {
        $sql .= ' AND employee_id = ' . $employeeId;
    }
    $conn->query($sql);
    return (int) $conn->affected_rows;
}

/**
 * Available C-Off days (non-expired remaining).
 */
function coffAvailableBalance($conn, $employeeId)
{
    ensureCoffTables($conn);
    coffExpireOverdue($conn, (int) $employeeId);
    $employeeId = (int) $employeeId;
    $st = $conn->prepare(
        "SELECT COALESCE(SUM(credit_days - used_days), 0) AS bal
         FROM coff_credits
         WHERE employee_id = ?
           AND status IN ('Open','Partial')
           AND expires_at >= CURDATE()
           AND (credit_days - used_days) > 0.001"
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return round((float) ($row['bal'] ?? 0), 2);
}

/**
 * Credit days from minutes on WO/Holiday.
 * Rule: ≥ 8 hours (480 min) → 1.0 full day; ≥ 4 hours (240 min) → 0.5 half day.
 */
function coffCreditDaysFromMinutes($minutes)
{
    $minutes = (int) $minutes;
    if ($minutes >= 480) {
        return 1.0;
    }
    if ($minutes >= 240) {
        return 0.5;
    }
    return 0.0;
}

/**
 * Earn C-Off for one work date (idempotent via unique key).
 * @return array{ok:bool,credited:float,message:string}
 */
function coffEarnFromWorkDay($conn, $employeeId, $workDate, $sourceType, $minutes, $punchIn = null, $punchOut = null)
{
    ensureCoffTables($conn);
    $employeeId = (int) $employeeId;
    $workDate = substr((string) $workDate, 0, 10);
    $sourceType = trim((string) $sourceType);
    if (!in_array($sourceType, ['Week Off', 'Holiday'], true)) {
        $sourceType = 'Week Off';
    }
    $minutes = (int) $minutes;
    $credit = coffCreditDaysFromMinutes($minutes);
    if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
        return ['ok' => false, 'credited' => 0, 'message' => 'Invalid employee/date'];
    }
    if ($credit <= 0) {
        return ['ok' => false, 'credited' => 0, 'message' => 'Need at least 4 hours for C-Off (4h=0.5, 8h=1 day)'];
    }

    $earnedAt = $workDate;
    $expiresAt = date('Y-m-d', strtotime($workDate . ' +2 months'));
    $remark = 'Worked on ' . $sourceType . ' · ' . round($minutes / 60, 2) . ' hrs → ' . $credit . ' day';

    // If already credited, update minutes/credit if still unused (Open)
    $chk = $conn->prepare('SELECT id, used_days, status FROM coff_credits WHERE employee_id = ? AND work_date = ? LIMIT 1');
    $chk->bind_param('is', $employeeId, $workDate);
    $chk->execute();
    $exist = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($exist) {
        $used = (float) ($exist['used_days'] ?? 0);
        if ($used > 0.001 || ($exist['status'] ?? '') === 'Used') {
            return ['ok' => true, 'credited' => 0, 'message' => 'Already credited (in use)'];
        }
        if (($exist['status'] ?? '') === 'Expired') {
            return ['ok' => true, 'credited' => 0, 'message' => 'Already expired'];
        }
        $id = (int) $exist['id'];
        $up = $conn->prepare(
            "UPDATE coff_credits SET
                source_type=?, credit_days=?, working_minutes=?, punch_in=?, punch_out=?,
                earned_at=?, expires_at=?, status='Open', remarks=?
             WHERE id=?"
        );
        $up->bind_param(
            'sdisssssi',
            $sourceType,
            $credit,
            $minutes,
            $punchIn,
            $punchOut,
            $earnedAt,
            $expiresAt,
            $remark,
            $id
        );
        $up->execute();
        $up->close();
        return ['ok' => true, 'credited' => $credit, 'message' => 'Updated'];
    }

    $ins = $conn->prepare(
        "INSERT INTO coff_credits
            (employee_id, work_date, source_type, credit_days, used_days, working_minutes,
             punch_in, punch_out, earned_at, expires_at, status, remarks)
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'Open', ?)"
    );
    $ins->bind_param(
        'issdisssss',
        $employeeId,
        $workDate,
        $sourceType,
        $credit,
        $minutes,
        $punchIn,
        $punchOut,
        $earnedAt,
        $expiresAt,
        $remark
    );
    // Fix types: employee_id i, work_date s, source s, credit d, minutes i, punch_in s, punch_out s, earned s, expires s, remarks s
    // 'issdisssss' = i s s d i s s s s s — credit is d then minutes i → isssd + i + ssss = issdisssss
    $ok = $ins->execute();
    $err = $ins->error;
    $ins->close();
    if (!$ok) {
        return ['ok' => false, 'credited' => 0, 'message' => $err];
    }
    return ['ok' => true, 'credited' => $credit, 'message' => 'Credited'];
}

/**
 * After day status rebuild: credit C-Off for calendar WO/Holiday days with punches > 4h.
 */
function coffSyncFromAttendanceMonth($conn, $employeeId, $month, $year)
{
    ensureCoffTables($conn);
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    if ($employeeId <= 0) {
        return ['credited' => 0, 'days' => 0];
    }

    $emp = getEmployeeById($employeeId);
    if (!$emp) {
        return ['credited' => 0, 'days' => 0];
    }
    $weekOffName = trim((string) ($emp['week_off_day'] ?? 'Sunday'));
    if ($weekOffName === '') {
        $weekOffName = 'Sunday';
    }
    $deptId = (int) ($emp['department_id'] ?? 0);
    $holidays = attendanceHolidaySet($conn, $year, $month, $deptId);
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

    $daysMap = [];
    $st = $conn->prepare(
        "SELECT attendance_date, day_status, punch_in, punch_out, working_minutes
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $st->bind_param('iss', $employeeId, $from, $to);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $daysMap[$r['attendance_date']] = $r;
    }
    $st->close();

    $count = 0;
    $totalDays = 0.0;
    for ($d = 1; $d <= $monthDays; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dow = date('l', strtotime($date));
        $isHoliday = isset($holidays[$date]);
        $isWeekOff = (!$isHoliday && strcasecmp($dow, $weekOffName) === 0);
        if (!$isHoliday && !$isWeekOff) {
            continue;
        }
        $day = $daysMap[$date] ?? null;
        if (!$day) {
            continue;
        }
        $minutes = (int) ($day['working_minutes'] ?? 0);
        $punchIn = $day['punch_in'] ?? null;
        $punchOut = $day['punch_out'] ?? null;
        // Must have worked (punch / minutes)
        if ($minutes <= 0 && empty($punchIn)) {
            continue;
        }
        if ($minutes <= 0 && $punchIn && $punchOut) {
            $minutes = attendanceWorkingMinutes($date, $punchIn, $punchOut);
        }
        $source = $isHoliday ? 'Holiday' : 'Week Off';
        $r = coffEarnFromWorkDay($conn, $employeeId, $date, $source, $minutes, $punchIn, $punchOut);
        if (!empty($r['ok']) && (float) ($r['credited'] ?? 0) > 0) {
            $count++;
            $totalDays += (float) $r['credited'];
        }
    }

    coffExpireOverdue($conn, $employeeId);
    // Mirror available into leave balance row for C-Off type (display)
    coffMirrorLeaveBalance($conn, $employeeId);

    return ['credited' => $count, 'days' => round($totalDays, 2)];
}

/**
 * Keep employee_leave_balances.credited_days in sync with ledger available (for UI).
 */
function coffMirrorLeaveBalance($conn, $employeeId)
{
    $employeeId = (int) $employeeId;
    $ltId = getCoffLeaveTypeId($conn);
    if ($ltId <= 0 || $employeeId <= 0) {
        return;
    }
    $year = (int) date('Y');
    $avail = coffAvailableBalance($conn, $employeeId);
    leaveEnsureBalanceRow($conn, $employeeId, $ltId, $year, 0);
    $bal = leaveGetBalance($conn, $employeeId, $ltId, $year);
    $balId = (int) ($bal['id'] ?? 0);
    if ($balId <= 0) {
        return;
    }
    // Store available as credited; used stays from approved leave sync
    $used = leaveSumApprovedDays($conn, $employeeId, $ltId, $year);
    // For C-Off display: opening=0, credited=avail+used (lifetime active), used=approved
    // Better: opening=0, credited=avail, used=0 in yearly row and show ledger separately
    // Keep: credited = available, used from approved this year
    $cred = round($avail + $used, 2);
    $up = $conn->prepare(
        'UPDATE employee_leave_balances SET opening_days = 0, credited_days = ?, used_days = ?, adjusted_days = 0 WHERE id = ?'
    );
    $up->bind_param('ddi', $cred, $used, $balId);
    $up->execute();
    $up->close();
}

/**
 * FIFO consume credits for approved C-Off leave.
 */
function coffConsumeForLeave($conn, $employeeId, $leaveRequestId, $daysNeeded, $usedOn)
{
    ensureCoffTables($conn);
    coffExpireOverdue($conn, (int) $employeeId);
    $employeeId = (int) $employeeId;
    $leaveRequestId = (int) $leaveRequestId;
    $daysNeeded = round((float) $daysNeeded, 2);
    $usedOn = substr((string) $usedOn, 0, 10);
    if ($daysNeeded <= 0) {
        return true;
    }

    $avail = coffAvailableBalance($conn, $employeeId);
    if ($avail + 0.0001 < $daysNeeded) {
        throw new RuntimeException('Insufficient C-Off balance. Available: ' . $avail . ' day(s).');
    }

    $st = $conn->prepare(
        "SELECT id, credit_days, used_days
         FROM coff_credits
         WHERE employee_id = ?
           AND status IN ('Open','Partial')
           AND expires_at >= CURDATE()
           AND (credit_days - used_days) > 0.001
         ORDER BY expires_at ASC, work_date ASC, id ASC"
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $res = $st->get_result();
    $remaining = $daysNeeded;
    while ($row = $res->fetch_assoc()) {
        if ($remaining <= 0.001) {
            break;
        }
        $cid = (int) $row['id'];
        $left = round((float) $row['credit_days'] - (float) $row['used_days'], 2);
        $take = min($left, $remaining);
        $newUsed = round((float) $row['used_days'] + $take, 2);
        $newStatus = ($newUsed + 0.001 >= (float) $row['credit_days']) ? 'Used' : 'Partial';
        $up = $conn->prepare('UPDATE coff_credits SET used_days = ?, status = ? WHERE id = ?');
        $up->bind_param('dsi', $newUsed, $newStatus, $cid);
        $up->execute();
        $up->close();

        $ins = $conn->prepare(
            'INSERT INTO coff_usage (coff_credit_id, leave_request_id, employee_id, days_used, used_on)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->bind_param('iiids', $cid, $leaveRequestId, $employeeId, $take, $usedOn);
        $ins->execute();
        $ins->close();
        $remaining = round($remaining - $take, 2);
    }
    $st->close();

    if ($remaining > 0.001) {
        throw new RuntimeException('Could not allocate full C-Off days from ledger.');
    }
    coffMirrorLeaveBalance($conn, $employeeId);
    return true;
}

/**
 * Restore credits when C-Off leave is rejected/cancelled.
 */
function coffRestoreForLeave($conn, $leaveRequestId)
{
    ensureCoffTables($conn);
    $leaveRequestId = (int) $leaveRequestId;
    $st = $conn->prepare('SELECT * FROM coff_usage WHERE leave_request_id = ?');
    $st->bind_param('i', $leaveRequestId);
    $st->execute();
    $res = $st->get_result();
    $empIds = [];
    while ($u = $res->fetch_assoc()) {
        $cid = (int) $u['coff_credit_id'];
        $days = (float) $u['days_used'];
        $empIds[(int) $u['employee_id']] = true;
        $g = $conn->prepare('SELECT credit_days, used_days, expires_at FROM coff_credits WHERE id = ? LIMIT 1');
        $g->bind_param('i', $cid);
        $g->execute();
        $c = $g->get_result()->fetch_assoc();
        $g->close();
        if (!$c) {
            continue;
        }
        $newUsed = max(0, round((float) $c['used_days'] - $days, 2));
        $expired = (string) $c['expires_at'] < date('Y-m-d');
        if ($expired) {
            $status = 'Expired';
        } elseif ($newUsed <= 0.001) {
            $status = 'Open';
            $newUsed = 0;
        } else {
            $status = 'Partial';
        }
        $up = $conn->prepare('UPDATE coff_credits SET used_days = ?, status = ? WHERE id = ?');
        $up->bind_param('dsi', $newUsed, $status, $cid);
        $up->execute();
        $up->close();
    }
    $st->close();
    $del = $conn->prepare('DELETE FROM coff_usage WHERE leave_request_id = ?');
    $del->bind_param('i', $leaveRequestId);
    $del->execute();
    $del->close();
    foreach (array_keys($empIds) as $eid) {
        coffMirrorLeaveBalance($conn, (int) $eid);
    }
}

function coffFetchHistory($employeeId = 0, $fromDate = '', $toDate = '', $status = '', $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureCoffTables($conn);
    coffExpireOverdue($conn, (int) $employeeId);

    $where = ['1=1'];
    $types = '';
    $params = [];
    if ((int) $employeeId > 0) {
        $where[] = 'c.employee_id = ?';
        $types .= 'i';
        $params[] = (int) $employeeId;
    }
    if ($fromDate !== '') {
        $where[] = 'c.work_date >= ?';
        $types .= 's';
        $params[] = $fromDate;
    }
    if ($toDate !== '') {
        $where[] = 'c.work_date <= ?';
        $types .= 's';
        $params[] = $toDate;
    }
    if ($status !== '') {
        $where[] = 'c.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $sql = 'SELECT c.*, e.employee_code, e.employee_name, d.department_name,
                   ROUND(c.credit_days - c.used_days, 2) AS remaining_days
            FROM coff_credits c
            INNER JOIN employees e ON e.id = c.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY c.work_date DESC, c.id DESC
            LIMIT 2000';
    $st = $conn->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $st->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

/**
 * Employee-wise C-Off summary report.
 */
function coffEmployeeReport($departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureCoffTables($conn);
    coffExpireOverdue($conn, 0);
    $departmentId = (int) $departmentId;

    $sql = "SELECT e.id, e.employee_code, e.employee_name, d.department_name,
                   COALESCE(SUM(CASE WHEN c.status IN ('Open','Partial') AND c.expires_at >= CURDATE()
                                     THEN c.credit_days - c.used_days ELSE 0 END), 0) AS available,
                   COALESCE(SUM(c.credit_days), 0) AS total_earned,
                   COALESCE(SUM(c.used_days), 0) AS total_used,
                   COALESCE(SUM(CASE WHEN c.status = 'Expired'
                                     THEN GREATEST(c.credit_days - c.used_days, 0) ELSE 0 END), 0) AS total_expired,
                   MIN(CASE WHEN c.status IN ('Open','Partial') AND c.expires_at >= CURDATE()
                                 AND (c.credit_days - c.used_days) > 0.001
                            THEN c.expires_at END) AS next_expiry
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN coff_credits c ON c.employee_id = e.id
            WHERE e.status = 1";
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ' . $departmentId;
    }
    $sql .= ' GROUP BY e.id, e.employee_code, e.employee_name, d.department_name
              HAVING total_earned > 0 OR available > 0
              ORDER BY d.department_name ASC, e.employee_code ASC';
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}
