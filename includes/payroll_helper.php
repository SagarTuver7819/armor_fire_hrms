<?php
/**
 * Payroll helper — salary diary, jobwork, slabs mapping, generate
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/master_helper.php';
require_once __DIR__ . '/employee_helper.php';

function ensurePayrollColumn($conn, $table, $column, $definition)
{
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $column = preg_replace('/[^a-z0-9_]/', '', $column);
    if ($table === '' || $column === '' || $definition === '') {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN " . $definition);
    }
}

function ensurePayrollTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    ensureMasterTables($conn);
    ensureEmployeesTable($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS employee_salary_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        line_type ENUM('component','slab') NOT NULL DEFAULT 'component',
        component_id INT DEFAULT NULL,
        slab_id INT DEFAULT NULL,
        label VARCHAR(150) NOT NULL,
        component_type VARCHAR(20) DEFAULT 'Earning',
        calculation VARCHAR(20) DEFAULT 'Fixed',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_esd_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS salary_diary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        working_days DECIMAL(6,2) NOT NULL DEFAULT 26,
        present_days DECIMAL(6,2) NOT NULL DEFAULT 26,
        overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_diary_emp_month (employee_id, month_no, year_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensurePayrollColumn($conn, 'salary_diary', 'week_off_days', 'week_off_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER present_days');
    ensurePayrollColumn($conn, 'salary_diary', 'holiday_days', 'holiday_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER week_off_days');
    ensurePayrollColumn($conn, 'salary_diary', 'pl_days', 'pl_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER holiday_days');
    ensurePayrollColumn($conn, 'salary_diary', 'sl_days', 'sl_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER pl_days');
    ensurePayrollColumn($conn, 'salary_diary', 'dl_days', 'dl_days DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER sl_days');
    ensurePayrollColumn($conn, 'salary_diary', 'loan_amount', 'loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER overtime_hours');
    ensurePayrollColumn($conn, 'salary_diary', 'advance_amount', 'advance_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER loan_amount');
    ensurePayrollColumn($conn, 'salary_diary', 'arrears_amount', 'arrears_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER advance_amount');

    $conn->query("CREATE TABLE IF NOT EXISTS jobwork_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        work_date DATE NOT NULL,
        item_name VARCHAR(150) DEFAULT NULL,
        quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
        rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_jw_emp_date (employee_id, work_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS salary_payslips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        pay_type VARCHAR(20) NOT NULL DEFAULT 'Salary',
        generated_from VARCHAR(30) NOT NULL DEFAULT 'attendance',
        present_days DECIMAL(6,2) DEFAULT NULL,
        working_days DECIMAL(6,2) DEFAULT NULL,
        jobwork_total DECIMAL(12,2) DEFAULT NULL,
        slab_id INT DEFAULT NULL,
        earnings DECIMAL(12,2) NOT NULL DEFAULT 0,
        deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
        net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        breakup_json TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_payslip_emp_month (employee_id, month_no, year_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensurePayrollColumn($conn, 'salary_payslips', 'actual_amount', 'actual_amount DECIMAL(12,2) DEFAULT NULL AFTER jobwork_total');
    ensurePayrollColumn($conn, 'salary_payslips', 'govt_gross', 'govt_gross DECIMAL(12,2) DEFAULT NULL AFTER actual_amount');

    if ($closeAfter) {
        $conn->close();
    }
}

function getEmployeeSalaryDetails($employeeId)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $employeeId = (int) $employeeId;
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM employee_salary_details WHERE employee_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function mapJobworkToSlab($amount)
{
    $slabs = getActiveMasterRows('salary_slabs', 'min_amount ASC, id ASC');
    $amount = (float) $amount;
    if (!$slabs) {
        return null;
    }
    foreach ($slabs as $slab) {
        $min = (float) $slab['min_amount'];
        $max = (float) $slab['max_amount'];
        if ($amount >= $min && $amount <= $max) {
            return $slab;
        }
    }
    $last = $slabs[count($slabs) - 1];
    if ($amount > (float) $last['max_amount']) {
        return $last;
    }
    return $slabs[0];
}

function getDiaryRow($employeeId, $month, $year)
{
    $cache = diaryMonthCache($month, $year);
    return $cache[(int) $employeeId] ?? null;
}

function diaryMonthCache($month, $year)
{
    static $cache = [];
    $month = (int) $month;
    $year = (int) $year;
    $key = $month . '-' . $year;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM salary_diary WHERE month_no = ? AND year_no = ?");
    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[(int) $row['employee_id']] = $row;
    }
    $stmt->close();
    $conn->close();
    $cache[$key] = $rows;
    return $rows;
}

function jobworkMonthCache($month, $year)
{
    static $cache = [];
    $month = (int) $month;
    $year = (int) $year;
    $key = $month . '-' . $year;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $amount = [];
    $qty = [];
    $days = [];
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));

    $conn = getDBConnection();
    if (is_file(__DIR__ . '/contractor_helper.php')) {
        require_once __DIR__ . '/contractor_helper.php';
        ensureContractorTables($conn);
        $res = $conn->query(
            "SELECT employee_id, SUM(total_amount) AS a, SUM(total_qty) AS q
             FROM contractor_operation_sheets
             WHERE month_no = {$month} AND year_no = {$year} AND status = 1
             GROUP BY employee_id"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) $row['employee_id'];
                $amount[$id] = (float) $row['a'];
                $qty[$id] = (float) $row['q'];
            }
        }
        $res2 = $conn->query(
            "SELECT i.days_json, s.employee_id
             FROM contractor_operation_items i
             INNER JOIN contractor_operation_sheets s ON s.id = i.sheet_id
             WHERE s.month_no = {$month} AND s.year_no = {$year} AND s.status = 1"
        );
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $id = (int) $row['employee_id'];
                if (!isset($days[$id])) {
                    $days[$id] = [];
                }
                $decoded = json_decode((string) $row['days_json'], true);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $dayNo => $cell) {
                    $v = (float) ($cell['q'] ?? 0) + (float) ($cell['r'] ?? 0) + (float) ($cell['ot'] ?? 0);
                    if ($v > 0) {
                        $days[$id][(int) $dayNo] = true;
                    }
                }
            }
        }
    }

    $fromEsc = $conn->real_escape_string($from);
    $toEsc = $conn->real_escape_string($to);
    $res3 = $conn->query(
        "SELECT employee_id, SUM(amount) AS a, SUM(quantity) AS q
         FROM jobwork_entries
         WHERE work_date BETWEEN '{$fromEsc}' AND '{$toEsc}'
         GROUP BY employee_id"
    );
    if ($res3) {
        while ($row = $res3->fetch_assoc()) {
            $id = (int) $row['employee_id'];
            $amount[$id] = ($amount[$id] ?? 0) + (float) $row['a'];
            $qty[$id] = ($qty[$id] ?? 0) + (float) $row['q'];
        }
    }
    $res4 = $conn->query(
        "SELECT employee_id, DAY(work_date) AS d FROM jobwork_entries
         WHERE work_date BETWEEN '{$fromEsc}' AND '{$toEsc}' AND quantity > 0"
    );
    if ($res4) {
        while ($row = $res4->fetch_assoc()) {
            $id = (int) $row['employee_id'];
            if (!isset($days[$id])) {
                $days[$id] = [];
            }
            $days[$id][(int) $row['d']] = true;
        }
    }
    $conn->close();

    $cache[$key] = ['amount' => $amount, 'qty' => $qty, 'days' => $days];
    return $cache[$key];
}

function getJobworkEntryTotal($employeeId, $month, $year)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM jobwork_entries
         WHERE employee_id = ? AND work_date BETWEEN ? AND ?"
    );
    $stmt->bind_param('iss', $employeeId, $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return (float) ($row['total'] ?? 0);
}

/**
 * Department Jobwork Entry list = manual jobwork_entries + Operations Rate List daily rows.
 *
 * @return array<int, array<string, mixed>>
 */
function getDepartmentJobworkList($departmentId, $month, $year)
{
    $departmentId = (int) $departmentId;
    $month = (int) $month;
    $year = (int) $year;
    if ($departmentId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
        return [];
    }

    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $rows = [];

    $conn = getDBConnection();
    ensurePayrollTables($conn);

    $lq = $conn->prepare(
        "SELECT j.id, j.work_date, j.item_name, j.quantity, j.rate, j.amount, j.remarks,
                e.employee_code, e.employee_name
         FROM jobwork_entries j
         INNER JOIN employees e ON e.id = j.employee_id
         WHERE e.department_id = ? AND j.work_date BETWEEN ? AND ?
         ORDER BY j.work_date DESC, j.id DESC"
    );
    $lq->bind_param('iss', $departmentId, $from, $to);
    $lq->execute();
    $manual = $lq->get_result()->fetch_all(MYSQLI_ASSOC);
    $lq->close();

    foreach ($manual as $row) {
        $rows[] = [
            'source' => 'manual',
            'id' => (int) $row['id'],
            'work_date' => $row['work_date'],
            'employee_code' => $row['employee_code'],
            'employee_name' => $row['employee_name'],
            'item_name' => $row['item_name'] ?: '-',
            'quantity' => (float) $row['quantity'],
            'rate' => (float) $row['rate'],
            'amount' => (float) $row['amount'],
            'sheet_id' => 0,
            'sort_ts' => strtotime((string) $row['work_date']) . '-' . str_pad((string) $row['id'], 8, '0', STR_PAD_LEFT),
        ];
    }

    if (is_file(__DIR__ . '/contractor_helper.php')) {
        require_once __DIR__ . '/contractor_helper.php';
        ensureContractorTables($conn);

        $oq = $conn->prepare(
            "SELECT s.id AS sheet_id, s.employee_id, s.operation, s.month_no, s.year_no,
                    i.product_id, i.rate, i.ot_rate, i.rejection_rate, i.days_json,
                    e.employee_code, e.employee_name,
                    COALESCE(NULLIF(TRIM(p.process), ''), NULLIF(TRIM(p.product_name), ''), CONCAT('Product #', i.product_id)) AS product_label
             FROM contractor_operation_sheets s
             INNER JOIN employees e ON e.id = s.employee_id
             INNER JOIN contractor_operation_items i ON i.sheet_id = s.id
             LEFT JOIN contractor_products p ON p.id = i.product_id
             LEFT JOIN contractor_employment em ON em.employee_id = e.id AND em.status = 1
             WHERE s.month_no = ? AND s.year_no = ? AND s.status = 1
               AND (e.department_id = ? OR em.department_id = ?)
             ORDER BY e.employee_code ASC, i.sort_order ASC, i.id ASC"
        );
        $oq->bind_param('iiii', $month, $year, $departmentId, $departmentId);
        $oq->execute();
        $orl = $oq->get_result()->fetch_all(MYSQLI_ASSOC);
        $oq->close();

        foreach ($orl as $item) {
            $days = json_decode((string) ($item['days_json'] ?? '{}'), true);
            if (!is_array($days)) {
                $days = [];
            }
            $operation = (string) ($item['operation'] ?? '');
            $rate = (float) ($item['rate'] ?? 0);
            $otRate = (float) ($item['ot_rate'] ?? 0);
            $rejRate = (float) ($item['rejection_rate'] ?? 0);
            $label = trim((string) ($item['product_label'] ?? ''));
            if ($label === '') {
                $label = 'Product #' . (int) ($item['product_id'] ?? 0);
            }
            if ($operation !== '') {
                $label .= ' · ' . $operation;
            }

            foreach ($days as $dayNo => $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                $q = (float) ($cell['q'] ?? 0);
                $rQty = (float) ($cell['r'] ?? 0);
                $ot = (float) ($cell['ot'] ?? 0);
                if (($q + $rQty + $ot) <= 0) {
                    continue;
                }
                $d = (int) $dayNo;
                if ($d < 1 || $d > 31) {
                    continue;
                }
                $workDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if (!checkdate($month, $d, $year)) {
                    continue;
                }
                $calc = calculateContractorRow($operation, $rate, $otRate, $rejRate, [(string) $d => $cell]);
                $rows[] = [
                    'source' => 'operations',
                    'id' => 0,
                    'work_date' => $workDate,
                    'employee_code' => $item['employee_code'],
                    'employee_name' => $item['employee_name'],
                    'item_name' => $label,
                    'quantity' => (float) ($calc['total_qty'] ?? ($q + $ot)),
                    'rate' => $rate,
                    'amount' => (float) ($calc['total_amount'] ?? 0),
                    'sheet_id' => (int) ($item['sheet_id'] ?? 0),
                    'sort_ts' => strtotime($workDate) . '-o' . str_pad((string) ($item['sheet_id'] ?? 0), 6, '0', STR_PAD_LEFT) . str_pad((string) $d, 2, '0', STR_PAD_LEFT),
                ];
            }
        }
    }

    $conn->close();

    usort($rows, static function ($a, $b) {
        return strcmp((string) ($b['sort_ts'] ?? ''), (string) ($a['sort_ts'] ?? ''));
    });

    return $rows;
}

function getJobworkTotal($employeeId, $month, $year)
{
    $cache = jobworkMonthCache($month, $year);
    return round((float) ($cache['amount'][(int) $employeeId] ?? 0), 2);
}

function getJobworkQtyTotal($employeeId, $month, $year)
{
    $cache = jobworkMonthCache($month, $year);
    return round((float) ($cache['qty'][(int) $employeeId] ?? 0), 2);
}

function countWeekOffDaysInMonth($month, $year, $weekOffDay, $fromDate = null, $toDate = null)
{
    $map = [
        'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
        'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6,
    ];
    $want = $map[(string) $weekOffDay] ?? 0;
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $from = $monthStart;
    $to = $monthEnd;
    if ($fromDate && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $fromDate)) {
        $from = substr((string) $fromDate, 0, 10);
    }
    if ($toDate && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $toDate)) {
        $to = substr((string) $toDate, 0, 10);
    }
    if ($from < $monthStart) {
        $from = $monthStart;
    }
    if ($to > $monthEnd) {
        $to = $monthEnd;
    }
    if ($from > $to) {
        return 0;
    }
    $n = 0;
    $ts = strtotime($from);
    $endTs = strtotime($to);
    while ($ts !== false && $ts <= $endTs) {
        if ((int) date('w', $ts) === $want) {
            $n++;
        }
        $ts = strtotime('+1 day', $ts);
    }
    return $n;
}

/**
 * Employment-active date range inside a month (joining / exit aware).
 * Returns null when employee is not active in that month.
 */
function payrollEmploymentRangeInMonth($month, $year, $joiningDate = null, $exitDate = null)
{
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $from = $monthStart;
    $to = $monthEnd;

    $join = '';
    if ($joiningDate && $joiningDate !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $joiningDate)) {
        $join = substr((string) $joiningDate, 0, 10);
    }
    $exit = '';
    if ($exitDate && $exitDate !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $exitDate)) {
        $exit = substr((string) $exitDate, 0, 10);
    }

    if ($join !== '' && $join > $monthEnd) {
        return null;
    }
    if ($exit !== '' && $exit < $monthStart) {
        return null;
    }
    if ($join !== '' && $join > $from) {
        $from = $join;
    }
    if ($exit !== '' && $exit < $to) {
        $to = $exit;
    }
    if ($from > $to) {
        return null;
    }

    $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
    return [
        'from' => $from,
        'to' => $to,
        'days' => max(0, $days),
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ];
}

function getJobworkWorkedDays($employeeId, $month, $year)
{
    $cache = jobworkMonthCache($month, $year);
    $set = $cache['days'][(int) $employeeId] ?? [];
    return count($set);
}

function getContractorUnderEmployees($mainContractorId)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $mainContractorId = (int) $mainContractorId;
    $rows = [];
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.status = 1 AND e.pay_type = 'Jobwork' AND e.main_contractor_id = ?
         ORDER BY e.employee_code ASC"
    );
    $stmt->bind_param('i', $mainContractorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function statutoryPf($gross, array $emp)
{
    if (($emp['pf_deduction'] ?? 'No') !== 'Yes') {
        return 0.0;
    }
    // PF wage ceiling ₹15,000 × 12% = ₹1,800 max
    $pfWage = min(15000.0, (float) $gross);
    return round($pfWage * 0.12, 2);
}

function statutoryPt($gross)
{
    // Professional Tax: ₹200 when amount is ₹12,001 or more; otherwise 0
    return ((float) $gross >= 12001) ? 200.0 : 0.0;
}

/**
 * Count Holiday Master dates in a month (optionally only Paid=Yes)
 */
function payrollCountHolidaysInMonth($year, $month, $paidOnly = false, $fromDate = null, $toDate = null)
{
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $from = $monthStart;
    $to = $monthEnd;
    if ($fromDate && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $fromDate)) {
        $from = substr((string) $fromDate, 0, 10);
    }
    if ($toDate && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $toDate)) {
        $to = substr((string) $toDate, 0, 10);
    }
    if ($from < $monthStart) {
        $from = $monthStart;
    }
    if ($to > $monthEnd) {
        $to = $monthEnd;
    }
    if ($from > $to) {
        return 0.0;
    }

    if (function_exists('countHolidaysInMonth') && $from === $monthStart && $to === $monthEnd) {
        return (float) countHolidaysInMonth($year, $month, $paidOnly);
    }

    $conn = getDBConnection();
    $n = 0;
    $res = @$conn->query(
        "SELECT holiday_date, is_paid FROM holidays
         WHERE status = 1 AND holiday_type = 'Holiday'
           AND holiday_date BETWEEN '{$from}' AND '{$to}'"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            if ($paidOnly && (($r['is_paid'] ?? 'Yes') === 'No')) {
                continue;
            }
            if (!empty($r['holiday_date'])) {
                $n++;
            }
        }
    }
    $conn->close();
    return (float) $n;
}

function getPayrollAttendanceBundle(array $emp, $month, $year, $actualAmount)
{
    $employeeId = (int) ($emp['id'] ?? 0);
    $monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $diary = getDiaryRow($employeeId, $month, $year);
    $payType = function_exists('normalizePayType')
        ? normalizePayType($emp['pay_type'] ?? 'Salary')
        : 'Salary';

    $range = payrollEmploymentRangeInMonth(
        $month,
        $year,
        $emp['date_of_joining'] ?? null,
        $emp['date_of_exit'] ?? null
    );

    if ($range === null) {
        return [
            'month_days' => $monthDays,
            'present' => 0.0,
            'week_off' => 0.0,
            'holiday' => 0.0,
            'week_off_paid' => 0.0,
            'holiday_paid' => 0.0,
            'pl' => 0.0,
            'sl' => 0.0,
            'dl' => 0.0,
            'total_days' => 0.0,
            'loan' => $diary ? (float) ($diary['loan_amount'] ?? 0) : 0.0,
            'advance' => $diary ? (float) ($diary['advance_amount'] ?? 0) : 0.0,
            'arrears' => $diary ? (float) ($diary['arrears_amount'] ?? 0) : 0.0,
            'salary' => round((float) ($emp['decided_salary'] ?? 0), 2),
            'govt_gross' => 0.0,
            'pf' => 0.0,
            'pt' => 0.0,
            'working' => 0.0,
            'emp_from' => null,
            'emp_to' => null,
        ];
    }

    $empFrom = $range['from'];
    $empTo = $range['to'];
    $eligibleDays = (float) $range['days'];

    // Week offs / holidays only from joining date through exit date inside this month
    $autoWeekOff = (float) countWeekOffDaysInMonth(
        $month,
        $year,
        $emp['week_off_day'] ?? 'Sunday',
        $empFrom,
        $empTo
    );
    $autoHoliday = (float) payrollCountHolidaysInMonth($year, $month, false, $empFrom, $empTo);
    $autoPaidHoliday = (float) payrollCountHolidaysInMonth($year, $month, true, $empFrom, $empTo);

    $workedJw = (float) getJobworkWorkedDays($employeeId, $month, $year);
    $fullPresent = max(0, $eligibleDays - $autoWeekOff - $autoHoliday);

    if ($payType === 'Salary') {
        $autoPresent = $fullPresent;
    } else {
        $autoPresent = $workedJw > 0 ? min((float) $workedJw, $fullPresent) : $fullPresent;
    }

    $hasDiary = is_array($diary) && !empty($diary);
    $weekOffRaw = $autoWeekOff;
    $holidayRaw = $autoHoliday;
    if ($hasDiary) {
        $diaryHoliday = (float) ($diary['holiday_days'] ?? 0);
        if ($diaryHoliday > 0) {
            $holidayRaw = min($diaryHoliday, $autoHoliday > 0 ? $autoHoliday : $diaryHoliday);
        }
    }

    $present = $hasDiary ? (float) ($diary['present_days'] ?? 0) : $autoPresent;
    if ($present > $fullPresent) {
        $present = $fullPresent;
    }
    $pl = $hasDiary ? (float) ($diary['pl_days'] ?? 0) : 0;
    $sl = $hasDiary ? (float) ($diary['sl_days'] ?? 0) : 0;
    $dl = $hasDiary ? (float) ($diary['dl_days'] ?? 0) : 0;

    if ($present >= $eligibleDays && ($weekOffRaw + $holidayRaw) > 0) {
        $present = max(0, $eligibleDays - $weekOffRaw - $holidayRaw - $pl - $sl - $dl);
    }

    $weekOffPaid = (($emp['week_off_benefits'] ?? 'No') === 'Yes') ? $weekOffRaw : 0.0;
    $holidayPaid = 0.0;
    if (($emp['holiday_benefits'] ?? 'No') === 'Yes') {
        if ($hasDiary && $holidayRaw > 0 && $autoHoliday > 0) {
            $holidayPaid = round($holidayRaw * ($autoPaidHoliday / max(1.0, $autoHoliday)), 2);
            $holidayPaid = min($holidayPaid, $autoPaidHoliday);
        } else {
            $holidayPaid = $autoPaidHoliday;
        }
    }

    $loan = $diary ? (float) ($diary['loan_amount'] ?? 0) : 0;
    $advance = $diary ? (float) ($diary['advance_amount'] ?? 0) : 0;
    $arrears = $diary ? (float) ($diary['arrears_amount'] ?? 0) : 0;

    $totalDays = $present + $weekOffPaid + $holidayPaid + $pl + $sl + $dl;
    if ($totalDays > $eligibleDays) {
        $totalDays = $eligibleDays;
    }
    if ($totalDays > $monthDays) {
        $totalDays = (float) $monthDays;
    }
    if ($totalDays < 0) {
        $totalDays = 0;
    }

    $salary = (float) ($emp['decided_salary'] ?? 0);
    if ($payType !== 'Salary') {
        $salary = (float) $actualAmount;
    }
    $factor = $monthDays > 0 ? ($totalDays / $monthDays) : 1;
    $govtGross = round($salary * $factor, 2);

    $pf = statutoryPf($govtGross, $emp);
    $pt = statutoryPt($govtGross);

    return [
        'month_days' => $monthDays,
        'present' => $present,
        'week_off' => $weekOffRaw,
        'holiday' => $holidayRaw,
        'week_off_paid' => $weekOffPaid,
        'holiday_paid' => $holidayPaid,
        'pl' => $pl,
        'sl' => $sl,
        'dl' => $dl,
        'total_days' => $totalDays,
        'loan' => $loan,
        'advance' => $advance,
        'arrears' => $arrears,
        'salary' => round($salary, 2),
        'govt_gross' => $govtGross,
        'pf' => $pf,
        'pt' => $pt,
        'working' => $eligibleDays,
        'emp_from' => $empFrom,
        'emp_to' => $empTo,
    ];
}

function calculateEmployeeSalary(array $emp, $month, $year)
{
    $payType = function_exists('normalizePayType')
        ? normalizePayType($emp['pay_type'] ?? 'Salary')
        : ((($emp['pay_type'] ?? 'Salary') === 'Jobwork') ? 'Jobwork' : 'Salary');
    $employeeId = (int) $emp['id'];
    $lines = getEmployeeSalaryDetails($employeeId);
    $breakup = [];
    $earnings = 0.0;
    $deductions = 0.0;
    $source = 'attendance';
    $present = null;
    $working = null;
    $jwTotal = null;
    $slabId = null;
    $actualAmount = 0.0;
    $govtAmount = 0.0;

    if ($payType === 'Jobwork') {
        $source = 'jobwork';
        $jwTotal = getJobworkTotal($employeeId, $month, $year);
        $monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $att = getPayrollAttendanceBundle($emp, $month, $year, $jwTotal);
        $present = $att['present'];
        $working = $monthDays;
        $govtGross = $att['govt_gross'];
        $earnings = $govtGross;
        $breakup[] = ['label' => 'Actual jobwork (qty × rate)', 'type' => 'Info', 'amount' => $jwTotal];
        $breakup[] = [
            'label' => 'Govt gross (actual ÷ ' . $monthDays . ' days × ' . $att['total_days'] . ' paid days)',
            'type' => 'Earning',
            'amount' => $govtGross,
        ];
        $deductions += $att['pf'] + $att['pt'] + $att['loan'] + $att['advance'];
        if ($att['pf'] > 0) {
            $breakup[] = ['label' => 'P.F.', 'type' => 'Deduction', 'amount' => $att['pf']];
        }
        if ($att['pt'] > 0) {
            $breakup[] = ['label' => 'P.T.', 'type' => 'Deduction', 'amount' => $att['pt']];
        }
        if ($att['loan'] > 0) {
            $breakup[] = ['label' => 'Loan', 'type' => 'Deduction', 'amount' => $att['loan']];
        }
        if ($att['advance'] > 0) {
            $breakup[] = ['label' => 'Advance', 'type' => 'Deduction', 'amount' => $att['advance']];
        }
        if ($att['arrears'] > 0) {
            $earnings += $att['arrears'];
            $breakup[] = ['label' => 'Salary Arrears', 'type' => 'Earning', 'amount' => $att['arrears']];
        }
        $actualAmount = $jwTotal;
        $govtAmount = $govtGross;
    } elseif ($payType === 'ContractorMain') {
        $source = 'contractor_team';
        $under = getContractorUnderEmployees($employeeId);
        $jwTotal = 0.0;
        foreach ($under as $u) {
            $jwTotal += getJobworkTotal((int) $u['id'], $month, $year);
        }
        $monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $att = getPayrollAttendanceBundle($emp, $month, $year, $jwTotal);
        $present = $att['present'];
        $working = $monthDays;
        $earnings = $att['govt_gross'];
        $breakup[] = ['label' => 'Under employees actual', 'type' => 'Info', 'amount' => $jwTotal];
        $breakup[] = ['label' => 'Govt gross (team)', 'type' => 'Earning', 'amount' => $att['govt_gross']];
        $deductions += $att['pf'] + $att['pt'] + $att['loan'] + $att['advance'];
        if ($att['pf'] > 0) {
            $breakup[] = ['label' => 'P.F.', 'type' => 'Deduction', 'amount' => $att['pf']];
        }
        if ($att['pt'] > 0) {
            $breakup[] = ['label' => 'P.T.', 'type' => 'Deduction', 'amount' => $att['pt']];
        }
        if ($att['loan'] > 0) {
            $breakup[] = ['label' => 'Loan', 'type' => 'Deduction', 'amount' => $att['loan']];
        }
        if ($att['advance'] > 0) {
            $breakup[] = ['label' => 'Advance', 'type' => 'Deduction', 'amount' => $att['advance']];
        }
        if ($att['arrears'] > 0) {
            $earnings += $att['arrears'];
            $breakup[] = ['label' => 'Salary Arrears', 'type' => 'Earning', 'amount' => $att['arrears']];
        }
        $actualAmount = $jwTotal;
        $govtAmount = $att['govt_gross'];
    } else {
        // Normal salary — same formula as Salary Register
        $baseAmount = (float) ($emp['decided_salary'] ?? 0);
        $componentLines = array_values(array_filter($lines, function ($l) {
            return ($l['line_type'] ?? 'component') === 'component';
        }));
        if ($baseAmount <= 0) {
            foreach ($componentLines as $line) {
                if (($line['component_type'] ?? 'Earning') !== 'Deduction') {
                    $baseAmount += (float) $line['amount'];
                }
            }
        }
        if ($baseAmount <= 0) {
            $masters = getActiveMasterRows('salary_components', 'id ASC');
            $basic = (float) ($emp['decided_salary'] ?? 0);
            foreach ($masters as $c) {
                $amt = (float) $c['default_value'];
                if (($c['calculation'] ?? '') === 'Percentage' && $basic > 0) {
                    $amt = round($basic * $amt / 100, 2);
                }
                $componentLines[] = [
                    'label' => $c['component_name'],
                    'component_type' => $c['component_type'],
                    'amount' => $amt,
                ];
                if (($c['component_type'] ?? 'Earning') !== 'Deduction') {
                    $baseAmount += $amt;
                }
            }
            if ($baseAmount <= 0 && $basic > 0) {
                $baseAmount = $basic;
                $componentLines[] = [
                    'label' => 'Decided Salary',
                    'component_type' => 'Earning',
                    'amount' => $basic,
                ];
            }
        }

        $att = getPayrollAttendanceBundle($emp, $month, $year, $baseAmount);
        $present = $att['present'];
        $working = (float) $att['month_days'];
        $factor = $working > 0 ? ($att['total_days'] / $working) : 1;
        if ($factor < 0) {
            $factor = 0;
        }
        if ($factor > 1) {
            $factor = 1;
        }

        $earningComponents = [];
        $deductionComponents = [];
        foreach ($componentLines as $line) {
            if (($line['component_type'] ?? 'Earning') === 'Deduction') {
                $deductionComponents[] = $line;
            } else {
                $earningComponents[] = $line;
            }
        }

        if ($earningComponents) {
            $earnBase = 0.0;
            foreach ($earningComponents as $line) {
                $earnBase += (float) $line['amount'];
            }
            if ($earnBase <= 0) {
                $earnBase = $baseAmount > 0 ? $baseAmount : 1;
            }
            // Scale component breakup to attendance-based gross (matches register)
            $targetGross = (float) $att['govt_gross'];
            foreach ($earningComponents as $line) {
                $share = ((float) $line['amount'] / $earnBase) * $targetGross;
                $amt = round($share, 2);
                $breakup[] = ['label' => $line['label'], 'type' => 'Earning', 'amount' => $amt];
                $earnings += $amt;
            }
            // Fix rounding drift
            $diff = round($targetGross - $earnings, 2);
            if (abs($diff) >= 0.01 && $breakup) {
                $last = count($breakup) - 1;
                $breakup[$last]['amount'] = round($breakup[$last]['amount'] + $diff, 2);
                $earnings = $targetGross;
            }
        } else {
            $earnings = (float) $att['govt_gross'];
            $breakup[] = [
                'label' => 'Gross (Salary × ' . $att['total_days'] . '/' . $att['month_days'] . ' days)',
                'type' => 'Earning',
                'amount' => $earnings,
            ];
        }

        foreach ($deductionComponents as $line) {
            $amt = round((float) $line['amount'] * $factor, 2);
            $breakup[] = ['label' => $line['label'], 'type' => 'Deduction', 'amount' => $amt];
            $deductions += $amt;
        }

        // Statutory + diary deductions (same as Salary Register)
        if ($att['pf'] > 0) {
            $deductions += $att['pf'];
            $breakup[] = ['label' => 'P.F.', 'type' => 'Deduction', 'amount' => $att['pf']];
        }
        if ($att['pt'] > 0) {
            $deductions += $att['pt'];
            $breakup[] = ['label' => 'P.T.', 'type' => 'Deduction', 'amount' => $att['pt']];
        }
        if ($att['loan'] > 0) {
            $deductions += $att['loan'];
            $breakup[] = ['label' => 'Loan', 'type' => 'Deduction', 'amount' => $att['loan']];
        }
        if ($att['advance'] > 0) {
            $deductions += $att['advance'];
            $breakup[] = ['label' => 'Advance', 'type' => 'Deduction', 'amount' => $att['advance']];
        }
        if ($att['arrears'] > 0) {
            $earnings += $att['arrears'];
            $breakup[] = ['label' => 'Salary Arrears', 'type' => 'Earning', 'amount' => $att['arrears']];
        }

        $actualAmount = $earnings;
        $govtAmount = (float) $att['govt_gross'];
    }

    $net = $earnings - $deductions;
    if ($net < 0) {
        $net = 0;
    }

    return [
        'pay_type'        => $payType,
        'generated_from'  => $source,
        'present_days'    => $present,
        'working_days'    => $working,
        'jobwork_total'   => $jwTotal,
        'actual_amount'   => round($actualAmount, 2),
        'govt_gross'      => round($govtAmount, 2),
        'slab_id'          => $slabId,
        'earnings'        => round($earnings, 2),
        'deductions'      => round($deductions, 2),
        'net_salary'      => round($net, 2),
        'breakup'         => $breakup,
    ];
}

function savePayslip($employeeId, $month, $year, array $calc)
{
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $json = json_encode($calc['breakup']);
    $from = $calc['generated_from'];
    $payType = $calc['pay_type'];
    $present = $calc['present_days'] !== null ? (float) $calc['present_days'] : 0;
    $working = $calc['working_days'] !== null ? (float) $calc['working_days'] : 0;
    $jw = $calc['jobwork_total'] !== null ? (float) $calc['jobwork_total'] : 0;
    $actual = isset($calc['actual_amount']) ? (float) $calc['actual_amount'] : $jw;
    $govt = isset($calc['govt_gross']) ? (float) $calc['govt_gross'] : (float) $calc['earnings'];
    $slabId = $calc['slab_id'] !== null ? (int) $calc['slab_id'] : 0;
    $earn = $calc['earnings'];
    $ded = $calc['deductions'];
    $net = $calc['net_salary'];

    $stmt = $conn->prepare(
        "INSERT INTO salary_payslips
         (employee_id, month_no, year_no, pay_type, generated_from, present_days, working_days,
          jobwork_total, actual_amount, govt_gross, slab_id, earnings, deductions, net_salary, breakup_json)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            pay_type=VALUES(pay_type), generated_from=VALUES(generated_from),
            present_days=VALUES(present_days), working_days=VALUES(working_days),
            jobwork_total=VALUES(jobwork_total), actual_amount=VALUES(actual_amount),
            govt_gross=VALUES(govt_gross), slab_id=VALUES(slab_id),
            earnings=VALUES(earnings), deductions=VALUES(deductions),
            net_salary=VALUES(net_salary), breakup_json=VALUES(breakup_json)"
    );
    $stmt->bind_param(
        'iiissddddidddds',
        $employeeId,
        $month,
        $year,
        $payType,
        $from,
        $present,
        $working,
        $jw,
        $actual,
        $govt,
        $slabId,
        $earn,
        $ded,
        $net,
        $json
    );
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok;
}

function moneyInr($n)
{
    return '₹ ' . number_format((float) $n, 2);
}
