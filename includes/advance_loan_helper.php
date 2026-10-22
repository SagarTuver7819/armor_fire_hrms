<?php
/**
 * Employee-wise Advance Master and Loan Master.
 * Monthly installment is the salary-register deduction for that month.
 */

function ensureAdvanceLoanTables($conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_recoveries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind ENUM('Advance','Loan') NOT NULL,
            employee_id INT NOT NULL,
            department_id INT NOT NULL DEFAULT 0,
            txn_date DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            installment_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            start_month TINYINT NOT NULL,
            start_year SMALLINT NOT NULL,
            remarks VARCHAR(255) DEFAULT NULL,
            status ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recovery_emp (employee_id),
            INDEX idx_recovery_dept_kind (department_id, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function advanceLoanNormalizeKind($kind)
{
    return strcasecmp((string) $kind, 'Loan') === 0 ? 'Loan' : 'Advance';
}

function advanceLoanMonthLabel($month, $year)
{
    $month = (int) $month;
    $year = (int) $year;
    if ($month < 1 || $month > 12 || $year < 2000) {
        return '';
    }
    return date('M Y', mktime(0, 0, 0, $month, 1, $year));
}

/**
 * @return array{recovered:float,this:float,balance:float}
 */
function advanceLoanSchedule($amount, $installment, $startMonth, $startYear, $month, $year, $status = 'Open')
{
    $amount = round(max(0, (float) $amount), 2);
    $installment = round(max(0, (float) $installment), 2);
    if (strcasecmp((string) $status, 'Closed') === 0) {
        return ['recovered' => $amount, 'this' => 0.0, 'balance' => 0.0];
    }
    $startIdx = ((int) $startYear) * 12 + (int) $startMonth;
    $asOfIdx = ((int) $year) * 12 + (int) $month;
    if ($amount <= 0 || $asOfIdx < $startIdx || $startMonth < 1 || $startMonth > 12) {
        return ['recovered' => 0.0, 'this' => 0.0, 'balance' => $amount];
    }
    $elapsed = $asOfIdx - $startIdx + 1;
    if ($installment <= 0) {
        return [
            'recovered' => $amount,
            'this' => $elapsed === 1 ? $amount : 0.0,
            'balance' => 0.0,
        ];
    }
    $recovered = min($amount, round($installment * $elapsed, 2));
    $before = min($amount, round($installment * ($elapsed - 1), 2));
    return [
        'recovered' => $recovered,
        'this' => round($recovered - $before, 2),
        'balance' => round($amount - $recovered, 2),
    ];
}

/**
 * Advance is cut in full in the salary month of the advance date.
 */
function advanceFullMonthCut(array $row, $month, $year)
{
    if (strcasecmp((string) ($row['status'] ?? 'Open'), 'Closed') === 0) {
        return 0.0;
    }
    $txn = substr((string) ($row['txn_date'] ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn)) {
        return 0.0;
    }
    $ts = strtotime($txn);
    if ($ts === false) {
        return 0.0;
    }
    if ((int) date('n', $ts) === (int) $month && (int) date('Y', $ts) === (int) $year) {
        return round(max(0, (float) ($row['amount'] ?? 0)), 2);
    }
    return 0.0;
}

function advanceLoanEmployees($conn, $departmentId, $includeEmployeeId = 0)
{
    $departmentId = (int) $departmentId;
    $includeEmployeeId = (int) $includeEmployeeId;
    $sql = "SELECT id, employee_code, employee_name, pay_type, status
            FROM employees
            WHERE department_id = ? AND (status = 1 OR id = ?)
            ORDER BY status DESC, employee_name ASC";
    $st = $conn->prepare($sql);
    $st->bind_param('ii', $departmentId, $includeEmployeeId);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function advanceLoanList($conn, $departmentId, $kind)
{
    ensureAdvanceLoanTables($conn);
    $departmentId = (int) $departmentId;
    $kind = advanceLoanNormalizeKind($kind);
    $st = $conn->prepare(
        "SELECT r.*, e.employee_code, e.employee_name
         FROM employee_recoveries r
         INNER JOIN employees e ON e.id = r.employee_id
         WHERE r.department_id = ? AND r.kind = ?
         ORDER BY e.employee_name ASC, r.txn_date DESC, r.id DESC"
    );
    $st->bind_param('is', $departmentId, $kind);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function advanceLoanGet($conn, $id)
{
    ensureAdvanceLoanTables($conn);
    $id = (int) $id;
    $st = $conn->prepare(
        "SELECT r.*, e.employee_code, e.employee_name
         FROM employee_recoveries r
         INNER JOIN employees e ON e.id = r.employee_id
         WHERE r.id = ? LIMIT 1"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function advanceLoanOpenCount($conn, $departmentId, $kind)
{
    ensureAdvanceLoanTables($conn);
    $departmentId = (int) $departmentId;
    $kind = advanceLoanNormalizeKind($kind);
    $st = $conn->prepare(
        "SELECT COUNT(*) AS c FROM employee_recoveries
         WHERE department_id = ? AND kind = ? AND status = 'Open'"
    );
    $st->bind_param('is', $departmentId, $kind);
    $st->execute();
    $c = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $c;
}

/**
 * Salary deduction for one employee in a month.
 * @return array{has_records:bool,advance:float,loan:float}
 */
function advanceLoanMonthDue($conn, $employeeId, $month, $year)
{
    ensureAdvanceLoanTables($conn);
    $employeeId = (int) $employeeId;
    $month = (int) $month;
    $year = (int) $year;
    $out = ['has_records' => false, 'advance' => 0.0, 'loan' => 0.0];
    if ($employeeId <= 0) {
        return $out;
    }
    $st = $conn->prepare(
        'SELECT kind, amount, installment_amount, start_month, start_year, status, txn_date
         FROM employee_recoveries WHERE employee_id = ?'
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $out['has_records'] = true;
        if ($row['kind'] === 'Advance') {
            $out['advance'] += advanceFullMonthCut($row, $month, $year);
            continue;
        }
        $sch = advanceLoanSchedule(
            $row['amount'],
            $row['installment_amount'],
            $row['start_month'],
            $row['start_year'],
            $month,
            $year,
            $row['status']
        );
        $out['loan'] += $sch['this'];
    }
    $st->close();
    $out['advance'] = round($out['advance'], 2);
    $out['loan'] = round($out['loan'], 2);
    return $out;
}

function advanceLoanPushDiary($conn, $employeeId, $month = 0, $year = 0, $applyWhenEmpty = false)
{
    $employeeId = (int) $employeeId;
    $month = $month > 0 ? (int) $month : (int) date('n');
    $year = $year > 0 ? (int) $year : (int) date('Y');
    $due = advanceLoanMonthDue($conn, $employeeId, $month, $year);
    if (!$due['has_records'] && !$applyWhenEmpty) {
        return;
    }
    $chk = $conn->prepare('SELECT id FROM salary_diary WHERE employee_id = ? AND month_no = ? AND year_no = ? LIMIT 1');
    $chk->bind_param('iii', $employeeId, $month, $year);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) {
        return;
    }
    $up = $conn->prepare(
        'UPDATE salary_diary SET loan_amount = ?, advance_amount = ? WHERE employee_id = ? AND month_no = ? AND year_no = ?'
    );
    $up->bind_param('ddiii', $due['loan'], $due['advance'], $employeeId, $month, $year);
    $up->execute();
    $up->close();
}
