<?php
/**
 * Payroll reports — joining/exit, department cost summary, register lock + NEFT
 */

require_once __DIR__ . '/salary_register_helper.php';
require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/auth.php';


function ensurePayrollReportTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensurePayrollTables($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS salary_register_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            month_no TINYINT NOT NULL,
            year_no SMALLINT NOT NULL,
            register_type VARCHAR(40) NOT NULL DEFAULT 'salary',
            department_id INT NOT NULL DEFAULT 0,
            locked_by INT DEFAULT NULL,
            locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            snapshot_json LONGTEXT NOT NULL,
            neft_json LONGTEXT,
            total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
            employee_count INT NOT NULL DEFAULT 0,
            neft_count INT NOT NULL DEFAULT 0,
            remarks VARCHAR(255) DEFAULT NULL,
            UNIQUE KEY uq_reg_lock (month_no, year_no, register_type, department_id),
            INDEX idx_reg_lock_month (year_no, month_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($closeAfter) {
        $conn->close();
    }
}

/**
 * Employees who joined or exited in the given month (with prorated salary days).
 */
function getMonthlyJoiningExitReport($month, $year, $deptId = 0)
{
    $month = (int) $month;
    $year = (int) $year;
    $deptId = (int) $deptId;
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $monthDays = (int) date('t', strtotime($monthStart));

    $conn = getDBConnection();
    ensureEmployeesTable($conn);

    $sql = "SELECT e.*, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE (
                (e.date_of_joining IS NOT NULL AND e.date_of_joining != '' AND e.date_of_joining != '0000-00-00'
                 AND e.date_of_joining BETWEEN ? AND ?)
                OR
                (e.date_of_exit IS NOT NULL AND e.date_of_exit != '' AND e.date_of_exit != '0000-00-00'
                 AND e.date_of_exit BETWEEN ? AND ?)
            )";
    $types = 'ssss';
    $params = [$monthStart, $monthEnd, $monthStart, $monthEnd];
    if ($deptId > 0) {
        $sql .= ' AND e.department_id = ?';
        $types .= 'i';
        $params[] = $deptId;
    }
    $sql .= ' ORDER BY d.department_name ASC, e.employee_name ASC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $emps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    $joiners = [];
    $exiters = [];
    foreach ($emps as $emp) {
        $doj = substr((string) ($emp['date_of_joining'] ?? ''), 0, 10);
        $doe = substr((string) ($emp['date_of_exit'] ?? ''), 0, 10);
        $isJoiner = ($doj !== '' && $doj !== '0000-00-00' && $doj >= $monthStart && $doj <= $monthEnd);
        $isExiter = ($doe !== '' && $doe !== '0000-00-00' && $doe >= $monthStart && $doe <= $monthEnd);

        ensurePayrollDiaryFromAttendance((int) $emp['id'], $month, $year);
        $payType = normalizePayType($emp['pay_type'] ?? 'Salary');
        $base = (float) ($emp['decided_salary'] ?? 0);
        if ($payType !== 'Salary') {
            $base = getJobworkTotal((int) $emp['id'], $month, $year);
        }
        $att = getPayrollAttendanceBundle($emp, $month, $year, $base);
        $row = [
            'employee_id' => (int) $emp['id'],
            'employee_code' => (string) ($emp['employee_code'] ?? ''),
            'employee_name' => (string) ($emp['employee_name'] ?? ''),
            'department' => (string) ($emp['department_name'] ?? ''),
            'department_id' => (int) ($emp['department_id'] ?? 0),
            'designation' => (string) ($emp['designation'] ?? '-'),
            'pay_type' => $payType,
            'doj' => formatDateDisplay($emp['date_of_joining'] ?? ''),
            'doe' => formatDateDisplay($emp['date_of_exit'] ?? ''),
            'eligible_days' => (float) ($att['working'] ?? 0),
            'month_days' => $monthDays,
            'present' => (float) $att['present'],
            'week_off' => (float) $att['week_off'],
            'holiday' => (float) ($att['holiday'] ?? 0),
            'week_off_paid' => (float) ($att['week_off_paid'] ?? 0),
            'holiday_paid' => (float) ($att['holiday_paid'] ?? 0),
            'pl' => (float) $att['pl'],
            'sl' => (float) $att['sl'],
            'dl' => (float) $att['dl'],
            'total_days' => (float) $att['total_days'],
            'salary' => (float) $att['salary'],
            'gross' => (float) $att['govt_gross'],
            'pf' => (float) $att['pf'],
            'pt' => (float) $att['pt'],
            'net' => max(0, round(
                (float) $att['govt_gross'] - (float) $att['pf'] - (float) $att['pt']
                - (float) $att['loan'] - (float) $att['advance'] + (float) $att['arrears'],
                2
            )),
            'emp_from' => $att['emp_from'] ?? null,
            'emp_to' => $att['emp_to'] ?? null,
            'event' => $isJoiner && $isExiter ? 'Both' : ($isJoiner ? 'Joining' : 'Exit'),
        ];

        if ($isJoiner) {
            $joiners[] = $row;
        }
        if ($isExiter) {
            $exiters[] = $row;
        }
    }


    return [
        'month' => $month,
        'year' => $year,
        'month_days' => $monthDays,
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
        'joiners' => $joiners,
        'exiters' => $exiters,
        'join_count' => count($joiners),
        'exit_count' => count($exiters),
    ];
}

/**
 * Department-wise monthly payroll cost + company grand summary.
 * Aggregates all register types (salary + jobwork govt + actual + contractor under).
 */
function getDepartmentMonthlyCostSummary($month, $year, $deptId = 0)
{
    $month = (int) $month;
    $year = (int) $year;
    $deptId = (int) $deptId;

    $byDept = [];
    $types = ['salary', 'jobwork_govt', 'jobwork_actual'];
    foreach ($types as $type) {
        $groups = getSalaryRegisterData($type, $month, $year, $deptId, 0);
        foreach ($groups as $g) {
            foreach ($g['rows'] as $r) {
                $did = (int) ($r['department_id'] ?? 0);
                $dname = (string) ($r['department'] ?? 'Department');
                if ($dname === '') {
                    $dname = 'Department';
                }
                if (!isset($byDept[$did])) {
                    $byDept[$did] = [
                        'department_id' => $did,
                        'department' => $dname,
                        'employees' => 0,
                        'employee_ids' => [],
                        'gross' => 0.0,
                        'pf' => 0.0,
                        'pt' => 0.0,
                        'loan' => 0.0,
                        'advance' => 0.0,
                        'deduction' => 0.0,
                        'arrears' => 0.0,
                        'net' => 0.0,
                        'by_type' => [
                            'salary' => ['gross' => 0.0, 'net' => 0.0, 'count' => 0],
                            'jobwork_govt' => ['gross' => 0.0, 'net' => 0.0, 'count' => 0],
                            'jobwork_actual' => ['gross' => 0.0, 'net' => 0.0, 'count' => 0],
                        ],
                    ];
                }
                $eid = (int) ($r['employee_id'] ?? 0);
                if ($eid > 0 && !isset($byDept[$did]['employee_ids'][$eid])) {
                    $byDept[$did]['employee_ids'][$eid] = true;
                    $byDept[$did]['employees']++;
                }
                $gross = (float) ($r['gross'] ?? 0);
                $net = (float) ($r['net'] ?? 0);
                $pf = (float) ($r['pf'] ?? 0);
                $pt = (float) ($r['pt'] ?? 0);
                $loan = (float) ($r['loan'] ?? 0);
                $adv = (float) ($r['advance'] ?? 0);
                $ded = (float) ($r['total_deduction'] ?? ($pf + $pt + $loan + $adv));
                $arr = (float) ($r['arrears'] ?? 0);

                $byDept[$did]['gross'] += $gross;
                $byDept[$did]['pf'] += $pf;
                $byDept[$did]['pt'] += $pt;
                $byDept[$did]['loan'] += $loan;
                $byDept[$did]['advance'] += $adv;
                $byDept[$did]['deduction'] += $ded;
                $byDept[$did]['arrears'] += $arr;
                $byDept[$did]['net'] += $net;

                if (!isset($byDept[$did]['by_type'][$type])) {
                    $byDept[$did]['by_type'][$type] = ['gross' => 0.0, 'net' => 0.0, 'count' => 0];
                }
                $byDept[$did]['by_type'][$type]['gross'] += $gross;
                $byDept[$did]['by_type'][$type]['net'] += $net;
                $byDept[$did]['by_type'][$type]['count']++;
            }
        }
    }

    // Round + drop helper keys
    $rows = [];
    foreach ($byDept as $row) {
        unset($row['employee_ids']);
        foreach (['gross', 'pf', 'pt', 'loan', 'advance', 'deduction', 'arrears', 'net'] as $k) {
            $row[$k] = round((float) $row[$k], 2);
        }
        foreach ($row['by_type'] as $tk => $tv) {
            $row['by_type'][$tk]['gross'] = round((float) $tv['gross'], 2);
            $row['by_type'][$tk]['net'] = round((float) $tv['net'], 2);
        }
        $rows[] = $row;
    }
    usort($rows, static function ($a, $b) {
        return strcasecmp($a['department'], $b['department']);
    });

    $summary = [
        'departments' => count($rows),
        'employees' => 0,
        'gross' => 0.0,
        'pf' => 0.0,
        'pt' => 0.0,
        'loan' => 0.0,
        'advance' => 0.0,
        'deduction' => 0.0,
        'arrears' => 0.0,
        'net' => 0.0,
    ];
    foreach ($rows as $r) {
        $summary['employees'] += (int) $r['employees'];
        $summary['gross'] += $r['gross'];
        $summary['pf'] += $r['pf'];
        $summary['pt'] += $r['pt'];
        $summary['loan'] += $r['loan'];
        $summary['advance'] += $r['advance'];
        $summary['deduction'] += $r['deduction'];
        $summary['arrears'] += $r['arrears'];
        $summary['net'] += $r['net'];
    }
    foreach (['gross', 'pf', 'pt', 'loan', 'advance', 'deduction', 'arrears', 'net'] as $k) {
        $summary[$k] = round($summary[$k], 2);
    }


    return [
        'month' => $month,
        'year' => $year,
        'rows' => $rows,
        'summary' => $summary,
    ];
}

function getSalaryRegisterLock($month, $year, $type, $deptId = 0)
{
    $conn = getDBConnection();
    ensurePayrollReportTables($conn);
    $month = (int) $month;
    $year = (int) $year;
    $type = (string) $type;
    $deptId = (int) $deptId;
    $stmt = $conn->prepare(
        'SELECT * FROM salary_register_locks
         WHERE month_no = ? AND year_no = ? AND register_type = ? AND department_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('iisi', $month, $year, $type, $deptId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function isSalaryRegisterLocked($month, $year, $type, $deptId = 0)
{
    return getSalaryRegisterLock($month, $year, $type, $deptId) !== null;
}

/**
 * Flatten register groups into employee rows for lock / NEFT.
 */
function flattenSalaryRegisterGroups(array $groups)
{
    $rows = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $r) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Build NEFT rows from register snapshot rows.
 */
function buildNeftRowsFromRegister(array $registerRows)
{
    $neft = [];
    $sr = 0;
    foreach ($registerRows as $r) {
        $net = round((float) ($r['net'] ?? 0), 2);
        if ($net <= 0) {
            continue;
        }
        $account = trim((string) ($r['account'] ?? ''));
        if ($account === '' || $account === '-') {
            continue;
        }
        $sr++;
        $neft[] = [
            'sr' => $sr,
            'employee_id' => (int) ($r['employee_id'] ?? 0),
            'employee_code' => (string) ($r['employee_code'] ?? ''),
            'employee_name' => (string) ($r['employee_name'] ?? ''),
            'department' => (string) ($r['department'] ?? ''),
            'bank_name' => (string) ($r['bank_name'] ?? ''),
            'account' => $account,
            'ifsc' => (string) ($r['ifsc'] ?? ''),
            'amount' => $net,
            'remarks' => 'Salary',
        ];
    }
    return $neft;
}

/**
 * Finalize & lock salary register for month/type/dept → snapshot + NEFT.
 */
function lockSalaryRegister($month, $year, $type, $deptId = 0, $userId = null)
{
    ensurePayrollReportTables();
    $month = (int) $month;
    $year = (int) $year;
    $type = (string) $type;
    $deptId = (int) $deptId;
    if (!isset(salaryRegisterTypes()[$type])) {
        return ['ok' => false, 'error' => 'Invalid register type'];
    }
    if (isSalaryRegisterLocked($month, $year, $type, $deptId)) {
        return ['ok' => false, 'error' => 'Register already locked for this month'];
    }

    $groups = getSalaryRegisterData($type, $month, $year, $deptId, 0);
    $rows = flattenSalaryRegisterGroups($groups);

    // Enrich bank name if missing
    foreach ($rows as &$r) {
        if (empty($r['bank_name']) && !empty($r['employee_id'])) {
            $emp = getEmployeeById((int) $r['employee_id']);
            if ($emp) {
                $r['bank_name'] = (string) ($emp['bank_name'] ?? '');
                if (empty($r['account']) || $r['account'] === '-') {
                    $r['account'] = (string) ($emp['bank_account_number'] ?? '');
                }
                if (empty($r['ifsc']) || $r['ifsc'] === '-') {
                    $r['ifsc'] = (string) ($emp['ifsc_code'] ?? '');
                }
            }
        }
    }
    unset($r);

    $neft = buildNeftRowsFromRegister($rows);
    $totalGross = 0.0;
    $totalNet = 0.0;
    foreach ($rows as $r) {
        $totalGross += (float) ($r['gross'] ?? 0);
        $totalNet += (float) ($r['net'] ?? 0);
    }

    $snapshot = json_encode([
        'groups' => $groups,
        'rows' => $rows,
        'locked_at' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    $neftJson = json_encode($neft, JSON_UNESCAPED_UNICODE);
    $empCount = count($rows);
    $neftCount = count($neft);
    $uid = $userId !== null ? (int) $userId : (int) ($_SESSION['user_id'] ?? 0);
    if ($uid < 0) {
        $uid = 0;
    }

    $conn = getDBConnection();
    ensurePayrollReportTables($conn);
    $stmt = $conn->prepare(
        'INSERT INTO salary_register_locks
            (month_no, year_no, register_type, department_id, locked_by, snapshot_json, neft_json,
             total_gross, total_net, employee_count, neft_count, remarks)
         VALUES (?,?,?,?,NULLIF(?,0),?,?,?,?,?,?,?)'
    );
    $remarks = 'Finalized';
    $stmt->bind_param(
        'iisiissddiis',
        $month,
        $year,
        $type,
        $deptId,
        $uid,
        $snapshot,
        $neftJson,
        $totalGross,
        $totalNet,
        $empCount,
        $neftCount,
        $remarks
    );
    $ok = $stmt->execute();
    $err = $stmt->error;
    $id = (int) $conn->insert_id;
    $stmt->close();
    $conn->close();

    if (!$ok) {
        return ['ok' => false, 'error' => $err ?: 'Lock failed'];
    }
    return [
        'ok' => true,
        'id' => $id,
        'employee_count' => $empCount,
        'neft_count' => $neftCount,
        'total_net' => round($totalNet, 2),
    ];
}

function unlockSalaryRegister($month, $year, $type, $deptId = 0)
{
    if (!function_exists('isAdmin') || !isAdmin()) {
        return ['ok' => false, 'error' => 'Only Admin can unlock'];
    }
    $conn = getDBConnection();
    ensurePayrollReportTables($conn);
    $month = (int) $month;
    $year = (int) $year;
    $type = (string) $type;
    $deptId = (int) $deptId;
    $stmt = $conn->prepare(
        'DELETE FROM salary_register_locks
         WHERE month_no = ? AND year_no = ? AND register_type = ? AND department_id = ?'
    );
    $stmt->bind_param('iisi', $month, $year, $type, $deptId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return ['ok' => (bool) $ok];
}

function getNeftSheetData($month, $year, $type, $deptId = 0)
{
    $lock = getSalaryRegisterLock($month, $year, $type, $deptId);
    if (!$lock) {
        return null;
    }
    $neft = json_decode((string) ($lock['neft_json'] ?? '[]'), true);
    if (!is_array($neft)) {
        $neft = [];
    }
    return [
        'lock' => $lock,
        'rows' => $neft,
        'total_amount' => round((float) ($lock['total_net'] ?? 0), 2),
        // Prefer sum of NEFT amounts (excludes missing bank)
        'neft_total' => round(array_sum(array_map(static function ($r) {
            return (float) ($r['amount'] ?? 0);
        }, $neft)), 2),
    ];
}
