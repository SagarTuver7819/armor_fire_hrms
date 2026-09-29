<?php
/**
 * Employee hourly KPI sheets (shift-wise)
 */

if (!function_exists('ensureKpiTables')) {

    function ensureKpiTables($conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS kpi_sheets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                kpi_date DATE NOT NULL,
                shift_label VARCHAR(120) DEFAULT NULL,
                shift_in VARCHAR(30) DEFAULT NULL,
                shift_out VARCHAR(30) DEFAULT NULL,
                status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
                responsibility_ack TINYINT(1) NOT NULL DEFAULT 0,
                prepared_by_name VARCHAR(150) DEFAULT NULL,
                submitted_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_kpi_emp_date (employee_id, kpi_date),
                INDEX idx_kpi_date_status (kpi_date, status),
                INDEX idx_kpi_employee (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS kpi_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sheet_id INT NOT NULL,
                slot_index INT NOT NULL DEFAULT 0,
                time_from VARCHAR(20) NOT NULL DEFAULT '',
                time_to VARCHAR(20) NOT NULL DEFAULT '',
                activity_text TEXT,
                slot_status ENUM('open','submitted') NOT NULL DEFAULT 'open',
                slot_submitted_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_kpi_sheet_slot (sheet_id, slot_index),
                INDEX idx_kpi_entries_sheet (sheet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Upgrade older installs
        $colCheck = $conn->query("SHOW COLUMNS FROM kpi_entries LIKE 'slot_status'");
        if (!$colCheck || $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE kpi_entries ADD COLUMN slot_status ENUM('open','submitted') NOT NULL DEFAULT 'open' AFTER activity_text");
            $conn->query("ALTER TABLE kpi_entries ADD COLUMN slot_submitted_at DATETIME DEFAULT NULL AFTER slot_status");
        }

        if ($close) {
            $conn->close();
        }
    }

    /**
     * Parse "g:i A" / "H:i" to minutes from midnight.
     */
    function kpiTimeToMinutes($time)
    {
        $time = trim((string) $time);
        if ($time === '') {
            return null;
        }
        $ts = strtotime($time);
        if ($ts === false) {
            return null;
        }
        return ((int) date('G', $ts) * 60) + (int) date('i', $ts);
    }

    function kpiMinutesToLabel($mins)
    {
        $mins = ((int) $mins) % (24 * 60);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return date('H:i', mktime($h, $m, 0));
    }

    function kpiMinutesToDisplay($mins)
    {
        $mins = ((int) $mins) % (24 * 60);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return date('g:i A', mktime($h, $m, 0));
    }

    /**
     * Build hourly slots from employee shift (inclusive start → end).
     * Night shifts that cross midnight are supported.
     *
     * @return array<int,array{index:int,time_from:string,time_to:string,label:string}>
     */
    function kpiBuildHourlySlots(array $emp, array $shifts = [])
    {
        $resolved = ['in' => '9:00 AM', 'out' => '6:00 PM', 'label' => '9:00 AM → 6:00 PM'];
        if (function_exists('attendanceResolveEmployeeShiftTimes')) {
            $resolved = attendanceResolveEmployeeShiftTimes($emp, $shifts);
        }

        $start = kpiTimeToMinutes($resolved['in']);
        $end = kpiTimeToMinutes($resolved['out']);
        if ($start === null) {
            $start = 9 * 60;
        }
        if ($end === null) {
            $end = 18 * 60;
        }

        // Align to hour boundaries
        $start = (int) (floor($start / 60) * 60);
        $end = (int) (ceil($end / 60) * 60);
        if ($end === $start) {
            $end = $start + 60;
        }

        $slots = [];
        $cur = $start;
        $guard = 0;
        while ($guard < 24) {
            $next = $cur + 60;
            $slots[] = [
                'index' => count($slots) + 1,
                'time_from' => kpiMinutesToLabel($cur),
                'time_to' => kpiMinutesToLabel($next),
                'label' => kpiMinutesToLabel($cur) . ' – ' . kpiMinutesToLabel($next),
                'label_ampm' => kpiMinutesToDisplay($cur) . ' – ' . kpiMinutesToDisplay($next),
            ];
            $cur = $next % (24 * 60);
            $guard++;
            if ($end > $start) {
                if ($cur >= $end || $guard >= 16) {
                    break;
                }
            } else {
                // overnight
                if ($cur === $end || $guard >= 16) {
                    break;
                }
            }
        }

        if (!$slots) {
            $slots[] = [
                'index' => 1,
                'time_from' => '09:00',
                'time_to' => '10:00',
                'label' => '09:00 – 10:00',
                'label_ampm' => '9:00 AM – 10:00 AM',
            ];
        }

        return [
            'slots' => $slots,
            'shift_in' => $resolved['in'],
            'shift_out' => $resolved['out'],
            'shift_label' => $resolved['label'],
        ];
    }

    function kpiGetSheetById($sheetId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $sheetId = (int) $sheetId;
        $st = $conn->prepare('SELECT * FROM kpi_sheets WHERE id = ? LIMIT 1');
        $st->bind_param('i', $sheetId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $row ?: null;
    }

    function kpiGetSheet($employeeId, $date, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $employeeId = (int) $employeeId;
        $date = substr((string) $date, 0, 10);
        $st = $conn->prepare('SELECT * FROM kpi_sheets WHERE employee_id = ? AND kpi_date = ? LIMIT 1');
        $st->bind_param('is', $employeeId, $date);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $row ?: null;
    }

    function kpiGetEntries($sheetId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $sheetId = (int) $sheetId;
        $entries = [];
        $st = $conn->prepare(
            'SELECT * FROM kpi_entries WHERE sheet_id = ? ORDER BY slot_index ASC'
        );
        $st->bind_param('i', $sheetId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $entries[(int) $r['slot_index']] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $entries;
    }

    /**
     * Create draft sheet + empty slots if missing.
     */
    function kpiEnsureDraftSheet($employeeId, $date, array $emp, array $shifts = [], $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $employeeId = (int) $employeeId;
        $date = substr((string) $date, 0, 10);

        $existing = kpiGetSheet($employeeId, $date, $conn);
        if ($existing) {
            if ($close) {
                $conn->close();
            }
            return $existing;
        }

        $built = kpiBuildHourlySlots($emp, $shifts);
        $shiftIn = $built['shift_in'];
        $shiftOut = $built['shift_out'];
        $shiftLabel = $built['shift_label'];
        $prepared = trim((string) ($emp['employee_name'] ?? ''));

        $st = $conn->prepare(
            "INSERT INTO kpi_sheets (employee_id, kpi_date, shift_label, shift_in, shift_out, status, prepared_by_name)
             VALUES (?, ?, ?, ?, ?, 'draft', ?)"
        );
        $st->bind_param('isssss', $employeeId, $date, $shiftLabel, $shiftIn, $shiftOut, $prepared);
        $st->execute();
        $sheetId = (int) $conn->insert_id;
        $st->close();

        $ins = $conn->prepare(
            'INSERT INTO kpi_entries (sheet_id, slot_index, time_from, time_to, activity_text) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($built['slots'] as $slot) {
            $idx = (int) $slot['index'];
            $tf = $slot['time_from'];
            $tt = $slot['time_to'];
            $act = '';
            $ins->bind_param('iisss', $sheetId, $idx, $tf, $tt, $act);
            $ins->execute();
        }
        $ins->close();

        $sheet = kpiGetSheetById($sheetId, $conn);
        if ($close) {
            $conn->close();
        }
        return $sheet;
    }

    function kpiSaveDraft($sheetId, array $activitiesByIndex, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $sheetId = (int) $sheetId;
        $sheet = kpiGetSheetById($sheetId, $conn);
        if (!$sheet || ($sheet['status'] ?? '') === 'submitted') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Sheet not editable.'];
        }

        $st = $conn->prepare(
            "UPDATE kpi_entries SET activity_text = ?
             WHERE sheet_id = ? AND slot_index = ? AND slot_status = 'open'"
        );
        foreach ($activitiesByIndex as $idx => $text) {
            $idx = (int) $idx;
            $text = trim((string) $text);
            if (function_exists('mb_substr')) {
                $text = mb_substr($text, 0, 2000);
            } else {
                $text = substr($text, 0, 2000);
            }
            $st->bind_param('sii', $text, $sheetId, $idx);
            $st->execute();
        }
        $st->close();

        if ($close) {
            $conn->close();
        }
        return ['ok' => true];
    }

    /**
     * Submit one hourly slot (locks that hour).
     */
    function kpiSubmitHour($sheetId, $slotIndex, $activityText, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $sheetId = (int) $sheetId;
        $slotIndex = (int) $slotIndex;
        $sheet = kpiGetSheetById($sheetId, $conn);
        if (!$sheet || ($sheet['status'] ?? '') === 'submitted') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Sheet not editable.'];
        }

        $activityText = trim((string) $activityText);
        if (function_exists('mb_substr')) {
            $activityText = mb_substr($activityText, 0, 2000);
        } else {
            $activityText = substr($activityText, 0, 2000);
        }
        if ($activityText === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please enter activity for this hour before Submit.'];
        }

        $st = $conn->prepare(
            "UPDATE kpi_entries
             SET activity_text = ?, slot_status = 'submitted', slot_submitted_at = NOW()
             WHERE sheet_id = ? AND slot_index = ? AND slot_status = 'open'"
        );
        $st->bind_param('sii', $activityText, $sheetId, $slotIndex);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();

        if ($close) {
            $conn->close();
        }
        if (!$ok) {
            return ['ok' => false, 'error' => 'This hour is already submitted or not found.'];
        }
        return ['ok' => true];
    }

    function kpiSubmitSheet($sheetId, $ack, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $sheetId = (int) $sheetId;
        $sheet = kpiGetSheetById($sheetId, $conn);
        if (!$sheet) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'KPI sheet not found.'];
        }
        if (($sheet['status'] ?? '') === 'submitted') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Already submitted.'];
        }
        if (!$ack) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please confirm: Based on my best knowledge, the above information is correct, and I confirm its accuracy.'];
        }

        $entries = kpiGetEntries($sheetId, $conn);
        if (!$entries) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'No hourly slots found.'];
        }
        $openCount = 0;
        $filled = 0;
        foreach ($entries as $e) {
            if (($e['slot_status'] ?? 'open') !== 'submitted') {
                $openCount++;
            }
            if (trim((string) ($e['activity_text'] ?? '')) !== '') {
                $filled++;
            }
        }
        if ($openCount > 0) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please Submit each hour first. ' . $openCount . ' hour(s) still pending.'];
        }
        if ($filled < 1) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please fill at least one hour activity before submit.'];
        }

        $st = $conn->prepare(
            "UPDATE kpi_sheets
             SET status = 'submitted', responsibility_ack = 1, submitted_at = NOW()
             WHERE id = ? AND status = 'draft'"
        );
        $st->bind_param('i', $sheetId);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();

        if ($close) {
            $conn->close();
        }
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Submit failed.'];
    }

    /**
     * Report visibility: submitted sheets show from next calendar day (for employees).
     * HR/Admin can always view submitted sheets.
     */
    function kpiCanViewReport(array $sheet, $asStaff = false)
    {
        if (($sheet['status'] ?? '') !== 'submitted') {
            return false;
        }
        if ($asStaff) {
            return true;
        }
        $kpiDate = substr((string) ($sheet['kpi_date'] ?? ''), 0, 10);
        return $kpiDate !== '' && $kpiDate < date('Y-m-d');
    }

    function kpiCountSubmittedOnDate($date, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $date = substr((string) $date, 0, 10);
        $st = $conn->prepare(
            "SELECT COUNT(*) AS c FROM kpi_sheets WHERE kpi_date = ? AND status = 'submitted'"
        );
        $st->bind_param('s', $date);
        $st->execute();
        $c = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $c;
    }

    function kpiListSubmitted($date = '', $deptId = 0, $limit = 200, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $limit = max(1, min(500, (int) $limit));
        $date = substr((string) $date, 0, 10);
        $deptId = (int) $deptId;

        $sql = "SELECT k.*, e.employee_code, e.employee_name, e.designation, d.department_name
                FROM kpi_sheets k
                INNER JOIN employees e ON e.id = k.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE k.status = 'submitted'";
        $types = '';
        $params = [];
        if ($date !== '') {
            $sql .= ' AND k.kpi_date = ?';
            $types .= 's';
            $params[] = $date;
        }
        if ($deptId > 0) {
            $sql .= ' AND e.department_id = ?';
            $types .= 'i';
            $params[] = $deptId;
        }
        $sql .= ' ORDER BY k.kpi_date DESC, e.employee_name ASC LIMIT ' . $limit;

        $rows = [];
        if ($types !== '') {
            $st = $conn->prepare($sql);
            $st->bind_param($types, ...$params);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $st->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }
        }

        if ($close) {
            $conn->close();
        }
        return $rows;
    }

    function kpiListForEmployee($employeeId, $onlyVisibleReports = true, $limit = 60, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureKpiTables($conn);
        $employeeId = (int) $employeeId;
        $limit = max(1, min(200, (int) $limit));
        $today = date('Y-m-d');

        $sql = "SELECT * FROM kpi_sheets WHERE employee_id = ?";
        if ($onlyVisibleReports) {
            $sql .= " AND status = 'submitted' AND kpi_date < ?";
        }
        $sql .= ' ORDER BY kpi_date DESC LIMIT ' . $limit;

        $st = $conn->prepare($sql);
        if ($onlyVisibleReports) {
            $st->bind_param('is', $employeeId, $today);
        } else {
            $st->bind_param('i', $employeeId);
        }
        $st->execute();
        $res = $st->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $rows;
    }

    /**
     * Due open hour slots for employee reminder (shift-wise).
     * Due when current time >= slot end, or within last 5 minutes of the hour.
     *
     * @return array{ok:bool,sheet_id?:int,submitted?:bool,slots?:array<int,array>,error?:string}
     */
    function kpiGetDueReminderPayload($employeeId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $employeeId = (int) $employeeId;
        if ($employeeId <= 0) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Invalid employee'];
        }

        if (!function_exists('getEmployeeById')) {
            require_once __DIR__ . '/employee_helper.php';
        }
        $emp = getEmployeeById($employeeId);
        if (!$emp) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Employee not found'];
        }

        ensureKpiTables($conn);
        $today = date('Y-m-d');
        $shifts = [];
        if (function_exists('getActiveMasterRows')) {
            $shifts = getActiveMasterRows('shifts', 'name ASC');
        } else {
            require_once __DIR__ . '/master_helper.php';
            if (function_exists('ensureMasterTables')) {
                ensureMasterTables($conn);
            }
            if (function_exists('getActiveMasterRows')) {
                $shifts = getActiveMasterRows('shifts', 'name ASC');
            }
        }

        $sheet = kpiEnsureDraftSheet($employeeId, $today, $emp, $shifts, $conn);
        if (!$sheet) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Could not load KPI sheet'];
        }

        if (($sheet['status'] ?? '') === 'submitted') {
            if ($close) {
                $conn->close();
            }
            return [
                'ok' => true,
                'sheet_id' => (int) $sheet['id'],
                'submitted' => true,
                'slots' => [],
            ];
        }

        $entries = kpiGetEntries((int) $sheet['id'], $conn);
        $nowMins = ((int) date('G')) * 60 + (int) date('i');
        $due = [];
        foreach ($entries as $e) {
            if (($e['slot_status'] ?? 'open') === 'submitted') {
                continue;
            }
            $from = (string) ($e['time_from'] ?? '');
            $to = (string) ($e['time_to'] ?? '');
            $start = kpiTimeToMinutes($from);
            $end = kpiTimeToMinutes($to);
            if ($end <= $start) {
                $end += 24 * 60;
            }
            $isDue = ($nowMins >= $end) || ($nowMins >= $start && $nowMins >= ($end - 5));
            if (!$isDue) {
                continue;
            }
            $due[] = [
                'slot_index' => (int) $e['slot_index'],
                'time_from' => $from,
                'time_to' => $to,
                'label' => $from . ' – ' . $to,
                'activity_text' => (string) ($e['activity_text'] ?? ''),
            ];
        }

        usort($due, static function ($a, $b) {
            return $a['slot_index'] <=> $b['slot_index'];
        });

        if ($close) {
            $conn->close();
        }

        return [
            'ok' => true,
            'sheet_id' => (int) $sheet['id'],
            'submitted' => false,
            'kpi_date' => $today,
            'slots' => $due,
            'kpi_url' => function_exists('app_url') ? app_url('employee/kpi.php') : '/employee/kpi.php',
        ];
    }
}
