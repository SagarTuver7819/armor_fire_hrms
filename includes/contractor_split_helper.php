<?php
/**
 * Hard-scoped: PURANSINH PUNITBHAI (CO72216) Jobwork/Contractor amount
 * → split across MELTING 1 Normal Salary employees by attendance.
 * Melting 2 / other departments / other contractors: untouched.
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';

/**
 * @return array<int, array{main_name: string, main_code: string, department_name: string, name_aliases: string[]}>
 */
function contractorAttendanceSplitRules()
{
    return [
        [
            // Live NEFT: PURANSINH PUNITBHAI · CO72216 · MELTING 1 · Jobwork ~8.4L
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
    ];
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
 * Resolve main contractor/jobwork person for a split rule (code first, then aliases).
 * Live person is Jobwork (CO…), not necessarily ContractorMain.
 */
function resolveContractorAttendanceSplitMain(array $rule)
{
    static $cache = [];
    $code = strtoupper(trim((string) ($rule['main_code'] ?? '')));
    $ck = $code !== '' ? $code : strtolower((string) ($rule['main_name'] ?? ''));
    if ($ck === '') {
        return null;
    }
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $row = null;

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

    if (!$row) {
        $aliases = $rule['name_aliases'] ?? [];
        $aliases[] = (string) ($rule['main_name'] ?? '');
        foreach ($aliases as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }
            $like = '%' . $alias . '%';
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

/**
 * Pool amount for the month = Jobwork ops total (live ~8.4L) else decided_salary.
 */
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
    // Live NEFT shows ops/jobwork amount — prefer that when present
    $pool = $jw > 0 ? $jw : $decided;
    return round(max(0, $pool), 2);
}

/**
 * @return array{
 *   main_id: int,
 *   main_name: string,
 *   main_code: string,
 *   department_id: int,
 *   pool: float,
 *   total_days: float,
 *   shares: array<int, float>
 * }|null
 */
function getContractorAttendanceSplitMonthData($month, $year, array $rule)
{
    static $cache = [];
    $month = (int) $month;
    $year = (int) $year;
    $deptName = (string) ($rule['department_name'] ?? '');
    $code = strtoupper(trim((string) ($rule['main_code'] ?? '')));
    $ck = $month . '-' . $year . '|' . $code . '|' . strtolower($deptName);
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

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

    if ($pool <= 0) {
        $cache[$ck] = [
            'main_id' => $mainId,
            'main_name' => (string) ($main['employee_name'] ?? ''),
            'main_code' => (string) ($main['employee_code'] ?? ''),
            'department_id' => $deptId,
            'pool' => 0.0,
            'total_days' => 0.0,
            'shares' => [],
        ];
        return $cache[$ck];
    }

    // Recipients: MELTING 1 Normal + Jobwork (never Melting 2; never main himself)
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.department_id = ?
           AND e.pay_type IN ('Salary', 'Jobwork')
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
         ORDER BY e.pay_type ASC, e.employee_code ASC, e.employee_name ASC"
    );
    $stmt->bind_param('iiss', $deptId, $mainId, $monthStart, $monthEnd);
    $stmt->execute();
    $emps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    $daysByEmp = [];
    $totalDays = 0.0;
    foreach ($emps as $emp) {
        $eid = (int) ($emp['id'] ?? 0);
        if ($eid <= 0 || (int) ($emp['department_id'] ?? 0) !== $deptId) {
            continue;
        }
        $pay = normalizePayType($emp['pay_type'] ?? '');
        if ($pay !== 'Salary' && $pay !== 'Jobwork') {
            continue;
        }
        $base = (float) ($emp['decided_salary'] ?? 0);
        if ($pay === 'Salary' && function_exists('employeeDecidedSalaryAsOf')) {
            $base = (float) employeeDecidedSalaryAsOf($eid, $monthEnd, null, $base);
        }
        if ($pay === 'Jobwork' && function_exists('getJobworkTotal')) {
            $jw = (float) getJobworkTotal($eid, $month, $year);
            if ($jw > 0) {
                $base = $jw;
            }
        }
        $att = getPayrollAttendanceBundle($emp, $month, $year, $base > 0 ? $base : 1);
        $days = (float) ($att['total_days'] ?? 0);
        // Jobwork: if no attendance marks, use ops worked days
        if ($days <= 0 && $pay === 'Jobwork' && function_exists('getJobworkWorkedDays')) {
            $days = (float) getJobworkWorkedDays($eid, $month, $year);
        }
        if ($days < 0) {
            $days = 0;
        }
        // Min weight 1 so EVERY Melting 1 Normal+Jobwork gets a share (more days = larger share)
        $weight = $days > 0 ? $days : 1.0;
        $daysByEmp[$eid] = $weight;
        $totalDays += $weight;
    }

    $shares = [];
    $ids = array_keys($daysByEmp);
    $n = count($ids);
    $distributed = 0.0;
    if ($n > 0 && $totalDays > 0) {
        $assigned = 0.0;
        $last = $n - 1;
        foreach ($ids as $i => $eid) {
            if ($i === $last) {
                $shares[$eid] = round($pool - $assigned, 2);
            } else {
                $part = round($pool * ($daysByEmp[$eid] / $totalDays), 2);
                $shares[$eid] = $part;
                $assigned += $part;
            }
            if ($shares[$eid] < 0) {
                $shares[$eid] = 0.0;
            }
        }
        $distributed = round(array_sum($shares), 2);
    }

    // Remainder after attendance split (usually 0; if somehow short, keep on main)
    $remainder = round(max(0, $pool - $distributed), 2);

    $cache[$ck] = [
        'main_id' => $mainId,
        'main_name' => (string) ($main['employee_name'] ?? ''),
        'main_code' => (string) ($main['employee_code'] ?? ''),
        'department_id' => $deptId,
        'pool' => $pool,
        'total_days' => $totalDays,
        'shares' => $shares,
        'recipient_count' => $n,
        'distributed' => $distributed,
        'remainder' => $remainder,
    ];
    return $cache[$ck];
}

function getContractorAttendanceShareForEmployee(array $emp, $month, $year)
{
    $deptId = (int) ($emp['department_id'] ?? 0);
    $eid = (int) ($emp['id'] ?? 0);
    if ($deptId <= 0 || $eid <= 0) {
        return null;
    }
    $pay = normalizePayType($emp['pay_type'] ?? '');
    if ($pay !== 'Salary' && $pay !== 'Jobwork') {
        return null;
    }

    foreach (contractorAttendanceSplitRules() as $rule) {
        $data = getContractorAttendanceSplitMonthData($month, $year, $rule);
        if (!$data || (int) $data['department_id'] !== $deptId) {
            continue;
        }
        if ($eid === (int) $data['main_id']) {
            return [
                'share' => 0.0,
                'remainder' => (float) ($data['remainder'] ?? 0),
                'main_id' => (int) $data['main_id'],
                'main_name' => (string) $data['main_name'],
                'main_code' => (string) ($data['main_code'] ?? ''),
                'pool' => (float) $data['pool'],
                'is_main' => true,
            ];
        }
        $share = (float) ($data['shares'][$eid] ?? 0);
        return [
            'share' => round($share, 2),
            'remainder' => 0.0,
            'main_id' => (int) $data['main_id'],
            'main_name' => (string) $data['main_name'],
            'main_code' => (string) ($data['main_code'] ?? ''),
            'pool' => (float) $data['pool'],
            'is_main' => false,
        ];
    }
    return null;
}

function applyContractorAttendanceShareToRegisterRow(array $row, $month, $year)
{
    $eid = (int) ($row['employee_id'] ?? 0);
    $deptId = (int) ($row['department_id'] ?? 0);
    $pay = normalizePayType($row['pay_type'] ?? '');
    $skip = contractorAttendanceSplitMainIds();

    // Main (PURANSINH / CO72216): show only remainder after Melting 1 split
    if ($eid > 0 && isset($skip[$eid])) {
        $info = getContractorAttendanceShareForEmployee([
            'id' => $eid,
            'department_id' => $deptId,
            'pay_type' => $pay ?: 'Jobwork',
        ], $month, $year);
        $remainder = (float) ($info['remainder'] ?? 0);
        $row['contractor_main_share'] = 0.0;
        $row['contractor_split_out'] = true;
        $row['contractor_remainder'] = $remainder;
        $row['gross'] = $remainder;
        $row['net'] = $remainder;
        $row['actual'] = $remainder;
        $row['salary'] = $remainder;
        $row['remarks'] = $remainder > 0
            ? 'Remainder after Melting 1 attendance split'
            : 'Fully split to Melting 1 (Normal + Jobwork)';
        return $row;
    }

    if ($pay !== 'Salary' && $pay !== 'Jobwork') {
        return $row;
    }

    $emp = [
        'id' => $eid,
        'department_id' => $deptId,
        'pay_type' => $pay,
    ];
    $info = getContractorAttendanceShareForEmployee($emp, $month, $year);
    if (!$info || (float) $info['share'] <= 0) {
        $row['contractor_main_share'] = 0.0;
        return $row;
    }
    $share = (float) $info['share'];
    $row['contractor_main_share'] = $share;
    $row['gross'] = round((float) ($row['gross'] ?? 0) + $share, 2);
    $row['net'] = round((float) ($row['net'] ?? 0) + $share, 2);
    if ($row['net'] < 0) {
        $row['net'] = 0.0;
    }
    $label = trim(($info['main_code'] ?? '') . ' — ' . ($info['main_name'] ?? ''));
    $row['main_contractor'] = $label !== '—' && $label !== '' ? $label : (string) $info['main_name'];
    $row['main_contractor_id'] = (int) $info['main_id'];
    return $row;
}
