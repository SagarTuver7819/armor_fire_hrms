<?php
/**
 * Main Contractor pool → department Normal Salary attendance fund + remainder on Main
 * (Melting 1, Melting 2, CNC — same rule):
 * - Dept Normal Salary keep attendance net on register/NEFT (funded from Main pool).
 * - Main Jobwork/NEFT = pool − Σ(Normal nets) → remainder.
 * - Dept Jobwork (not main) stay on Jobwork register with own qty — no pool dump.
 *
 * Melting 1: PURANSINH PUNITBHAI / CO72216
 * Melting 2: auto from department main_contractor links / Jobwork CO
 * CNC: Ravi (name match + department auto-resolve)
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';

/**
 * @return array<int, array{main_name: string, main_code: string, department_name: string, name_aliases: string[], resolve_from_department?: bool}>
 */
function contractorAttendanceSplitRules()
{
    return [
        [
            'main_name' => 'PURANSINH PUNITBHAI',
            'main_code' => 'CO72216',
            'department_name' => 'MELTING 1',
            'name_aliases' => [
                'PURANSINH PUNITBHAI',
                'Puran Singh',
                'Puransinh',
                'Punit Singh',
                'PUNITBHAI',
            ],
        ],
        [
            'main_name' => 'Melting 2 Main Contractor',
            'main_code' => '',
            'department_name' => 'MELTING 2',
            'name_aliases' => [],
            'resolve_from_department' => true,
        ],
        [
            // Same as Melting 1 / Puran — Ravi CNC Main Contractor
            'main_name' => 'RAVI',
            'main_code' => '',
            'department_name' => 'CNC',
            'name_aliases' => [
                'RAVI',
                'Ravi',
                'RAVI BHAI',
                'Ravi Bhai',
            ],
            'resolve_from_department' => true,
        ],
    ];
}

/**
 * Options for Employee form "Contractor Main" when pay type = Jobwork.
 * Includes ContractorMain + configured Melting mains (e.g. PURANSINH CO72216).
 *
 * @return array<int, array{id:int, employee_code:string, employee_name:string}>
 */
function getMainContractorSelectOptions($excludeId = 0)
{
    $excludeId = (int) $excludeId;
    $byId = [];
    foreach (getEmployeesByPayType('ContractorMain', $excludeId) as $row) {
        $byId[(int) $row['id']] = $row;
    }
    foreach (contractorAttendanceSplitRules() as $rule) {
        $main = resolveContractorAttendanceSplitMain($rule);
        if ($main && (int) $main['id'] !== $excludeId) {
            $byId[(int) $main['id']] = [
                'id' => (int) $main['id'],
                'employee_code' => (string) ($main['employee_code'] ?? ''),
                'employee_name' => (string) ($main['employee_name'] ?? ''),
                'department_id' => (int) ($main['department_id'] ?? 0),
            ];
        }
    }
    $rows = array_values($byId);
    usort($rows, static function ($a, $b) {
        return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
    });
    return $rows;
}

/**
 * @return array<int, true>
 */
function contractorAttendanceSplitMainIds()
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = [];
    foreach (contractorAttendanceSplitRules() as $rule) {
        $main = resolveContractorAttendanceSplitMain($rule);
        if ($main) {
            $ids[(int) $main['id']] = true;
        }
    }
    return $ids;
}

/**
 * Find Main Contractor for a split department (most-linked main_contractor_id, else Jobwork/CO in dept).
 *
 * @return array<string, mixed>|null
 */
function resolveMainContractorForDepartment($departmentName)
{
    $deptId = resolveDepartmentIdByName($departmentName);
    if ($deptId <= 0) {
        return null;
    }
    $conn = getDBConnection();
    ensureEmployeesTable($conn);

    // 1) Most common main_contractor_id among active Salary/Jobwork in this department
    $st = $conn->prepare(
        "SELECT e.main_contractor_id AS mid, COUNT(*) AS c
         FROM employees e
         WHERE e.department_id = ?
           AND e.status = 1
           AND e.pay_type IN ('Salary', 'Jobwork')
           AND e.main_contractor_id IS NOT NULL
           AND e.main_contractor_id > 0
         GROUP BY e.main_contractor_id
         ORDER BY c DESC, e.main_contractor_id ASC
         LIMIT 1"
    );
    $st->bind_param('i', $deptId);
    $st->execute();
    $top = $st->get_result()->fetch_assoc();
    $st->close();
    $mid = (int) ($top['mid'] ?? 0);
    if ($mid > 0) {
        $st = $conn->prepare(
            "SELECT e.*, d.department_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.id = ? AND e.status = 1
             LIMIT 1"
        );
        $st->bind_param('i', $mid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $conn->close();
            return $row;
        }
    }

    // 2) Jobwork / ContractorMain / CO… employee in this department (largest decided_salary)
    $st = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.department_id = ?
           AND e.status = 1
           AND (
                e.pay_type IN ('Jobwork', 'ContractorMain')
                OR UPPER(TRIM(e.employee_code)) LIKE 'CO%'
           )
         ORDER BY
           CASE WHEN e.pay_type = 'ContractorMain' THEN 0
                WHEN e.pay_type = 'Jobwork' THEN 1
                ELSE 2 END,
           e.decided_salary DESC,
           e.id ASC
         LIMIT 1"
    );
    $st->bind_param('i', $deptId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    return $row ?: null;
}

function resolveContractorAttendanceSplitMain(array $rule)
{
    static $cache = [];
    $code = strtoupper(trim((string) ($rule['main_code'] ?? '')));
    $deptName = trim((string) ($rule['department_name'] ?? ''));
    $ck = $code !== ''
        ? $code
        : ('dept:' . strtolower($deptName !== '' ? $deptName : (string) ($rule['main_name'] ?? '')));
    if ($ck === '' || $ck === 'dept:') {
        return null;
    }
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $row = null;
    $deptId = $deptName !== '' ? resolveDepartmentIdByName($deptName) : 0;

    if ($code !== '') {
        $stmt = $conn->prepare(
            "SELECT e.*, d.department_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE UPPER(TRIM(e.employee_code)) = ?
               AND e.status = 1
             LIMIT 1"
        );
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Prefer department Main (linked / Jobwork CO) before loose name match — CNC Ravi etc.
    if (!$row && (!empty($rule['resolve_from_department']) || $code === '') && $deptName !== '') {
        $conn->close();
        $row = resolveMainContractorForDepartment($deptName);
        if ($row) {
            $cache[$ck] = $row;
            return $cache[$ck];
        }
        $conn = getDBConnection();
        ensureEmployeesTable($conn);
    }

    if (!$row) {
        $aliases = $rule['name_aliases'] ?? [];
        $aliases[] = (string) ($rule['main_name'] ?? '');
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '' || stripos($alias, 'Main Contractor') !== false) {
                continue;
            }
            $like = '%' . $alias . '%';
            if ($deptId > 0) {
                $stmt = $conn->prepare(
                    "SELECT e.*, d.department_name
                     FROM employees e
                     LEFT JOIN departments d ON d.id = e.department_id
                     WHERE e.status = 1
                       AND e.department_id = ?
                       AND (
                            e.pay_type IN ('Jobwork', 'ContractorMain')
                            OR UPPER(TRIM(e.employee_code)) LIKE 'CO%'
                       )
                       AND LOWER(e.employee_name) LIKE LOWER(?)
                     ORDER BY
                       CASE WHEN e.pay_type = 'ContractorMain' THEN 0
                            WHEN e.pay_type = 'Jobwork' THEN 1
                            ELSE 2 END,
                       e.id ASC
                     LIMIT 1"
                );
                $stmt->bind_param('is', $deptId, $like);
            } else {
                $stmt = $conn->prepare(
                    "SELECT e.*, d.department_name
                     FROM employees e
                     LEFT JOIN departments d ON d.id = e.department_id
                     WHERE e.status = 1
                       AND (
                            e.pay_type IN ('Jobwork', 'ContractorMain')
                            OR UPPER(TRIM(e.employee_code)) LIKE 'CO%'
                       )
                       AND LOWER(e.employee_name) LIKE LOWER(?)
                     ORDER BY
                       CASE WHEN e.pay_type = 'ContractorMain' THEN 0
                            WHEN e.pay_type = 'Jobwork' THEN 1
                            ELSE 2 END,
                       e.id ASC
                     LIMIT 1"
                );
                $stmt->bind_param('s', $like);
            }
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                break;
            }
        }
    }

    $conn->close();

    $cache[$ck] = $row ?: null;
    return $cache[$ck];
}

function resolveDepartmentIdByName($departmentName)
{
    $departmentName = trim((string) $departmentName);
    if ($departmentName === '') {
        return 0;
    }
    static $cache = [];
    $key = strtolower($departmentName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $conn = getDBConnection();
    if (function_exists('ensureMasterTables')) {
        require_once __DIR__ . '/master_helper.php';
        ensureMasterTables($conn);
    }
    $stmt = $conn->prepare(
        'SELECT id FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(?) LIMIT 1'
    );
    $stmt->bind_param('s', $departmentName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    $cache[$key] = (int) ($row['id'] ?? 0);
    return $cache[$key];
}

function contractorAttendanceSplitPoolAmount(array $main, $month, $year)
{
    $mainId = (int) ($main['id'] ?? 0);
    $month = (int) $month;
    $year = (int) $year;
    $jw = 0.0;
    if ($mainId > 0 && function_exists('getJobworkTotal')) {
        $jw = (float) getJobworkTotal($mainId, $month, $year);
    }
    $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
    $decided = (float) ($main['decided_salary'] ?? 0);
    if (function_exists('employeeDecidedSalaryAsOf')) {
        $decided = (float) employeeDecidedSalaryAsOf($mainId, $monthEnd, null, $decided);
    }
    $pool = $jw > 0 ? $jw : $decided;
    return round(max(0, $pool), 2);
}

/**
 * Link Salary + Jobwork staff in split department under the Main Contractor.
 */
function ensureSplitDeptEmployeesUnderMain(array $rule)
{
    static $done = [];
    $deptName = trim((string) ($rule['department_name'] ?? ''));
    $code = strtoupper(trim((string) ($rule['main_code'] ?? '')));
    $key = ($code !== '' ? $code : 'auto') . '|' . strtolower($deptName);
    if ($deptName === '' || isset($done[$key])) {
        return;
    }
    $done[$key] = true;

    $main = resolveContractorAttendanceSplitMain($rule);
    $deptId = resolveDepartmentIdByName($deptName);
    if (!$main || $deptId <= 0) {
        return;
    }
    $mainId = (int) $main['id'];
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare(
        "UPDATE employees
         SET main_contractor_id = ?
         WHERE department_id = ?
           AND id <> ?
           AND pay_type IN ('Salary', 'Jobwork')
           AND status = 1
           AND (main_contractor_id IS NULL OR main_contractor_id = 0 OR main_contractor_id <> ?)"
    );
    $stmt->bind_param('iiii', $mainId, $deptId, $mainId, $mainId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/** @deprecated use ensureSplitDeptEmployeesUnderMain */
function ensureMelting1EmployeesUnderMain(array $rule)
{
    ensureSplitDeptEmployeesUnderMain($rule);
}

/**
 * Month data: dept Normal nets funded from Main pool; remainder on Main.
 *
 * @return array<string, mixed>|null
 */
function getContractorAttendanceSplitMonthData($month, $year, array $rule)
{
    static $cache = [];
    $month = (int) $month;
    $year = (int) $year;
    $deptName = (string) ($rule['department_name'] ?? '');
    $code = strtoupper(trim((string) ($rule['main_code'] ?? '')));
    $ck = $month . '-' . $year . '|' . ($code !== '' ? $code : 'auto') . '|' . strtolower($deptName);
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    ensureSplitDeptEmployeesUnderMain($rule);

    $main = resolveContractorAttendanceSplitMain($rule);
    $deptId = resolveDepartmentIdByName($deptName);
    if (!$main || $deptId <= 0) {
        $cache[$ck] = null;
        return null;
    }

    $mainId = (int) $main['id'];
    $pool = contractorAttendanceSplitPoolAmount($main, $month, $year);
    $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
    $monthStart = sprintf('%04d-%02d-01', $year, $month);

    // Recipients: this department Normal Salary ONLY (Jobwork stay on Jobwork register)
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.department_id = ?
           AND e.pay_type = 'Salary'
           AND e.id <> ?
           AND (
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
           )
         ORDER BY e.employee_code ASC, e.employee_name ASC"
    );
    $stmt->bind_param('iiss', $deptId, $mainId, $monthStart, $monthEnd);
    $stmt->execute();
    $emps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // Normal net per employee (attendance) — DO NOT replace with pool dump
    $normalNetByEmp = [];
    $daysByEmp = [];
    $totalDays = 0.0;
    $distributed = 0.0;
    foreach ($emps as $emp) {
        $eid = (int) ($emp['id'] ?? 0);
        if ($eid <= 0 || (int) ($emp['department_id'] ?? 0) !== $deptId) {
            continue;
        }
        if (normalizePayType($emp['pay_type'] ?? '') !== 'Salary') {
            continue;
        }
        // Build Normal row without re-entering share apply (avoid recursion)
        $row = buildSalaryRegisterRowWithoutContractorSplit($emp, $month, $year, 'salary');
        $net = round((float) ($row['net'] ?? 0), 2);
        $days = (float) ($row['total_days'] ?? 0);
        if ($days < 0) {
            $days = 0;
        }
        $normalNetByEmp[$eid] = max(0, $net);
        $daysByEmp[$eid] = $days;
        $totalDays += $days;
        $distributed += max(0, $net);
    }
    $distributed = round($distributed, 2);

    // If Normal salaries exceed pool, scale down by attendance weight so pool covers them
    $shares = []; // funded amount per emp (for display); usually = normal net
    if ($pool > 0 && $distributed > $pool && $distributed > 0) {
        $assigned = 0.0;
        $ids = array_keys($normalNetByEmp);
        $n = count($ids);
        foreach ($ids as $i => $eid) {
            if ($i === $n - 1) {
                $shares[$eid] = round($pool - $assigned, 2);
            } else {
                $part = round($pool * ($normalNetByEmp[$eid] / $distributed), 2);
                $shares[$eid] = $part;
                $assigned += $part;
            }
        }
        $distributed = round(array_sum($shares), 2);
    } else {
        foreach ($normalNetByEmp as $eid => $net) {
            $shares[$eid] = $net; // funded = Normal net (attendance)
        }
    }

    $remainder = round(max(0, $pool - $distributed), 2);

    $cache[$ck] = [
        'main_id' => $mainId,
        'main_name' => (string) ($main['employee_name'] ?? ''),
        'main_code' => (string) ($main['employee_code'] ?? ''),
        'department_id' => $deptId,
        'department_name' => $deptName,
        'pool' => $pool,
        'total_days' => $totalDays,
        'shares' => $shares,
        'normal_nets' => $normalNetByEmp,
        'recipient_count' => count($normalNetByEmp),
        'distributed' => $distributed,
        'remainder' => $remainder,
        'mode' => 'normal_salary_fund',
    ];
    return $cache[$ck];
}

/**
 * Build salary row without contractor-split side effects (prevents recursion).
 */
function buildSalaryRegisterRowWithoutContractorSplit(array $emp, $month, $year, $mode)
{
    static $depth = 0;
    $depth++;
    $GLOBALS['__skip_contractor_split'] = true;
    try {
        if (!function_exists('buildSalaryRegisterRow')) {
            require_once __DIR__ . '/salary_register_helper.php';
        }
        return buildSalaryRegisterRow($emp, $month, $year, $mode);
    } finally {
        $depth--;
        if ($depth <= 0) {
            unset($GLOBALS['__skip_contractor_split']);
            $depth = 0;
        }
    }
}

function getContractorAttendanceShareForEmployee(array $emp, $month, $year)
{
    $deptId = (int) ($emp['department_id'] ?? 0);
    $eid = (int) ($emp['id'] ?? 0);
    if ($eid <= 0) {
        return null;
    }
    $pay = normalizePayType($emp['pay_type'] ?? '');

    foreach (contractorAttendanceSplitRules() as $rule) {
        $data = getContractorAttendanceSplitMonthData($month, $year, $rule);
        if (!$data) {
            continue;
        }
        // Main (Puran): always match by id — even if his department_id ≠ MELTING 1
        if ($eid === (int) $data['main_id']) {
            return [
                'share' => 0.0,
                'remainder' => (float) ($data['remainder'] ?? 0),
                'main_id' => (int) $data['main_id'],
                'main_name' => (string) $data['main_name'],
                'main_code' => (string) ($data['main_code'] ?? ''),
                'pool' => (float) $data['pool'],
                'distributed' => (float) ($data['distributed'] ?? 0),
                'is_main' => true,
                'split_department_id' => (int) $data['department_id'],
                'split_department_name' => (string) ($data['department_name'] ?? ''),
            ];
        }
        // Department Normal Salary only
        if ($deptId <= 0 || (int) $data['department_id'] !== $deptId) {
            continue;
        }
        if ($pay !== 'Salary') {
            return null;
        }
        if (!isset($data['shares'][$eid]) && !isset($data['normal_nets'][$eid])) {
            return null;
        }
        return [
            'share' => 0.0,
            'funded' => (float) ($data['shares'][$eid] ?? $data['normal_nets'][$eid] ?? 0),
            'remainder' => 0.0,
            'main_id' => (int) $data['main_id'],
            'main_name' => (string) $data['main_name'],
            'main_code' => (string) ($data['main_code'] ?? ''),
            'pool' => (float) $data['pool'],
            'is_main' => false,
            'split_department_id' => (int) $data['department_id'],
            'split_department_name' => (string) ($data['department_name'] ?? ''),
        ];
    }
    return null;
}

/**
 * Ensure Melting Main appears on Jobwork Salary Register for split dept / All departments.
 *
 * @param array<int, array<string, mixed>> $emps
 * @return array<int, array<string, mixed>>
 */
function ensureSplitMainInJobworkEmployeeList(array $emps, $deptId, $month, $year)
{
    $deptId = (int) $deptId;
    foreach (contractorAttendanceSplitRules() as $rule) {
        $splitDeptId = resolveDepartmentIdByName((string) ($rule['department_name'] ?? ''));
        // Only when viewing All depts or this split department
        if ($deptId > 0 && $splitDeptId > 0 && $deptId !== $splitDeptId) {
            continue;
        }
        $main = resolveContractorAttendanceSplitMain($rule);
        if (!$main) {
            continue;
        }
        $mainId = (int) $main['id'];
        $have = false;
        foreach ($emps as $e) {
            if ((int) ($e['id'] ?? 0) === $mainId) {
                $have = true;
                break;
            }
        }
        if ($have) {
            continue;
        }
        // Force split-dept label on Jobwork register (Melting 1 / 2 / CNC)
        if ($splitDeptId > 0) {
            $main['department_id'] = $splitDeptId;
            $main['department_name'] = (string) ($rule['department_name'] ?? '');
        }
        $emps[] = $main;
    }
    return $emps;
}

function applyContractorAttendanceShareToRegisterRow(array $row, $month, $year)
{
    if (!empty($GLOBALS['__skip_contractor_split'])) {
        return $row;
    }

    $eid = (int) ($row['employee_id'] ?? 0);
    $deptId = (int) ($row['department_id'] ?? 0);
    $pay = normalizePayType($row['pay_type'] ?? '');
    $skip = contractorAttendanceSplitMainIds();

    // Split Main: Jobwork register = remainder after Normal salaries (Melting / CNC)
    if ($eid > 0 && isset($skip[$eid])) {
        $info = getContractorAttendanceShareForEmployee([
            'id' => $eid,
            'department_id' => $deptId,
            'pay_type' => $pay ?: 'Jobwork',
        ], $month, $year);
        $remainder = (float) ($info['remainder'] ?? 0);
        $pool = (float) ($info['pool'] ?? 0);
        $distributed = (float) ($info['distributed'] ?? 0);
        $deptLabel = trim((string) ($info['split_department_name'] ?? ''));
        if ($deptLabel === '') {
            $deptLabel = 'DEPT';
        }
        $splitDeptId = (int) ($info['split_department_id'] ?? 0);
        $row['contractor_main_share'] = 0.0;
        $row['contractor_split_out'] = true;
        $row['contractor_remainder'] = $remainder;
        $row['contractor_pool'] = $pool;
        $row['contractor_distributed'] = $distributed;
        $row['department'] = $deptLabel;
        $row['department_id'] = $splitDeptId > 0 ? $splitDeptId : $deptId;
        $row['gross'] = $remainder;
        $row['net'] = $remainder;
        $row['actual'] = $remainder;
        $row['salary'] = $remainder;
        $row['qty'] = $remainder > 0 ? 1 : 0;
        $row['remarks'] = $deptLabel . ' after split · Pool ₹ ' . number_format($pool, 2)
            . ' − Normal ₹ ' . number_format($distributed, 2)
            . ' = Remainder ₹ ' . number_format($remainder, 2);
        return $row;
    }

    // Split Normal: keep Normal net; tag under Main (no Jobwork dump)
    if ($pay === 'Salary') {
        $info = getContractorAttendanceShareForEmployee([
            'id' => $eid,
            'department_id' => $deptId,
            'pay_type' => 'Salary',
        ], $month, $year);
        if ($info && empty($info['is_main'])) {
            $row['contractor_main_share'] = 0.0;
            $row['contractor_funded'] = (float) ($info['funded'] ?? 0);
            $label = trim(($info['main_code'] ?? '') . ' — ' . ($info['main_name'] ?? ''));
            $row['main_contractor'] = $label !== '' && $label !== '—' ? $label : (string) ($info['main_name'] ?? '');
            $row['main_contractor_id'] = (int) ($info['main_id'] ?? 0);
            // If pool was short and funded < net, scale net/gross to funded amount
            $funded = (float) ($info['funded'] ?? 0);
            $net = round((float) ($row['net'] ?? 0), 2);
            if ($funded > 0 && $net > 0 && $funded + 0.009 < $net) {
                $factor = $funded / $net;
                $row['gross'] = round((float) ($row['gross'] ?? 0) * $factor, 2);
                $row['net'] = $funded;
            }
        }
        return $row;
    }

    // Melting Jobwork (not main): no pool share — own Jobwork qty only
    return $row;
}
