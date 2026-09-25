<?php
/**
 * Custom Roles & department-wise module permissions (Admin configures).
 */

require_once __DIR__ . '/permission_modules.php';
require_once __DIR__ . '/text_case.php';

function ensureRoleTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(50) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_roles_name (name),
            INDEX idx_roles_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            module_key VARCHAR(50) NOT NULL,
            department_id INT NOT NULL DEFAULT 0 COMMENT '0 = All Departments',
            can_view TINYINT(1) NOT NULL DEFAULT 0,
            can_add TINYINT(1) NOT NULL DEFAULT 0,
            can_edit TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_role_mod_dept (role_id, module_key, department_id),
            INDEX idx_rp_role (role_id),
            INDEX idx_rp_module (module_key),
            INDEX idx_rp_dept (department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'custom_role_id'");
    if ($col && $col->num_rows === 0) {
        $conn->query(
            "ALTER TABLE users
             ADD COLUMN custom_role_id INT NULL DEFAULT NULL AFTER department_id,
             ADD INDEX idx_users_custom_role (custom_role_id)"
        );
    }

    $colEmp = $conn->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($colEmp && $colEmp->num_rows === 0) {
        $conn->query(
            "ALTER TABLE users
             ADD COLUMN employee_id INT NULL DEFAULT NULL AFTER custom_role_id,
             ADD INDEX idx_users_employee (employee_id)"
        );
    }

    if ($closeAfter) {
        $conn->close();
    }
}

/* ── Roles CRUD helpers ── */

function fetchRoles($activeOnly = false)
{
    ensureRoleTables();
    $conn = getDBConnection();
    $sql = 'SELECT r.*,
                   (SELECT COUNT(*) FROM users u WHERE u.custom_role_id = r.id) AS user_count
            FROM roles r';
    if ($activeOnly) {
        $sql .= ' WHERE r.status = 1';
    }
    $sql .= ' ORDER BY r.name ASC';
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

function getRoleById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM roles WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function saveRole(array $data, $id = 0)
{
    ensureRoleTables();
    $id = (int) $id;
    $name = trim((string) ($data['name'] ?? ''));
    $code = trim((string) ($data['code'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;

    if ($name === '') {
        return ['ok' => false, 'error' => 'Role name is required.'];
    }

    $conn = getDBConnection();

    if ($id > 0) {
        $chk = $conn->prepare('SELECT id FROM roles WHERE name = ? AND id <> ? LIMIT 1');
        $chk->bind_param('si', $name, $id);
    } else {
        $chk = $conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $chk->bind_param('s', $name);
    }
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        $conn->close();
        return ['ok' => false, 'error' => 'Role name already exists.'];
    }
    $chk->close();

    if ($id > 0) {
        $stmt = $conn->prepare(
            'UPDATE roles SET name = ?, code = ?, description = ?, status = ? WHERE id = ?'
        );
        $stmt->bind_param('sssii', $name, $code, $description, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update role.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    $stmt = $conn->prepare(
        'INSERT INTO roles (name, code, description, status) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('sssi', $name, $code, $description, $status);
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    $conn->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not create role.'];
    }
    return ['ok' => true, 'id' => $newId];
}

function deleteRole($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    ensureRoleTables();
    $conn = getDBConnection();

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM users WHERE custom_role_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    if ($c > 0) {
        $conn->close();
        return ['ok' => false, 'error' => 'Role is assigned to ' . $c . ' user(s). Reassign first.'];
    }

    $stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM roles WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not delete role.'];
}

/**
 * Load permission rows for role + department scope into module => flags map.
 *
 * @return array<string, array{view:int,add:int,edit:int,delete:int}>
 */
function getRolePermissionMatrix($roleId, $departmentId = 0)
{
    $roleId = (int) $roleId;
    $departmentId = (int) $departmentId;
    $matrix = [];
    foreach (getPermissionModules() as $key => $mod) {
        $matrix[$key] = ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
    }
    if ($roleId <= 0) {
        return $matrix;
    }

    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'SELECT module_key, can_view, can_add, can_edit, can_delete
         FROM role_permissions
         WHERE role_id = ? AND department_id = ?'
    );
    $stmt->bind_param('ii', $roleId, $departmentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = (string) $row['module_key'];
        if (!isset($matrix[$key])) {
            continue;
        }
        $matrix[$key] = [
            'view'   => (int) $row['can_view'] ? 1 : 0,
            'add'    => (int) $row['can_add'] ? 1 : 0,
            'edit'   => (int) $row['can_edit'] ? 1 : 0,
            'delete' => (int) $row['can_delete'] ? 1 : 0,
        ];
    }
    $stmt->close();
    $conn->close();
    return $matrix;
}

/**
 * Replace permissions for one role + department scope.
 * $perms = [ module_key => ['view'=>0|1,'add'=>..., ...] ]
 */
function saveRolePermissionMatrix($roleId, $departmentId, array $perms)
{
    $roleId = (int) $roleId;
    $departmentId = (int) $departmentId;
    if ($roleId <= 0) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    if (!getRoleById($roleId)) {
        return ['ok' => false, 'error' => 'Role not found.'];
    }

    ensureRoleTables();
    $conn = getDBConnection();
    $conn->begin_transaction();
    try {
        $del = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ? AND department_id = ?');
        $del->bind_param('ii', $roleId, $departmentId);
        $del->execute();
        $del->close();

        $ins = $conn->prepare(
            'INSERT INTO role_permissions
                (role_id, module_key, department_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (getPermissionModules() as $key => $mod) {
            $p = $perms[$key] ?? [];
            $v = !empty($p['view']) ? 1 : 0;
            $a = !empty($p['add']) ? 1 : 0;
            $e = !empty($p['edit']) ? 1 : 0;
            $d = !empty($p['delete']) ? 1 : 0;
            // Skip empty rows to keep table lean
            if ($v + $a + $e + $d === 0) {
                continue;
            }
            // Add/Edit/Delete imply View
            if (($a || $e || $d) && !$v) {
                $v = 1;
            }
            $ins->bind_param('isiiiii', $roleId, $key, $departmentId, $v, $a, $e, $d);
            $ins->execute();
        }
        $ins->close();
        $conn->commit();
        $conn->close();

        // Refresh session cache if current user has this role
        if (!empty($_SESSION['custom_role_id']) && (int) $_SESSION['custom_role_id'] === $roleId) {
            loadUserPermissionsIntoSession((int) $_SESSION['custom_role_id'], true);
        }

        return ['ok' => true];
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ── Session permission cache ── */

/**
 * Load all permission rows for a role into $_SESSION['permissions'].
 * Structure: module => [ 'all' => flags, deptId => flags, ... ]
 * flags = ['view'=>bool,'add'=>bool,'edit'=>bool,'delete'=>bool]
 */
function loadUserPermissionsIntoSession($roleId, $force = false)
{
    $roleId = (int) $roleId;
    if (!$force && isset($_SESSION['permissions']) && (int) ($_SESSION['permissions_role_id'] ?? 0) === $roleId) {
        return;
    }

    $_SESSION['permissions'] = [];
    $_SESSION['permissions_role_id'] = $roleId;

    if ($roleId <= 0) {
        return;
    }

    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        'SELECT module_key, department_id, can_view, can_add, can_edit, can_delete
         FROM role_permissions WHERE role_id = ?'
    );
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $mod = (string) $row['module_key'];
        $dept = (int) $row['department_id'];
        $flags = [
            'view'   => (int) $row['can_view'] === 1,
            'add'    => (int) $row['can_add'] === 1,
            'edit'   => (int) $row['can_edit'] === 1,
            'delete' => (int) $row['can_delete'] === 1,
        ];
        if (!isset($map[$mod])) {
            $map[$mod] = [];
        }
        if ($dept === 0) {
            $map[$mod]['all'] = $flags;
        } else {
            $map[$mod][$dept] = $flags;
        }
    }
    $stmt->close();
    $conn->close();
    $_SESSION['permissions'] = $map;
}

function clearPermissionSessionCache()
{
    unset($_SESSION['permissions'], $_SESSION['permissions_role_id'], $_SESSION['custom_role_id']);
}

/**
 * Resolve flags for module + optional department.
 * Prefers exact department row; falls back to All Departments (dept 0).
 *
 * @return array{view:bool,add:bool,edit:bool,delete:bool}
 */
function resolvePermissionFlags($module, $departmentId = 0)
{
    $empty = ['view' => false, 'add' => false, 'edit' => false, 'delete' => false];
    $module = (string) $module;
    $departmentId = (int) $departmentId;

    $perms = $_SESSION['permissions'] ?? null;
    if (!is_array($perms) || !isset($perms[$module]) || !is_array($perms[$module])) {
        return $empty;
    }
    $modMap = $perms[$module];

    if ($departmentId > 0 && isset($modMap[$departmentId]) && is_array($modMap[$departmentId])) {
        return array_merge($empty, $modMap[$departmentId]);
    }
    if (isset($modMap['all']) && is_array($modMap['all'])) {
        return array_merge($empty, $modMap['all']);
    }
    // If checking "any dept" (0) and only specific depts exist, OR flags across depts
    if ($departmentId === 0) {
        $merged = $empty;
        foreach ($modMap as $k => $flags) {
            if ($k === 'all' || !is_array($flags)) {
                continue;
            }
            foreach (['view', 'add', 'edit', 'delete'] as $act) {
                if (!empty($flags[$act])) {
                    $merged[$act] = true;
                }
            }
        }
        return $merged;
    }
    return $empty;
}

/**
 * Admin always true. Non-admin needs custom role + matching permission.
 * Reloads permissions once per request so Admin permission changes apply without re-login.
 * DEPT_HEAD is hard-scoped to headed departments only.
 */
function canAccess($module, $action = 'view', $departmentId = 0)
{
    if (!function_exists('isAdmin')) {
        require_once __DIR__ . '/auth.php';
    }
    if (isAdmin()) {
        return true;
    }
    if (!isValidPermissionModule($module) || !isValidPermissionAction($action)) {
        return false;
    }
    if (empty($_SESSION['custom_role_id'])) {
        return false;
    }

    static $permsLoadedFor = 0;
    $roleId = (int) $_SESSION['custom_role_id'];
    if ($permsLoadedFor !== $roleId) {
        loadUserPermissionsIntoSession($roleId, true);
        $permsLoadedFor = $roleId;
        if (!function_exists('refreshHeadedDepartmentsSession')) {
            require_once __DIR__ . '/department_head_helper.php';
        }
        if (empty($_SESSION['role_code'])) {
            refreshHeadedDepartmentsSession();
        }
    }

    $departmentId = (int) $departmentId;
    $headed = $_SESSION['headed_department_ids'] ?? [];
    $isDeptHead = strtoupper((string) ($_SESSION['role_code'] ?? '')) === 'DEPT_HEAD';

    if ($isDeptHead) {
        if (!is_array($headed) || $headed === []) {
            return false;
        }
        if ($departmentId > 0 && !in_array($departmentId, array_map('intval', $headed), true)) {
            return false;
        }
        // Matrix stored as All (0) means "within headed depts"
        $flags = resolvePermissionFlags($module, 0);
        return !empty($flags[$action]);
    }

    $flags = resolvePermissionFlags($module, $departmentId);
    return !empty($flags[$action]);
}

function requireAccess($module, $action = 'view', $departmentId = 0)
{
    if (!function_exists('requireLogin')) {
        require_once __DIR__ . '/auth.php';
    }
    requireLogin();
    if (canAccess($module, $action, $departmentId)) {
        return;
    }
    require_once __DIR__ . '/../config/app.php';
    if (!function_exists('isOfficeStaffRole')) {
        require_once __DIR__ . '/department_head_helper.php';
    }
    if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
        $dest = app_url('employee/dashboard.php');
    } elseif (isAdmin() || (function_exists('isHR') && isHR())) {
        $dest = app_url('hr/dashboard.php');
    } elseif (function_exists('isEmployee') && isEmployee()) {
        $dest = app_url('employee/dashboard.php');
    } else {
        $dest = app_url('dashboard.php');
    }
    header('Location: ' . $dest . (strpos($dest, '?') === false ? '?' : '&') . 'msg=denied');
    exit;
}

/**
 * Department IDs the user may access for module+action.
 * Returns null = all departments (Admin or All-dept grant).
 * Returns [] = none. Returns int[] = specific.
 */
function allowedDepartmentsFor($module, $action = 'view')
{
    if (!function_exists('isAdmin')) {
        require_once __DIR__ . '/auth.php';
    }
    if (isAdmin()) {
        return null;
    }
    if (empty($_SESSION['custom_role_id'])) {
        return [];
    }
    if (!isset($_SESSION['permissions']) || (int) ($_SESSION['permissions_role_id'] ?? 0) !== (int) $_SESSION['custom_role_id']) {
        loadUserPermissionsIntoSession((int) $_SESSION['custom_role_id'], true);
    }
    if (empty($_SESSION['role_code']) && function_exists('refreshHeadedDepartmentsSession')) {
        refreshHeadedDepartmentsSession();
    } elseif (empty($_SESSION['role_code'])) {
        require_once __DIR__ . '/department_head_helper.php';
        refreshHeadedDepartmentsSession();
    }

    $isDeptHead = strtoupper((string) ($_SESSION['role_code'] ?? '')) === 'DEPT_HEAD';
    $headed = array_map('intval', $_SESSION['headed_department_ids'] ?? []);

    if ($isDeptHead) {
        if ($headed === [] || !canAccess($module, $action, 0)) {
            return [];
        }
        return array_values(array_unique($headed));
    }

    $modMap = $_SESSION['permissions'][$module] ?? null;
    if (!is_array($modMap) || $modMap === []) {
        return [];
    }
    if (isset($modMap['all']) && !empty($modMap['all'][$action])) {
        return null;
    }
    $ids = [];
    foreach ($modMap as $k => $flags) {
        if ($k === 'all' || !is_array($flags)) {
            continue;
        }
        if (!empty($flags[$action])) {
            $ids[] = (int) $k;
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Clamp a requested department filter to allowed set.
 * Returns 0 for "all allowed", or a single dept id, or -1 if none allowed.
 */
function clampDepartmentFilter($module, $requestedDeptId, $action = 'view')
{
    $requestedDeptId = (int) $requestedDeptId;
    $allowed = allowedDepartmentsFor($module, $action);
    if ($allowed === null) {
        return $requestedDeptId; // admin / all
    }
    if ($allowed === []) {
        return -1;
    }
    if ($requestedDeptId > 0) {
        return in_array($requestedDeptId, $allowed, true) ? $requestedDeptId : -1;
    }
    // "All" requested but user only has specific depts — keep 0 and let list filter use IN (...)
    return 0;
}

function getAllowedDepartmentIdsSql($module, $action = 'view')
{
    $allowed = allowedDepartmentsFor($module, $action);
    if ($allowed === null) {
        return null; // no SQL restriction
    }
    if ($allowed === []) {
        return '0'; // no match
    }
    return implode(',', array_map('intval', $allowed));
}

/* ── Staff users helpers ── */

function fetchStaffUsers()
{
    ensureRoleTables();
    $conn = getDBConnection();
    $sql = "SELECT u.id, u.username, u.full_name, u.role, u.department_id, u.custom_role_id, u.status,
                   r.name AS role_name, d.department_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.custom_role_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.role IN ('admin', 'hr')
            ORDER BY u.role ASC, u.full_name ASC";
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

function getStaffUserById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT id, username, full_name, role, department_id, custom_role_id, status
         FROM users WHERE id = ? AND role IN ('admin', 'hr') LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function saveStaffUser(array $data, $id = 0)
{
    ensureRoleTables();
    $id = (int) $id;
    $username = forceDetailUpper($data['username'] ?? '');
    $fullName = forceDetailUpper($data['full_name'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $role = strtolower(trim((string) ($data['role'] ?? 'hr')));
    if (!in_array($role, ['admin', 'hr'], true)) {
        $role = 'hr';
    }
    $departmentId = (int) ($data['department_id'] ?? 0);
    $departmentId = $departmentId > 0 ? $departmentId : null;
    $customRoleId = (int) ($data['custom_role_id'] ?? 0);
    $customRoleId = $customRoleId > 0 ? $customRoleId : null;
    $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;

    if ($username === '' || $fullName === '') {
        return ['ok' => false, 'error' => 'Username and full name are required.'];
    }
    if ($id <= 0 && $password === '') {
        return ['ok' => false, 'error' => 'Password is required for new users.'];
    }
    // Admin login role does not need custom role; HR should have one for module access
    if ($role === 'admin') {
        $customRoleId = null;
    }

    $conn = getDBConnection();

    if ($id > 0) {
        $chk = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $chk->bind_param('si', $username, $id);
    } else {
        $chk = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $chk->bind_param('s', $username);
    }
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $chk->close();
        $conn->close();
        return ['ok' => false, 'error' => 'Username already exists.'];
    }
    $chk->close();

    // mysqli bind_param does not send SQL NULL for type "i" reliably — use 0 sentinel then NULLIF
    $deptBind = $departmentId === null ? 0 : (int) $departmentId;
    $roleBind = $customRoleId === null ? 0 : (int) $customRoleId;

    if ($id > 0) {
        if ($password !== '') {
            $stmt = $conn->prepare(
                'UPDATE users SET username = ?, password = ?, full_name = ?, role = ?,
                 department_id = NULLIF(?, 0), custom_role_id = NULLIF(?, 0), status = ?
                 WHERE id = ?'
            );
            $stmt->bind_param(
                'ssssiiii',
                $username,
                $password,
                $fullName,
                $role,
                $deptBind,
                $roleBind,
                $status,
                $id
            );
        } else {
            $stmt = $conn->prepare(
                'UPDATE users SET username = ?, full_name = ?, role = ?,
                 department_id = NULLIF(?, 0), custom_role_id = NULLIF(?, 0), status = ?
                 WHERE id = ?'
            );
            $stmt->bind_param(
                'sssiiii',
                $username,
                $fullName,
                $role,
                $deptBind,
                $roleBind,
                $status,
                $id
            );
        }
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update user.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    $stmt = $conn->prepare(
        'INSERT INTO users (username, password, full_name, role, department_id, custom_role_id, status)
         VALUES (?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?)'
    );
    $stmt->bind_param(
        'ssssiii',
        $username,
        $password,
        $fullName,
        $role,
        $deptBind,
        $roleBind,
        $status
    );
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    $conn->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not create user.'];
    }
    return ['ok' => true, 'id' => $newId];
}

function deleteStaffUser($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid user.'];
    }
    if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $id) {
        return ['ok' => false, 'error' => 'You cannot delete your own account.'];
    }
    $user = getStaffUserById($id);
    if (!$user) {
        return ['ok' => false, 'error' => 'User not found.'];
    }
    if (($user['role'] ?? '') === 'admin') {
        // Prevent deleting last admin
        $conn = getDBConnection();
        $c = 0;
        $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND status = 1");
        if ($res) {
            $c = (int) ($res->fetch_assoc()['c'] ?? 0);
        }
        if ($c <= 1) {
            $conn->close();
            return ['ok' => false, 'error' => 'Cannot delete the last active Admin.'];
        }
        $conn->close();
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role IN (\'admin\', \'hr\')');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not delete user.'];
}

/* ── Employee portal login assignment ── */

/**
 * Active employees for assign dropdown (code + name + dept).
 */
function fetchEmployeesForRoleAssign($departmentId = 0)
{
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    ensureEmployeesTable();
    ensureRoleTables();
    $conn = getDBConnection();
    $departmentId = (int) $departmentId;
    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.department_id, d.department_name,
                   u.id AS portal_user_id, u.username AS portal_username, u.custom_role_id, r.name AS role_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u ON u.employee_id = e.id AND u.role = 'employee'
            LEFT JOIN roles r ON r.id = u.custom_role_id
            WHERE e.status = 1
              AND (e.date_of_exit IS NULL OR e.date_of_exit = '' OR e.date_of_exit = '0000-00-00' OR e.date_of_exit > CURDATE())";
    if ($departmentId > 0) {
        $sql .= ' AND e.department_id = ' . $departmentId;
    }
    $sql .= ' ORDER BY e.employee_name ASC';
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

/**
 * Users with role=employee (portal logins) for a role or all.
 */
function fetchEmployeePortalAssignments($roleId = 0)
{
    ensureRoleTables();
    $conn = getDBConnection();
    $roleId = (int) $roleId;
    $sql = "SELECT u.id, u.username, u.password, u.full_name, u.custom_role_id, u.employee_id, u.department_id, u.status,
                   r.name AS role_name, e.employee_code, e.employee_name, d.department_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.custom_role_id
            LEFT JOIN employees e ON e.id = u.employee_id
            LEFT JOIN departments d ON d.id = COALESCE(u.department_id, e.department_id)
            WHERE u.role = 'employee'";
    if ($roleId > 0) {
        $sql .= ' AND u.custom_role_id = ' . $roleId;
    }
    $sql .= ' ORDER BY u.full_name ASC';
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

function getEmployeePortalUserById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    ensureRoleTables();
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT u.*, e.employee_code, e.employee_name, e.department_id AS emp_department_id, r.name AS role_name
         FROM users u
         LEFT JOIN employees e ON e.id = u.employee_id
         LEFT JOIN roles r ON r.id = u.custom_role_id
         WHERE u.id = ? AND u.role = 'employee' LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

/**
 * Create / update employee portal login + assign custom role.
 * $data: employee_id, custom_role_id, username, password, status, user_id (optional for edit)
 */
function assignEmployeePortalLogin(array $data)
{
    ensureRoleTables();
    if (!function_exists('getEmployeeById')) {
        require_once __DIR__ . '/employee_helper.php';
    }

    $userId = (int) ($data['user_id'] ?? 0);
    $employeeId = (int) ($data['employee_id'] ?? 0);
    $customRoleId = (int) ($data['custom_role_id'] ?? 0);
    $username = forceDetailUpper($data['username'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $status = isset($data['status']) ? ((int) $data['status'] ? 1 : 0) : 1;

    if ($employeeId <= 0) {
        return ['ok' => false, 'error' => 'Select an employee.'];
    }
    if ($customRoleId <= 0 || !getRoleById($customRoleId)) {
        return ['ok' => false, 'error' => 'Select a valid custom role.'];
    }
    if ($username === '') {
        return ['ok' => false, 'error' => 'Username is required.'];
    }

    $emp = getEmployeeById($employeeId);
    if (!$emp || (int) ($emp['status'] ?? 0) !== 1) {
        return ['ok' => false, 'error' => 'Employee not found or inactive.'];
    }
    $fullName = forceDetailUpper($emp['employee_name'] ?? '');
    $departmentId = (int) ($emp['department_id'] ?? 0);
    $deptBind = $departmentId > 0 ? $departmentId : 0;

    $conn = getDBConnection();

    // Resolve existing portal login for this employee FIRST (update path)
    if ($userId <= 0) {
        $find = $conn->prepare(
            "SELECT id FROM users WHERE employee_id = ? AND role = 'employee' LIMIT 1"
        );
        $find->bind_param('i', $employeeId);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();
        if ($found) {
            $userId = (int) $found['id'];
        }
    } else {
        // Ensure edit target is this employee's portal user
        $chkOwn = $conn->prepare(
            "SELECT id, employee_id FROM users WHERE id = ? AND role = 'employee' LIMIT 1"
        );
        $chkOwn->bind_param('i', $userId);
        $chkOwn->execute();
        $own = $chkOwn->get_result()->fetch_assoc();
        $chkOwn->close();
        if (!$own) {
            $conn->close();
            return ['ok' => false, 'error' => 'Employee login not found.'];
        }
        if ((int) ($own['employee_id'] ?? 0) !== $employeeId) {
            $conn->close();
            return ['ok' => false, 'error' => 'Login does not match selected employee.'];
        }
    }

    // If username already exists on another row belonging to SAME employee, use that row
    $uChk = $conn->prepare(
        "SELECT id, employee_id, role FROM users WHERE username = ? LIMIT 1"
    );
    $uChk->bind_param('s', $username);
    $uChk->execute();
    $uRow = $uChk->get_result()->fetch_assoc();
    $uChk->close();
    if ($uRow) {
        $uRowId = (int) $uRow['id'];
        $uEmpId = (int) ($uRow['employee_id'] ?? 0);
        $uRole = (string) ($uRow['role'] ?? '');
        if ($userId > 0 && $uRowId === $userId) {
            // same record — OK
        } elseif ($uRole === 'employee' && $uEmpId === $employeeId) {
            $userId = $uRowId;
        } else {
            $conn->close();
            return ['ok' => false, 'error' => 'Username already exists.'];
        }
    }

    // Another portal login for same employee with different id?
    if ($userId > 0) {
        $dup = $conn->prepare(
            "SELECT id FROM users WHERE employee_id = ? AND role = 'employee' AND id <> ? LIMIT 1"
        );
        $dup->bind_param('ii', $employeeId, $userId);
        $dup->execute();
        $dupRow = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($dupRow) {
            $conn->close();
            return ['ok' => false, 'error' => 'This employee already has a portal login.'];
        }
    }

    if ($userId > 0) {
        if ($password !== '') {
            $stmt = $conn->prepare(
                "UPDATE users SET username = ?, password = ?, full_name = ?, role = 'employee',
                 department_id = NULLIF(?, 0), custom_role_id = ?, employee_id = ?, status = ?
                 WHERE id = ? AND role = 'employee'"
            );
            $stmt->bind_param(
                'sssiiiii',
                $username,
                $password,
                $fullName,
                $deptBind,
                $customRoleId,
                $employeeId,
                $status,
                $userId
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET username = ?, full_name = ?, role = 'employee',
                 department_id = NULLIF(?, 0), custom_role_id = ?, employee_id = ?, status = ?
                 WHERE id = ? AND role = 'employee'"
            );
            $stmt->bind_param(
                'ssiiiii',
                $username,
                $fullName,
                $deptBind,
                $customRoleId,
                $employeeId,
                $status,
                $userId
            );
        }
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update employee login.'];
        }
        syncDepartmentHeadFromRoleAssignment($employeeId, $customRoleId);
        return ['ok' => true, 'id' => $userId, 'updated' => true];
    }

    if ($password === '') {
        $conn->close();
        return ['ok' => false, 'error' => 'Password is required for new login.'];
    }

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password, full_name, role, department_id, custom_role_id, employee_id, status)
         VALUES (?, ?, ?, 'employee', NULLIF(?, 0), ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssiiii',
        $username,
        $password,
        $fullName,
        $deptBind,
        $customRoleId,
        $employeeId,
        $status
    );
    $ok = $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    $conn->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not create employee login.'];
    }
    syncDepartmentHeadFromRoleAssignment($employeeId, $customRoleId);
    return ['ok' => true, 'id' => $newId, 'updated' => false];
}

/**
 * When Dept Head role is assigned, register employee as that department's head (matrix).
 * When role is changed away from Dept Head, clear their headed departments.
 */
function syncDepartmentHeadFromRoleAssignment($employeeId, $customRoleId)
{
    $employeeId = (int) $employeeId;
    $customRoleId = (int) $customRoleId;
    if ($employeeId <= 0) {
        return;
    }
    if (!function_exists('setDepartmentHead')) {
        require_once __DIR__ . '/department_head_helper.php';
    }
    if (!function_exists('getEmployeeById')) {
        require_once __DIR__ . '/employee_helper.php';
    }

    $code = strtoupper((string) (getRoleCodeById($customRoleId) ?? ''));
    if ($code !== 'DEPT_HEAD') {
        foreach (getHeadedDepartmentIds($employeeId) as $deptId) {
            clearDepartmentHead((int) $deptId);
        }
        return;
    }

    $emp = getEmployeeById($employeeId);
    $deptId = (int) ($emp['department_id'] ?? 0);
    if ($deptId <= 0) {
        return;
    }
    setDepartmentHead($deptId, $employeeId, (int) ($_SESSION['user_id'] ?? 0));
}

function revokeEmployeePortalLogin($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Invalid user.'];
    }
    $user = getEmployeePortalUserById($userId);
    if (!$user) {
        return ['ok' => false, 'error' => 'Employee login not found.'];
    }
    $employeeId = (int) ($user['employee_id'] ?? 0);
    if ($employeeId > 0) {
        if (!function_exists('getHeadedDepartmentIds')) {
            require_once __DIR__ . '/department_head_helper.php';
        }
        foreach (getHeadedDepartmentIds($employeeId) as $deptId) {
            clearDepartmentHead((int) $deptId);
        }
    }
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Could not revoke login.'];
}
