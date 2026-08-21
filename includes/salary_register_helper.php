<?php
/**
 * Salary register rows — Normal / Jobwork Govt / Jobwork Actual / Contractor Main
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';
require_once __DIR__ . '/contractor_helper.php';

function salaryRegisterTypes()
{
    return [
        'salary' => '1 · Normal Salary',
        'jobwork_govt' => '2 · Jobwork (Government)',
        'jobwork_actual' => '2 · Jobwork (Actual)',
        'contractor_main' => '3 · Contractor Main (Team)',
    ];
}

function formatRegisterDate($value)
{
    if (!$value || $value === '0000-00-00') {
        return '-';
    }
    $ts = strtotime((string) $value);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function buildSalaryRegisterRow(array $emp, $month, $year, $mode)
{
    $mode = (string) $mode;
    $payType = normalizePayType($emp['pay_type'] ?? 'Salary');
    $actual = getJobworkTotal((int) $emp['id'], $month, $year);
    $qty = getJobworkQtyTotal((int) $emp['id'], $month, $year);
    $baseAmount = (float) ($emp['decided_salary'] ?? 0);
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
        'total_days' => $att['total_days'],
        'month_days' => $att['month_days'],
        'qty' => $qty,
        'actual' => round($actual, 2),
        'gross' => $gross,
        'pf' => $pf,
        'pt' => $pt,
        'loan' => $loan,
        'advance' => $advance,
        'total_deduction' => $totalDed,
        'arrears' => $att['arrears'],
        'net' => $net,
        'account' => (string) ($emp['bank_account_number'] ?? '-'),
        'ifsc' => (string) ($emp['ifsc_code'] ?? '-'),
        'remarks' => (string) ($emp['extra_note'] ?? '-'),
    ];
}

function fetchSalaryRegisterEmployees($deptId, $employeeId, $payTypes)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $sql = "SELECT e.*, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 1";
    $types = '';
    $params = [];
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
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    return $rows;
}

function getSalaryRegisterData($type, $month, $year, $deptId = 0, $employeeId = 0)
{
    ensurePayrollTables();
    $type = (string) $type;
    $groups = [];

    if ($type === 'contractor_main') {
        $mains = fetchSalaryRegisterEmployees($deptId, $employeeId, ['ContractorMain']);
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
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Salary']);
        $mode = 'salary';
        $title = 'Salaried Person';
        $subtitle = 'Attendance-wise salary';
        $payFilter = 'Salary';
    } elseif ($type === 'jobwork_actual') {
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Jobwork']);
        $mode = 'jobwork_actual';
        $title = 'Jobwork — Actual (Qty × Rate)';
        $subtitle = 'Actual production earning';
        $payFilter = 'Jobwork';
    } else {
        $emps = fetchSalaryRegisterEmployees($deptId, $employeeId, ['Jobwork']);
        $mode = 'jobwork_govt';
        $title = 'Jobwork — Government';
        $subtitle = 'Actual earning divided across month days';
        $payFilter = 'Jobwork';
    }

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
