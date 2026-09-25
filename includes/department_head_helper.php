<?php
/**
 * Department Heads (1 employee per department) + seed standard roles.
 */

require_once __DIR__ . '/permission_modules.php';

function ensureDepartmentHeadTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    if (!function_exists('ensureRoleTables')) {
        require_once __DIR__ . '/permission_helper.php';
    }
    ensureRoleTables($conn);

    $conn->query(
        "CREATE TABLE IF NOT EXISTS department_heads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            employee_id INT NOT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            set_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dh_department (department_id),
            INDEX idx_dh_employee (employee_id),
            INDEX idx_dh_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    seedStandardRoles($conn);

    if ($closeAfter) {
        $conn->close();
    }
}

/**
 * Seed HR_HEAD, PAYROLL_HEAD, DEPT_HEAD, OFFICE_STAFF with default All-dept matrices.
 */
function seedStandardRoles($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureRoleTables($conn);

    $defs = [
        'HR_HEAD' => [
            'name' => 'HR Head',
            'description' => 'HR operations · All departments · Leave view & approve',
            'perms' => [
                'employees' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
                'circulars' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
                'policies' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
                'attendance' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0],
                'leave' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0],
                'payroll' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'masters' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0],
                'contractor' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'departments' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
            ],
        ],
        'PAYROLL_HEAD' => [
            'name' => 'Payroll Head',
            'description' => 'Payroll full · Leave approve for all departments',
            'perms' => [
                'employees' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'circulars' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'policies' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'attendance' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'leave' => ['view' => 1, 'add' => 0, 'edit' => 1, 'delete' => 0],
                'payroll' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1],
                'masters' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'contractor' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'departments' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
            ],
        ],
        'DEPT_HEAD' => [
            'name' => 'Department Head',
            'description' => 'Department Head · Scoped to headed department only',
            'perms' => [
                'employees' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0],
                'circulars' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'policies' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'attendance' => ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0],
                'leave' => ['view' => 1, 'add' => 1, 'edit' => 0, 'delete' => 0],
                'payroll' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'masters' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'contractor' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'departments' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
            ],
        ],
        'OFFICE_STAFF' => [
            'name' => 'Office Staff',
            'description' => 'Employee portal · Self leave apply · Circulars/Policies view',
            'perms' => [
                'employees' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'circulars' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'policies' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'attendance' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'leave' => ['view' => 1, 'add' => 1, 'edit' => 0, 'delete' => 0],
                'payroll' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'masters' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'contractor' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
                'departments' => ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0],
            ],
        ],
    ];

    foreach ($defs as $code => $def) {
        $stmt = $conn->prepare('SELECT id FROM roles WHERE code = ? LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $roleId = (int) $existing['id'];
        } else {
            $name = $def['name'];
            $desc = $def['description'];
            $status = 1;
            $ins = $conn->prepare(
                'INSERT INTO roles (name, code, description, status) VALUES (?, ?, ?, ?)'
            );
            $ins->bind_param('sssi', $name, $code, $desc, $status);
            $ins->execute();
            $roleId = (int) $ins->insert_id;
            $ins->close();
        }

        if ($roleId <= 0) {
            continue;
        }

        // Seed matrix only if this role has no permission rows yet
        $chk = $conn->prepare('SELECT COUNT(*) AS c FROM role_permissions WHERE role_id = ?');
        $chk->bind_param('i', $roleId);
        $chk->execute();
        $c = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
        $chk->close();
        if ($c > 0) {
            continue;
        }

        $insP = $conn->prepare(
            'INSERT INTO role_permissions
                (role_id, module_key, department_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, 0, ?, ?, ?, ?)'
        );
        foreach ($def['perms'] as $modKey => $flags) {
            $v = (int) ($flags['view'] ?? 0);
            $a = (int) ($flags['add'] ?? 0);
            $e = (int) ($flags['edit'] ?? 0);
            $d = (int) ($flags['delete'] ?? 0);
            if ($v + $a + $e + $d === 0) {
                continue;
            }
            $insP->bind_param('isiiii', $roleId, $modKey, $v, $a, $e, $d);
            $insP->execute();
        }
        $insP->close();
    }

    if ($closeAfter) {
        $conn->close();
    }
}

function fetchDepartmentHeadsList()
{
    ensureDepartmentHeadTables();
    $conn = getDBConnection();
    $sql = "SELECT d.id AS department_id, d.department_name, d.icon_color,
                   dh.id AS head_row_id, dh.employee_id, dh.status AS head_status,
                   e.employee_code, e.employee_name,
                   u.id AS portal_user_id, u.username AS portal_username, r.code AS role_code, r.name AS role_name
            FROM departments d
            LEFT JOIN department_heads dh ON dh.department_id = d.id AND dh.status = 1
            LEFT JOIN employees e ON e.id = dh.employee_id
            LEFT JOIN users u ON u.employee_id = e.id AND u.role = 'employee' AND u.status = 1
            LEFT JOIN roles r ON r.id = u.custom_role_id
            WHERE d.status = 1
            ORDER BY d.sort_order ASC, d.department_name ASC";
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $conn->close();
    return $rows;
}

function getDepartmentHead($departmentId)
{
    $departmentId = (int) $departmentId;
    if ($departmentId <= 0) {
        return null;
    }
    ensureDepartmentHeadTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT dh.*, e.employee_code, e.employee_name, e.department_id AS emp_department_id
         FROM department_heads dh
         INNER JOIN employees e ON e.id = dh.employee_id
         WHERE dh.department_id = ? AND dh.status = 1
         LIMIT 1"
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

/**
 * @return int[]
 */
function getHeadedDepartmentIds($employeeId)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return [];
    }
    ensureDepartmentHeadTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'SELECT department_id FROM department_heads WHERE employee_id = ? AND status = 1'
    );
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['department_id'];
    }
    $stmt->close();
    $conn->close();
    return $ids;
}

function setDepartmentHead($departmentId, $employeeId, $setBy = 0)
{
    $departmentId = (int) $departmentId;
    $employeeId = (int) $employeeId;
    $setBy = (int) $setBy;
    if ($departmentId <= 0) {
        return ['ok' => false, 'error' => 'Invalid department.'];
    }
    if ($employeeId <= 0) {
        return ['ok' => false, 'error' => 'Select an employee.'];
    }
    if (!function_exists('getEmployeeById')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $emp = getEmployeeById($employeeId);
    if (!$emp || (int) ($emp['status'] ?? 0) !== 1) {
        return ['ok' => false, 'error' => 'Employee not found or inactive.'];
    }
    if ((int) ($emp['department_id'] ?? 0) !== $departmentId) {
        return ['ok' => false, 'error' => 'Employee must belong to this department.'];
    }

    ensureDepartmentHeadTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'INSERT INTO department_heads (department_id, employee_id, status, set_by)
         VALUES (?, ?, 1, NULLIF(?, 0))
         ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), status = 1, set_by = VALUES(set_by)'
    );
    $stmt->bind_param('iii', $departmentId, $employeeId, $setBy);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not set department head.'];
    }
    return ['ok' => true];
}

function clearDepartmentHead($departmentId)
{
    $departmentId = (int) $departmentId;
    if ($departmentId <= 0) {
        return ['ok' => false, 'error' => 'Invalid department.'];
    }
    ensureDepartmentHeadTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare('UPDATE department_heads SET status = 0 WHERE department_id = ?');
    $stmt->bind_param('i', $departmentId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not clear head.'];
}

function getRoleCodeById($roleId)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return '';
    }
    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT code FROM roles WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return strtoupper(trim((string) ($row['code'] ?? '')));
}

function getRoleIdByCode($code)
{
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return 0;
    }
    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id FROM roles WHERE code = ? AND status = 1 LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return (int) ($row['id'] ?? 0);
}

/**
 * Load headed department ids into session for current employee portal user.
 */
function refreshHeadedDepartmentsSession()
{
    $_SESSION['headed_department_ids'] = [];
    $_SESSION['role_code'] = '';
    $roleId = (int) ($_SESSION['custom_role_id'] ?? 0);
    if ($roleId > 0) {
        $_SESSION['role_code'] = getRoleCodeById($roleId);
    }
    $empId = (int) ($_SESSION['employee_id'] ?? 0);
    if ($empId > 0) {
        $_SESSION['headed_department_ids'] = getHeadedDepartmentIds($empId);
    }
}

function isDeptHeadRole()
{
    return strtoupper((string) ($_SESSION['role_code'] ?? '')) === 'DEPT_HEAD';
}

function isOfficeStaffRole()
{
    return strtoupper((string) ($_SESSION['role_code'] ?? '')) === 'OFFICE_STAFF';
}

function isHrHeadRole()
{
    return strtoupper((string) ($_SESSION['role_code'] ?? '')) === 'HR_HEAD';
}

/**
 * Admin, legacy HR login, or HR Head custom role may manage department heads.
 */
function canManageDepartmentHeads()
{
    if (!function_exists('isAdmin')) {
        require_once __DIR__ . '/auth.php';
    }
    return isAdmin() || (function_exists('isHR') && isHR()) || isHrHeadRole();
}

function requireDepartmentHeadsManager()
{
    if (!function_exists('requireLogin')) {
        require_once __DIR__ . '/auth.php';
    }
    requireLogin();
    if (canManageDepartmentHeads()) {
        return;
    }
    require_once __DIR__ . '/../config/app.php';
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

/**
 * Employee Matrix rows — department-wise heads + assigned Head roles (HR / Payroll / Dept).
 *
 * @return array<int,array<string,mixed>>
 */
function fetchEmployeeMatrixHeads()
{
    ensureDepartmentHeadTables();
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    if (!function_exists('ensureRoleTables')) {
        require_once __DIR__ . '/permission_helper.php';
    }
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    ensureRoleTables($conn);

    $byEmp = [];

    // 1) Explicit department heads (one per department)
    $sqlDh = "SELECT d.id AS department_id, d.department_name, d.sort_order,
                     e.id AS employee_id, e.employee_code, e.employee_name, e.designation,
                     e.desk_no, e.office_mobile, e.office_email, e.photo_file,
                     'DEPT_HEAD' AS head_role_code, 'Department Head' AS head_role_label
              FROM department_heads dh
              INNER JOIN departments d ON d.id = dh.department_id AND d.status = 1
              INNER JOIN employees e ON e.id = dh.employee_id AND e.status = 1
              WHERE dh.status = 1
              ORDER BY d.sort_order ASC, d.department_name ASC";
    $res = $conn->query($sqlDh);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $byEmp[$eid] = $row;
        }
    }

    // 2) Portal logins with Head roles (HR / Payroll / Dept Head)
    $sqlRole = "SELECT e.id AS employee_id, e.employee_code, e.employee_name, e.designation,
                       e.desk_no, e.office_mobile, e.office_email, e.photo_file,
                       e.department_id, d.department_name, d.sort_order,
                       r.code AS head_role_code, r.name AS head_role_label
                FROM users u
                INNER JOIN employees e ON e.id = u.employee_id AND e.status = 1
                INNER JOIN roles r ON r.id = u.custom_role_id AND r.status = 1
                LEFT JOIN departments d ON d.id = e.department_id AND d.status = 1
                WHERE u.role = 'employee' AND u.status = 1
                  AND UPPER(TRIM(r.code)) IN ('HR_HEAD', 'PAYROLL_HEAD', 'DEPT_HEAD')
                ORDER BY
                    CASE UPPER(TRIM(r.code))
                        WHEN 'HR_HEAD' THEN 1
                        WHEN 'PAYROLL_HEAD' THEN 2
                        ELSE 3
                    END,
                    d.sort_order ASC,
                    e.employee_name ASC";
    $res2 = $conn->query($sqlRole);
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $eid = (int) ($row['employee_id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['head_role_code'] ?? '')));
            $labelMap = [
                'HR_HEAD' => 'HR Head',
                'PAYROLL_HEAD' => 'Payroll Head',
                'DEPT_HEAD' => 'Department Head',
            ];
            $row['head_role_label'] = $labelMap[$code] ?? trim((string) ($row['head_role_label'] ?? 'Head'));
            $row['head_role_code'] = $code;

            if (isset($byEmp[$eid])) {
                // Keep department_heads department; upgrade label if HR/Payroll
                if ($code === 'HR_HEAD' || $code === 'PAYROLL_HEAD') {
                    $byEmp[$eid]['head_role_code'] = $code;
                    $byEmp[$eid]['head_role_label'] = $row['head_role_label'];
                }
                continue;
            }
            $byEmp[$eid] = $row;
        }
    }

    $conn->close();

    $rows = array_values($byEmp);
    usort($rows, static function ($a, $b) {
        $rank = static function ($code) {
            $code = strtoupper((string) $code);
            if ($code === 'HR_HEAD') {
                return 1;
            }
            if ($code === 'PAYROLL_HEAD') {
                return 2;
            }
            return 3;
        };
        $ra = $rank($a['head_role_code'] ?? '');
        $rb = $rank($b['head_role_code'] ?? '');
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        $sa = (int) ($a['sort_order'] ?? 999);
        $sb = (int) ($b['sort_order'] ?? 999);
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return strcasecmp((string) ($a['employee_name'] ?? ''), (string) ($b['employee_name'] ?? ''));
    });

    return $rows;
}

/**
 * Employees for a department (active) — for head picker.
 */
function fetchEmployeesByDepartment($departmentId)
{
    $departmentId = (int) $departmentId;
    if ($departmentId <= 0) {
        return [];
    }
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    ensureEmployeesTable();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT id, employee_code, employee_name
         FROM employees
         WHERE department_id = ? AND status = 1
           AND (date_of_exit IS NULL OR date_of_exit = '' OR date_of_exit = '0000-00-00' OR date_of_exit > CURDATE())
         ORDER BY employee_name ASC"
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

/**
 * Default employee portal password (shared test password as requested).
 */
function employeePortalDefaultPassword()
{
    return 'name@123';
}

/**
 * Bulk create / refresh Office Staff logins for all active employees.
 * Username = employee_code, Password = name@123
 *
 * @return array{ok:bool,created:int,updated:int,skipped:int,errors:string[],samples:array}
 */
function bulkProvisionOfficeStaffLogins($resetPassword = true)
{
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    if (!function_exists('assignEmployeePortalLogin')) {
        require_once __DIR__ . '/permission_helper.php';
    }
    ensureEmployeesTable();
    ensureDepartmentHeadTables();

    $roleId = getRoleIdByCode('OFFICE_STAFF');
    if ($roleId <= 0) {
        return [
            'ok' => false,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => ['Office Staff role missing. Run DB Sync.'],
            'samples' => [],
        ];
    }

    $password = employeePortalDefaultPassword();
    $conn = getDBConnection();
    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.department_id, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 1
              AND (e.date_of_exit IS NULL OR e.date_of_exit = '' OR e.date_of_exit = '0000-00-00' OR e.date_of_exit > CURDATE())
              AND e.employee_code IS NOT NULL AND TRIM(e.employee_code) <> ''
            ORDER BY e.employee_code ASC";
    $res = $conn->query($sql);
    $conn->close();

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $samples = [];

    if (!$res) {
        return [
            'ok' => false,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => ['Could not load employees.'],
            'samples' => [],
        ];
    }

    while ($emp = $res->fetch_assoc()) {
        $code = trim((string) ($emp['employee_code'] ?? ''));
        if ($code === '') {
            $skipped++;
            continue;
        }
        // Skip if already a non-office portal user (e.g. Dept Head / Payroll) — only update Office Staff or create new
        $conn2 = getDBConnection();
        $stmt = $conn2->prepare(
            "SELECT u.id, u.custom_role_id, r.code AS role_code
             FROM users u
             LEFT JOIN roles r ON r.id = u.custom_role_id
             WHERE u.employee_id = ? AND u.role = 'employee'
             LIMIT 1"
        );
        $eid = (int) $emp['id'];
        $stmt->bind_param('i', $eid);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn2->close();

        if ($existing) {
            $exCode = strtoupper(trim((string) ($existing['role_code'] ?? '')));
            if ($exCode !== '' && $exCode !== 'OFFICE_STAFF') {
                $skipped++;
                continue; // keep Dept Head / Payroll / HR Head assignments
            }
            $result = assignEmployeePortalLogin([
                'user_id' => (int) $existing['id'],
                'employee_id' => $eid,
                'custom_role_id' => $roleId,
                'username' => $code,
                'password' => $resetPassword ? $password : '',
                'status' => 1,
            ]);
            if ($result['ok']) {
                $updated++;
            } else {
                $errors[] = $code . ': ' . ($result['error'] ?? 'update failed');
            }
        } else {
            $result = assignEmployeePortalLogin([
                'user_id' => 0,
                'employee_id' => $eid,
                'custom_role_id' => $roleId,
                'username' => $code,
                'password' => $password,
                'status' => 1,
            ]);
            if ($result['ok']) {
                $created++;
                if (count($samples) < 8) {
                    $samples[] = [
                        'code' => $code,
                        'name' => (string) ($emp['employee_name'] ?? ''),
                        'department' => (string) ($emp['department_name'] ?? ''),
                        'username' => $code,
                        'password' => $password,
                    ];
                }
            } else {
                $errors[] = $code . ': ' . ($result['error'] ?? 'create failed');
            }
        }
    }

    return [
        'ok' => $errors === [],
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'samples' => $samples,
        'password' => $password,
    ];
}
