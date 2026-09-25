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
            leave_half VARCHAR(10) NOT NULL DEFAULT 'FULL',
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

    // Older installs may miss leave_half
    $col = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_half'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE leave_requests ADD COLUMN leave_half VARCHAR(10) NOT NULL DEFAULT 'FULL' AFTER days");
    }
    $colAtt = $conn->query("SHOW COLUMNS FROM leave_requests LIKE 'attachment_path'");
    if ($colAtt && $colAtt->num_rows === 0) {
        $conn->query("ALTER TABLE leave_requests ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL AFTER reason");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS leave_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            leave_request_id INT NOT NULL,
            user_id INT NOT NULL,
            employee_id INT NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            INDEX idx_ln_user_unread (user_id, read_at),
            INDEX idx_ln_request (leave_request_id),
            INDEX idx_ln_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Ensure leave_types policy columns exist (also created in master_helper)
    if (function_exists('ensureMasterTables')) {
        ensureMasterTables($conn);
    }

    // Default PL policy: 24/year, monthly CF, max 6 concurrent, year-end encashment
    @$conn->query(
        "UPDATE leave_types SET
            days_allowed = IF(days_allowed <= 0, 24, days_allowed),
            accrual_type = 'monthly',
            monthly_carry_forward = 1,
            max_concurrent_applicants = 6,
            allow_encashment = 1,
            is_paid = 'Yes'
         WHERE UPPER(TRIM(code)) = 'PL'"
    );

    // Sick Leave (SL): 2 days / month, no carry-forward (unused wipes each month)
    $slChk = $conn->query("SELECT id FROM leave_types WHERE UPPER(TRIM(code)) = 'SL' LIMIT 1");
    if ($slChk && $slChk->num_rows === 0) {
        $conn->query(
            "INSERT INTO leave_types
                (code, leave_type, days_allowed, is_paid, description, accrual_type,
                 monthly_carry_forward, max_concurrent_applicants, allow_encashment, status)
             VALUES
                ('SL', 'Sick Leave', 24, 'Yes',
                 '2 days every month. Unused balance wipes at month end (no carry-forward). Attachment optional on request.',
                 'monthly', 0, 0, 0, 1)"
        );
    } else {
        @$conn->query(
            "UPDATE leave_types SET
                leave_type = 'Sick Leave',
                days_allowed = 24,
                is_paid = 'Yes',
                description = '2 days every month. Unused balance wipes at month end (no carry-forward). Attachment optional on request.',
                accrual_type = 'monthly',
                monthly_carry_forward = 0,
                allow_encashment = 0,
                status = 1
             WHERE UPPER(TRIM(code)) = 'SL'"
        );
    }

    // Duty Leave (DL): no balance / no carry-forward — use anytime, paid, usage counted for report
    $dlChk = $conn->query("SELECT id FROM leave_types WHERE UPPER(TRIM(code)) = 'DL' LIMIT 1");
    if ($dlChk && $dlChk->num_rows === 0) {
        $conn->query(
            "INSERT INTO leave_types
                (code, leave_type, days_allowed, is_paid, description, accrual_type,
                 monthly_carry_forward, max_concurrent_applicants, allow_encashment, status)
             VALUES
                ('DL', 'Duty Leave', 0, 'Yes',
                 'Official duty outside company premises. No balance / no carry-forward. Paid. Biometric not expected.',
                 'yearly', 0, 0, 0, 1)"
        );
    } else {
        @$conn->query(
            "UPDATE leave_types SET
                leave_type = 'Duty Leave',
                days_allowed = 0,
                is_paid = 'Yes',
                description = 'Official duty outside company premises. No balance / no carry-forward. Paid. Biometric not expected.',
                accrual_type = 'yearly',
                monthly_carry_forward = 0,
                allow_encashment = 0,
                status = 1
             WHERE UPPER(TRIM(code)) = 'DL'"
        );
    }

    // LWP: unpaid — auto-counts when no attendance, not WO/Holiday, and no other leave
    $lwpChk = $conn->query("SELECT id FROM leave_types WHERE UPPER(TRIM(code)) = 'LWP' LIMIT 1");
    if ($lwpChk && $lwpChk->num_rows === 0) {
        $conn->query(
            "INSERT INTO leave_types
                (code, leave_type, days_allowed, is_paid, description, accrual_type,
                 monthly_carry_forward, max_concurrent_applicants, allow_encashment, status)
             VALUES
                ('LWP', 'Leave Without Pay', 0, 'No',
                 'Auto when absent on working day (no punch, not holiday/week-off, no other leave). Unpaid. Affects attendance & salary.',
                 'yearly', 0, 0, 0, 1)"
        );
    } else {
        @$conn->query(
            "UPDATE leave_types SET
                leave_type = 'Leave Without Pay',
                days_allowed = 0,
                is_paid = 'No',
                description = 'Auto when absent on working day (no punch, not holiday/week-off, no other leave). Unpaid. Affects attendance & salary.',
                accrual_type = 'yearly',
                monthly_carry_forward = 0,
                allow_encashment = 0,
                status = 1
             WHERE UPPER(TRIM(code)) = 'LWP'"
        );
    }

    if ($closeAfter) {
        $conn->close();
    }
}

/**
 * Normalize leave half codes used in leave + attendance
 * FULL | FHL | SHL  (also accepts FHF/SHF)
 */
function leaveNormalizeHalf($half)
{
    $half = strtoupper(trim((string) $half));
    if ($half === 'FHF' || $half === 'FIRST' || $half === 'FIRST_HALF') {
        return 'FHL';
    }
    if ($half === 'SHF' || $half === 'SECOND' || $half === 'SECOND_HALF') {
        return 'SHL';
    }
    if ($half === 'FHL' || $half === 'SHL') {
        return $half;
    }
    return 'FULL';
}

function leaveHalfLabel($half)
{
    $half = leaveNormalizeHalf($half);
    if ($half === 'FHL') {
        return 'FHL';
    }
    if ($half === 'SHL') {
        return 'SHL';
    }
    return 'Full Day';
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

/**
 * Sum of Approved leave days for employee + type + year (balance used = this only).
 * Pending / Rejected / Cancelled never count toward used_days.
 */
function leaveSumApprovedDays($conn, $employeeId, $leaveTypeId, $year)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    if ($employeeId <= 0 || $leaveTypeId <= 0 || $year <= 0) {
        return 0.0;
    }
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(days), 0) AS days_c
         FROM leave_requests
         WHERE employee_id = ?
           AND leave_type_id = ?
           AND status = 'Approved'
           AND YEAR(from_date) = ?"
    );
    $stmt->bind_param('iii', $employeeId, $leaveTypeId, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float) ($row['days_c'] ?? 0), 2);
}

/**
 * Rebuild used_days from Approved requests only (Pending does not reduce balance).
 * PL also includes auto flex penalties (Half PL from late/early policy).
 */
function leaveSyncUsedDaysFromApproved($conn, $employeeId, $leaveTypeId, $year)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year);
    $used = leaveSumApprovedDays($conn, $employeeId, $leaveTypeId, $year);
    $lt = getLeaveTypeById($leaveTypeId, $conn);
    $code = strtoupper(trim((string) ($lt['code'] ?? '')));
    if ($code === 'PL') {
        $used = round($used + leaveSumFlexPlHalfDays($conn, $employeeId, $year, 0), 2);
    }
    $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
    $balId = (int) ($bal['id'] ?? 0);
    if ($balId <= 0) {
        return $used;
    }
    $upd = $conn->prepare('UPDATE employee_leave_balances SET used_days = ? WHERE id = ?');
    $upd->bind_param('di', $used, $balId);
    $upd->execute();
    $upd->close();
    return $used;
}

/**
 * Half-day PL auto-deducted from late/early flex penalties (source=auto_flex).
 * Pass $excludeMonth > 0 to skip that calendar month (for rebuild).
 */
function leaveSumFlexPlHalfDays($conn, $employeeId, $year, $excludeMonth = 0)
{
    $employeeId = (int) $employeeId;
    $year = (int) $year;
    $excludeMonth = (int) $excludeMonth;
    if ($employeeId <= 0 || $year < 2000) {
        return 0.0;
    }
    ensureAttendanceTables($conn);
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $sql = "SELECT COALESCE(SUM(0.5), 0) AS d
            FROM attendance_day_status
            WHERE employee_id = ?
              AND attendance_date BETWEEN ? AND ?
              AND day_status = 'Half Day'
              AND source = 'auto_flex'
              AND UPPER(COALESCE(penalty_leave, '')) = 'PL'";
    if ($excludeMonth >= 1 && $excludeMonth <= 12) {
        $sql .= ' AND MONTH(attendance_date) <> ' . $excludeMonth;
    }
    $st = $conn->prepare($sql);
    $st->bind_param('iss', $employeeId, $from, $to);
    $st->execute();
    $d = (float) ($st->get_result()->fetch_assoc()['d'] ?? 0);
    $st->close();
    return round($d, 2);
}

/** Sync PL used_days = approved requests + auto flex Half PL. */
function leaveSyncPlUsedFromAttendance($conn, $employeeId, $year)
{
    ensureLeaveTables($conn);
    $employeeId = (int) $employeeId;
    $year = (int) $year;
    if ($employeeId <= 0 || $year < 2000) {
        return 0.0;
    }
    $ltRes = $conn->query("SELECT id FROM leave_types WHERE status = 1 AND UPPER(TRIM(code)) = 'PL' LIMIT 1");
    $lt = $ltRes ? $ltRes->fetch_assoc() : null;
    $ltId = $lt ? (int) $lt['id'] : 0;
    if ($ltId <= 0) {
        return 0.0;
    }
    return leaveSyncUsedDaysFromApproved($conn, $employeeId, $ltId, $year);
}

/**
 * Pending days already applied (not yet approved) — for availability check only.
 * Pass $year = 0 to sum pending across all years (used by C-Off).
 */
function leaveSumPendingDays($conn, $employeeId, $leaveTypeId, $year, $excludeId = 0)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    $excludeId = (int) $excludeId;
    $sql = "SELECT COALESCE(SUM(days), 0) AS days_c
            FROM leave_requests
            WHERE employee_id = ?
              AND leave_type_id = ?
              AND status = 'Pending'";
    if ($year > 0) {
        $sql .= ' AND YEAR(from_date) = ' . $year;
    }
    if ($excludeId > 0) {
        $sql .= ' AND id <> ' . $excludeId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $employeeId, $leaveTypeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float) ($row['days_c'] ?? 0), 2);
}

function leaveTypeIsCoff($lt)
{
    if (!$lt || !is_array($lt)) {
        return false;
    }
    $code = strtoupper(trim((string) ($lt['code'] ?? '')));
    return in_array($code, ['C-OFF', 'COFF', 'C OFF'], true);
}

/** Duty Leave — no balance quota; paid; usage tracked for report only. */
function leaveTypeIsDutyLeave($lt)
{
    if (!$lt || !is_array($lt)) {
        return false;
    }
    $code = strtoupper(trim((string) ($lt['code'] ?? '')));
    return $code === 'DL';
}

/**
 * No-balance leave types that can be used freely (still counted when approved).
 * DL = Duty Leave (paid), LWP = unpaid.
 */
function leaveTypeIsUnlimitedUse($lt)
{
    if (!$lt || !is_array($lt)) {
        return false;
    }
    $code = strtoupper(trim((string) ($lt['code'] ?? '')));
    if ($code === 'DL' || $code === 'LWP') {
        return true;
    }
    // Legacy: unpaid with 0 days allowed
    return (float) ($lt['days_allowed'] ?? 0) <= 0 && ($lt['is_paid'] ?? '') === 'No';
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
        return 'dl'; // Duty Leave — paid (salary diary dl_days)
    }
    if ($code === 'C-OFF' || $code === 'COFF' || $code === 'C OFF') {
        return 'none'; // C-Off tracked via coff ledger; diary marked via attendance leave remarks
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
        "SELECT id, code, leave_type, days_allowed, is_paid, description,
                accrual_type, monthly_carry_forward, max_concurrent_applicants, allow_encashment
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
        'SELECT id, code, leave_type, days_allowed, is_paid, description, status,
                accrual_type, monthly_carry_forward, max_concurrent_applicants, allow_encashment
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
        leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year);
        return (int) $existing['id'];
    }

    if ($opening === null) {
        $lt = getLeaveTypeById($leaveTypeId, $conn);
        $isMonthly = leaveTypeIsMonthlyAccrual($lt);
        // Monthly types start at 0; credit accrues via leaveRefreshMonthlyAccrual
        $opening = $isMonthly ? 0 : ($lt ? (float) ($lt['days_allowed'] ?? 0) : 0);
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
    if ($id > 0) {
        leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year);
    }
    return $id;
}

function leaveTypeIsMonthlyAccrual($lt)
{
    if (!$lt || !is_array($lt)) {
        return false;
    }
    $accrual = strtolower(trim((string) ($lt['accrual_type'] ?? 'yearly')));
    return $accrual === 'monthly';
}

/** Monthly leave that does NOT carry unused days (e.g. Sick Leave — wipe each month). */
function leaveTypeHasMonthlyWipe($lt)
{
    if (!$lt || !leaveTypeIsMonthlyAccrual($lt)) {
        return false;
    }
    return (int) ($lt['monthly_carry_forward'] ?? 0) === 0;
}

function leaveTypeIsSickLeave($lt)
{
    if (!$lt || !is_array($lt)) {
        return false;
    }
    return strtoupper(trim((string) ($lt['code'] ?? ''))) === 'SL';
}

function leaveMonthlyCreditRate($daysAllowed)
{
    $daysAllowed = (float) $daysAllowed;
    if ($daysAllowed <= 0) {
        return 0.0;
    }
    return round($daysAllowed / 12, 2);
}

/** Approved days in a specific calendar month (for monthly-wipe leave like SL). */
function leaveSumApprovedDaysInMonth($conn, $employeeId, $leaveTypeId, $year, $month)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    $month = (int) $month;
    if ($employeeId <= 0 || $leaveTypeId <= 0 || $year <= 0 || $month < 1 || $month > 12) {
        return 0.0;
    }
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(days), 0) AS days_c
         FROM leave_requests
         WHERE employee_id = ?
           AND leave_type_id = ?
           AND status = 'Approved'
           AND YEAR(from_date) = ?
           AND MONTH(from_date) = ?"
    );
    $stmt->bind_param('iiii', $employeeId, $leaveTypeId, $year, $month);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float) ($row['days_c'] ?? 0), 2);
}

function leaveSumPendingDaysInMonth($conn, $employeeId, $leaveTypeId, $year, $month, $excludeId = 0)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    $month = (int) $month;
    $excludeId = (int) $excludeId;
    if ($employeeId <= 0 || $leaveTypeId <= 0 || $year <= 0 || $month < 1 || $month > 12) {
        return 0.0;
    }
    $sql = "SELECT COALESCE(SUM(days), 0) AS days_c
            FROM leave_requests
            WHERE employee_id = ?
              AND leave_type_id = ?
              AND status = 'Pending'
              AND YEAR(from_date) = ?
              AND MONTH(from_date) = ?";
    if ($excludeId > 0) {
        $sql .= ' AND id <> ' . $excludeId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $employeeId, $leaveTypeId, $year, $month);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float) ($row['days_c'] ?? 0), 2);
}

/**
 * Monthly accrual:
 * - Carry-forward Yes (PL): cumulative credit months × rate, unused stays
 * - Carry-forward No (SL): only current month quota (e.g. 2 days); unused wipes
 */
function leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $asOfMonth = null)
{
    $employeeId = (int) $employeeId;
    $leaveTypeId = (int) $leaveTypeId;
    $year = (int) $year;
    $lt = getLeaveTypeById($leaveTypeId, $conn);
    if (!$lt || !leaveTypeIsMonthlyAccrual($lt)) {
        return;
    }

    $nowY = (int) date('Y');
    $nowM = (int) date('n');
    if ($asOfMonth === null) {
        if ($year < $nowY) {
            $asOfMonth = 12;
        } elseif ($year > $nowY) {
            $asOfMonth = 0;
        } else {
            $asOfMonth = $nowM;
        }
    }
    $asOfMonth = max(0, min(12, (int) $asOfMonth));

    $quota = (float) ($lt['days_allowed'] ?? 0);
    $perMonth = leaveMonthlyCreditRate($quota);
    $wipe = leaveTypeHasMonthlyWipe($lt);

    if ($wipe) {
        // Sick Leave style: only this month's 2 days; unused prior months wiped
        $targetCredit = ($asOfMonth >= 1) ? $perMonth : 0.0;
        $usedMonth = ($asOfMonth >= 1)
            ? leaveSumApprovedDaysInMonth($conn, $employeeId, $leaveTypeId, $year, $asOfMonth)
            : 0.0;
    } else {
        // PL style: cumulative within year
        $targetCredit = min($quota, round($perMonth * $asOfMonth, 2));
        $usedMonth = null;
    }

    $stmt = $conn->prepare(
        'SELECT id, credited_days, used_days FROM employee_leave_balances
         WHERE employee_id = ? AND leave_type_id = ? AND year_no = ? LIMIT 1'
    );
    $stmt->bind_param('iii', $employeeId, $leaveTypeId, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $zero = 0.0;
        $usedIns = $wipe ? $usedMonth : $zero;
        $ins = $conn->prepare(
            'INSERT INTO employee_leave_balances
                (employee_id, leave_type_id, year_no, opening_days, credited_days, used_days, adjusted_days)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $ins->bind_param('iiiddd', $employeeId, $leaveTypeId, $year, $zero, $targetCredit, $usedIns);
        $ins->execute();
        $ins->close();
        return;
    }

    $balId = (int) $row['id'];
    if ($wipe) {
        $upd = $conn->prepare(
            'UPDATE employee_leave_balances
             SET opening_days = 0, credited_days = ?, used_days = ?, adjusted_days = 0
             WHERE id = ?'
        );
        $upd->bind_param('ddi', $targetCredit, $usedMonth, $balId);
        $upd->execute();
        $upd->close();
        return;
    }

    $current = (float) ($row['credited_days'] ?? 0);
    if ($targetCredit > $current + 0.001) {
        $upd = $conn->prepare('UPDATE employee_leave_balances SET credited_days = ? WHERE id = ?');
        $upd->bind_param('di', $targetCredit, $balId);
        $upd->execute();
        $upd->close();
    }
}

/**
 * Count other employees with overlapping Pending/Approved leave of same type.
 */
function leaveCountConcurrentApplicants($conn, $leaveTypeId, $fromDate, $toDate, $excludeEmployeeId = 0)
{
    $leaveTypeId = (int) $leaveTypeId;
    $excludeEmployeeId = (int) $excludeEmployeeId;
    $sql = "SELECT COUNT(DISTINCT employee_id) AS c
            FROM leave_requests
            WHERE leave_type_id = ?
              AND status IN ('Pending', 'Approved')
              AND from_date <= ?
              AND to_date >= ?";
    if ($excludeEmployeeId > 0) {
        $sql .= ' AND employee_id <> ' . $excludeEmployeeId;
    }
    $st = $conn->prepare($sql);
    $st->bind_param('iss', $leaveTypeId, $toDate, $fromDate);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int) ($row['c'] ?? 0);
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
            // C-Off / DL / LWP: never allocate yearly opening balance
            if (leaveTypeIsCoff($lt) || leaveTypeIsDutyLeave($lt) || strtoupper(trim((string) ($lt['code'] ?? ''))) === 'LWP') {
                leaveEnsureBalanceRow($conn, $empId, $ltId, $year, 0);
                leaveSyncUsedDaysFromApproved($conn, $empId, $ltId, $year);
                $created++;
                continue;
            }
            $isMonthly = leaveTypeIsMonthlyAccrual($lt);
            $days = $isMonthly ? 0.0 : (float) ($lt['days_allowed'] ?? 0);
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
            if ($overwriteUnused && (float) ($row['used_days'] ?? 0) <= 0 && !$isMonthly) {
                $upd = $conn->prepare(
                    'UPDATE employee_leave_balances SET opening_days = ? WHERE id = ?'
                );
                $balId = (int) $row['id'];
                $upd->bind_param('di', $days, $balId);
                $upd->execute();
                $upd->close();
                $updated++;
            }
            if ($isMonthly) {
                leaveRefreshMonthlyAccrual($conn, $empId, $ltId, $year);
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

    // Keep C-Off balance row in sync with ledger (2-month expiry)
    if (is_file(__DIR__ . '/coff_helper.php')) {
        if (!function_exists('coffMirrorLeaveBalance')) {
            require_once __DIR__ . '/coff_helper.php';
        }
        try {
            coffExpireOverdue($conn, $employeeId);
            coffMirrorLeaveBalance($conn, $employeeId);
        } catch (Throwable $e) {
            // ignore mirror failures on balance view
        }
    }

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
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        if ($code === 'DL' || $code === 'LWP') {
            // No balance quota — only usage is tracked
            $row['opening_days'] = 0;
            $row['credited_days'] = 0;
            $row['adjusted_days'] = 0;
            $row['remaining_days'] = 0;
        } elseif ($code === 'SL') {
            // Refresh this-month wipe balance for display
            leaveRefreshMonthlyAccrual($conn, $employeeId, (int) $row['leave_type_id'], $year);
            $fresh = leaveGetBalance($conn, $employeeId, (int) $row['leave_type_id'], $year);
            if ($fresh) {
                $row['opening_days'] = $fresh['opening_days'] ?? 0;
                $row['credited_days'] = $fresh['credited_days'] ?? 0;
                $row['used_days'] = $fresh['used_days'] ?? 0;
                $row['adjusted_days'] = $fresh['adjusted_days'] ?? 0;
            }
            $row['remaining_days'] = leaveBalanceRemaining($row);
        } else {
            $row['remaining_days'] = leaveBalanceRemaining($row);
        }
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
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        if ($code === 'DL' || $code === 'LWP') {
            $row['opening_days'] = 0;
            $row['credited_days'] = 0;
            $row['adjusted_days'] = 0;
            $row['remaining_days'] = 0;
        } elseif ($code === 'C-OFF' || $code === 'COFF') {
            $row['opening_days'] = 0;
            $row['remaining_days'] = leaveBalanceRemaining($row);
        } else {
            $row['remaining_days'] = leaveBalanceRemaining($row);
        }
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
    $empDeptId = (int) ($emp['department_id'] ?? 0);
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
            foreach (attendanceHolidaySet($conn, $y, $m, $empDeptId) as $d => $meta) {
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
    $leaveHalf = leaveNormalizeHalf($data['leave_half'] ?? 'FULL');

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
    // FHL/SHL on a date range: each working day counts as 0.5 (e.g. 5–8 FHL)
    if (in_array($leaveHalf, ['FHL', 'SHL'], true)) {
        $days = round($days * 0.5, 2);
    }

    if (leaveHasOverlap($conn, $employeeId, $fromDate, $toDate)) {
        throw new RuntimeException('Overlapping leave already exists for these dates.');
    }

    // Max concurrent employees (PL = 6 at same dates)
    $maxConcurrent = (int) ($lt['max_concurrent_applicants'] ?? 0);
    if ($maxConcurrent > 0) {
        $others = leaveCountConcurrentApplicants($conn, $leaveTypeId, $fromDate, $toDate, $employeeId);
        if ($others >= $maxConcurrent) {
            throw new RuntimeException(
                'Maximum ' . $maxConcurrent . ' employees can take '
                . (($lt['code'] ?? 'this') . ' leave') . ' on overlapping dates. Currently '
                . $others . ' already applied/approved.'
            );
        }
    }

    $year = (int) date('Y', strtotime($fromDate));
    $month = (int) date('n', strtotime($fromDate));
    $isCoff = leaveTypeIsCoff($lt);
    $isDuty = leaveTypeIsDutyLeave($lt);
    $isUnlimited = leaveTypeIsUnlimitedUse($lt);
    $isWipe = leaveTypeHasMonthlyWipe($lt);
    $attachmentPath = trim((string) ($data['attachment_path'] ?? ''));
    if ($attachmentPath === '') {
        $attachmentPath = null;
    }

    if ($isCoff) {
        if (!function_exists('coffAvailableBalance')) {
            require_once __DIR__ . '/coff_helper.php';
        }
        coffExpireOverdue($conn, $employeeId);
        $coffBal = coffAvailableBalance($conn, $employeeId);
        $pendingAll = leaveSumPendingDays($conn, $employeeId, $leaveTypeId, 0);
        $available = round($coffBal - $pendingAll, 2);
        if ($available + 0.0001 < $days) {
            throw new RuntimeException(
                'Insufficient C-Off balance. Available: ' . max(0, $available)
                . ' day(s). Earn by working on Week Off / Holiday (4 hrs = 0.5 day, 8 hrs = 1 day). Use within 2 months.'
            );
        }
        leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
    } elseif ($isDuty || $isUnlimited) {
        // DL / LWP: no balance check — opening always 0; usage counted on approval
        leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
    } elseif ($isWipe) {
        // SL: 2 days this month only — unused prior months wiped
        leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
        leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);
        $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
        $remaining = leaveBalanceRemaining($bal);
        $pending = leaveSumPendingDaysInMonth($conn, $employeeId, $leaveTypeId, $year, $month);
        $available = round($remaining - $pending, 2);
        if ($available + 0.0001 < $days) {
            $rate = leaveMonthlyCreditRate((float) ($lt['days_allowed'] ?? 0));
            throw new RuntimeException(
                'Insufficient Sick Leave for this month. Available: ' . max(0, $available)
                . ' day(s) (quota ' . $rate . ' / month, unused days wipe each month).'
            );
        }
    } else {
        leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year);
        leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);

        $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
        $remaining = leaveBalanceRemaining($bal);
        $pending = leaveSumPendingDays($conn, $employeeId, $leaveTypeId, $year);
        $available = round($remaining - $pending, 2);
        if ($available + 0.0001 < $days) {
            throw new RuntimeException(
                'Insufficient leave balance. Available: ' . $available . ' day(s) (after pending).'
            );
        }
    }

    $stmt = $conn->prepare(
        "INSERT INTO leave_requests
            (employee_id, leave_type_id, from_date, to_date, days, leave_half, reason, attachment_path, status, applied_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)"
    );
    $stmt->bind_param(
        'iissdsssi',
        $employeeId,
        $leaveTypeId,
        $fromDate,
        $toDate,
        $days,
        $leaveHalf,
        $reason,
        $attachmentPath,
        $appliedBy
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not save leave request: ' . $err);
    }
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function leaveMarkAttendanceDays($conn, $employeeId, $fromDate, $toDate, $leaveCode = '', $leaveHalf = 'FULL')
{
    ensureAttendanceTables($conn);
    $employeeId = (int) $employeeId;
    $from = strtotime($fromDate);
    $to = strtotime($toDate);
    $emp = getEmployeeById($employeeId);
    $weekOff = strtolower(trim((string) ($emp['week_off_day'] ?? 'Sunday')));
    $empDeptId = (int) ($emp['department_id'] ?? 0);
    $leaveHalf = leaveNormalizeHalf($leaveHalf);
    $isHalf = in_array($leaveHalf, ['FHL', 'SHL'], true);
    $status = $isHalf ? 'Half Day' : 'Leave';
    $code = strtoupper(trim((string) $leaveCode));
    $remarks = '';
    if ($code !== '') {
        $remarks = $code;
        if ($isHalf) {
            $remarks .= ' ' . $leaveHalf;
        }
    } elseif ($isHalf) {
        $remarks = $leaveHalf;
    }
    $source = 'leave';

    for ($ts = $from; $ts <= $to; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $dow = strtolower(date('l', $ts));
        if ($weekOff !== '' && $dow === $weekOff) {
            continue;
        }
        $y = (int) date('Y', $ts);
        $m = (int) date('n', $ts);
        $holidays = attendanceHolidaySet($conn, $y, $m, $empDeptId);
        if (isset($holidays[$date])) {
            continue;
        }
        $zero = 0;
        if ($isHalf) {
            // Keep existing punch in/out (other half worked)
            $stmt = $conn->prepare(
                "INSERT INTO attendance_day_status
                    (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source, remarks)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    day_status = VALUES(day_status),
                    remarks = VALUES(remarks),
                    source = VALUES(source)"
            );
            $stmt->bind_param('ississ', $employeeId, $date, $status, $zero, $source, $remarks);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO attendance_day_status
                    (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source, remarks)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    day_status = VALUES(day_status),
                    punch_in = NULL,
                    punch_out = NULL,
                    working_minutes = 0,
                    remarks = VALUES(remarks),
                    source = VALUES(source)"
            );
            $stmt->bind_param('ississ', $employeeId, $date, $status, $zero, $source, $remarks);
        }
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
           AND day_status IN ('Leave', 'Half Day')"
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

        // LWP unpaid → none; DL (Duty Leave) → dl_days (paid); SL → sl; else PL bucket.

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
        "SELECT lr.*, lt.code, lt.is_paid, lt.days_allowed, lt.max_concurrent_applicants,
                lt.accrual_type, lt.monthly_carry_forward
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
    $month = (int) date('n', strtotime($req['from_date']));
    $code = strtoupper((string) ($req['code'] ?? ''));
    $isCoff = leaveTypeIsCoff($req);
    $isUnlimited = leaveTypeIsUnlimitedUse($req);
    $isWipe = leaveTypeHasMonthlyWipe($req);

    $maxConcurrent = (int) ($req['max_concurrent_applicants'] ?? 0);
    if ($maxConcurrent > 0) {
        $others = leaveCountConcurrentApplicants(
            $conn,
            $leaveTypeId,
            $req['from_date'],
            $req['to_date'],
            $employeeId
        );
        if ($others >= $maxConcurrent) {
            throw new RuntimeException(
                'Cannot approve: already ' . $others . ' employees on overlapping '
                . ($code !== '' ? $code : 'leave') . ' (max ' . $maxConcurrent . ').'
            );
        }
    }

    if ($isCoff) {
        if (!function_exists('coffConsumeForLeave')) {
            require_once __DIR__ . '/coff_helper.php';
        }
    } elseif (!$isUnlimited) {
        leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);
    }
    $conn->begin_transaction();
    try {
        if ($isCoff) {
            coffConsumeForLeave($conn, $employeeId, $requestId, $days, $req['from_date']);
        } elseif ($isWipe) {
            leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
            leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            // Pending will become Approved — remaining must cover this request
            // used_days currently = approved only; remaining = credit - approved
            if (leaveBalanceRemaining($bal) + 0.0001 < $days) {
                throw new RuntimeException(
                    'Insufficient Sick Leave for this month at approval time. Available: '
                    . leaveBalanceRemaining($bal) . ' day(s).'
                );
            }
            // Mark approved first then refresh used from month (includes this request after status update)
            // So bump used temporarily; refresh after status update below
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances SET used_days = used_days + ? WHERE id = ?'
            );
            $balId = (int) $bal['id'];
            $upd->bind_param('di', $days, $balId);
            $upd->execute();
            $upd->close();
        } elseif (!$isUnlimited) {
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
            // DL / LWP: track usage count only (opening stays 0)
            leaveEnsureBalanceRow($conn, $employeeId, $leaveTypeId, $year, 0);
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances SET opening_days = 0, credited_days = 0, used_days = used_days + ? WHERE id = ?'
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

        if ($isWipe) {
            leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);
        }

        leaveMarkAttendanceDays(
            $conn,
            $employeeId,
            $req['from_date'],
            $req['to_date'],
            (string) ($req['code'] ?? ''),
            leaveNormalizeHalf($req['leave_half'] ?? 'FULL')
        );
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
        leaveCreateStatusNotification($conn, $requestId, 'Approved');
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
    leaveCreateStatusNotification($conn, $requestId, 'Rejected');
}

function leaveCancelApproved($conn, $requestId, $userId, $remarks = '')
{
    ensureLeaveTables($conn);
    $requestId = (int) $requestId;
    $userId = (int) $userId;
    $stmt = $conn->prepare(
        "SELECT lr.*, lt.code, lt.is_paid, lt.accrual_type, lt.monthly_carry_forward
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
    $month = (int) date('n', strtotime($req['from_date']));
    $isCoff = leaveTypeIsCoff($req);
    $isWipe = leaveTypeHasMonthlyWipe($req);

    if ($isCoff && !function_exists('coffRestoreForLeave')) {
        require_once __DIR__ . '/coff_helper.php';
    }

    $conn->begin_transaction();
    try {
        if ($isCoff) {
            coffRestoreForLeave($conn, $requestId);
        } elseif ($isWipe) {
            // Status update first then refresh month used from remaining Approved
            // Temporarily reduce; refresh after cancel status
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances
                 SET used_days = GREATEST(0, used_days - ?) WHERE id = ?'
            );
            $balId = (int) $bal['id'];
            $upd->bind_param('di', $days, $balId);
            $upd->execute();
            $upd->close();
        } else {
            $bal = leaveGetBalance($conn, $employeeId, $leaveTypeId, $year);
            $upd = $conn->prepare(
                'UPDATE employee_leave_balances
                 SET used_days = GREATEST(0, used_days - ?) WHERE id = ?'
            );
            $balId = (int) $bal['id'];
            $upd->bind_param('di', $days, $balId);
            $upd->execute();
            $upd->close();
        }

        $st = $conn->prepare(
            "UPDATE leave_requests
             SET status = 'Cancelled', approved_by = ?, approved_at = NOW(), action_remarks = ?
             WHERE id = ?"
        );
        $st->bind_param('isi', $userId, $remarks, $requestId);
        $st->execute();
        $st->close();

        if ($isWipe) {
            leaveRefreshMonthlyAccrual($conn, $employeeId, $leaveTypeId, $year, $month);
        }

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
        leaveCreateStatusNotification($conn, $requestId, 'Cancelled');
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

function fetchLeaveRequests($departmentId = 0, $status = '', $year = 0, $conn = null, $employeeId = 0, $departmentIds = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureLeaveTables($conn);
    }
    $departmentId = (int) $departmentId;
    $year = (int) $year;
    $employeeId = (int) $employeeId;
    $status = trim((string) $status);
    if (is_array($departmentIds)) {
        $departmentIds = array_values(array_unique(array_filter(array_map('intval', $departmentIds))));
    } else {
        $departmentIds = null;
    }

    $sql = "SELECT lr.*, e.employee_code, e.employee_name, e.department_id,
                   d.department_name, lt.code, lt.leave_type, lt.is_paid
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE 1=1";
    $types = '';
    $params = [];
    if ($employeeId > 0) {
        $sql .= ' AND lr.employee_id = ?';
        $types .= 'i';
        $params[] = $employeeId;
    }
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    } elseif (is_array($departmentIds) && $departmentIds !== []) {
        $ph = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql .= " AND e.department_id IN ({$ph})";
        $types .= str_repeat('i', count($departmentIds));
        foreach ($departmentIds as $did) {
            $params[] = $did;
        }
    } elseif (is_array($departmentIds) && $departmentIds === []) {
        $sql .= ' AND 1=0';
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

/**
 * Create bell notification for the employee's portal login after leave action.
 */
function leaveCreateStatusNotification($conn, $requestId, $eventType)
{
    ensureLeaveTables($conn);
    $requestId = (int) $requestId;
    $eventType = trim((string) $eventType);
    if ($requestId <= 0 || !in_array($eventType, ['Approved', 'Rejected', 'Cancelled'], true)) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT lr.id, lr.employee_id, lr.from_date, lr.to_date, lr.days, lr.leave_half,
                lt.code, lt.name AS leave_type_name,
                e.employee_name
         FROM leave_requests lr
         INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
         INNER JOIN employees e ON e.id = lr.employee_id
         WHERE lr.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) {
        return false;
    }

    $employeeId = (int) ($req['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        return false;
    }

    $ust = $conn->prepare(
        "SELECT id FROM users
         WHERE employee_id = ? AND role = 'employee' AND status = 1
         ORDER BY id DESC
         LIMIT 1"
    );
    $ust->bind_param('i', $employeeId);
    $ust->execute();
    $userRow = $ust->get_result()->fetch_assoc();
    $ust->close();
    if (!$userRow) {
        return false;
    }
    $notifyUserId = (int) $userRow['id'];

    $code = trim((string) ($req['code'] ?? ''));
    $typeName = trim((string) ($req['leave_type_name'] ?? ''));
    $typeLabel = $code !== '' ? $code : ($typeName !== '' ? $typeName : 'Leave');
    $from = (string) ($req['from_date'] ?? '');
    $to = (string) ($req['to_date'] ?? '');
    $fromShow = function_exists('formatDateDisplay') ? formatDateDisplay($from) : $from;
    $toShow = function_exists('formatDateDisplay') ? formatDateDisplay($to) : $to;
    $days = (float) ($req['days'] ?? 0);
    $daysLabel = rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
    $datePart = ($from === $to || $to === '')
        ? $fromShow
        : ($fromShow . ' to ' . $toShow);

    if ($eventType === 'Approved') {
        $title = 'Leave approved · ' . $typeLabel;
        $body = $datePart . ' · ' . $daysLabel . ' day(s)';
    } elseif ($eventType === 'Rejected') {
        $title = 'Leave rejected · ' . $typeLabel;
        $body = $datePart . ' · ' . $daysLabel . ' day(s)';
    } else {
        $title = 'Leave cancelled · ' . $typeLabel;
        $body = $datePart . ' · ' . $daysLabel . ' day(s)';
    }

    $ins = $conn->prepare(
        "INSERT INTO leave_notifications
            (leave_request_id, user_id, employee_id, event_type, title, body, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $ins->bind_param(
        'iiisss',
        $requestId,
        $notifyUserId,
        $employeeId,
        $eventType,
        $title,
        $body
    );
    $ok = $ins->execute();
    $ins->close();
    return (bool) $ok;
}

/**
 * @return array<int,array>
 */
function fetchUnreadLeaveNotifications($userId, $limit = 12)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [];
    }
    $conn = getDBConnection();
    ensureLeaveTables($conn);
    $limit = max(1, min(30, (int) $limit));
    $st = $conn->prepare(
        "SELECT id, leave_request_id, event_type, title, body, created_at,
                DATE(created_at) AS notify_date
         FROM leave_notifications
         WHERE user_id = ?
           AND read_at IS NULL
         ORDER BY created_at DESC, id DESC
         LIMIT {$limit}"
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    $conn->close();
    return $rows;
}

function markLeaveNotificationRead($notificationId, $userId)
{
    $notificationId = (int) $notificationId;
    $userId = (int) $userId;
    if ($notificationId <= 0 || $userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureLeaveTables($conn);
    $st = $conn->prepare(
        "UPDATE leave_notifications
         SET read_at = NOW()
         WHERE id = ? AND user_id = ? AND read_at IS NULL"
    );
    $st->bind_param('ii', $notificationId, $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

function markAllLeaveNotificationsRead($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureLeaveTables($conn);
    $st = $conn->prepare(
        "UPDATE leave_notifications
         SET read_at = NOW()
         WHERE user_id = ? AND read_at IS NULL"
    );
    $st->bind_param('i', $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

/**
 * Daily encashment rate from decided salary (salary ÷ 30).
 */
function leaveEncashmentDailyRate($monthlySalary)
{
    $monthlySalary = (float) $monthlySalary;
    if ($monthlySalary <= 0) {
        return 0.0;
    }
    return round($monthlySalary / 30, 2);
}

/**
 * Employee-wise year-end PL (or any encashable type) report.
 * Remaining days × (decided_salary ÷ 30).
 *
 * @return array{leave_type:array|null,year:int,rows:array,totals:array}
 */
function leaveEncashmentReport($year, $leaveTypeId = 0, $departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureLeaveTables($conn);
    $year = (int) $year;
    $leaveTypeId = (int) $leaveTypeId;
    $departmentId = (int) $departmentId;
    if ($year < 2000) {
        $year = (int) date('Y');
    }

    $lt = null;
    if ($leaveTypeId > 0) {
        $lt = getLeaveTypeById($leaveTypeId, $conn);
    } else {
        // Default: PL
        $res = $conn->query(
            "SELECT id, code, leave_type, days_allowed, is_paid, accrual_type,
                    monthly_carry_forward, max_concurrent_applicants, allow_encashment, status
             FROM leave_types
             WHERE status = 1 AND UPPER(TRIM(code)) = 'PL'
             LIMIT 1"
        );
        $lt = $res ? $res->fetch_assoc() : null;
        if ($lt) {
            $leaveTypeId = (int) $lt['id'];
        }
    }

    $rows = [];
    $totals = [
        'employees' => 0,
        'quota' => 0,
        'accrued' => 0,
        'used' => 0,
        'remaining' => 0,
        'encashment' => 0,
    ];

    if (!$lt || $leaveTypeId <= 0) {
        if ($closeAfter) {
            $conn->close();
        }
        return ['leave_type' => null, 'year' => $year, 'rows' => [], 'totals' => $totals];
    }

    $asOfMonth = ($year < (int) date('Y')) ? 12 : (($year > (int) date('Y')) ? 0 : (int) date('n'));

    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.decided_salary, e.department_id,
                   d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 1";
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ' . $departmentId;
    }
    $sql .= ' ORDER BY d.department_name ASC, e.employee_code ASC, e.employee_name ASC';
    $empRes = $conn->query($sql);
    while ($emp = $empRes->fetch_assoc()) {
        $empId = (int) $emp['id'];
        leaveEnsureBalanceRow($conn, $empId, $leaveTypeId, $year);
        leaveRefreshMonthlyAccrual($conn, $empId, $leaveTypeId, $year, $asOfMonth);
        leaveSyncUsedDaysFromApproved($conn, $empId, $leaveTypeId, $year);
        $bal = leaveGetBalance($conn, $empId, $leaveTypeId, $year);
        $opening = (float) ($bal['opening_days'] ?? 0);
        $credited = (float) ($bal['credited_days'] ?? 0);
        $adjusted = (float) ($bal['adjusted_days'] ?? 0);
        $used = (float) ($bal['used_days'] ?? 0);
        $accrued = round($opening + $credited + $adjusted, 2);
        $remaining = leaveBalanceRemaining($bal);
        if ($remaining < 0) {
            $remaining = 0;
        }
        $salary = (float) ($emp['decided_salary'] ?? 0);
        $rate = leaveEncashmentDailyRate($salary);
        $amount = round($remaining * $rate, 2);

        $rows[] = [
            'employee_id' => $empId,
            'employee_code' => (string) ($emp['employee_code'] ?? ''),
            'employee_name' => (string) ($emp['employee_name'] ?? ''),
            'department_name' => (string) ($emp['department_name'] ?? ''),
            'decided_salary' => $salary,
            'quota' => (float) ($lt['days_allowed'] ?? 0),
            'accrued' => $accrued,
            'used' => $used,
            'remaining' => $remaining,
            'daily_rate' => $rate,
            'encashment_amount' => $amount,
        ];
        $totals['employees']++;
        $totals['quota'] += (float) ($lt['days_allowed'] ?? 0);
        $totals['accrued'] += $accrued;
        $totals['used'] += $used;
        $totals['remaining'] += $remaining;
        $totals['encashment'] += $amount;
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'leave_type' => $lt,
        'year' => $year,
        'as_of_month' => $asOfMonth,
        'rows' => $rows,
        'totals' => $totals,
    ];
}

/**
 * Duty Leave (DL) usage report — no balance / no carry-forward.
 * Paid days counted via salary diary dl_days when approved.
 *
 * @return array{leave_type:array|null,year:int,month:int,rows:array,detail:array,totals:array}
 */
function leaveDutyLeaveReport($year = 0, $month = 0, $departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureLeaveTables($conn);
    $year = (int) $year;
    $month = (int) $month;
    $departmentId = (int) $departmentId;
    if ($year < 2000) {
        $year = (int) date('Y');
    }

    $ltRes = $conn->query(
        "SELECT id, code, leave_type, days_allowed, is_paid, description
         FROM leave_types WHERE status = 1 AND UPPER(TRIM(code)) = 'DL' LIMIT 1"
    );
    $lt = $ltRes ? $ltRes->fetch_assoc() : null;
    $ltId = $lt ? (int) $lt['id'] : 0;

    $totals = [
        'employees' => 0,
        'requests' => 0,
        'days' => 0.0,
        'pending_days' => 0.0,
        'approved_days' => 0.0,
    ];
    $rows = [];
    $detail = [];

    if ($ltId <= 0) {
        if ($closeAfter) {
            $conn->close();
        }
        return ['leave_type' => null, 'year' => $year, 'month' => $month, 'rows' => [], 'detail' => [], 'totals' => $totals];
    }

    // Employee-wise approved usage
    $sql = "SELECT e.id, e.employee_code, e.employee_name, d.department_name,
                   COALESCE(SUM(CASE WHEN lr.status = 'Approved' THEN lr.days ELSE 0 END), 0) AS approved_days,
                   COALESCE(SUM(CASE WHEN lr.status = 'Pending' THEN lr.days ELSE 0 END), 0) AS pending_days,
                   COUNT(CASE WHEN lr.status = 'Approved' THEN 1 END) AS approved_count,
                   COUNT(CASE WHEN lr.status = 'Pending' THEN 1 END) AS pending_count
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN leave_requests lr
              ON lr.employee_id = e.id
             AND lr.leave_type_id = {$ltId}
             AND YEAR(lr.from_date) = {$year}";
    if ($month >= 1 && $month <= 12) {
        $sql .= " AND MONTH(lr.from_date) = {$month}";
    }
    $sql .= ' WHERE e.status = 1';
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ' . $departmentId;
    }
    $sql .= ' GROUP BY e.id, e.employee_code, e.employee_name, d.department_name
              HAVING approved_days > 0 OR pending_days > 0
              ORDER BY d.department_name ASC, e.employee_code ASC';

    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $approved = (float) ($r['approved_days'] ?? 0);
            $pending = (float) ($r['pending_days'] ?? 0);
            $rows[] = $r;
            $totals['employees']++;
            $totals['approved_days'] += $approved;
            $totals['pending_days'] += $pending;
            $totals['days'] += $approved;
            $totals['requests'] += (int) ($r['approved_count'] ?? 0) + (int) ($r['pending_count'] ?? 0);
        }
    }

    // Detail ledger of requests
    $dsql = "SELECT lr.*, e.employee_code, e.employee_name, d.department_name, lt.code, lt.leave_type
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.leave_type_id = {$ltId}
               AND YEAR(lr.from_date) = {$year}";
    if ($month >= 1 && $month <= 12) {
        $dsql .= " AND MONTH(lr.from_date) = {$month}";
    }
    if ($departmentId > 0) {
        $dsql .= ' AND e.department_id = ' . $departmentId;
    }
    $dsql .= " AND lr.status IN ('Approved','Pending','Cancelled','Rejected')
               ORDER BY lr.from_date DESC, lr.id DESC
               LIMIT 1000";
    $dres = $conn->query($dsql);
    if ($dres) {
        while ($r = $dres->fetch_assoc()) {
            $detail[] = $r;
        }
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'leave_type' => $lt,
        'year' => $year,
        'month' => $month,
        'rows' => $rows,
        'detail' => $detail,
        'totals' => $totals,
    ];
}

/**
 * Sync LWP used_days for the year from attendance auto marks + approved LWP requests.
 * Opening stays 0 (no balance).
 */
function leaveSyncLwpUsedFromAttendance($conn, $employeeId, $month, $year, $monthLwpDays = null)
{
    ensureLeaveTables($conn);
    $employeeId = (int) $employeeId;
    $year = (int) $year;
    if ($employeeId <= 0 || $year < 2000) {
        return 0.0;
    }
    $ltRes = $conn->query("SELECT id FROM leave_types WHERE status = 1 AND UPPER(TRIM(code)) = 'LWP' LIMIT 1");
    $lt = $ltRes ? $ltRes->fetch_assoc() : null;
    $ltId = $lt ? (int) $lt['id'] : 0;
    if ($ltId <= 0) {
        return 0.0;
    }

    // Count auto LWP days in attendance for the year
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $st = $conn->prepare(
        "SELECT COALESCE(SUM(
            CASE
              WHEN day_status = 'Half Day' AND UPPER(COALESCE(remarks,'')) LIKE '%LWP%' THEN 0.5
              WHEN (day_status = 'Leave' OR day_status = 'Absent')
                   AND (source = 'auto_lwp' OR UPPER(COALESCE(remarks,'')) LIKE '%LWP%') THEN 1
              ELSE 0
            END
         ), 0) AS lwp_c
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $st->bind_param('iss', $employeeId, $from, $to);
    $st->execute();
    $attLwp = (float) ($st->get_result()->fetch_assoc()['lwp_c'] ?? 0);
    $st->close();

    // Approved applied LWP (source leave) — avoid double count if already in attendance with remarks LWP
    // Attendance already has leave-protected LWP from leaveMarkAttendanceDays, so att count covers both.
    $used = round($attLwp, 2);
    leaveEnsureBalanceRow($conn, $employeeId, $ltId, $year, 0);
    $bal = leaveGetBalance($conn, $employeeId, $ltId, $year);
    $balId = (int) ($bal['id'] ?? 0);
    if ($balId > 0) {
        $upd = $conn->prepare(
            'UPDATE employee_leave_balances SET opening_days = 0, credited_days = 0, adjusted_days = 0, used_days = ? WHERE id = ?'
        );
        $upd->bind_param('di', $used, $balId);
        $upd->execute();
        $upd->close();
    }
    return $used;
}

/**
 * LWP report — unpaid days from attendance (auto) + leave requests.
 */
function leaveLwpReport($year = 0, $month = 0, $departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureLeaveTables($conn);
    ensureAttendanceTables($conn);
    if (function_exists('ensurePayrollTables')) {
        ensurePayrollTables($conn);
    }
    $year = (int) $year;
    $month = (int) $month;
    $departmentId = (int) $departmentId;
    if ($year < 2000) {
        $year = (int) date('Y');
    }

    $ltRes = $conn->query(
        "SELECT id, code, leave_type, days_allowed, is_paid, description
         FROM leave_types WHERE status = 1 AND UPPER(TRIM(code)) = 'LWP' LIMIT 1"
    );
    $lt = $ltRes ? $ltRes->fetch_assoc() : null;

    $from = ($month >= 1 && $month <= 12)
        ? sprintf('%04d-%02d-01', $year, $month)
        : sprintf('%04d-01-01', $year);
    $to = ($month >= 1 && $month <= 12)
        ? date('Y-m-t', strtotime($from))
        : sprintf('%04d-12-31', $year);

    $totals = [
        'employees' => 0,
        'lwp_days' => 0.0,
        'salary_impact' => 0.0,
    ];
    $rows = [];
    $detail = [];

    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.decided_salary, d.department_name,
                   COALESCE(SUM(
                     CASE
                       WHEN a.day_status = 'Half Day' AND UPPER(COALESCE(a.remarks,'')) LIKE '%LWP%' THEN 0.5
                       WHEN (a.day_status IN ('Leave','Absent'))
                            AND (a.source = 'auto_lwp' OR UPPER(COALESCE(a.remarks,'')) LIKE '%LWP%') THEN 1
                       ELSE 0
                     END
                   ), 0) AS lwp_days
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN attendance_day_status a
              ON a.employee_id = e.id
             AND a.attendance_date BETWEEN '{$from}' AND '{$to}'
            WHERE e.status = 1";
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ' . $departmentId;
    }
    $sql .= ' GROUP BY e.id, e.employee_code, e.employee_name, e.decided_salary, d.department_name
              HAVING lwp_days > 0
              ORDER BY d.department_name ASC, e.employee_code ASC';

    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $days = (float) ($r['lwp_days'] ?? 0);
            $salary = (float) ($r['decided_salary'] ?? 0);
            $rate = $salary > 0 ? round($salary / 30, 2) : 0.0;
            $impact = round($days * $rate, 2);
            $r['daily_rate'] = $rate;
            $r['salary_impact'] = $impact;
            $rows[] = $r;
            $totals['employees']++;
            $totals['lwp_days'] += $days;
            $totals['salary_impact'] += $impact;
        }
    }

    $dsql = "SELECT a.attendance_date, a.day_status, a.source, a.remarks, a.working_minutes,
                    e.employee_code, e.employee_name, d.department_name
             FROM attendance_day_status a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE a.attendance_date BETWEEN '{$from}' AND '{$to}'
               AND (
                    a.source = 'auto_lwp'
                    OR UPPER(COALESCE(a.remarks,'')) LIKE '%LWP%'
                    OR a.day_status = 'Absent'
               )
               AND e.status = 1";
    if ($departmentId > 0) {
        $dsql .= ' AND e.department_id = ' . $departmentId;
    }
    $dsql .= ' ORDER BY a.attendance_date DESC, e.employee_code ASC LIMIT 2000';
    $dres = $conn->query($dsql);
    if ($dres) {
        while ($r = $dres->fetch_assoc()) {
            // Skip week-off / holiday mis-tags
            $detail[] = $r;
        }
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'leave_type' => $lt,
        'year' => $year,
        'month' => $month,
        'from' => $from,
        'to' => $to,
        'rows' => $rows,
        'detail' => $detail,
        'totals' => $totals,
    ];
}

function leaveAttachmentUploadDir()
{
    return dirname(__DIR__) . '/assets/uploads/leave_attachments';
}

/**
 * Optional leave request attachment (PDF / image). Returns relative path or null.
 */
function leaveUploadAttachment($file, $employeeId = 0)
{
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Attachment upload failed.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid attachment upload.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Attachment must be under 5 MB.');
    }
    $orig = (string) ($file['name'] ?? 'file');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Attachment allowed types: PDF, JPG, PNG, WEBP, GIF.');
    }
    $dir = leaveAttachmentUploadDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create attachment folder.');
    }
    $safe = 'leave_' . (int) $employeeId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $safe;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save attachment.');
    }
    return 'assets/uploads/leave_attachments/' . $safe;
}

function leaveAttachmentUrl($relativePath)
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || strpos($relativePath, 'assets/uploads/leave_attachments/') !== 0) {
        return '';
    }
    if (strpos($relativePath, '..') !== false) {
        return '';
    }
    return function_exists('app_url') ? app_url($relativePath) : '/' . ltrim($relativePath, '/');
}



