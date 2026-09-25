<?php
/**
 * Attendance — punches, Excel/CSV import, monthly summary → salary diary
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/payroll_helper.php';

function ensureAttendanceTables($conn = null)
{
    static $ready = false;
    if ($ready && $conn === null) {
        return;
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureEmployeesTable($conn);
    ensureEmployeeColumn($conn, 'biometric_user_id', "biometric_user_id VARCHAR(50) DEFAULT NULL AFTER employee_code");

    $conn->query("CREATE TABLE IF NOT EXISTS attendance_punches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        punch_time TIME NOT NULL,
        punch_type ENUM('in','out') NOT NULL DEFAULT 'in',
        shift_name VARCHAR(100) DEFAULT NULL,
        source VARCHAR(30) NOT NULL DEFAULT 'import',
        import_batch_id INT DEFAULT NULL,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_att_punch (employee_id, attendance_date, punch_time, punch_type),
        INDEX idx_att_emp_date (employee_id, attendance_date),
        INDEX idx_att_date (attendance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS attendance_import_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NOT NULL,
        total_rows INT NOT NULL DEFAULT 0,
        success_rows INT NOT NULL DEFAULT 0,
        skipped_rows INT NOT NULL DEFAULT 0,
        error_rows INT NOT NULL DEFAULT 0,
        error_log TEXT,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS attendance_day_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        day_status ENUM('Present','Absent','Week Off','Holiday','Leave','Half Day') NOT NULL DEFAULT 'Absent',
        punch_in TIME DEFAULT NULL,
        punch_out TIME DEFAULT NULL,
        working_minutes INT NOT NULL DEFAULT 0,
        source VARCHAR(30) NOT NULL DEFAULT 'import',
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_att_day (employee_id, attendance_date),
        INDEX idx_att_day_emp_month (employee_id, attendance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Older installs may miss remarks
    $col = $conn->query("SHOW COLUMNS FROM attendance_day_status LIKE 'remarks'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE attendance_day_status ADD COLUMN remarks VARCHAR(255) DEFAULT NULL AFTER source");
    }

    $ready = true;
    if ($closeAfter) {
        $conn->close();
    }
}

function attendanceNormalizeHeader($h)
{
    $h = strtolower(trim((string) $h));
    $h = str_replace([' ', '-'], '_', $h);
    $map = [
        'employee_code' => 'employee_code',
        'emp_code' => 'employee_code',
        'code' => 'employee_code',
        'biometric_user_id' => 'biometric_user_id',
        'biometric_id' => 'biometric_user_id',
        'bio_id' => 'biometric_user_id',
        'employee_name' => 'employee_name',
        'name' => 'employee_name',
        'attendance_date' => 'attendance_date',
        'date' => 'attendance_date',
        'punch_in_time' => 'punch_time',
        'punch_time' => 'punch_time',
        'time' => 'punch_time',
        'punch_out_time' => 'punch_out_time',
        'attendace_type' => 'punch_type',
        'attendance_type' => 'punch_type',
        'punch_type' => 'punch_type',
        'type' => 'punch_type',
        'shift' => 'shift_name',
        'shift_name' => 'shift_name',
        'remarks' => 'remarks',
        'remark' => 'remarks',
    ];
    return $map[$h] ?? $h;
}

function attendanceParseDate($value)
{
    if (function_exists('parseDateInput')) {
        return parseDateInput($value);
    }
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function attendanceParseTime($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
    }
    $ts = strtotime($value);
    return $ts ? date('H:i:s', $ts) : null;
}

function attendanceParsePunchType($value)
{
    $v = strtolower(trim((string) $value));
    if ($v === '' || $v === 'in' || $v === 'punch in' || $v === 'punch_in') {
        return 'in';
    }
    if ($v === 'out' || $v === 'punch out' || $v === 'punch_out') {
        return 'out';
    }
    return 'in';
}

/**
 * Minimal CSV / XLSX / HTML-XLS reader → array of assoc rows
 */
function attendanceReadSpreadsheet($filePath, $ext)
{
    $ext = strtolower($ext);
    if ($ext === 'csv' || $ext === 'txt') {
        return attendanceReadCsv($filePath);
    }
    if ($ext === 'xlsx') {
        return attendanceReadXlsx($filePath);
    }
    if ($ext === 'xls') {
        $raw = file_get_contents($filePath);
        if ($raw !== false && stripos($raw, '<table') !== false) {
            return attendanceReadHtmlTable($raw);
        }
        // Fallback: try as CSV
        return attendanceReadCsv($filePath);
    }
    throw new RuntimeException('Unsupported file type. Use .xlsx, .xls or .csv');
}

function attendanceReadCsv($filePath)
{
    $fh = fopen($filePath, 'r');
    if (!$fh) {
        throw new RuntimeException('Unable to open file');
    }
    $headers = null;
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if ($headers === null) {
            $headers = array_map('attendanceNormalizeHeader', $data);
            continue;
        }
        if (count(array_filter($data, fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $key) {
            $row[$key] = $data[$i] ?? '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function attendanceReadHtmlTable($html)
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $tables = $dom->getElementsByTagName('table');
    if ($tables->length === 0) {
        return [];
    }
    $table = $tables->item(0);
    $trs = $table->getElementsByTagName('tr');
    $headers = null;
    $rows = [];
    foreach ($trs as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $td) {
            if ($td->nodeName === 'td' || $td->nodeName === 'th') {
                $cells[] = trim($td->textContent);
            }
        }
        if (!$cells) {
            continue;
        }
        if ($headers === null) {
            $headers = array_map('attendanceNormalizeHeader', $cells);
            continue;
        }
        $row = [];
        foreach ($headers as $i => $key) {
            $row[$key] = $cells[$i] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
}

function attendanceReadXlsx($filePath)
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Unable to open XLSX file');
    }
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $sx = @simplexml_load_string($ss);
        if ($sx) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $shared[] = $text;
                }
            }
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) {
        throw new RuntimeException('XLSX sheet not found');
    }
    $xml = @simplexml_load_string($sheet);
    if (!$xml) {
        throw new RuntimeException('Invalid XLSX sheet');
    }
    $grid = [];
    foreach ($xml->sheetData->row as $row) {
        $rIdx = (int) $row['r'];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            $col = 0;
            $letters = $m[1];
            for ($i = 0; $i < strlen($letters); $i++) {
                $col = $col * 26 + (ord($letters[$i]) - 64);
            }
            $col--;
            $type = (string) ($c['t'] ?? '');
            $val = isset($c->v) ? (string) $c->v : '';
            if ($type === 's' && isset($shared[(int) $val])) {
                $val = $shared[(int) $val];
            }
            $grid[$rIdx][$col] = $val;
        }
    }
    if (!$grid) {
        return [];
    }
    ksort($grid);
    $headers = null;
    $rows = [];
    foreach ($grid as $cols) {
        ksort($cols);
        $max = max(array_keys($cols));
        $data = [];
        for ($i = 0; $i <= $max; $i++) {
            $data[$i] = $cols[$i] ?? '';
        }
        if ($headers === null) {
            $headers = array_map('attendanceNormalizeHeader', $data);
            continue;
        }
        if (count(array_filter($data, fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $key) {
            $row[$key] = $data[$i] ?? '';
        }
        $rows[] = $row;
    }
    return $rows;
}

function attendanceFindEmployee($conn, array $row)
{
    $code = trim((string) ($row['employee_code'] ?? ''));
    $bio = trim((string) ($row['biometric_user_id'] ?? ''));
    $name = trim((string) ($row['employee_name'] ?? ''));

    if ($code !== '') {
        $stmt = $conn->prepare("SELECT id, employee_code, employee_name, department_id, week_off_day, pay_type FROM employees WHERE status = 1 AND employee_code = ? LIMIT 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($emp) {
            return $emp;
        }
    }
    if ($bio !== '') {
        $stmt = $conn->prepare("SELECT id, employee_code, employee_name, department_id, week_off_day, pay_type FROM employees WHERE status = 1 AND biometric_user_id = ? LIMIT 1");
        $stmt->bind_param('s', $bio);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($emp) {
            return $emp;
        }
    }
    if ($name !== '') {
        $stmt = $conn->prepare("SELECT id, employee_code, employee_name, department_id, week_off_day, pay_type FROM employees WHERE status = 1 AND employee_name = ? LIMIT 2");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if (count($rows) === 1) {
            return $rows[0];
        }
    }
    return null;
}

function attendanceInsertPunch($conn, $employeeId, $date, $time, $type, $source, $batchId, $shift = null, $remarks = null)
{
    $employeeId = (int) $employeeId;
    $batchId = $batchId !== null && $batchId !== '' ? (int) $batchId : 0;
    $shift = $shift !== null ? (string) $shift : '';
    $remarks = $remarks !== null ? (string) $remarks : '';
    $stmt = $conn->prepare(
        "INSERT INTO attendance_punches
            (employee_id, attendance_date, punch_time, punch_type, shift_name, source, import_batch_id, remarks)
         VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, ''))
         ON DUPLICATE KEY UPDATE
            shift_name = VALUES(shift_name),
            source = VALUES(source),
            import_batch_id = VALUES(import_batch_id),
            remarks = VALUES(remarks)"
    );
    $stmt->bind_param('isssssis', $employeeId, $date, $time, $type, $shift, $source, $batchId, $remarks);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $ok ? $affected : 0;
}

function attendanceHolidaySet($conn, $year, $month, $departmentId = 0)
{
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    if (!function_exists('holidayDateMapForWindow')) {
        require_once __DIR__ . '/master_helper.php';
    }
    $map = holidayDateMapForWindow($conn, $from, $to, (int) $departmentId);
    $set = [];
    foreach ($map as $d => $info) {
        $set[$d] = ['paid' => !empty($info['paid'])];
    }
    return $set;
}

/**
 * Count master holidays in month (all / paid-only)
 */
function countHolidaysInMonth($year, $month, $paidOnly = false, $conn = null, $departmentId = 0)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $set = attendanceHolidaySet($conn, $year, $month, $departmentId);
    $n = 0;
    foreach ($set as $info) {
        if ($paidOnly && empty($info['paid'])) {
            continue;
        }
        $n++;
    }
    if ($closeAfter) {
        $conn->close();
    }
    return (float) $n;
}

function attendanceRebuildDayStatus($conn, $employeeId, $month, $year)
{
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    $monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

    $emp = null;
    $st = $conn->prepare('SELECT id, week_off_day, date_of_joining, date_of_exit, department_id FROM employees WHERE id = ? LIMIT 1');
    $st->bind_param('i', $employeeId);
    $st->execute();
    $emp = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$emp) {
        return null;
    }

    $holidays = attendanceHolidaySet($conn, $year, $month, (int) ($emp['department_id'] ?? 0));
    $weekOffName = trim((string) ($emp['week_off_day'] ?? 'Sunday'));
    $joinDate = '';
    if (!empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00') {
        $joinDate = substr((string) $emp['date_of_joining'], 0, 10);
    }
    $exitDate = '';
    if (!empty($emp['date_of_exit']) && $emp['date_of_exit'] !== '0000-00-00') {
        $exitDate = substr((string) $emp['date_of_exit'], 0, 10);
    }

    $punchesByDate = [];
    $st = $conn->prepare(
        "SELECT attendance_date, punch_time, punch_type
         FROM attendance_punches
         WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
         ORDER BY attendance_date ASC, punch_time ASC"
    );
    $st->bind_param('iss', $employeeId, $from, $to);
    $st->execute();
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
        $d = $r['attendance_date'];
        $punchesByDate[$d][] = $r;
    }
    $st->close();

    $summary = [
        'present' => 0.0,
        'half_day' => 0.0,
        'week_off' => 0.0,
        'holiday' => 0.0,
        'leave' => 0.0,
        'absent' => 0.0,
        'working_minutes' => 0,
    ];

    $upsert = $conn->prepare(
        "INSERT INTO attendance_day_status
            (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source)
         VALUES (?, ?, ?, ?, ?, ?, 'import')
         ON DUPLICATE KEY UPDATE
            day_status = VALUES(day_status),
            punch_in = VALUES(punch_in),
            punch_out = VALUES(punch_out),
            working_minutes = VALUES(working_minutes),
            source = VALUES(source)"
    );

    for ($day = 1; $day <= $monthDays; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dow = date('l', strtotime($date));
        $isWeekOff = ($weekOffName !== '' && strcasecmp($dow, $weekOffName) === 0);
        $isHoliday = isset($holidays[$date]);
        $list = $punchesByDate[$date] ?? [];

        // Outside joining → exit window: do not count present / week-off / holiday
        if (($joinDate !== '' && $date < $joinDate) || ($exitDate !== '' && $date > $exitDate)) {
            $status = 'Absent';
            $punchIn = null;
            $punchOut = null;
            $minutes = 0;
            $upsert->bind_param('issssi', $employeeId, $date, $status, $punchIn, $punchOut, $minutes);
            $upsert->execute();
            continue;
        }

        $punchIn = null;
        $punchOut = null;
        foreach ($list as $p) {
            if ($p['punch_type'] === 'in' && $punchIn === null) {
                $punchIn = $p['punch_time'];
            }
            if ($p['punch_type'] === 'out') {
                $punchOut = $p['punch_time'];
            }
        }
        if ($punchIn === null && $list) {
            $punchIn = $list[0]['punch_time'];
        }
        if ($punchOut === null && count($list) > 1) {
            $punchOut = $list[count($list) - 1]['punch_time'];
        }

        $minutes = 0;
        if ($punchIn && $punchOut) {
            $minutes = attendanceWorkingMinutes($date, $punchIn, $punchOut);
        }

        if ($list) {
            if ($minutes > 0 && $minutes < 240) {
                $status = 'Half Day';
                $summary['half_day'] += 1;
                $summary['present'] += 0.5;
            } else {
                $status = 'Present';
                $summary['present'] += 1;
            }
            $summary['working_minutes'] += $minutes;
        } elseif ($isHoliday) {
            $status = 'Holiday';
            $summary['holiday'] += 1;
        } elseif ($isWeekOff) {
            $status = 'Week Off';
            $summary['week_off'] += 1;
        } else {
            $status = 'Absent';
            $summary['absent'] += 1;
        }

        $upsert->bind_param('issssi', $employeeId, $date, $status, $punchIn, $punchOut, $minutes);
        $upsert->execute();
    }
    $upsert->close();

    return $summary;
}

function attendanceSyncSalaryDiary($conn, $employeeId, $month, $year, array $summary)
{
    ensurePayrollTables($conn);
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    $monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $present = round((float) ($summary['present'] ?? 0), 2);
    $weekOff = round((float) ($summary['week_off'] ?? 0), 2);
    $holidayDays = round((float) ($summary['holiday'] ?? 0), 2);
    $otHours = round(((int) ($summary['working_minutes'] ?? 0)) / 60, 2);
    $hasLeaveFromAtt = array_key_exists('pl', $summary) || array_key_exists('sl', $summary) || array_key_exists('dl', $summary);

    // Preserve leave / loan / advance / arrears if diary already exists (unless attendance supplied leave days)
    $existing = null;
    $st = $conn->prepare('SELECT * FROM salary_diary WHERE employee_id = ? AND month_no = ? AND year_no = ? LIMIT 1');
    $st->bind_param('iii', $employeeId, $month, $year);
    $st->execute();
    $existing = $st->get_result()->fetch_assoc();
    $st->close();

    $pl = $hasLeaveFromAtt ? round((float) ($summary['pl'] ?? 0), 2) : ($existing ? (float) ($existing['pl_days'] ?? 0) : 0);
    $sl = $hasLeaveFromAtt ? round((float) ($summary['sl'] ?? 0), 2) : ($existing ? (float) ($existing['sl_days'] ?? 0) : 0);
    $dl = $hasLeaveFromAtt ? round((float) ($summary['dl'] ?? 0), 2) : ($existing ? (float) ($existing['dl_days'] ?? 0) : 0);
    $loan = $existing ? (float) ($existing['loan_amount'] ?? 0) : 0;
    $advance = $existing ? (float) ($existing['advance_amount'] ?? 0) : 0;
    $arrears = $existing ? (float) ($existing['arrears_amount'] ?? 0) : 0;
    $remarks = $existing ? ($existing['remarks'] ?? null) : 'Synced from attendance';

    if (function_exists('ensurePayrollColumn')) {
        ensurePayrollColumn($conn, 'salary_diary', 'holiday_days', 'holiday_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER week_off_days');
    }

    $stmt = $conn->prepare(
        "INSERT INTO salary_diary
            (employee_id, month_no, year_no, working_days, present_days, week_off_days, holiday_days, pl_days, sl_days, dl_days,
             overtime_hours, loan_amount, advance_amount, arrears_amount, remarks)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            working_days = VALUES(working_days),
            present_days = VALUES(present_days),
            week_off_days = VALUES(week_off_days),
            holiday_days = VALUES(holiday_days),
            pl_days = VALUES(pl_days),
            sl_days = VALUES(sl_days),
            dl_days = VALUES(dl_days),
            overtime_hours = VALUES(overtime_hours),
            remarks = VALUES(remarks)"
    );
    $working = (float) $monthDays;
    $stmt->bind_param(
        'iiiddddddddddds',
        $employeeId,
        $month,
        $year,
        $working,
        $present,
        $weekOff,
        $holidayDays,
        $pl,
        $sl,
        $dl,
        $otHours,
        $loan,
        $advance,
        $arrears,
        $remarks
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * If attendance day-status exists for the month, refresh salary diary present / week-off / leave.
 * Paid days = Present + Half + Week Off + Holiday + PL + SL + DL (+ C-Off as present).
 * Respects joining → exit date window. Returns true when diary was updated from attendance.
 */
function ensurePayrollDiaryFromAttendance($employeeId, $month, $year, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureAttendanceTables($conn);
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    if ($employeeId <= 0 || $month < 1 || $month > 12) {
        if ($closeAfter) {
            $conn->close();
        }
        return false;
    }

    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));

    $chk = $conn->prepare(
        "SELECT COUNT(*) AS c FROM attendance_day_status WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $chk->bind_param('iss', $employeeId, $from, $to);
    $chk->execute();
    $dayCount = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
    $chk->close();

    if ($dayCount <= 0) {
        // Rebuild from punches if any
        $pchk = $conn->prepare(
            "SELECT COUNT(*) AS c FROM attendance_punches WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
        );
        $pchk->bind_param('iss', $employeeId, $from, $to);
        $pchk->execute();
        $punchCount = (int) ($pchk->get_result()->fetch_assoc()['c'] ?? 0);
        $pchk->close();
        if ($punchCount > 0) {
            attendanceRebuildDayStatus($conn, $employeeId, $month, $year);
            $dayCount = $punchCount;
        }
    }

    if ($dayCount <= 0) {
        if ($closeAfter) {
            $conn->close();
        }
        return false;
    }

    $joinDate = '';
    $exitDate = '';
    $est = $conn->prepare('SELECT date_of_joining, date_of_exit FROM employees WHERE id = ? LIMIT 1');
    $est->bind_param('i', $employeeId);
    $est->execute();
    $erow = $est->get_result()->fetch_assoc();
    $est->close();
    if ($erow) {
        if (!empty($erow['date_of_joining']) && $erow['date_of_joining'] !== '0000-00-00') {
            $joinDate = substr((string) $erow['date_of_joining'], 0, 10);
        }
        if (!empty($erow['date_of_exit']) && $erow['date_of_exit'] !== '0000-00-00') {
            $exitDate = substr((string) $erow['date_of_exit'], 0, 10);
        }
    }

    $st = $conn->prepare(
        "SELECT attendance_date, day_status, remarks, working_minutes, punch_in, punch_out
         FROM attendance_day_status
         WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
    );
    $st->bind_param('iss', $employeeId, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $dayMap = [];
    $workingMinutes = 0;
    while ($r = $res->fetch_assoc()) {
        $date = substr((string) ($r['attendance_date'] ?? ''), 0, 10);
        if ($date === '') {
            continue;
        }
        $dayMap[$date] = $r;
        $workingMinutes += (int) ($r['working_minutes'] ?? 0);
    }
    $st->close();

    $empRow = [
        'id' => $employeeId,
        'week_off_day' => 'Sunday',
        'date_of_joining' => $joinDate !== '' ? $joinDate : null,
        'date_of_exit' => $exitDate !== '' ? $exitDate : null,
        'department_id' => 0,
    ];
    $est2 = $conn->prepare('SELECT week_off_day, date_of_joining, date_of_exit, department_id FROM employees WHERE id = ? LIMIT 1');
    $est2->bind_param('i', $employeeId);
    $est2->execute();
    $erow2 = $est2->get_result()->fetch_assoc();
    $est2->close();
    if ($erow2) {
        $empRow['week_off_day'] = $erow2['week_off_day'] ?? 'Sunday';
        $empRow['date_of_joining'] = $erow2['date_of_joining'] ?? null;
        $empRow['date_of_exit'] = $erow2['date_of_exit'] ?? null;
        $empRow['department_id'] = (int) ($erow2['department_id'] ?? 0);
    }

    $holidaySet = attendanceHolidaySet($conn, $year, $month, (int) $empRow['department_id']);
    $totals = attendanceBuildEmployeeMonthTotals($empRow, $dayMap, $month, $year, $holidaySet);

    $summary = [
        'present' => (float) $totals['present'] + (float) $totals['C-Off'],
        'week_off' => (float) $totals['week_off'],
        'holiday' => (float) $totals['holiday'],
        'pl' => (float) $totals['PL'],
        'sl' => (float) $totals['SL'],
        'dl' => (float) $totals['DL'],
        'working_minutes' => $workingMinutes,
    ];

    attendanceSyncSalaryDiary($conn, $employeeId, $month, $year, $summary);
    if ($closeAfter) {
        $conn->close();
    }
    return true;
}

function attendanceImportFile($conn, $filePath, $originalName, $userId = null)
{
    ensureAttendanceTables($conn);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $rows = attendanceReadSpreadsheet($filePath, $ext);

    $stmt = $conn->prepare(
        "INSERT INTO attendance_import_batches (file_name, total_rows, created_by) VALUES (?, ?, ?)"
    );
    $total = count($rows);
    $uid = $userId ? (int) $userId : null;
    $stmt->bind_param('sii', $originalName, $total, $uid);
    $stmt->execute();
    $batchId = (int) $conn->insert_id;
    $stmt->close();

    $success = 0;
    $skipped = 0;
    $errors = 0;
    $errorLog = [];
    $touched = []; // empId|Y-m

    $lineNo = 1;
    foreach ($rows as $row) {
        $lineNo++;
        $date = attendanceParseDate($row['attendance_date'] ?? '');
        $time = attendanceParseTime($row['punch_time'] ?? ($row['punch_in_time'] ?? ''));
        $outTime = attendanceParseTime($row['punch_out_time'] ?? '');
        $type = attendanceParsePunchType($row['punch_type'] ?? '');
        $shift = trim((string) ($row['shift_name'] ?? '')) ?: null;
        $remarks = trim((string) ($row['remarks'] ?? '')) ?: null;

        if (!$date || !$time) {
            $errors++;
            $errorLog[] = "Row {$lineNo}: invalid date/time";
            continue;
        }
        $emp = attendanceFindEmployee($conn, $row);
        if (!$emp) {
            $errors++;
            $errorLog[] = "Row {$lineNo}: employee not found";
            continue;
        }
        $empId = (int) $emp['id'];
        $aff = attendanceInsertPunch($conn, $empId, $date, $time, $type, 'import', $batchId, $shift, $remarks);
        if ($aff === 0) {
            $skipped++;
        } else {
            $success++;
        }
        if ($outTime) {
            $aff2 = attendanceInsertPunch($conn, $empId, $date, $outTime, 'out', 'import', $batchId, $shift, $remarks);
            if ($aff2 === 0) {
                $skipped++;
            } else {
                $success++;
            }
        }
        $ym = substr($date, 0, 7);
        $touched[$empId . '|' . $ym] = [$empId, (int) substr($date, 5, 2), (int) substr($date, 0, 4)];
    }

    foreach ($touched as $meta) {
        [$empId, $month, $year] = $meta;
        $summary = attendanceRebuildDayStatus($conn, $empId, $month, $year);
        if ($summary) {
            attendanceSyncSalaryDiary($conn, $empId, $month, $year, $summary);
        }
    }

    $errorText = implode("\n", array_slice($errorLog, 0, 200));
    $upd = $conn->prepare(
        "UPDATE attendance_import_batches
         SET success_rows = ?, skipped_rows = ?, error_rows = ?, error_log = ?
         WHERE id = ?"
    );
    $upd->bind_param('iiisi', $success, $skipped, $errors, $errorText, $batchId);
    $upd->execute();
    $upd->close();

    return [
        'batch_id' => $batchId,
        'total' => $total,
        'success' => $success,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_log' => $errorLog,
        'employees_synced' => count($touched),
    ];
}

function getAttendanceMonthlyReport($conn, $month, $year, $deptId = 0, $employeeId = 0)
{
    ensureAttendanceTables($conn);
    $month = (int) $month;
    $year = (int) $year;
    $deptId = (int) $deptId;
    $employeeId = (int) $employeeId;
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));

    $where = 'e.status = 1 AND e.pay_type IN (\'Salary\',\'Jobwork\')';
    $types = '';
    $params = [];
    if ($deptId > 0) {
        $where .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    }
    if ($employeeId > 0) {
        $where .= ' AND e.id = ?';
        $types .= 'i';
        $params[] = $employeeId;
    }

    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.department_id, e.week_off_day, e.pay_type,
                   d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE {$where}
            ORDER BY d.department_name ASC, e.employee_code ASC";
    if ($params) {
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $emps = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    } else {
        $emps = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $rows = [];
    foreach ($emps as $emp) {
        $eid = (int) $emp['id'];
        // Ensure day status exists if punches exist
        $chk = $conn->prepare(
            "SELECT COUNT(*) AS c FROM attendance_punches WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
        );
        $chk->bind_param('iss', $eid, $from, $to);
        $chk->execute();
        $hasPunches = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();
        if ($hasPunches > 0) {
            attendanceRebuildDayStatus($conn, $eid, $month, $year);
        }

        $st = $conn->prepare(
            "SELECT day_status, COUNT(*) AS c, COALESCE(SUM(working_minutes),0) AS mins
             FROM attendance_day_status
             WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
             GROUP BY day_status"
        );
        $st->bind_param('iss', $eid, $from, $to);
        $st->execute();
        $res = $st->get_result();
        $counts = [
            'Present' => 0, 'Half Day' => 0, 'Week Off' => 0, 'Holiday' => 0,
            'Leave' => 0, 'Absent' => 0, 'mins' => 0,
        ];
        while ($r = $res->fetch_assoc()) {
            $counts[$r['day_status']] = (int) $r['c'];
            $counts['mins'] += (int) $r['mins'];
        }
        $st->close();

        $present = (float) $counts['Present'] + (0.5 * (float) $counts['Half Day']);
        $rows[] = [
            'employee_id' => $eid,
            'employee_code' => $emp['employee_code'],
            'employee_name' => $emp['employee_name'],
            'department_name' => $emp['department_name'] ?: '-',
            'pay_type' => $emp['pay_type'],
            'present' => $present,
            'week_off' => (float) $counts['Week Off'],
            'half_days' => (float) $counts['Half Day'],
            'leave' => (float) $counts['Leave'],
            'absent' => (float) $counts['Absent'],
            'holiday' => (float) $counts['Holiday'],
            'compensated' => 0.0,
            'working_hours' => round($counts['mins'] / 60, 2),
        ];
    }
    return $rows;
}

function attendanceWorkingMinutes($date, $punchIn, $punchOut)
{
    if (!$punchIn || !$punchOut) {
        return 0;
    }
    $start = strtotime($date . ' ' . $punchIn);
    $end = strtotime($date . ' ' . $punchOut);
    if ($start === false || $end === false) {
        return 0;
    }
    if ($end <= $start) {
        $end += 86400;
    }
    return max(0, (int) (($end - $start) / 60));
}

function attendanceTimeForInput($time)
{
    $time = trim((string) $time);
    if ($time === '' || $time === '00:00:00') {
        return '';
    }
    return date('H:i', strtotime($time));
}

function attendanceNormalizeInputTime($time)
{
    $time = trim((string) $time);
    if ($time === '') {
        return null;
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        $time .= ':00';
    }
    $ts = strtotime($time);
    if ($ts === false) {
        return null;
    }
    return date('H:i:s', $ts);
}

/**
 * Resolve employee In/Out display times from Join Employee shift (master match or shift_time text).
 * @return array{in:string,out:string,label:string}
 */
function attendanceResolveEmployeeShiftTimes(array $emp, array $shifts = [], $fallbackIn = '9:00 AM', $fallbackOut = '6:00 PM')
{
    $fallbackIn = trim((string) $fallbackIn) !== '' ? trim((string) $fallbackIn) : '9:00 AM';
    $fallbackOut = trim((string) $fallbackOut) !== '' ? trim((string) $fallbackOut) : '6:00 PM';

    $shiftType = trim((string) ($emp['shift_type'] ?? ''));
    $shiftTime = trim((string) ($emp['shift_time'] ?? ''));
    $inDisp = '';
    $outDisp = '';
    $label = '';

    if ($shifts && function_exists('findMatchingShiftId')) {
        $sid = findMatchingShiftId($shifts, $shiftType !== '' ? $shiftType : 'Day', $shiftTime);
        if ($sid > 0) {
            foreach ($shifts as $s) {
                if ((int) ($s['id'] ?? 0) !== $sid) {
                    continue;
                }
                $inRaw = attendanceTimeForInput($s['start_time'] ?? '');
                $outRaw = attendanceTimeForInput($s['end_time'] ?? '');
                if ($inRaw) {
                    $inDisp = date('g:i A', strtotime($inRaw));
                }
                if ($outRaw) {
                    $outDisp = date('g:i A', strtotime($outRaw));
                }
                $label = function_exists('formatShiftOptionLabel') ? formatShiftOptionLabel($s) : (string) ($s['name'] ?? '');
                break;
            }
        }
    }

    // Parse stored text like "02:00 PM - 10:00 PM" / "9:00 AM - 6:00 PM"
    if (($inDisp === '' || $outDisp === '') && $shiftTime !== '') {
        if (preg_match('/(\d{1,2}:\d{2}\s*[AP]M)\s*[-–to]+\s*(\d{1,2}:\d{2}\s*[AP]M)/i', $shiftTime, $m)) {
            if ($inDisp === '') {
                $tsIn = strtotime($m[1]);
                $inDisp = $tsIn ? date('g:i A', $tsIn) : trim($m[1]);
            }
            if ($outDisp === '') {
                $tsOut = strtotime($m[2]);
                $outDisp = $tsOut ? date('g:i A', $tsOut) : trim($m[2]);
            }
            if ($label === '') {
                $label = $shiftTime . ($shiftType !== '' ? ' · ' . $shiftType : '');
            }
        }
    }

    if ($inDisp === '') {
        $inDisp = $fallbackIn;
    }
    if ($outDisp === '') {
        $outDisp = $fallbackOut;
    }
    if ($label === '') {
        $label = $inDisp . ' → ' . $outDisp;
    }

    return [
        'in' => $inDisp,
        'out' => $outDisp,
        'label' => $label,
    ];
}

/**
 * Empty summary row for Excel-style attendance totals.
 * Columns: Present Days, Week Off, PL, SL, DL, C-Off, Holiday, Total Days, Total Pay Days
 */
function attendanceEmptyLeaveTotals()
{
    return [
        'present' => 0.0,
        'week_off' => 0.0,
        'PL' => 0.0,
        'SL' => 0.0,
        'DL' => 0.0,
        'C-Off' => 0.0,
        'holiday' => 0.0,
        'LWP' => 0.0,
        'total_days' => 0.0,
        'total_pay_days' => 0.0,
    ];
}

/**
 * Map leave type code to totals key (PL / SL / DL / C-Off / LWP).
 */
function attendanceLeaveTotalsKey($type)
{
    $type = strtoupper(trim((string) $type));
    if ($type === '' || $type === 'LEAVE') {
        return 'PL';
    }
    if ($type === 'COFF' || $type === 'C-OFF') {
        return 'C-Off';
    }
    if ($type === 'CL' || $type === 'EL') {
        return 'PL';
    }
    if (in_array($type, ['PL', 'SL', 'DL', 'LWP'], true)) {
        return $type;
    }
    return '';
}

/**
 * Add one resolved day into Excel summary totals.
 */
function attendanceAddDayToLeaveTotals(array &$totals, $status, $leaveType = '', $leaveHalf = '')
{
    $status = (string) $status;
    if ($status === '') {
        return;
    }
    $leaveKey = attendanceLeaveTotalsKey($leaveType);
    $half = strtoupper(trim((string) $leaveHalf));
    $isHalf = ($status === 'Half Day' || in_array($half, ['FHF', 'SHF', 'FHL', 'SHL'], true));
    $inc = $isHalf ? 0.5 : 1.0;

    if ($status === 'Present') {
        $totals['present'] += 1.0;
        return;
    }
    if ($status === 'Week Off') {
        $totals['week_off'] += 1.0;
        return;
    }
    if ($status === 'Holiday') {
        $totals['holiday'] += 1.0;
        return;
    }
    if ($status === 'Half Day') {
        $totals['present'] += 0.5;
        if ($leaveKey !== '' && $leaveKey !== 'LWP') {
            $totals[$leaveKey] += 0.5;
        } elseif ($leaveKey === 'LWP') {
            $totals['LWP'] += 0.5;
        }
        return;
    }
    if ($status === 'Leave') {
        if ($leaveKey === '') {
            $leaveKey = 'PL';
        }
        $totals[$leaveKey] += $inc;
    }
}

/**
 * Finalise Total Days / Total Pay Days from component columns.
 */
function attendanceFinalizeLeaveTotals(array &$totals)
{
    $totals['total_days'] = round(
        (float) $totals['present']
        + (float) $totals['week_off']
        + (float) $totals['PL']
        + (float) $totals['SL']
        + (float) $totals['DL']
        + (float) $totals['C-Off']
        + (float) $totals['holiday'],
        2
    );
    // Paid days = same (LWP / Absent excluded)
    $totals['total_pay_days'] = $totals['total_days'];
}

/**
 * Build month summary + fill missing Week Off / Holiday into day map for display.
 * Present / PL / SL / DL / C-Off come from attendance marks;
 * unmarked Week Off / Holiday days are filled from employee week-off day + holiday master.
 *
 * @param array $emp employee row (week_off_day, date_of_joining, date_of_exit, department_id)
 * @param array $dayMapByDate date => attendance_day_status row (mutated: virtual WO/Holiday added)
 * @param array $holidaySet date => info from attendanceHolidaySet
 * @return array leave totals
 */
function attendanceBuildEmployeeMonthTotals(array $emp, array &$dayMapByDate, $month, $year, array $holidaySet)
{
    $totals = attendanceEmptyLeaveTotals();
    $month = (int) $month;
    $year = (int) $year;
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $weekOffName = trim((string) ($emp['week_off_day'] ?? 'Sunday'));
    if ($weekOffName === '') {
        $weekOffName = 'Sunday';
    }

    $joinDate = '';
    $exitDate = '';
    if (!empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00') {
        $joinDate = substr((string) $emp['date_of_joining'], 0, 10);
    }
    if (!empty($emp['date_of_exit']) && $emp['date_of_exit'] !== '0000-00-00') {
        $exitDate = substr((string) $emp['date_of_exit'], 0, 10);
    }

    for ($d = 1; $d <= $monthDays; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        if ($joinDate !== '' && $date < $joinDate) {
            continue;
        }
        if ($exitDate !== '' && $date > $exitDate) {
            continue;
        }

        $cell = $dayMapByDate[$date] ?? null;
        $status = trim((string) ($cell['day_status'] ?? ''));
        $dowName = date('l', strtotime($date));
        $isCalWeekOff = (strcasecmp($dowName, $weekOffName) === 0);
        $isCalHoliday = isset($holidaySet[$date]);

        // Holiday wins over Week Off — same day must count only once
        if ($isCalHoliday && ($status === '' || $status === 'Week Off' || $status === 'Absent')) {
            $wasEmpty = ($status === '' || !empty($cell['_virtual']));
            $status = 'Holiday';
            $dayMapByDate[$date] = [
                'employee_id' => (int) ($emp['id'] ?? 0),
                'attendance_date' => $date,
                'day_status' => 'Holiday',
                'punch_in' => null,
                'punch_out' => null,
                'remarks' => (string) (($cell['remarks'] ?? '') ?: ''),
                'working_minutes' => 0,
                '_virtual' => $wasEmpty ? 1 : 0,
            ];
            attendanceAddDayToLeaveTotals($totals, 'Holiday');
            continue;
        }

        // No attendance mark → apply employee Week Off from calendar
        if ($status === '') {
            if ($isCalWeekOff) {
                $status = 'Week Off';
                $dayMapByDate[$date] = [
                    'employee_id' => (int) ($emp['id'] ?? 0),
                    'attendance_date' => $date,
                    'day_status' => 'Week Off',
                    'punch_in' => null,
                    'punch_out' => null,
                    'remarks' => '',
                    'working_minutes' => 0,
                    '_virtual' => 1,
                ];
                attendanceAddDayToLeaveTotals($totals, $status);
            }
            continue;
        }

        $parsed = attendanceParseLeaveRemark($cell['remarks'] ?? '');
        $type = $parsed['leave_type'];
        $half = $parsed['leave_half'];
        if ($type === '' && $status === 'Leave') {
            $type = 'PL';
        }
        attendanceAddDayToLeaveTotals($totals, $status, $type, $half);
    }

    attendanceFinalizeLeaveTotals($totals);
    return $totals;
}

/**
 * Excel-style monthly attendance grid (same as Attendance Report.xlsx)
 * Summary: Present Days, Week Off, PL, SL, DL, C-Off, Holiday, Total Days, Total Pay Days
 *
 * @return array{employees:array, days:array, leave_totals:array, month_days:int, from:string, to:string}
 */
function getAttendanceExcelMonthGrid($month, $year, $deptId = 0, $employeeId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureAttendanceTables($conn);
    $deptId = (int) $deptId;
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

    $where = "(
                e.status = 1
                OR (
                    e.date_of_exit IS NOT NULL
                    AND e.date_of_exit != ''
                    AND e.date_of_exit != '0000-00-00'
                    AND e.date_of_exit >= '{$conn->real_escape_string($from)}'
                )
            )
            AND (
                e.date_of_joining IS NULL
                OR e.date_of_joining = ''
                OR e.date_of_joining = '0000-00-00'
                OR e.date_of_joining <= '{$conn->real_escape_string($to)}'
            )";
    $types = '';
    $params = [];
    if ($deptId > 0) {
        $where .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    }
    if ($employeeId > 0) {
        $where .= ' AND e.id = ?';
        $types .= 'i';
        $params[] = $employeeId;
    }

    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.date_of_joining, e.date_of_exit,
                   e.week_off_day, e.shift_type, e.shift_time, e.pay_type, e.department_id,
                   d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE {$where}
            ORDER BY d.department_name ASC, e.employee_code ASC, e.employee_name ASC";

    if ($types !== '') {
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $employees = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    } else {
        $employees = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $dayMap = [];
    if ($employees) {
        $ids = array_map(static function ($e) {
            return (int) $e['id'];
        }, $employees);
        $idList = implode(',', $ids);
        $res = $conn->query(
            "SELECT employee_id, attendance_date, day_status, punch_in, punch_out, remarks, working_minutes
             FROM attendance_day_status
             WHERE attendance_date BETWEEN '{$conn->real_escape_string($from)}' AND '{$conn->real_escape_string($to)}'
               AND employee_id IN ({$idList})"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $dayMap[(int) $r['employee_id']][$r['attendance_date']] = $r;
            }
        }
    }

    $leaveTotals = [];
    $holidayCache = [];
    foreach ($employees as $emp) {
        $eid = (int) $emp['id'];
        $deptKey = (int) ($emp['department_id'] ?? 0);
        if (!isset($holidayCache[$deptKey])) {
            $holidayCache[$deptKey] = attendanceHolidaySet($conn, $year, $month, $deptKey);
        }
        if (!isset($dayMap[$eid])) {
            $dayMap[$eid] = [];
        }
        $leaveTotals[$eid] = attendanceBuildEmployeeMonthTotals(
            $emp,
            $dayMap[$eid],
            $month,
            $year,
            $holidayCache[$deptKey]
        );
    }

    if ($closeAfter) {
        $conn->close();
    }

    return [
        'employees' => $employees,
        'days' => $dayMap,
        'leave_totals' => $leaveTotals,
        'month_days' => $monthDays,
        'from' => $from,
        'to' => $to,
        'month' => $month,
        'year' => $year,
    ];
}

/**
 * Department employees + full month day map for Excel-style manual grid
 * @return array{employees:array, days:array<int,array>, leave_totals:array}
 */
function getDepartmentManualAttendanceMonth($deptId, $month, $year, $conn = null)
{
    return getAttendanceExcelMonthGrid($month, $year, (int) $deptId, 0, $conn);
}

/**
 * Format leave total for display (1 / 0.5 / blank)
 */
function attendanceFormatLeaveTotal($n)
{
    $n = (float) $n;
    if ($n <= 0) {
        return '';
    }
    if (abs($n - (int) $n) < 0.001) {
        return (string) (int) $n;
    }
    return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
}

/**
 * Render Excel-format month table HTML (screen or export).
 * @param array $grid from getAttendanceExcelMonthGrid
 * @param array $opts export=bool, tableClass=string, editable=bool
 */
function attendanceRenderExcelMonthTableHtml(array $grid, array $opts = [])
{
    $export = !empty($opts['export']);
    $tableClass = $opts['tableClass'] ?? ($export ? '' : 'data-table excel-att-table');
    $monthDays = (int) ($grid['month_days'] ?? 0);
    $month = (int) ($grid['month'] ?? date('n'));
    $year = (int) ($grid['year'] ?? date('Y'));
    $employees = $grid['employees'] ?? [];
    $dayMap = $grid['days'] ?? [];
    $leaveTotals = $grid['leave_totals'] ?? [];

    $dayNames = [];
    for ($d = 1; $d <= $monthDays; $d++) {
        $dayNames[$d] = date('D', mktime(0, 0, 0, $month, $d, $year));
    }

    $border = $export ? ' border="1"' : '';
    $html = '<table class="' . htmlspecialchars($tableClass) . '"' . $border . ' style="width:100%;border-collapse:collapse;">';
    $html .= '<thead>';
    $html .= '<tr>';
    $html .= '<th>Employee Code</th><th>Employee Name</th><th>Designation</th><th>Department</th><th>Date of Joining</th>';
    for ($d = 1; $d <= $monthDays; $d++) {
        $thStyle = $export ? 'text-align:center;font-weight:700;background:#1e3a5f;color:#fff;' : 'text-align:center;';
        $html .= '<th style="' . $thStyle . '">' . $d . '<br><span style="font-weight:500;font-size:10px;opacity:0.9;">' . htmlspecialchars($dayNames[$d]) . '</span></th>';
    }
    $sumStyle = $export ? 'text-align:center;font-weight:700;background:#f58220;color:#fff;' : 'text-align:center;';
    $html .= '<th style="' . $sumStyle . '">Present Days</th>';
    $html .= '<th style="' . $sumStyle . '">Week Off</th>';
    $html .= '<th style="' . $sumStyle . '">PL</th>';
    $html .= '<th style="' . $sumStyle . '">SL</th>';
    $html .= '<th style="' . $sumStyle . '">DL</th>';
    $html .= '<th style="' . $sumStyle . '">C-Off</th>';
    $html .= '<th style="' . $sumStyle . '">Holiday</th>';
    $html .= '<th style="' . $sumStyle . '">Total Days</th>';
    $html .= '<th style="' . $sumStyle . '">Total Pay Days</th>';
    $html .= '</tr></thead><tbody>';

    if (!$employees) {
        $cols = 5 + $monthDays + 9;
        $html .= '<tr><td colspan="' . $cols . '">No employees found.</td></tr>';
    }

    foreach ($employees as $emp) {
        $eid = (int) $emp['id'];
        $totals = $leaveTotals[$eid] ?? attendanceEmptyLeaveTotals();
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['employee_code'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['employee_name'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['designation'] ?? '')) . '</td>';
        $html .= '<td>' . htmlspecialchars((string) ($emp['department_name'] ?? '')) . '</td>';
        $doj = '';
        if (function_exists('formatDateDisplay')) {
            $doj = formatDateDisplay($emp['date_of_joining'] ?? '');
        } elseif (!empty($emp['date_of_joining'])) {
            $ts = strtotime((string) $emp['date_of_joining']);
            $doj = $ts ? date('d-m-Y', $ts) : '';
        }
        $html .= '<td>' . htmlspecialchars($doj) . '</td>';

        for ($d = 1; $d <= $monthDays; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $day = $dayMap[$eid][$date] ?? null;
            $html .= attendanceRenderDayCellTd($day, $export);
        }

        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['present'] ?? 0) !== '' ? attendanceFormatLeaveTotal($totals['present'] ?? 0) : '0') . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['week_off'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['PL'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['SL'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['DL'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['C-Off'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['holiday'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['total_days'] ?? 0) !== '' ? attendanceFormatLeaveTotal($totals['total_days'] ?? 0) : '0') . '</td>';
        $html .= '<td style="text-align:center;">' . htmlspecialchars(attendanceFormatLeaveTotal($totals['total_pay_days'] ?? 0) !== '' ? attendanceFormatLeaveTotal($totals['total_pay_days'] ?? 0) : '0') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    return $html;
}

/**
 * One day cell for report / export — bold status labels, FHL/SHL + punch line
 */
function attendanceRenderDayCellTd($day, $export = false)
{
    $text = $day ? attendanceDayToExcelText($day) : '';
    $status = (string) ($day['day_status'] ?? '');
    $parsed = attendanceParseLeaveRemark($day['remarks'] ?? '');
    $half = strtoupper((string) ($parsed['leave_half'] ?? ''));
    if ($half === 'FHF') {
        $half = 'FHL';
    }
    if ($half === 'SHF') {
        $half = 'SHL';
    }

    $cssClass = 'att-day';
    $bg = '';
    $fg = '';
    if ($status === 'Present') {
        $cssClass .= ' is-present';
        $bg = '#ecfdf5';
        $fg = '#047857';
    } elseif ($status === 'Half Day' || in_array($half, ['FHL', 'SHL'], true)) {
        $cssClass .= ' is-half';
        $bg = '#fff7ed';
        $fg = '#c2410c';
    } elseif ($status === 'Leave') {
        $cssClass .= ' is-leave';
        $bg = '#fef2f2';
        $fg = '#b91c1c';
    } elseif ($status === 'Week Off') {
        $cssClass .= ' is-weekoff';
        $bg = '#f1f5f9';
        $fg = '#475569';
    } elseif ($status === 'Holiday') {
        $cssClass .= ' is-holiday';
        $bg = '#eff6ff';
        $fg = '#1d4ed8';
    } elseif ($status === 'Absent') {
        $cssClass .= ' is-absent';
        $bg = '#fee2e2';
        $fg = '#991b1b';
    }

    $lines = $text !== '' ? preg_split("/\r\n|\n|\r/", $text) : [];
    $inner = '';
    if ($lines) {
        foreach ($lines as $i => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $safe = htmlspecialchars($line);
            if ($i === 0) {
                $inner .= '<strong class="att-cell-status">' . $safe . '</strong>';
            } else {
                $inner .= '<span class="att-cell-time">' . $safe . '</span>';
            }
        }
    }

    if ($export) {
        $style = 'text-align:center;font-size:11px;vertical-align:middle;padding:6px 4px;';
        if ($bg !== '') {
            $style .= 'background:' . $bg . ';color:' . $fg . ';';
        }
        $exportInner = '';
        foreach ($lines as $i => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $safe = htmlspecialchars($line);
            if ($i === 0) {
                $exportInner .= '<div style="font-weight:700;line-height:1.25;">' . $safe . '</div>';
            } else {
                $exportInner .= '<div style="font-weight:600;font-size:10px;opacity:0.9;margin-top:2px;">' . $safe . '</div>';
            }
        }
        return '<td style="' . $style . '">' . $exportInner . '</td>';
    }

    return '<td class="' . htmlspecialchars($cssClass) . '">' . $inner . '</td>';
}

/**
 * Format stored day row as Excel cell text
 */
function attendanceDayToExcelText(array $day = null)
{
    if (!$day || empty($day['day_status'])) {
        return '';
    }
    $parsed = attendanceParseLeaveRemark($day['remarks'] ?? '');
    return attendanceExcelCellPreview(
        $day['day_status'],
        $day['punch_in'] ?? '',
        $day['punch_out'] ?? '',
        $parsed['leave_type'],
        $parsed['leave_half']
    );
}


/**
 * Build Excel-style day cell text: "9:00 AM | 06:00 PM" / "PL" / "week off"
 * Half leave: FHL/SHL label on top, punch in/out below
 */
function attendanceExcelCellPreview($status, $punchIn, $punchOut, $leaveType = '', $leaveHalf = '')
{
    $status = (string) $status;
    $leaveType = strtoupper(trim((string) $leaveType));
    $leaveHalf = strtoupper(trim((string) $leaveHalf));
    if ($leaveHalf === 'FHF') {
        $leaveHalf = 'FHL';
    }
    if ($leaveHalf === 'SHF') {
        $leaveHalf = 'SHL';
    }

    if ($status === 'Week Off') {
        return 'Week Off';
    }
    if ($status === 'Holiday') {
        return 'Holiday';
    }
    if ($status === 'Absent') {
        return 'Absent';
    }
    if ($status === 'Leave' && ($leaveHalf === '' || $leaveHalf === 'FULL')) {
        return $leaveType !== '' ? $leaveType : 'Leave';
    }

    $inDisp = '';
    $outDisp = '';
    if ($punchIn) {
        $ts = strtotime((string) $punchIn);
        $inDisp = $ts ? date('g:i A', $ts) : '';
    }
    if ($punchOut) {
        $ts = strtotime((string) $punchOut);
        $outDisp = $ts ? date('g:i A', $ts) : '';
    }

    $timePart = '';
    if ($inDisp !== '' || $outDisp !== '') {
        $timePart = trim($inDisp . ' | ' . $outDisp, ' |');
    }

    // First / Second half leave — short FHL/SHL + punch times below
    if (in_array($leaveHalf, ['FHL', 'SHL'], true) || $status === 'Half Day') {
        $halfLabel = '';
        if ($leaveHalf === 'FHL' || $leaveHalf === 'SHL') {
            $halfLabel = $leaveType !== '' ? ($leaveType . ' · ' . $leaveHalf) : $leaveHalf;
        } elseif ($status === 'Half Day') {
            $halfLabel = $leaveType !== '' ? ($leaveType . ' · Half') : 'Half Day';
        }
        if ($halfLabel !== '' && $timePart !== '') {
            return $halfLabel . "\n" . $timePart;
        }
        if ($halfLabel !== '') {
            return $halfLabel;
        }
        if ($timePart !== '') {
            return $timePart;
        }
    }

    $leavePart = '';
    if ($leaveType !== '') {
        $leavePart = $leaveType;
        if (in_array($leaveHalf, ['FHL', 'SHL'], true)) {
            $leavePart .= ' ' . $leaveHalf;
        }
    }

    if ($timePart !== '' && $leavePart !== '') {
        return $timePart . "\n" . $leavePart;
    }
    if ($timePart !== '') {
        return $timePart;
    }
    if ($leavePart !== '') {
        return $leavePart;
    }
    return $status !== '' ? $status : '-';
}

/**
 * Parse leave type / half from saved remarks (e.g. "PL SHF", "SL")
 */
function attendanceParseLeaveRemark($remarks)
{
    $remarks = strtoupper(trim((string) $remarks));
    $out = ['leave_type' => '', 'leave_half' => ''];
    if ($remarks === '') {
        return $out;
    }
    if (preg_match('/\b(PL|SL|C-?OFF|COFF|DL|LWP|CL|EL)\b/', $remarks, $m)) {
        $code = strtoupper($m[1]);
        if ($code === 'COFF' || $code === 'C-OFF') {
            $code = 'C-Off';
        }
        $out['leave_type'] = $code;
    }
    if (preg_match('/\b(FHL|SHL|FHF|SHF|FULL)\b/', $remarks, $m)) {
        $h = strtoupper($m[1]);
        if ($h === 'FHF') {
            $h = 'FHL';
        }
        if ($h === 'SHF') {
            $h = 'SHL';
        }
        $out['leave_half'] = $h;
    }
    return $out;
}

/**
 * Save one employee day from manual department entry
 * @param bool $syncDiary when false, caller should sync diary once per employee/month
 */
function attendanceSaveManualDay($conn, $employeeId, $date, $status, $punchIn, $punchOut, $shiftName = null, $remarks = null, $leaveType = null, $leaveHalf = null, $syncDiary = true)
{
    ensureAttendanceTables($conn);
    $employeeId = (int) $employeeId;
    $date = date('Y-m-d', strtotime($date));
    $allowed = ['Present', 'Absent', 'Week Off', 'Holiday', 'Leave', 'Half Day'];
    if (!in_array($status, $allowed, true)) {
        $status = 'Present';
    }
    $punchIn = attendanceNormalizeInputTime($punchIn);
    $punchOut = attendanceNormalizeInputTime($punchOut);
    $shiftName = $shiftName !== null && $shiftName !== '' ? (string) $shiftName : null;

    $leaveType = strtoupper(trim((string) $leaveType));
    if ($leaveType === 'COFF') {
        $leaveType = 'C-OFF';
    }
    $leaveHalf = strtoupper(trim((string) $leaveHalf));
    if ($leaveHalf === 'FHF') {
        $leaveHalf = 'FHL';
    }
    if ($leaveHalf === 'SHF') {
        $leaveHalf = 'SHL';
    }
    if (!in_array($leaveHalf, ['FULL', 'FHL', 'SHL'], true)) {
        $leaveHalf = '';
    }

    // Build remarks like Excel: "PL SHL" / "SL" / free text
    $remarkParts = [];
    if (in_array($status, ['Leave', 'Half Day'], true) && $leaveType !== '') {
        $tag = $leaveType === 'C-OFF' ? 'C-Off' : $leaveType;
        if ($status === 'Half Day' && in_array($leaveHalf, ['FHL', 'SHL'], true)) {
            $tag .= ' ' . $leaveHalf;
        } elseif ($status === 'Leave' && $leaveHalf === 'FULL') {
            // full day leave — type only
        } elseif ($status === 'Leave' && in_array($leaveHalf, ['FHL', 'SHL'], true)) {
            $tag .= ' ' . $leaveHalf;
            $status = 'Half Day';
        }
        $remarkParts[] = $tag;
    }
    $extraRemark = trim((string) $remarks);
    // Strip old leave tags from free remark to avoid duplication
    if ($extraRemark !== '') {
        $extraRemark = trim(preg_replace('/\b(PL|SL|C-?Off|COFF|DL|LWP|CL|EL)(\s+(FHF|SHF|FHL|SHL|FULL))?\b/i', '', $extraRemark));
        if ($extraRemark !== '') {
            $remarkParts[] = $extraRemark;
        }
    }
    $remarks = $remarkParts ? implode(' · ', $remarkParts) : null;

    $del = $conn->prepare('DELETE FROM attendance_punches WHERE employee_id = ? AND attendance_date = ?');
    $del->bind_param('is', $employeeId, $date);
    $del->execute();
    $del->close();

    $minutes = 0;
    if (in_array($status, ['Present', 'Half Day'], true)) {
        if ($punchIn) {
            attendanceInsertPunch($conn, $employeeId, $date, $punchIn, 'in', 'manual', null, $shiftName, $remarks);
        }
        if ($punchOut) {
            attendanceInsertPunch($conn, $employeeId, $date, $punchOut, 'out', 'manual', null, $shiftName, $remarks);
        }
        $minutes = attendanceWorkingMinutes($date, $punchIn, $punchOut);
        if ($status === 'Present' && $minutes > 0 && $minutes < 240) {
            $status = 'Half Day';
        }
    } elseif ($status === 'Leave') {
        $punchIn = null;
        $punchOut = null;
        $minutes = 0;
    } else {
        $punchIn = null;
        $punchOut = null;
        $minutes = 0;
    }

    $upsert = $conn->prepare(
        "INSERT INTO attendance_day_status
            (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source, remarks)
         VALUES (?, ?, ?, ?, ?, ?, 'manual', ?)
         ON DUPLICATE KEY UPDATE
            day_status = VALUES(day_status),
            punch_in = VALUES(punch_in),
            punch_out = VALUES(punch_out),
            working_minutes = VALUES(working_minutes),
            source = 'manual',
            remarks = VALUES(remarks)"
    );
    $upsert->bind_param('issssis', $employeeId, $date, $status, $punchIn, $punchOut, $minutes, $remarks);
    $upsert->execute();
    $upsert->close();

    // Sync diary buckets for salary
    if ($syncDiary && function_exists('ensurePayrollDiaryFromAttendance')) {
        $m = (int) date('n', strtotime($date));
        $y = (int) date('Y', strtotime($date));
        ensurePayrollDiaryFromAttendance($employeeId, $m, $y, $conn);
    }
    return true;
}
