<?php
/**
 * Contractor Manage — tables, lists, and Operations Rate List calculations
 * Logic matches the Armor Steel contractor modules (qty × rate, R, OT, Foundry).
 */

require_once __DIR__ . '/../config/database.php';

function contractorOperations()
{
    return [
        'CLEANING' => 'CLEANING',
        'Lathe Employee wise' => 'Lathe Employee wise',
        'GRINDING' => 'GRINDING',
        'CNC' => 'CNC',
        'ARGON WELDING' => 'ARGON WELDING',
        'BUFF' => 'BUFF',
        'COATING' => 'COATING',
        'CORE' => 'CORE',
        'ASS-1' => 'ASS-1',
        'ASS-2' => 'ASS-2',
        'RRL' => 'RRL',
        'BUTTERFLY' => 'BUTTERFLY',
        'FLEXIBLE' => 'FLEXIBLE',
        'FOUNDRY' => 'FOUNDRY',
    ];
}

function contractorHideGradeOps()
{
    return ['ASS-2', 'BUTTERFLY', 'FOUNDRY', 'CORE'];
}

function contractorRepairOps()
{
    return ['BUFF', 'RRL', 'FLEXIBLE', 'ASS-2', 'COATING', 'FOUNDRY'];
}

function contractorEnsureColumn($conn, $table, $column, $definition)
{
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $column = preg_replace('/[^a-z0-9_]/', '', $column);
    if ($table === '' || $column === '' || $definition === '') {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN " . $definition);
    }
}

function contractorOtRepairOps()
{
    return ['RRL', 'FLEXIBLE'];
}

function contractorMonths()
{
    return [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
}

function contractorPaymentModes()
{
    return ['Bank' => 'Bank', 'Cash' => 'Cash', 'Cheque' => 'Cheque'];
}

function contractorEmploymentTypes()
{
    return [
        'Permanent' => 'Permanent',
        'Temporary' => 'Temporary',
        'Contract' => 'Contract',
        'Trainee' => 'Trainee',
    ];
}

function ensureContractorTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_name VARCHAR(120) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_contractor_grades_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operation VARCHAR(80) NOT NULL,
        product_name VARCHAR(180) NOT NULL,
        process VARCHAR(120) DEFAULT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'PCS',
        rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
        ot_text VARCHAR(40) DEFAULT NULL,
        rejection_rate DECIMAL(12, 2) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cprod_op (operation),
        INDEX idx_cprod_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_code VARCHAR(30) NOT NULL UNIQUE,
        biometric_user_id VARCHAR(40) DEFAULT NULL,
        surname VARCHAR(80) DEFAULT NULL,
        first_name VARCHAR(80) DEFAULT NULL,
        father_name VARCHAR(120) DEFAULT NULL,
        full_name VARCHAR(180) NOT NULL,
        date_of_birth DATE DEFAULT NULL,
        gender VARCHAR(20) DEFAULT NULL,
        blood_group VARCHAR(10) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        username VARCHAR(80) DEFAULT NULL,
        contact_number VARCHAR(20) DEFAULT NULL,
        other_number VARCHAR(20) DEFAULT NULL,
        current_address TEXT,
        permanent_address TEXT,
        aadhar_number VARCHAR(20) DEFAULT NULL,
        pan_number VARCHAR(20) DEFAULT NULL,
        marital_status VARCHAR(20) DEFAULT NULL,
        grade_id INT DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        bank_account_number VARCHAR(40) DEFAULT NULL,
        ifsc_code VARCHAR(20) DEFAULT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_ce_name (full_name),
        INDEX idx_ce_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_employment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        designation_type VARCHAR(40) DEFAULT NULL,
        designation VARCHAR(120) DEFAULT NULL,
        department_id INT DEFAULT NULL,
        sub_department VARCHAR(120) DEFAULT NULL,
        process VARCHAR(120) DEFAULT NULL,
        date_of_joining DATE DEFAULT NULL,
        confirmation_date DATE DEFAULT NULL,
        employee_pf_no VARCHAR(40) DEFAULT NULL,
        uan_no VARCHAR(40) DEFAULT NULL,
        payment_mode VARCHAR(30) DEFAULT NULL,
        employment_type VARCHAR(40) DEFAULT NULL,
        shift_id INT DEFAULT NULL,
        outdoor_attendance VARCHAR(10) DEFAULT 'No',
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_cem_emp (employee_id),
        INDEX idx_cem_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_operation_sheets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        operation VARCHAR(80) NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        total_qty DECIMAL(14, 2) NOT NULL DEFAULT 0,
        total_r DECIMAL(14, 2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
        salary_generated TINYINT(1) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_csheet (employee_id, operation, month_no, year_no, status),
        INDEX idx_csheet_op (operation, year_no, month_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_operation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sheet_id INT NOT NULL,
        product_id INT NOT NULL,
        grade_id INT DEFAULT NULL,
        rate DECIMAL(12, 4) NOT NULL DEFAULT 0,
        ot_rate DECIMAL(12, 4) NOT NULL DEFAULT 0,
        rejection_rate DECIMAL(12, 4) NOT NULL DEFAULT 0,
        days_json MEDIUMTEXT,
        total_qty DECIMAL(14, 2) NOT NULL DEFAULT 0,
        total_r DECIMAL(14, 2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_citem_sheet (sheet_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_salary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sheet_id INT NOT NULL,
        employee_id INT NOT NULL,
        operation VARCHAR(80) NOT NULL,
        month_no TINYINT NOT NULL,
        year_no SMALLINT NOT NULL,
        total_qty DECIMAL(14, 2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(14, 2) NOT NULL DEFAULT 0,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_csal_emp (employee_id, year_no, month_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    contractorEnsureColumn($conn, 'contractor_operation_items', 'grade_id', 'grade_id INT DEFAULT NULL AFTER product_id');
    contractorEnsureColumn($conn, 'contractor_employment', 'sub_department_id', 'sub_department_id INT DEFAULT NULL AFTER department_id');

    if ($closeAfter) {
        $conn->close();
    }
}

function generateContractorCode($conn)
{
    $max = 0;
    $res = $conn->query("SELECT employee_code FROM contractor_employees");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (preg_match('/(\d+)/', (string) $row['employee_code'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
    }
    return 'CO' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
}

function isContractorCodeUnique($conn, $code, $excludeId = 0)
{
    $stmt = $conn->prepare('SELECT id FROM contractor_employees WHERE employee_code = ? AND id <> ? LIMIT 1');
    $excludeId = (int) $excludeId;
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return empty($row);
}

function contractorDisplayName(array $row)
{
    $name = trim((string) ($row['employee_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $full = trim((string) ($row['full_name'] ?? ''));
    if ($full !== '') {
        return $full;
    }
    return trim(implode(' ', array_filter([
        $row['surname'] ?? '',
        $row['first_name'] ?? '',
        $row['father_name'] ?? '',
    ])));
}

function contractorJobworkWhere()
{
    return "e.status = 1 AND e.pay_type = 'Jobwork'";
}

function getContractorEmployeeById($id)
{
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $id = (int) $id;
    $stmt = $conn->prepare("SELECT e.*, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.id = ? AND e.status = 1 AND e.pay_type = 'Jobwork'
        LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function getActiveContractorEmployees($conn = null)
{
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureEmployeesTable($conn);
    $rows = [];
    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.department_id, e.designation, e.mobile_number
            FROM employees e
            WHERE " . contractorJobworkWhere() . "
            ORDER BY e.employee_code ASC, e.employee_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function contractorProductLabel(array $row)
{
    $name = trim((string) ($row['product_name'] ?? ''));
    $process = trim((string) ($row['process'] ?? ''));
    if ($process !== '' && $process !== '-') {
        return $name . ' ' . $process;
    }
    return $name;
}

function parseOtRate($otText)
{
    if ($otText === null || $otText === '') {
        return 0.0;
    }
    if (is_numeric($otText)) {
        return (float) $otText;
    }
    if (preg_match('/(\d+(?:\.\d+)?)/', (string) $otText, $m)) {
        return (float) $m[1];
    }
    return 0.0;
}

function daysInMonthNum($month, $year)
{
    $month = max(1, min(12, (int) $month));
    $year = (int) $year;
    return (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
}

/**
 * Same formulas as Armor Steel Operations Rate List JS.
 */
function calculateContractorRow($operation, $rate, $otRate, $rejRate, array $days)
{
    $repairOps = contractorRepairOps();
    $otRepairOps = contractorOtRepairOps();
    $normalQty = 0.0;
    $rQty = 0.0;
    $otQty = 0.0;

    foreach ($days as $cell) {
        $normalQty += (float) ($cell['q'] ?? 0);
        $rQty += (float) ($cell['r'] ?? 0);
        $otQty += (float) ($cell['ot'] ?? 0);
    }

    if (!in_array($operation, $repairOps, true)) {
        $rQty = 0.0;
    }
    if (!in_array($operation, $otRepairOps, true)) {
        $otQty = 0.0;
    }

    $totalQty = $normalQty + $otQty;
    $amount = 0.0;
    if ($operation === 'FOUNDRY') {
        $amount = ($normalQty + $rQty) * $rate;
    } elseif (in_array($operation, $repairOps, true)) {
        $amount = ($normalQty * $rate) + ($rQty * $rejRate) + ($otQty * $otRate);
    } else {
        $amount = $totalQty * $rate;
    }

    return [
        'normal_qty' => $normalQty,
        'total_qty' => round($totalQty, 2),
        'total_r' => round($rQty, 2),
        'total_amount' => round($amount, 2),
    ];
}

function contractorDtAjax($conn, $table, $select, $searchCols, $orderMap, $rowBuilder)
{
    $draw = (int) ($_POST['draw'] ?? 1);
    $start = max(0, (int) ($_POST['start'] ?? 0));
    $length = (int) ($_POST['length'] ?? 10);
    if ($length <= 0 || $length > 100) {
        $length = 10;
    }
    $search = trim($_POST['search']['value'] ?? '');
    $orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
    $orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
    $orderBy = $orderMap[$orderCol] ?? $orderMap[1] ?? 'id';

    $where = 'status = 1';
    $types = '';
    $params = [];
    if ($search !== '' && $searchCols) {
        $parts = [];
        $like = '%' . $search . '%';
        foreach ($searchCols as $col) {
            $parts[] = $col . ' LIKE ?';
            $types .= 's';
            $params[] = $like;
        }
        $where .= ' AND (' . implode(' OR ', $parts) . ')';
    }

    $total = (int) $conn->query("SELECT COUNT(*) AS c FROM {$table} WHERE status = 1")->fetch_assoc()['c'];

    $countSql = "SELECT COUNT(*) AS c FROM {$table} WHERE {$where}";
    if ($params) {
        $st = $conn->prepare($countSql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $filtered = (int) $st->get_result()->fetch_assoc()['c'];
        $st->close();
    } else {
        $filtered = (int) $conn->query($countSql)->fetch_assoc()['c'];
    }

    $sql = "{$select} WHERE {$where} ORDER BY {$orderBy} {$orderDir} LIMIT ?, ?";
    $types2 = $types . 'ii';
    $params2 = $params;
    $params2[] = $start;
    $params2[] = $length;
    $st = $conn->prepare($sql);
    $st->bind_param($types2, ...$params2);
    $st->execute();
    $res = $st->get_result();
    $data = [];
    $sr = $start;
    while ($row = $res->fetch_assoc()) {
        $sr++;
        $data[] = $rowBuilder($row, $sr);
    }
    $st->close();

    return [
        'draw' => $draw,
        'recordsTotal' => $total,
        'recordsFiltered' => $filtered,
        'data' => $data,
    ];
}

function contractorActionBtns($editUrl, $deleteUrl, $name)
{
    return '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars($editUrl) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . '<a href="' . htmlspecialchars($deleteUrl) . '" class="action-btn delete btn-delete" data-name="'
        . htmlspecialchars($name) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
        . '</div>';
}

function contractorEmployeeOptionLabel(array $row)
{
    return trim(($row['employee_code'] ?? '') . ' - ' . contractorDisplayName($row), ' -');
}

function getContractorGrades($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $rows = [];
    $res = $conn->query('SELECT id, grade_name AS name FROM contractor_grades WHERE status = 1 ORDER BY grade_name ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function getContractorProductsByOperation($operation, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM contractor_products WHERE status = 1 AND operation = ? ORDER BY product_name ASC');
    $stmt->bind_param('s', $operation);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function getContractorSheetById($id, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $id = (int) $id;
    $stmt = $conn->prepare('SELECT s.*, e.employee_code, e.employee_name AS full_name, e.employee_name
        FROM contractor_operation_sheets s
        INNER JOIN employees e ON e.id = s.employee_id
        WHERE s.id = ? AND s.status = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sheet = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sheet) {
        if ($closeAfter) {
            $conn->close();
        }
        return null;
    }
    $items = [];
    $it = $conn->prepare('SELECT * FROM contractor_operation_items WHERE sheet_id = ? ORDER BY sort_order ASC, id ASC');
    $it->bind_param('i', $id);
    $it->execute();
    $res = $it->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['days'] = json_decode((string) ($row['days_json'] ?? '{}'), true) ?: [];
        $items[] = $row;
    }
    $it->close();
    $sheet['items'] = $items;
    if ($closeAfter) {
        $conn->close();
    }
    return $sheet;
}

function emptyDayMap($month, $year)
{
    $n = daysInMonthNum($month, $year);
    $days = [];
    for ($d = 1; $d <= 31; $d++) {
        $days[(string) $d] = ['q' => '', 'r' => '', 'ot' => '', 'disabled' => ($d > $n)];
    }
    return $days;
}

function mergeDayMap($saved, $month, $year)
{
    $base = emptyDayMap($month, $year);
    if (!is_array($saved)) {
        return $base;
    }
    foreach ($saved as $k => $cell) {
        $k = (string) $k;
        if (!isset($base[$k])) {
            continue;
        }
        $base[$k]['q'] = $cell['q'] ?? ($cell['qty'] ?? '');
        $base[$k]['r'] = $cell['r'] ?? '';
        $base[$k]['ot'] = $cell['ot'] ?? '';
    }
    return $base;
}
