<?php
/**
 * Master Helper - ensure tables + CRUD helpers for all masters
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/masters_config.php';

/**
 * Create all master tables if missing (safe on every request)
 */
function ensureMasterTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    // Departments already exist from base SQL — ensure columns only
    $conn->query("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_name VARCHAR(150) NOT NULL,
        icon_class VARCHAR(80) NOT NULL DEFAULT 'fa-building',
        icon_color VARCHAR(20) NOT NULL DEFAULT '#4A90E2',
        sort_order INT NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS designations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        sort_order INT NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_designations_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        shift_type ENUM('Day','Night') NOT NULL DEFAULT 'Day',
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        remarks TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_shifts_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS leave_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        leave_type VARCHAR(150) NOT NULL,
        days_allowed INT NOT NULL DEFAULT 0,
        is_paid ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
        description TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_leave_types_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        holiday_type ENUM('Holiday','Week-Off') NOT NULL DEFAULT 'Holiday',
        holiday_date DATE DEFAULT NULL,
        week_day VARCHAR(20) DEFAULT NULL,
        is_paid ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
        remarks TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_holidays_status (status),
        INDEX idx_holidays_date (holiday_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Upgrade existing installs
    $holCols = [];
    $hc = $conn->query("SHOW COLUMNS FROM holidays");
    if ($hc) {
        while ($c = $hc->fetch_assoc()) {
            $holCols[] = $c['Field'];
        }
    }
    if ($holCols && !in_array('is_paid', $holCols, true)) {
        $conn->query("ALTER TABLE holidays ADD COLUMN is_paid ENUM('Yes','No') NOT NULL DEFAULT 'Yes' AFTER week_day");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS salary_components (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        component_name VARCHAR(150) NOT NULL,
        component_type ENUM('Earning','Deduction') NOT NULL DEFAULT 'Earning',
        calculation ENUM('Fixed','Percentage') NOT NULL DEFAULT 'Fixed',
        default_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_salary_components_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS salary_slabs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        slab_name VARCHAR(150) NOT NULL,
        min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        max_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        remarks TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_salary_slabs_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $slabCount = $conn->query("SELECT COUNT(*) AS c FROM salary_slabs");
    if ($slabCount && (int) $slabCount->fetch_assoc()['c'] === 0) {
        $conn->query("INSERT INTO salary_slabs (code, slab_name, min_amount, max_amount, monthly_salary, remarks, status) VALUES
            ('JW1', 'Jobwork Slab A', 0, 5000, 4500, 'Low output', 1),
            ('JW2', 'Jobwork Slab B', 5000.01, 10000, 8500, 'Medium output', 1),
            ('JW3', 'Jobwork Slab C', 10000.01, 20000, 16000, 'High output', 1),
            ('JW4', 'Jobwork Slab D', 20000.01, 999999, 24000, 'Very high output', 1)");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS document_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        document_name VARCHAR(150) NOT NULL,
        is_mandatory ENUM('Yes','No') NOT NULL DEFAULT 'No',
        validity_days INT NOT NULL DEFAULT 0,
        description TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_document_types_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_code VARCHAR(40) DEFAULT NULL,
        asset_name VARCHAR(150) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        serial_no VARCHAR(100) DEFAULT NULL,
        condition_status VARCHAR(40) NOT NULL DEFAULT 'Good',
        remarks TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_assets_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS report_catalog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        report_name VARCHAR(150) NOT NULL,
        module_name VARCHAR(100) DEFAULT NULL,
        description TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_report_catalog_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) DEFAULT NULL,
        product_name VARCHAR(150) NOT NULL,
        category VARCHAR(100) DEFAULT NULL,
        unit VARCHAR(40) DEFAULT 'Nos',
        description TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_products_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS sub_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_subdept_dept (department_id),
        INDEX idx_subdept_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    seedSubDepartmentsFromDepartments($conn);

    if ($closeAfter) {
        $conn->close();
    }
}

/**
 * First-run seed: one Sub Department per Department using the department name.
 * Extra sub-departments can be added later under the same department.
 */
function seedSubDepartmentsFromDepartments($conn)
{
    $res = $conn->query(
        "SELECT d.id, d.department_name
         FROM departments d
         WHERE d.status = 1
           AND NOT EXISTS (
               SELECT 1 FROM sub_departments s
               WHERE s.department_id = d.id AND s.status = 1
           )"
    );
    if (!$res) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO sub_departments (department_id, name, status) VALUES (?, ?, 1)');
    while ($row = $res->fetch_assoc()) {
        $deptId = (int) $row['id'];
        $name = (string) $row['department_name'];
        $stmt->bind_param('is', $deptId, $name);
        $stmt->execute();
    }
    $stmt->close();
}

function getSubDepartmentsByDepartment($departmentId, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureMasterTables($conn);
    }
    $departmentId = (int) $departmentId;
    $rows = [];
    if ($departmentId > 0) {
        $stmt = $conn->prepare(
            'SELECT id, department_id, name FROM sub_departments
             WHERE status = 1 AND department_id = ?
             ORDER BY name ASC'
        );
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function getSubDepartmentNameById($id, $departmentId = 0, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $id = (int) $id;
    $name = '';
    if ($id > 0) {
        if ($departmentId > 0) {
            $stmt = $conn->prepare(
                'SELECT name FROM sub_departments WHERE id = ? AND department_id = ? AND status = 1 LIMIT 1'
            );
            $deptId = (int) $departmentId;
            $stmt->bind_param('ii', $id, $deptId);
        } else {
            $stmt = $conn->prepare('SELECT name FROM sub_departments WHERE id = ? AND status = 1 LIMIT 1');
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = (string) ($row['name'] ?? '');
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $name;
}

/**
 * Count active rows for a master table
 */
function countMasterRows($table)
{
    $conn = getDBConnection();
    ensureMasterTables($conn);
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $res = $conn->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE status = 1");
    $c = $res ? (int) $res->fetch_assoc()['c'] : 0;
    $conn->close();
    return $c;
}

/**
 * Get one master row by id
 */
function getMasterRow($table, $id)
{
    $conn = getDBConnection();
    ensureMasterTables($conn);
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $id = (int) $id;
    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row;
}

/**
 * Active master rows for dropdowns
 */
function getActiveMasterRows($table, $orderBy = 'id ASC')
{
    $conn = getDBConnection();
    ensureMasterTables($conn);
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $orderBy = preg_replace('/[^a-z0-9_, ]/', '', $orderBy);
    if ($orderBy === '') {
        $orderBy = 'id ASC';
    }
    $rows = [];
    $res = $conn->query("SELECT * FROM `{$table}` WHERE status = 1 ORDER BY {$orderBy}");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $conn->close();
    return $rows;
}

/**
 * Soft delete master row
 */
function softDeleteMasterRow($table, $id)
{
    $conn = getDBConnection();
    ensureMasterTables($conn);
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $id = (int) $id;
    $stmt = $conn->prepare("UPDATE `{$table}` SET status = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $ok;
}

/**
 * Collect & sanitize POST values from master field config
 */
function collectMasterPostData(array $config, array $post)
{
    $data = [];
    foreach ($config['fields'] as $field) {
        $name = $field['name'];
        $raw = $post[$name] ?? ($field['default'] ?? '');
        if (is_string($raw)) {
            $raw = trim($raw);
        }

        $type = $field['type'];
        if ($type === 'number') {
            if ($raw === '' || $raw === null) {
                $data[$name] = isset($field['default']) ? $field['default'] : 0;
            } else {
                $data[$name] = (strpos((string) ($field['step'] ?? ''), '.') !== false)
                    ? (float) $raw
                    : (int) $raw;
            }
        } elseif ($type === 'date' || $type === 'time') {
            $data[$name] = ($raw === '') ? null : $raw;
        } else {
            $data[$name] = $raw;
        }
    }
    return $data;
}

/**
 * Validate required fields
 */
function validateMasterData(array $config, array $data)
{
    foreach ($config['fields'] as $field) {
        if (!empty($field['required'])) {
            $v = $data[$field['name']] ?? '';
            if ($v === '' || $v === null) {
                return $field['label'] . ' is required.';
            }
        }
    }
    return '';
}

/**
 * Insert or update master row
 */
function saveMasterRow(array $config, array $data, $id = 0)
{
    $conn = getDBConnection();
    ensureMasterTables($conn);

    $table = preg_replace('/[^a-z0-9_]/', '', $config['table']);
    $cols = array_keys($data);
    $id = (int) $id;

    // Normalize nulls to empty string for mysqli bind (DATE empty → NULL via SQL)
    foreach ($cols as $c) {
        if ($data[$c] === null) {
            $data[$c] = '';
        }
    }

    if ($id > 0) {
        $sets = [];
        $types = '';
        $values = [];
        foreach ($cols as $c) {
            // Empty date/time → NULL
            if ($data[$c] === '' && isMasterDateTimeField($config, $c)) {
                $sets[] = "`{$c}` = NULL";
            } else {
                $sets[] = "`{$c}` = ?";
                $types .= masterBindType($data[$c]);
                $values[] = $data[$c];
            }
        }
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $types .= 'i';
        $values[] = $id;
        if ($types !== 'i') {
            $stmt->bind_param($types, ...$values);
        } else {
            $stmt->bind_param('i', $id);
        }
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        return ['ok' => $ok, 'id' => $id, 'error' => $error];
    }

    $colParts = [];
    $phParts = [];
    $types = '';
    $values = [];
    foreach ($cols as $c) {
        $colParts[] = "`{$c}`";
        if ($data[$c] === '' && isMasterDateTimeField($config, $c)) {
            $phParts[] = 'NULL';
        } else {
            $phParts[] = '?';
            $types .= masterBindType($data[$c]);
            $values[] = $data[$c];
        }
    }
    $sql = "INSERT INTO `{$table}` (" . implode(', ', $colParts) . ", status) VALUES (" . implode(', ', $phParts) . ", 1)";
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $ok = $stmt->execute();
    $error = $stmt->error;
    $newId = (int) $conn->insert_id;
    $stmt->close();
    $conn->close();
    return ['ok' => $ok, 'id' => $newId, 'error' => $error];
}

function isMasterDateTimeField(array $config, $fieldName)
{
    foreach ($config['fields'] as $f) {
        if ($f['name'] === $fieldName && in_array($f['type'], ['date', 'time'], true)) {
            return true;
        }
    }
    return false;
}

/**
 * mysqli bind type for a value
 */
function masterBindType($value)
{
    if (is_int($value)) {
        return 'i';
    }
    if (is_float($value)) {
        return 'd';
    }
    return 's';
}

/**
 * Format cell for list display
 */
function formatMasterCell($key, $value)
{
    if ($value === null || $value === '') {
        return '-';
    }
    if ($key === 'icon_color') {
        $c = htmlspecialchars((string) $value);
        return '<span class="color-swatch" style="background:' . $c . '"></span> ' . $c;
    }
    if ($key === 'icon_class') {
        $ic = htmlspecialchars((string) $value);
        return '<i class="fa-solid ' . $ic . '"></i> <code>' . $ic . '</code>';
    }
    if ($key === 'holiday_date' && $value) {
        return date('d-m-Y', strtotime($value));
    }
    if (($key === 'start_time' || $key === 'end_time') && $value) {
        return date('h:i A', strtotime($value));
    }
    if ($key === 'default_value' || $key === 'min_amount' || $key === 'max_amount' || $key === 'monthly_salary') {
        return number_format((float) $value, 2);
    }
    return htmlspecialchars((string) $value);
}

/**
 * Server-side DataTables JSON for a master
 */
function masterAjaxListJson(array $config)
{
    $table = preg_replace('/[^a-z0-9_]/', '', $config['table']);
    $nameField = $config['name_field'];
    $listCols = $config['list_columns'];

    $draw   = (int) ($_POST['draw'] ?? 1);
    $start  = max(0, (int) ($_POST['start'] ?? 0));
    $length = (int) ($_POST['length'] ?? 10);
    if ($length <= 0 || $length > 100) {
        $length = 10;
    }
    $search = trim($_POST['search']['value'] ?? '');
    $orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
    $orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    // Column 0 = Sr, then list columns, then Action
    $orderMap = ['id'];
    foreach ($listCols as $c) {
        $orderMap[] = $c['key'];
    }
    $orderBy = $orderMap[$orderCol] ?? 'id';
    $orderBy = preg_replace('/[^a-z0-9_]/', '', $orderBy);

    $conn = getDBConnection();
    ensureMasterTables($conn);

    $totalRes = $conn->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE status = 1");
    $recordsTotal = (int) ($totalRes->fetch_assoc()['c'] ?? 0);

    $where = 'status = 1';
    $types = '';
    $params = [];

    if ($search !== '') {
        $parts = [];
        foreach ($listCols as $c) {
            $col = preg_replace('/[^a-z0-9_]/', '', $c['key']);
            $parts[] = "`{$col}` LIKE ?";
            $types .= 's';
            $params[] = '%' . $search . '%';
        }
        if ($parts) {
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
    }

    $stmtC = $conn->prepare("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}");
    if ($types !== '') {
        $stmtC->bind_param($types, ...$params);
    }
    $stmtC->execute();
    $recordsFiltered = (int) ($stmtC->get_result()->fetch_assoc()['c'] ?? 0);
    $stmtC->close();

    $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY `{$orderBy}` {$orderDir} LIMIT ?, ?";
    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $start;
    $paramsData[] = $length;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typesData, ...$paramsData);
    $stmt->execute();
    $result = $stmt->get_result();

    $folder = $config['folder'];
    $data = [];
    $sr = $start + 1;
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        $editUrl = app_url("masters/{$folder}/edit.php?id={$id}");
        $delUrl  = app_url("masters/{$folder}/delete.php?id={$id}");
        $label   = $row[$nameField] ?? ('#' . $id);

        $item = [$sr++];
        foreach ($listCols as $c) {
            $item[] = formatMasterCell($c['key'], $row[$c['key']] ?? null);
        }
        $item[] = '<div class="action-links" onclick="event.stopPropagation();">'
            . '<a href="' . htmlspecialchars($editUrl) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
            . '<a href="' . htmlspecialchars($delUrl) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars((string) $label) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';

        $data[] = $item;
    }

    $stmt->close();
    $conn->close();

    return [
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ];
}
