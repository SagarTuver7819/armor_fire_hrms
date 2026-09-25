<?php
/**
 * Salary register rows — Normal / Jobwork Govt / Jobwork Actual / Contractor Main
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/contractor_helper.php';
require_once __DIR__ . '/attendance_helper.php';

function salaryRegisterTypes()
{
    return [
        'salary' => '1 · Normal Fixed Salary (Attendance)',
        'jobwork_govt' => '2 · Jobwork Government (Attendance days)',
        'jobwork_actual' => '2 · Jobwork Regular / Actual (Qty × Rate)',
        'contractor_main' => '3 · Contractor Main (Team)',
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
        $gross = round($actual, 2);
        $salaryCol = round($actual, 2);
    }

    $pf = $att['pf'];
    $pt = $att['pt'];
    $loan = $att['loan'];
    $advance = $att['advance'];
    $totalDed = round($pf + $pt + $loan + $advance, 2);
    $net = round($gross - $totalDed + $att['arrears'], 2);
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

    return [
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
        'week_off' => $att['week_off'],
        'pl' => $att['pl'],
        'sl' => $att['sl'],
        'dl' => $att['dl'],
        'coff' => (float) ($att['coff'] ?? 0),
        'holiday' => (float) ($att['holiday'] ?? 0),
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
        'arrears' => $att['arrears'],
        'net' => $net,
        'bank_name' => (string) ($emp['bank_name'] ?? ''),
        'account' => (string) ($emp['bank_account_number'] ?? '-'),
        'ifsc' => (string) ($emp['ifsc_code'] ?? '-'),
        'remarks' => (string) ($emp['extra_note'] ?? '-'),
        'week_off_paid' => (float) ($att['week_off_paid'] ?? 0),
        'holiday_paid' => (float) ($att['holiday_paid'] ?? 0),
        'eligible_days' => (float) ($att['working'] ?? 0),
        'emp_from' => $att['emp_from'] ?? null,
        'emp_to' => $att['emp_to'] ?? null,
    ];
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
            WHERE (
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
        $mains = fetchSalaryRegisterEmployees($deptId, $employeeId, ['ContractorMain'], $month, $year);
        if ($employeeId > 0 && !$mains) {
            $one = getEmployeeById($employeeId);
            if ($one && normalizePayType($one['pay_type'] ?? '') === 'ContractorMain') {
                $mains = [$one];
            }
        }
        foreach ($mains as $main) {
            $under = getContractorUnderEmployees((int) $main['id']);
            if ($deptId > 0) {
                $under = array_values(array_filter($under, function ($u) use ($deptId) {
                    return (int) $u['department_id'] === (int) $deptId;
                }));
            }
            $syncIds = array_map(static function ($u) {
                return (int) $u['id'];
            }, $under);
            payrollBatchSyncDiariesFromAttendance($syncIds, $month, $year);
            $rows = [];
            foreach ($under as $u) {
                $row = buildSalaryRegisterRow($u, $month, $year, 'jobwork_govt');
                $row['mode'] = 'jobwork_govt';
                $rows[] = $row;
            }
            $groups[] = [
                'title' => 'Contractor Main: ' . ($main['employee_code'] ?? '') . ' — ' . ($main['employee_name'] ?? ''),
                'subtitle' => 'Under employees · government days split',
                'mode' => 'contractor_main',
                'rows' => $rows,
            ];
        }
        return $groups;
    }

    if ($type === 'salary') {
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Salary'], $month, $year);
        $mode = 'salary';
        $title = 'Salaried Person (Fixed)';
        $subtitle = 'Attendance-wise fixed salary';
        $payFilter = 'Salary';
    } elseif ($type === 'jobwork_actual') {
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Jobwork'], $month, $year);
        $mode = 'jobwork_actual';
        $title = 'Jobwork — Regular / Actual';
        $subtitle = 'Qty × Rate (contract base, no day split)';
        $payFilter = 'Jobwork';
    } else {
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Jobwork'], $month, $year);
        $mode = 'jobwork_govt';
        $title = 'Jobwork — Government';
        $subtitle = 'Actual earning × attendance paid days ÷ month days';
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
    return number_format((float) $n, $decimals);
}
