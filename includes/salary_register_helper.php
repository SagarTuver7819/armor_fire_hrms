<?php
/**
 * Salary register rows — Normal Fixed Salary / Jobwork Regular / Actual (Qty × Rate)
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/contractor_helper.php';
require_once __DIR__ . '/attendance_helper.php';
require_once __DIR__ . '/contractor_split_helper.php';

function salaryRegisterTypes()
{
    return [
        'salary' => '1 · Normal Fixed Salary (Attendance)',
        'jobwork_actual' => '2 · Jobwork Regular / Actual (Qty × Rate)',
        'contractor_main' => '3 · Main Contractor (Under Employees)',
    ];
}

function formatRegisterDate($value)
{
    $formatted = formatDateDisplay($value);
    return $formatted !== '' ? $formatted : '-';
}

function buildSalaryRegisterRow(array $emp, $month, $year, $mode)
{
    $mode = (string) $mode;
    // Diary sync is batched in getSalaryRegisterData — avoid per-row N+1
    $payType = normalizePayType($emp['pay_type'] ?? 'Salary');
    $actual = getJobworkTotal((int) $emp['id'], $month, $year);
    $qty = getJobworkQtyTotal((int) $emp['id'], $month, $year);
    $baseAmount = (float) ($emp['decided_salary'] ?? 0);
    if ($payType === 'Salary' && function_exists('employeeDecidedSalaryAsOf')) {
        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
        $baseAmount = employeeDecidedSalaryAsOf((int) ($emp['id'] ?? 0), $monthEnd, null, $baseAmount);
    }
    if ($payType === 'Salary' && $baseAmount <= 0) {
        foreach (getEmployeeSalaryDetails((int) $emp['id']) as $line) {
            if (($line['line_type'] ?? 'component') === 'component' && ($line['component_type'] ?? 'Earning') !== 'Deduction') {
                $baseAmount += (float) $line['amount'];
            }
        }
    }
    if ($payType !== 'Salary') {
        $baseAmount = $actual;
    }
    $att = getPayrollAttendanceBundle($emp, $month, $year, $baseAmount);

    $salaryCol = $att['salary'];
    $gross = $att['govt_gross'];
    if ($mode === 'jobwork_actual') {
        // Regular/Actual: full Qty × Rate (no attendance day split)
        $gross = round($actual, 2);
        $salaryCol = round($actual, 2);
    }

    $pf = $att['pf'];
    // PT on the Gross shown on this register (Jobwork Actual = full amount ≥ ₹12,001 → ₹200)
    $pt = function_exists('statutoryPt') ? statutoryPt($gross) : (float) ($att['pt'] ?? 0);
    $loan = $att['loan'];
    $advance = $att['advance'];
    $totalDed = round($pf + $pt + $loan + $advance, 2);
    $memo = (float) ($att['memo'] ?? 0);
    $net = round($gross - $totalDed - $memo + $att['arrears'], 2);
    if ($net < 0) {
        $net = 0;
    }

    $mainName = '';
    if (!empty($emp['main_contractor_id'])) {
        $main = getEmployeeById((int) $emp['main_contractor_id']);
        if ($main) {
            $mainName = trim(($main['employee_code'] ?? '') . ' — ' . ($main['employee_name'] ?? ''));
        }
    }

    $row = [
        'employee_id' => (int) $emp['id'],
        'pay_type' => $payType,
        'department' => (string) ($emp['department_name'] ?? ''),
        'department_id' => (int) ($emp['department_id'] ?? 0),
        'designation' => (string) ($emp['designation'] ?? '-'),
        'doj' => formatRegisterDate($emp['date_of_joining'] ?? ''),
        'uan' => (string) ($emp['uan_number'] ?? '-'),
        'employee_name' => (string) ($emp['employee_name'] ?? ''),
        'employee_code' => (string) ($emp['employee_code'] ?? ''),
        'main_contractor' => $mainName,
        'main_contractor_id' => (int) ($emp['main_contractor_id'] ?? 0),
        'salary' => $salaryCol,
        'present' => $att['present'],
        // Week-off Benefits = No → show 0 on Salary Register (not unpaid calendar WO)
        'week_off' => (float) ($att['week_off_paid'] ?? 0),
        'pl' => $att['pl'],
        'sl' => $att['sl'],
        'dl' => $att['dl'],
        'coff' => (float) ($att['coff'] ?? 0),
        // Holiday Benefits = No → show 0 for unpaid holidays on register
        'holiday' => (float) ($att['holiday_paid'] ?? 0),
        'lwp' => (float) ($att['lwp'] ?? 0),
        'total_days' => $att['total_days'],
        'month_days' => $att['month_days'],
        'qty' => $qty,
        'actual' => round($actual, 2),
        'gross' => $gross,
        'pf' => $pf,
        'pf_employer' => 0,
        'pt' => $pt,
        'loan' => $loan,
        'advance' => $advance,
        'total_deduction' => $totalDed,
        'memo' => $memo,
        'memo_remark' => (string) ($att['memo_remark'] ?? ''),
        'arrears' => $att['arrears'],
        'net' => $net,
        'bank_name' => (string) ($emp['bank_name'] ?? ''),
        'account' => (string) ($emp['bank_account_number'] ?? '-'),
        'ifsc' => (string) ($emp['ifsc_code'] ?? '-'),
        'remarks' => (function () use ($att, $emp) {
            $diaryRem = trim((string) ($att['diary_remarks'] ?? ''));
            if ($diaryRem !== '') {
                return $diaryRem;
            }
            $note = trim((string) ($emp['extra_note'] ?? ''));
            return $note !== '' ? $note : '-';
        })(),
        'week_off_paid' => (float) ($att['week_off_paid'] ?? 0),
        'holiday_paid' => (float) ($att['holiday_paid'] ?? 0),
        'week_off_raw' => (float) ($att['week_off'] ?? 0),
        'holiday_raw' => (float) ($att['holiday'] ?? 0),
        'eligible_days' => (float) ($att['working'] ?? 0),
        'emp_from' => $att['emp_from'] ?? null,
        'emp_to' => $att['emp_to'] ?? null,
        'contractor_main_share' => 0.0,
    ];

    // Melting / CNC / BUFF / ASSEMBLY 2+3: Normal funded from Main pool; Main Jobwork = remainder
    // Remainder only on Jobwork Actual register — never rewrite Normal Salary rows.
    if (function_exists('applyContractorAttendanceShareToRegisterRow')) {
        $row = applyContractorAttendanceShareToRegisterRow($row, $month, $year, $mode);
    }

    return registerApplyMoneyRoundOff($row);
}

function salaryRegisterEmploymentWhereSql()
{
    return "(
                e.status = 1
                OR (
                    e.date_of_exit IS NOT NULL
                    AND e.date_of_exit != ''
                    AND e.date_of_exit != '0000-00-00'
                    AND e.date_of_exit >= ?
                )
            )
            AND (
                e.date_of_joining IS NULL
                OR e.date_of_joining = ''
                OR e.date_of_joining = '0000-00-00'
                OR e.date_of_joining <= ?
            )";
}

function fetchSalaryRegisterEmployees($deptId, $employeeId, $payTypes, $month = 0, $year = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $month = (int) $month;
    $year = (int) $year;
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    if ($year < 2000) {
        $year = (int) date('Y');
    }
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    // Include active + exited employees who still worked in this month
    $sql = "SELECT e.*, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE " . salaryRegisterEmploymentWhereSql();
    $types = 'ss';
    $params = [$monthStart, $monthEnd];
    if ($payTypes) {
        $ph = implode(',', array_fill(0, count($payTypes), '?'));
        $sql .= " AND e.pay_type IN ({$ph})";
        $types .= str_repeat('s', count($payTypes));
        foreach ($payTypes as $t) {
            $params[] = $t;
        }
    }
    if ($deptId > 0) {
        $sql .= " AND e.department_id = ?";
        $types .= 'i';
        $params[] = $deptId;
    }
    if ($employeeId > 0) {
        $sql .= " AND e.id = ?";
        $types .= 'i';
        $params[] = $employeeId;
    }
    $sql .= " ORDER BY d.department_name ASC, e.employee_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $rows;
}

/**
 * Normal (fixed salary) staff for register / NEFT — pay_type = Salary only.
 * (CO code + Salary still = Normal; never send them to Jobwork register.)
 */
function fetchNormalRegisterEmployees($deptId, $employeeId, $month = 0, $year = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $month = (int) $month;
    $year = (int) $year;
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    if ($year < 2000) {
        $year = (int) date('Y');
    }
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $sql = "SELECT e.*, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE " . salaryRegisterEmploymentWhereSql() . "
            AND e.pay_type = 'Salary'";
    $types = 'ss';
    $params = [$monthStart, $monthEnd];
    if ($deptId > 0) {
        $sql .= " AND e.department_id = ?";
        $types .= 'i';
        $params[] = $deptId;
    }
    if ($employeeId > 0) {
        $sql .= " AND e.id = ?";
        $types .= 'i';
        $params[] = $employeeId;
    }
    $sql .= " ORDER BY d.department_name ASC, e.employee_code ASC, e.employee_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $rows;
}

/**
 * Jobwork register / NEFT — pay_type Jobwork + ContractorMain.
 * Normal Salary employees (even with CO… code) never appear here.
 * Main Contractors appear so every dept pool / remainder row is on Jobwork + NEFT.
 */
function fetchJobworkRegisterEmployees($deptId, $employeeId, $month = 0, $year = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    if (is_file(__DIR__ . '/contractor_helper.php')) {
        require_once __DIR__ . '/contractor_helper.php';
        ensureContractorTables($conn);
    }
    ensurePayrollTables($conn);

    $month = (int) $month;
    $year = (int) $year;
    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    if ($year < 2000) {
        $year = (int) date('Y');
    }
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    $opsIds = [];
    $resOps = $conn->query(
        "SELECT DISTINCT employee_id FROM contractor_operation_sheets
         WHERE month_no = {$month} AND year_no = {$year} AND status = 1 AND employee_id > 0"
    );
    if ($resOps) {
        while ($row = $resOps->fetch_assoc()) {
            $opsIds[(int) $row['employee_id']] = true;
        }
        $resOps->free();
    }
    $fromEsc = $conn->real_escape_string($monthStart);
    $toEsc = $conn->real_escape_string($monthEnd);
    $resJw = $conn->query(
        "SELECT DISTINCT employee_id FROM jobwork_entries
         WHERE work_date BETWEEN '{$fromEsc}' AND '{$toEsc}' AND employee_id > 0"
    );
    if ($resJw) {
        while ($row = $resJw->fetch_assoc()) {
            $opsIds[(int) $row['employee_id']] = true;
        }
        $resJw->free();
    }

    // Jobwork + Main Contractor — Salary/Normal never listed here
    $sql = "SELECT e.*, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE " . salaryRegisterEmploymentWhereSql() . "
            AND e.pay_type IN ('Jobwork', 'ContractorMain')";
    $types = 'ss';
    $params = [$monthStart, $monthEnd];

    if ($deptId > 0) {
        $sql .= " AND e.department_id = ?";
        $types .= 'i';
        $params[] = $deptId;
    }
    if ($employeeId > 0) {
        $sql .= " AND e.id = ?";
        $types .= 'i';
        $params[] = $employeeId;
    }
    $sql .= " ORDER BY d.department_name ASC, e.employee_code ASC, e.employee_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Ops extras: Jobwork / ContractorMain only (never pull Normal Salary)
    $have = [];
    foreach ($rows as $r) {
        $have[(int) $r['id']] = true;
    }
    $missing = [];
    foreach (array_keys($opsIds) as $oid) {
        if ($oid > 0 && empty($have[$oid])) {
            $missing[] = $oid;
        }
    }
    if ($missing) {
        $idList = implode(',', array_map('intval', $missing));
        $extraSql = "SELECT e.*, d.department_name
                     FROM employees e
                     LEFT JOIN departments d ON d.id = e.department_id
                     WHERE e.id IN ($idList)
                     AND e.pay_type IN ('Jobwork', 'ContractorMain')
                     AND " . salaryRegisterEmploymentWhereSql();
        $extraTypes = 'ss';
        $extraParams = [$monthStart, $monthEnd];
        if ($deptId > 0) {
            $extraSql .= " AND e.department_id = ?";
            $extraTypes .= 'i';
            $extraParams[] = $deptId;
        }
        if ($employeeId > 0) {
            $extraSql .= " AND e.id = ?";
            $extraTypes .= 'i';
            $extraParams[] = $employeeId;
        }
        $extraSql .= " ORDER BY d.department_name ASC, e.employee_code ASC";
        $stmt2 = $conn->prepare($extraSql);
        $stmt2->bind_param($extraTypes, ...$extraParams);
        $stmt2->execute();
        $extra = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();
        foreach ($extra as $r) {
            $rows[] = $r;
        }
    }

    $conn->close();

    usort($rows, static function ($a, $b) {
        $d = strcasecmp((string) ($a['department_name'] ?? ''), (string) ($b['department_name'] ?? ''));
        if ($d !== 0) {
            return $d;
        }
        $c = strcasecmp((string) ($a['employee_code'] ?? ''), (string) ($b['employee_code'] ?? ''));
        if ($c !== 0) {
            return $c;
        }
        return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
    });

    return $rows;
}

/**
 * Batch sync attendance → salary_diary for employees who have day marks this month.
 * Uses one DB connection; clears diary cache afterwards.
 */
function payrollBatchSyncDiariesFromAttendance(array $employeeIds, $month, $year)
{
    static $syncedKeys = [];
    $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds))));
    $month = (int) $month;
    $year = (int) $year;
    if (!$employeeIds || $month < 1 || $month > 12) {
        return 0;
    }

    // Skip IDs already synced this request for this month
    $pending = [];
    foreach ($employeeIds as $eid) {
        $k = $month . '-' . $year . '-' . $eid;
        if (!isset($syncedKeys[$k])) {
            $pending[] = $eid;
        }
    }
    if (!$pending) {
        return 0;
    }
    $employeeIds = $pending;


    $conn = getDBConnection();
    ensureAttendanceTables($conn);
    ensurePayrollTables($conn);

    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $idList = implode(',', $employeeIds);
    $withAtt = [];
    $res = $conn->query(
        "SELECT DISTINCT employee_id FROM attendance_day_status
         WHERE attendance_date BETWEEN '{$conn->real_escape_string($from)}' AND '{$conn->real_escape_string($to)}'
           AND employee_id IN ({$idList})"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $withAtt[(int) $r['employee_id']] = true;
        }
    }

    $synced = 0;
    foreach ($employeeIds as $eid) {
        $k = $month . '-' . $year . '-' . $eid;
        $syncedKeys[$k] = true;
        if (!isset($withAtt[$eid])) {
            continue;
        }
        if (ensurePayrollDiaryFromAttendance($eid, $month, $year, $conn)) {
            $synced++;
        }
    }
    $conn->close();
    if (function_exists('diaryMonthCacheClear')) {
        diaryMonthCacheClear($month, $year);
    }


    return $synced;
}

function getSalaryRegisterData($type, $month, $year, $deptId = 0, $employeeId = 0)
{
    ensurePayrollTables();
    $type = (string) $type;
    $groups = [];

    if ($type === 'contractor_main') {
        // All Main Contractors (pay_type) + configured Melting/CNC/BUFF/ASSEMBLY 2 split mains
        $mains = fetchSalaryRegisterEmployees($deptId, $employeeId, ['ContractorMain'], $month, $year);
        $byId = [];
        foreach ($mains as $main) {
            $byId[(int) $main['id']] = $main;
        }
        if (function_exists('contractorAttendanceSplitRules')) {
            foreach (contractorAttendanceSplitRules() as $rule) {
                $main = resolveContractorAttendanceSplitMain($rule);
                if (!$main) {
                    continue;
                }
                $mid = (int) $main['id'];
                if ($employeeId > 0 && $mid !== $employeeId) {
                    continue;
                }
                if ($deptId > 0) {
                    $splitDept = resolveDepartmentIdByName((string) ($rule['department_name'] ?? ''));
                    $mainDept = (int) ($main['department_id'] ?? 0);
                    if ($splitDept > 0 && $deptId !== $splitDept && $deptId !== $mainDept) {
                        continue;
                    }
                }
                if (!isset($byId[$mid])) {
                    $byId[$mid] = $main;
                }
            }
        }
        if ($employeeId > 0 && !$byId) {
            $one = getEmployeeById($employeeId);
            if ($one && normalizePayType($one['pay_type'] ?? '') === 'ContractorMain') {
                $byId[(int) $one['id']] = $one;
            }
        }
        $mains = array_values($byId);
        usort($mains, static function ($a, $b) {
            return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
        });

        foreach ($mains as $main) {
            $under = getContractorUnderEmployees((int) $main['id']);
            if ($deptId > 0) {
                $under = array_values(array_filter($under, static function ($u) use ($deptId) {
                    return (int) ($u['department_id'] ?? 0) === $deptId;
                }));
            }
            $syncIds = array_map(static function ($u) {
                return (int) $u['id'];
            }, $under);
            // Also sync the Main row itself on Jobwork register path
            $syncIds[] = (int) $main['id'];
            payrollBatchSyncDiariesFromAttendance($syncIds, $month, $year);
            $rows = [];
            // Main contractor remainder / jobwork row first
            $mainRow = buildSalaryRegisterRow($main, $month, $year, 'jobwork_actual');
            $mainRow['mode'] = 'jobwork_actual';
            $mainRow['remarks'] = trim((string) ($mainRow['remarks'] ?? ''));
            if ($mainRow['remarks'] === '' || $mainRow['remarks'] === '-') {
                $mainRow['remarks'] = 'Main Contractor';
            }
            $rows[] = $mainRow;
            $salaryUnder = [];
            $jwUnder = [];
            $normalGrossTotal = 0.0;
            $normalNetTotal = 0.0;
            $normalPtTotal = 0.0;
            foreach ($under as $u) {
                $uPay = normalizePayType($u['pay_type'] ?? '');
                $uMode = $uPay === 'Salary' ? 'salary' : 'jobwork_actual';
                $row = buildSalaryRegisterRow($u, $month, $year, $uMode);
                $row['mode'] = $uMode;
                if ($uMode === 'salary') {
                    $normalGrossTotal += (float) ($row['gross'] ?? 0);
                    $normalNetTotal += (float) ($row['net'] ?? 0);
                    $normalPtTotal += (float) ($row['pt'] ?? 0);
                    $salaryUnder[] = $row;
                } else {
                    $jwUnder[] = $row;
                }
            }
            $fundBasis = (string) ($mainRow['contractor_fund_basis'] ?? 'gross');
            $useNetPt = ($fundBasis === 'net_plus_pt');
            // Prefer split engine distributed (exact fund used for Main remainder)
            $fundTotal = !empty($mainRow['contractor_split_out'])
                ? (float) ($mainRow['contractor_distributed'] ?? 0)
                : ($useNetPt
                    ? round($normalNetTotal + $normalPtTotal, 2)
                    : round($normalGrossTotal, 2));
            $fundTitle = $useNetPt ? 'Normal Salary Net + PT Total' : 'Normal Salary Gross Total';
            $fundRemark = $useNetPt
                ? ('Σ Net ₹ ' . registerNum($normalNetTotal, 0) . ' + Σ PT ₹ ' . registerNum($normalPtTotal, 0) . ' · under Main')
                : 'Σ Gross · Normal Salary under Main';
            // Normal Salary under first → fund total after them → then Jobwork under
            foreach ($salaryUnder as $row) {
                $rows[] = $row;
            }
            if ($salaryUnder) {
                $rows[] = [
                    'employee_id' => 0,
                    'employee_code' => '',
                    'employee_name' => $fundTitle,
                    'is_normal_gross_total' => true,
                    'pay_type' => 'Salary',
                    'mode' => 'salary',
                    'designation' => '',
                    'doj' => '',
                    'uan' => '',
                    'salary' => 0,
                    'present' => 0,
                    'week_off' => 0,
                    'holiday' => 0,
                    'pl' => 0,
                    'sl' => 0,
                    'dl' => 0,
                    'coff' => 0,
                    'total_days' => 0,
                    'qty' => 0,
                    'actual' => 0,
                    'gross' => round($fundTotal, 2),
                    'pf' => 0,
                    'pt' => round($normalPtTotal, 2),
                    'loan' => 0,
                    'advance' => 0,
                    'total_deduction' => round($normalPtTotal, 2),
                    'memo' => 0,
                    'arrears' => 0,
                    'net' => round($normalNetTotal, 2),
                    'account' => '',
                    'ifsc' => '',
                    'remarks' => $fundRemark,
                    'main_contractor' => '',
                    'department' => (string) ($mainRow['department'] ?? ''),
                    'department_id' => (int) ($mainRow['department_id'] ?? 0),
                ];
            }
            foreach ($jwUnder as $row) {
                $rows[] = $row;
            }
            $groups[] = [
                'title' => 'Main Contractor: ' . ($main['employee_code'] ?? '') . ' — ' . ($main['employee_name'] ?? ''),
                'subtitle' => $useNetPt
                    ? 'Main + Normal Salary under (Net + PT total) + Jobwork under'
                    : 'Main + Normal Salary under (Gross total) + Jobwork under',
                'mode' => 'contractor_main',
                'normal_salary_gross' => round($normalGrossTotal, 2),
                'normal_salary_fund' => round($fundTotal, 2),
                'rows' => $rows,
            ];
        }
        return $groups;
    }

    if ($type === 'salary') {
        // Normal = Salary pay_type (exclude Melting/CNC Main — remainder is Jobwork only)
        $emps = fetchNormalRegisterEmployees($deptId, $employeeId, $month, $year);
        if (function_exists('contractorAttendanceSplitMainIds')) {
            $mains = contractorAttendanceSplitMainIds();
            if ($mains) {
                $emps = array_values(array_filter($emps, static function ($e) use ($mains) {
                    return !isset($mains[(int) ($e['id'] ?? 0)]);
                }));
            }
        }
        $mode = 'salary';
        $title = 'Salaried Person (Fixed)';
        $subtitle = 'Attendance-wise fixed salary';
        $payFilter = 'Salary';
    } else {
        // Jobwork = pay_type Jobwork only (+ force-include Melting/CNC mains after split)
        $emps = fetchJobworkRegisterEmployees($deptId, $employeeId, $month, $year);
        if (function_exists('ensureSplitMainInJobworkEmployeeList')) {
            $emps = ensureSplitMainInJobworkEmployeeList($emps, $deptId, $month, $year);
        }
        $mode = 'jobwork_actual';
        $title = 'Jobwork — Regular / Actual';
        $subtitle = 'Qty × Rate · Jobwork by department (Main Contractor = remainder after Normal Salary fund)';
        $payFilter = 'Jobwork';
    }

    $syncIds = array_map(static function ($e) {
        return (int) $e['id'];
    }, $emps);
    payrollBatchSyncDiariesFromAttendance($syncIds, $month, $year);

    $byDept = [];
    foreach ($emps as $emp) {
        $deptName = (string) ($emp['department_name'] ?? 'Department');
        if (!isset($byDept[$deptName])) {
            $byDept[$deptName] = [];
        }
        $row = buildSalaryRegisterRow($emp, $month, $year, $mode);
        $row['mode'] = $mode;
        $byDept[$deptName][] = $row;
    }

    foreach ($byDept as $deptName => $rows) {
        $groups[] = [
            'title' => $title . ' · ' . $deptName,
            'subtitle' => $subtitle,
            'mode' => $mode,
            'rows' => $rows,
        ];
    }

    if (!$groups && $payFilter) {
        $groups[] = [
            'title' => $title,
            'subtitle' => $subtitle,
            'mode' => $mode,
            'rows' => [],
        ];
    }

    return $groups;
}

function registerNum($n, $decimals = 2)
{
    if ($n === null || $n === '') {
        return '-';
    }
    if (function_exists('formatIndianNumber')) {
        return formatIndianNumber((float) $n, $decimals);
    }
    return number_format((float) $n, $decimals);
}

/**
 * Round money: .50+ rounds up to next rupee (0.00–0.49 → down).
 */
function registerRoundMoney($n)
{
    return (float) round((float) $n, 0, PHP_ROUND_HALF_UP);
}

/**
 * Apply rupee round-off on Salary Register money columns.
 */
function registerApplyMoneyRoundOff(array $row)
{
    foreach (['salary', 'gross', 'actual', 'pf', 'pt', 'loan', 'advance', 'total_deduction', 'memo', 'arrears', 'net'] as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            $row[$key] = registerRoundMoney($row[$key]);
        }
    }
    // Keep Net consistent after round: Gross − Ded − Memo + Arrears
    $gross = (float) ($row['gross'] ?? 0);
    $ded = (float) ($row['total_deduction'] ?? 0);
    $memo = (float) ($row['memo'] ?? 0);
    $arr = (float) ($row['arrears'] ?? 0);
    $net = registerRoundMoney($gross - $ded - $memo + $arr);
    if ($net < 0) {
        $net = 0.0;
    }
    $row['net'] = $net;
    return $row;
}

/**
 * Sum money columns for a department / grand total row.
 *
 * @return array{salary:float,gross:float,actual:float,pf:float,pt:float,loan:float,advance:float,total_deduction:float,arrears:float,net:float}
 */
function registerSumMoneyColumns(array $rows)
{
    $sum = [
        'salary' => 0.0,
        'gross' => 0.0,
        'actual' => 0.0,
        'pf' => 0.0,
        'pt' => 0.0,
        'loan' => 0.0,
        'advance' => 0.0,
        'total_deduction' => 0.0,
        'memo' => 0.0,
        'arrears' => 0.0,
        'net' => 0.0,
    ];
    foreach ($rows as $r) {
        foreach (array_keys($sum) as $k) {
            $sum[$k] += (float) ($r[$k] ?? 0);
        }
    }
    foreach (array_keys($sum) as $k) {
        $sum[$k] = registerRoundMoney($sum[$k]);
    }
    return $sum;
}
