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

/**
 * Resolve lock for a register view.
 * All-departments lock (department_id = 0) covers every department —
 * so Arrears / edits stay consistent across ADMINISTRATION, CNC, Foundry, etc.
 *
 * @return array{lock: ?array, scope_dept_id: int, from_all_departments: bool}
 */
function resolveSalaryRegisterLock($month, $year, $type, $deptId = 0)
{
    $deptId = (int) $deptId;
    $exact = getSalaryRegisterLock($month, $year, $type, $deptId);
    if ($exact) {
        return [
            'lock' => $exact,
            'scope_dept_id' => (int) ($exact['department_id'] ?? $deptId),
            'from_all_departments' => ((int) ($exact['department_id'] ?? 0) === 0),
        ];
    }
    if ($deptId > 0) {
        $all = getSalaryRegisterLock($month, $year, $type, 0);
        if ($all) {
            return [
                'lock' => $all,
                'scope_dept_id' => 0,
                'from_all_departments' => true,
            ];
        }
    }
    return [
        'lock' => null,
        'scope_dept_id' => $deptId,
        'from_all_departments' => false,
    ];
}

/**
 * Keep only one department's rows from a locked snapshot (All-departments lock).
 */
function filterSalaryRegisterGroupsByDepartment(array $groups, $deptId)
{
    $deptId = (int) $deptId;
    if ($deptId <= 0) {
        return $groups;
    }
    $out = [];
    foreach ($groups as $g) {
        $rows = [];
        foreach ($g['rows'] ?? [] as $r) {
            if ((int) ($r['department_id'] ?? 0) === $deptId) {
                $rows[] = $r;
            }
        }
        if ($rows) {
            $g['rows'] = $rows;
            $out[] = $g;
        }
    }
    return $out;
}

function isSalaryRegisterLocked($month, $year, $type, $deptId = 0)
{
    $resolved = resolveSalaryRegisterLock($month, $year, $type, $deptId);
    return $resolved['lock'] !== null;
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
            'department_id' => (int) ($r['department_id'] ?? 0),
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
    require_once __DIR__ . '/payroll_approval_helper.php';
    payrollApprovalOnLocked($id);
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
    $deptId = (int) $deptId;
    $resolved = resolveSalaryRegisterLock($month, $year, $type, $deptId);
    $lock = $resolved['lock'];
    if (!$lock) {
        return null;
    }
    $neft = json_decode((string) ($lock['neft_json'] ?? '[]'), true);
    if (!is_array($neft)) {
        $neft = [];
    }
    // All-departments NEFT viewed for one department → filter rows
    if ($deptId > 0 && !empty($resolved['from_all_departments'])) {
        $filtered = array_values(array_filter($neft, static function ($r) use ($deptId) {
            return (int) ($r['department_id'] ?? 0) === $deptId;
        }));
        // Older snapshots may lack department_id on NEFT rows — map via register snapshot
        if (!$filtered) {
            $snap = json_decode((string) ($lock['snapshot_json'] ?? ''), true);
            $empIds = [];
            foreach (($snap['rows'] ?? []) as $sr) {
                if ((int) ($sr['department_id'] ?? 0) === $deptId) {
                    $empIds[(int) ($sr['employee_id'] ?? 0)] = true;
                }
            }
            if ($empIds) {
                $filtered = array_values(array_filter($neft, static function ($r) use ($empIds) {
                    return isset($empIds[(int) ($r['employee_id'] ?? 0)]);
                }));
            }
        }
        $neft = $filtered;
        $sr = 0;
        foreach ($neft as &$row) {
            $row['sr'] = ++$sr;
        }
        unset($row);
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

/**
 * ICICI NFPS bank upload constants (from NFPS_FMT SALARY FOUNDARY AND OTHERS.xlsx).
 */
function neftNfpsSettings()
{
    return [
        'pymt_prod_type_code' => 'PAB_VENDOR',
        'pymt_mode' => 'NEFT',
        'debit_acc_no' => '239451000033',
        'email_id' => 'accounts@armorfire.co.in',
    ];
}

/**
 * Beneficiary name for bank file — letters/spaces only, uppercase.
 */
function neftNfpsBeneName($name)
{
    $name = strtoupper(trim((string) $name));
    $name = preg_replace('/[^A-Z0-9 ]+/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return substr(trim((string) $name), 0, 500);
}

/**
 * Merge Normal Salary + Jobwork Actual NEFT rows (all employees).
 */
function getCombinedNeftSheetData($month, $year, $deptId = 0)
{
    $month = (int) $month;
    $year = (int) $year;
    $deptId = (int) $deptId;
    $types = array_keys(function_exists('salaryRegisterTypes') ? salaryRegisterTypes() : ['salary' => 'Salary']);
    if (!$types) {
        $types = ['salary', 'jobwork_actual'];
    }

    $merged = [];
    $seen = [];
    $locks = [];
    $allApproved = true;
    $any = false;
    $payDate = '';

    foreach ($types as $type) {
        $data = getNeftSheetData($month, $year, $type, $deptId);
        if (!$data || empty($data['rows'])) {
            // Still track lock presence for status
            $resolved = function_exists('resolveSalaryRegisterLock')
                ? resolveSalaryRegisterLock($month, $year, $type, $deptId)
                : ['lock' => getSalaryRegisterLock($month, $year, $type, $deptId)];
            if (!empty($resolved['lock'])) {
                $any = true;
                $locks[] = $resolved['lock'];
                if (($resolved['lock']['approval_status'] ?? '') !== 'approved') {
                    $allApproved = false;
                }
            }
            continue;
        }
        $any = true;
        $locks[] = $data['lock'];
        if (($data['lock']['approval_status'] ?? '') !== 'approved') {
            $allApproved = false;
        }
        $at = (string) ($data['lock']['approved_at'] ?? $data['lock']['locked_at'] ?? '');
        if ($at !== '' && ($payDate === '' || strcmp($at, $payDate) > 0)) {
            $payDate = $at;
        }
        foreach ($data['rows'] as $r) {
            $eid = (int) ($r['employee_id'] ?? 0);
            $key = $eid > 0 ? 'e' . $eid : 'a' . md5(($r['account'] ?? '') . '|' . ($r['employee_name'] ?? ''));
            if (isset($seen[$key])) {
                // Same employee in both registers — keep higher net amount
                if ((float) ($r['amount'] ?? 0) > (float) ($merged[$seen[$key]]['amount'] ?? 0)) {
                    $merged[$seen[$key]] = $r;
                }
                continue;
            }
            $seen[$key] = count($merged);
            $merged[] = $r;
        }
    }

    if (!$any) {
        return null;
    }

    $sr = 0;
    $rows = [];
    foreach ($merged as $r) {
        $r['sr'] = ++$sr;
        $rows[] = $r;
    }

    $primary = $locks[0] ?? null;
    return [
        'lock' => $primary ? array_merge($primary, [
            'approval_status' => $allApproved ? 'approved' : (string) ($primary['approval_status'] ?? 'pending_check'),
            'approved_at' => $payDate !== '' ? $payDate : ($primary['approved_at'] ?? $primary['locked_at'] ?? null),
        ]) : null,
        'rows' => $rows,
        'neft_total' => round(array_sum(array_map(static function ($r) {
            return (float) ($r['amount'] ?? 0);
        }, $rows)), 2),
        'total_amount' => round(array_sum(array_map(static function ($r) {
            return (float) ($r['amount'] ?? 0);
        }, $rows)), 2),
        'combined' => true,
        'locks' => $locks,
    ];
}

/**
 * Build ICICI NFPS rows for Excel export.
 *
 * @return array{headers: string[], rows: array<int, array<int, string|float>>, total: float, pay_date: string, credit_narr: string}
 */
function buildNfpsNeftExport(array $neftRows, $month, $year, $payDateYmd = null)
{
    $cfg = neftNfpsSettings();
    $month = (int) $month;
    $year = (int) $year;
    $monthName = strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year)));
    $creditNarr = substr('SALARY ' . $monthName . ' ' . $year, 0, 30);

    if ($payDateYmd && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $payDateYmd, $m)) {
        $payDate = $m[3] . '-' . $m[2] . '-' . $m[1];
    } elseif ($payDateYmd && preg_match('/^\d{2}-\d{2}-\d{4}$/', (string) $payDateYmd)) {
        $payDate = (string) $payDateYmd;
    } else {
        $payDate = date('d-m-Y');
    }

    $headers = [
        'PYMT_PROD_TYPE_CODE',
        'PYMT_MODE',
        'DEBIT_ACC_NO',
        'BNF_NAME',
        'BENE_ACC_NO',
        'BENE_IFSC',
        'AMOUNT',
        'CREDIT_NARR',
        'PYMT_DATE',
        'MOBILE_NUM',
        'EMAIL_ID',
        'REMARK',
        'REF_NO',
    ];

    $rows = [];
    $total = 0.0;
    foreach ($neftRows as $r) {
        $amount = round((float) ($r['amount'] ?? 0), 2);
        if ($amount <= 0) {
            continue;
        }
        $account = preg_replace('/\s+/', '', (string) ($r['account'] ?? ''));
        $account = preg_replace('/[^0-9]/', '', $account);
        if ($account === '') {
            continue;
        }
        $ifsc = strtoupper(preg_replace('/\s+/', '', (string) ($r['ifsc'] ?? '')));
        $mobile = preg_replace('/\D+/', '', (string) ($r['mobile'] ?? ''));
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }
        if (strlen($mobile) !== 10) {
            $mobile = '';
        }

        $total += $amount;
        $rows[] = [
            $cfg['pymt_prod_type_code'],
            $cfg['pymt_mode'],
            $cfg['debit_acc_no'],
            neftNfpsBeneName($r['employee_name'] ?? ''),
            $account,
            $ifsc,
            $amount,
            $creditNarr,
            $payDate,
            $mobile,
            $cfg['email_id'],
            '',
            '',
        ];
    }

    return [
        'headers' => $headers,
        'rows' => $rows,
        'total' => round($total, 2),
        'pay_date' => $payDate,
        'credit_narr' => $creditNarr,
    ];
}

/**
 * Enrich NEFT rows with mobile from employee master (for NFPS MOBILE_NUM).
 */
function enrichNeftRowsWithEmployeeContact(array $neftRows)
{
    foreach ($neftRows as &$r) {
        if (!empty($r['mobile'])) {
            continue;
        }
        $eid = (int) ($r['employee_id'] ?? 0);
        if ($eid <= 0 || !function_exists('getEmployeeById')) {
            continue;
        }
        $emp = getEmployeeById($eid);
        if ($emp) {
            $r['mobile'] = (string) ($emp['mobile_number'] ?? $emp['office_mobile'] ?? '');
            if (empty($r['account']) || $r['account'] === '-') {
                $r['account'] = (string) ($emp['bank_account_number'] ?? '');
            }
            if (empty($r['ifsc']) || $r['ifsc'] === '-') {
                $r['ifsc'] = (string) ($emp['ifsc_code'] ?? '');
            }
            if (empty($r['employee_name'])) {
                $r['employee_name'] = (string) ($emp['employee_name'] ?? '');
            }
        }
    }
    unset($r);
    return $neftRows;
}
