<?php
/**
 * Hard-scoped Contractor Main salary → department Normal staff (attendance split).
 * ONLY rules listed in contractorAttendanceSplitRules() run — Melting 2 / others untouched.
 */

require_once __DIR__ . '/payroll_helper.php';
require_once __DIR__ . '/employee_helper.php';

/**
 * @return array<int, array{main_name: string, department_name: string}>
 */
function contractorAttendanceSplitRules()
{
    return [
        [
            'main_name' => 'Puran Singh',
            'department_name' => 'MELTING 1',
        ],
    ];
}

/**
 * Employee IDs of Contractor Mains used in attendance-split rules (exclude from NEFT).
 *
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
        $main = resolveContractorAttendanceSplitMain($rule['main_name'] ?? '');
        if ($main) {
            $ids[(int) $main['id']] = true;
        }
    }
    return $ids;
}

function resolveContractorAttendanceSplitMain($mainName)
{
    $mainName = trim((string) $mainName);
    if ($mainName === '') {
        return null;
    }
    static $cache = [];
    $key = strtolower($mainName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.pay_type = 'ContractorMain'
           AND e.status = 1
           AND LOWER(TRIM(e.employee_name)) = LOWER(?)
         LIMIT 1"
    );
    $stmt->bind_param('s', $mainName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        // Partial name match (e.g. "Puran Singh" vs "PURAN SINGH JI")
        $like = '%' . $mainName . '%';
        $stmt = $conn->prepare(
            "SELECT e.*, d.department_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.pay_type = 'ContractorMain'
               AND e.status = 1
               AND LOWER(e.employee_name) LIKE LOWER(?)
             ORDER BY e.id ASC
             LIMIT 1"
        );
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    $conn->close();
    $cache[$key] = $row ?: null;
    return $cache[$key];
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
 * Month cache: shares for Melting 1 Normal employees under a split rule.
 *
 * @return array{
 *   main_id: int,
 *   main_name: string,
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
    $mainName = (string) ($rule['main_name'] ?? '');
    $ck = $month . '-' . $year . '|' . strtolower($mainName) . '|' . strtolower($deptName);
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    $main = resolveContractorAttendanceSplitMain($mainName);
    $deptId = resolveDepartmentIdByName($deptName);
    if (!$main || $deptId <= 0) {
        $cache[$ck] = null;
        return null;
    }

    $mainId = (int) $main['id'];
    $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
    $pool = (float) ($main['decided_salary'] ?? 0);
    if (function_exists('employeeDecidedSalaryAsOf')) {
        $pool = (float) employeeDecidedSalaryAsOf($mainId, $monthEnd, null, $pool);
    }
    $pool = round(max(0, $pool), 2);
    if ($pool <= 0) {
        $cache[$ck] = [
            'main_id' => $mainId,
            'main_name' => (string) ($main['employee_name'] ?? $mainName),
            'department_id' => $deptId,
            'pool' => 0.0,
            'total_days' => 0.0,
            'shares' => [],
        ];
        return $cache[$ck];
    }

    // Recipients: ONLY this department_id + Normal Salary (Melting 2 never queried)
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.department_id = ?
           AND e.pay_type = 'Salary'
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
    $stmt->bind_param('iss', $deptId, $monthStart, $monthEnd);
    $stmt->execute();
    $emps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    $daysByEmp = [];
    $totalDays = 0.0;
    foreach ($emps as $emp) {
        $eid = (int) ($emp['id'] ?? 0);
        if ($eid <= 0 || $eid === $mainId) {
            continue;
        }
        // Hard guard — never leak other departments
        if ((int) ($emp['department_id'] ?? 0) !== $deptId) {
            continue;
        }
        if (normalizePayType($emp['pay_type'] ?? '') !== 'Salary') {
            continue;
        }
        $base = (float) ($emp['decided_salary'] ?? 0);
        if (function_exists('employeeDecidedSalaryAsOf')) {
            $base = (float) employeeDecidedSalaryAsOf($eid, $monthEnd, null, $base);
        }
        $att = getPayrollAttendanceBundle($emp, $month, $year, $base);
        $days = (float) ($att['total_days'] ?? 0);
        if ($days < 0) {
            $days = 0;
        }
        $daysByEmp[$eid] = $days;
        $totalDays += $days;
    }

    $shares = [];
    $ids = array_keys($daysByEmp);
    $n = count($ids);
    if ($n > 0 && $totalDays > 0) {
        // Attendance-weighted split (Melting 1 only)
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
    } elseif ($n > 0 && $pool > 0) {
        // No attendance marked yet — equal split so pool still reaches Melting 1 NEFT
        $assigned = 0.0;
        $last = $n - 1;
        $each = round($pool / $n, 2);
        foreach ($ids as $i => $eid) {
            if ($i === $last) {
                $shares[$eid] = round($pool - $assigned, 2);
            } else {
                $shares[$eid] = $each;
                $assigned += $each;
            }
        }
    }

    $cache[$ck] = [
        'main_id' => $mainId,
        'main_name' => (string) ($main['employee_name'] ?? $mainName),
        'main_code' => (string) ($main['employee_code'] ?? ''),
        'department_id' => $deptId,
        'pool' => $pool,
        'total_days' => $totalDays,
        'shares' => $shares,
    ];
    return $cache[$ck];
}

/**
 * Share for one Normal employee this month (0 if not Melting 1 / not in rule).
 *
 * @return array{share: float, main_id: int, main_name: string, pool: float}|null
 */
function getContractorAttendanceShareForEmployee(array $emp, $month, $year)
{
    $deptId = (int) ($emp['department_id'] ?? 0);
    $eid = (int) ($emp['id'] ?? 0);
    if ($deptId <= 0 || $eid <= 0) {
        return null;
    }
    if (normalizePayType($emp['pay_type'] ?? '') !== 'Salary') {
        return null;
    }

    foreach (contractorAttendanceSplitRules() as $rule) {
        $data = getContractorAttendanceSplitMonthData($month, $year, $rule);
        if (!$data || (int) $data['department_id'] !== $deptId) {
            continue;
        }
        if ($eid === (int) $data['main_id']) {
            return null;
        }
        $share = (float) ($data['shares'][$eid] ?? 0);
        return [
            'share' => round($share, 2),
            'main_id' => (int) $data['main_id'],
            'main_name' => (string) $data['main_name'],
            'main_code' => (string) ($data['main_code'] ?? ''),
            'pool' => (float) $data['pool'],
        ];
    }
    return null;
}

/**
 * Apply Melting-1-only share onto a Normal salary register row.
 */
function applyContractorAttendanceShareToRegisterRow(array $row, $month, $year)
{
    if (($row['pay_type'] ?? '') !== 'Salary' && normalizePayType($row['pay_type'] ?? '') !== 'Salary') {
        return $row;
    }
    // Reconstruct minimal emp for lookup
    $emp = [
        'id' => (int) ($row['employee_id'] ?? 0),
        'department_id' => (int) ($row['department_id'] ?? 0),
        'pay_type' => 'Salary',
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
    $row['main_contractor'] = $label !== '—' ? $label : (string) $info['main_name'];
    $row['main_contractor_id'] = (int) $info['main_id'];
    return $row;
}
