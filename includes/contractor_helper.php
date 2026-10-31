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
        'CUTTING' => 'CUTTING',
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

/**
 * Operations that show Grade next to Product (old reference: 304, 202, GUNMETAL, ALUMINIUM)
 */
function contractorShowGradeOps()
{
    return ['CNC', 'BUFF', 'ARGON WELDING', 'GRINDING'];
}

function contractorOperationShowsGrade($operation)
{
    $op = trim((string) $operation);
    if ($op === '') {
        return false;
    }
    if (in_array($op, contractorShowGradeOps(), true)) {
        return true;
    }
    // Allow short label "ARGON"
    if (stripos($op, 'ARGON') === 0) {
        return true;
    }
    return false;
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
    // Ops Rate List / contractor employees:
    // - Jobwork pay_type OR CO…/JW… codes (Melting CO workers)
    // - OR department name matches an Operations Rate List operation (e.g. CUTTING)
    // Salary Register stays separate (does not use this WHERE).
    $opsIn = [];
    foreach (array_keys(contractorOperations()) as $opName) {
        $opName = strtoupper(trim((string) $opName));
        if ($opName === '') {
            continue;
        }
        $opsIn[] = "'" . str_replace("'", "''", $opName) . "'";
    }
    $deptMatch = '';
    if ($opsIn) {
        $deptMatch = " OR EXISTS (
            SELECT 1 FROM departments d_ops
            WHERE d_ops.id = e.department_id
              AND UPPER(TRIM(d_ops.department_name)) IN (" . implode(',', $opsIn) . ")
        )";
    }
    return "e.status = 1 AND (
        e.pay_type = 'Jobwork'
        OR UPPER(TRIM(e.employee_code)) LIKE 'CO%'
        OR UPPER(TRIM(e.employee_code)) LIKE 'JW%'
        {$deptMatch}
    )";
}

function getContractorEmployeeById($id)
{
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $id = (int) $id;
    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE e.id = ? AND " . contractorJobworkWhere() . "
         LIMIT 1"
    );
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

/**
 * Ensure an employee appears in an ops Name dropdown (edit sheets whose
 * employee left Jobwork / inactive still need the saved name selected).
 *
 * @param array<int, array<string, mixed>> $employees
 * @return array<int, array<string, mixed>>
 */
function ensureOpsEmployeeInList(array $employees, $employeeId, $conn = null)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return $employees;
    }
    foreach ($employees as $emp) {
        if ((int) ($emp['id'] ?? 0) === $employeeId) {
            return $employees;
        }
    }

    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    if (!function_exists('ensureEmployeesTable')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    ensureEmployeesTable($conn);

    $stmt = $conn->prepare(
        "SELECT e.id, e.employee_code, e.employee_name, e.department_id, e.designation, e.mobile_number
         FROM employees e
         WHERE e.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    if ($row) {
        array_unshift($employees, $row);
    }
    return $employees;
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
        // Foundry: Day (q) and Night (r) kept separate; amount = (D+N) × rate
        $totalQty = $normalQty;
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

/**
 * Ensure a contractor_products row exists for operation + product name (for Ops Rate sheets).
 */
function contractorEnsureProduct($conn, $operation, $productName, $rate = 0.0)
{
    $operation = trim((string) $operation);
    $productName = trim((string) $productName);
    if ($productName === '') {
        $productName = 'General';
    }
    if ($operation === '') {
        return 0;
    }
    $st = $conn->prepare(
        "SELECT id FROM contractor_products
         WHERE status = 1 AND operation = ?
           AND (UPPER(TRIM(product_name)) = UPPER(?) OR UPPER(TRIM(IFNULL(process,''))) = UPPER(?))
         LIMIT 1"
    );
    $st->bind_param('sss', $operation, $productName, $productName);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        return (int) $row['id'];
    }
    $rate = (float) $rate;
    $ins = $conn->prepare(
        'INSERT INTO contractor_products (operation, product_name, process, unit, rate, ot_text, rejection_rate, status)
         VALUES (?, ?, ?, \'PCS\', ?, NULL, 0, 1)'
    );
    $ins->bind_param('sssd', $operation, $productName, $productName, $rate);
    $ins->execute();
    $id = (int) $conn->insert_id;
    $ins->close();
    return $id;
}

/**
 * Create Ops Rate List sheets (same format as RRL) from department Manual Jobwork Entry rows.
 * Skips emp/month that already have a sheet for this operation. Removes synced jobwork_entries
 * so salary (getJobworkTotal) does not double-count — data remains on the Ops sheet.
 *
 * @return int number of sheets created
 */
function contractorSyncJobworkEntriesToOpSheets($conn, $operation)
{
    $operation = trim((string) $operation);
    if ($operation === '' || !isset(contractorOperations()[$operation])) {
        return 0;
    }
    if (!function_exists('ensurePayrollTables')) {
        require_once __DIR__ . '/payroll_helper.php';
    }
    ensureContractorTables($conn);
    ensurePayrollTables($conn);

    $stD = $conn->prepare(
        "SELECT id FROM departments
         WHERE status = 1 AND (
            UPPER(TRIM(department_name)) = UPPER(?)
            OR UPPER(TRIM(department_name)) LIKE CONCAT('%', UPPER(?), '%')
         )
         ORDER BY (UPPER(TRIM(department_name)) = UPPER(?)) DESC, id ASC
         LIMIT 1"
    );
    $stD->bind_param('sss', $operation, $operation, $operation);
    $stD->execute();
    $dept = $stD->get_result()->fetch_assoc();
    $stD->close();
    $deptId = (int) ($dept['id'] ?? 0);
    if ($deptId <= 0) {
        return 0;
    }

    // Distinct emp + month + year that have manual entries
    $sql = "SELECT e.id AS employee_id, YEAR(j.work_date) AS year_no, MONTH(j.work_date) AS month_no
            FROM jobwork_entries j
            INNER JOIN employees e ON e.id = j.employee_id
            WHERE e.department_id = ? AND e.status = 1
            GROUP BY e.id, YEAR(j.work_date), MONTH(j.work_date)";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $deptId);
    $st->execute();
    $groups = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $created = 0;
    foreach ($groups as $g) {
        $empId = (int) $g['employee_id'];
        $month = (int) $g['month_no'];
        $year = (int) $g['year_no'];
        if ($empId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            continue;
        }

        $chk = $conn->prepare(
            'SELECT id FROM contractor_operation_sheets
             WHERE employee_id = ? AND operation = ? AND month_no = ? AND year_no = ? AND status = 1
             LIMIT 1'
        );
        $chk->bind_param('isii', $empId, $operation, $month, $year);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($exists) {
            continue;
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));
        $lq = $conn->prepare(
            "SELECT work_date, item_name, quantity, rate, amount
             FROM jobwork_entries
             WHERE employee_id = ? AND work_date BETWEEN ? AND ?
             ORDER BY work_date ASC, id ASC"
        );
        $lq->bind_param('iss', $empId, $from, $to);
        $lq->execute();
        $entries = $lq->get_result()->fetch_all(MYSQLI_ASSOC);
        $lq->close();
        if (!$entries) {
            continue;
        }

        // Group by item name → product lines; days_json from daily qty
        $byItem = [];
        foreach ($entries as $en) {
            $item = trim((string) ($en['item_name'] ?? ''));
            if ($item === '') {
                $item = 'General';
            }
            if (!isset($byItem[$item])) {
                $byItem[$item] = ['days' => [], 'rate_sum' => 0.0, 'rate_n' => 0];
            }
            $d = (int) date('j', strtotime((string) $en['work_date']));
            if ($d < 1 || $d > 31) {
                continue;
            }
            $key = (string) $d;
            if (!isset($byItem[$item]['days'][$key])) {
                $byItem[$item]['days'][$key] = ['q' => 0.0, 'r' => 0.0, 'ot' => 0.0];
            }
            $byItem[$item]['days'][$key]['q'] += (float) ($en['quantity'] ?? 0);
            $rate = (float) ($en['rate'] ?? 0);
            if ($rate > 0) {
                $byItem[$item]['rate_sum'] += $rate;
                $byItem[$item]['rate_n']++;
            }
        }

        $built = [];
        $grandQty = 0.0;
        $grandR = 0.0;
        $grandAmt = 0.0;
        $sort = 0;
        foreach ($byItem as $itemName => $pack) {
            $rate = $pack['rate_n'] > 0 ? round($pack['rate_sum'] / $pack['rate_n'], 4) : 0.0;
            $pid = contractorEnsureProduct($conn, $operation, $itemName, $rate);
            if ($pid <= 0) {
                continue;
            }
            $calc = calculateContractorRow($operation, $rate, 0.0, 0.0, $pack['days']);
            $grandQty += $calc['total_qty'];
            $grandR += $calc['total_r'];
            $grandAmt += $calc['total_amount'];
            $built[] = [
                'product_id' => $pid,
                'grade_id' => 0,
                'rate' => $rate,
                'ot_rate' => 0.0,
                'rejection_rate' => 0.0,
                'days_json' => json_encode($pack['days']),
                'total_qty' => $calc['total_qty'],
                'total_r' => $calc['total_r'],
                'total_amount' => $calc['total_amount'],
                'sort_order' => $sort++,
            ];
        }
        if (!$built) {
            continue;
        }

        $ins = $conn->prepare(
            'INSERT INTO contractor_operation_sheets
                (employee_id, operation, month_no, year_no, total_qty, total_r, total_amount, status)
             VALUES (?,?,?,?,?,?,?,1)'
        );
        $ins->bind_param('isiiddd', $empId, $operation, $month, $year, $grandQty, $grandR, $grandAmt);
        if (!$ins->execute()) {
            $ins->close();
            continue;
        }
        $sheetId = (int) $conn->insert_id;
        $ins->close();
        if ($sheetId <= 0) {
            continue;
        }

        $insI = $conn->prepare(
            'INSERT INTO contractor_operation_items
                (sheet_id, product_id, grade_id, rate, ot_rate, rejection_rate, days_json, total_qty, total_r, total_amount, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($built as $b) {
            $insI->bind_param(
                'iiidddsdddi',
                $sheetId,
                $b['product_id'],
                $b['grade_id'],
                $b['rate'],
                $b['ot_rate'],
                $b['rejection_rate'],
                $b['days_json'],
                $b['total_qty'],
                $b['total_r'],
                $b['total_amount'],
                $b['sort_order']
            );
            $insI->execute();
        }
        $insI->close();

        // Remove synced manual rows — now live on Ops Rate sheet (avoids double salary)
        $del = $conn->prepare(
            'DELETE FROM jobwork_entries WHERE employee_id = ? AND work_date BETWEEN ? AND ?'
        );
        $del->bind_param('iss', $empId, $from, $to);
        $del->execute();
        $del->close();

        $created++;
    }

    return $created;
}

function getContractorProductsByOperation($operation, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM contractor_products WHERE status = 1 AND operation = ? ORDER BY process ASC, product_name ASC');
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
    $it = $conn->prepare('SELECT i.*,
            p.process AS product_process,
            p.product_name AS product_name,
            p.rate AS product_rate,
            p.ot_text AS product_ot_text,
            p.rejection_rate AS product_rejection_rate,
            g.grade_name AS grade_name
        FROM contractor_operation_items i
        LEFT JOIN contractor_products p ON p.id = i.product_id
        LEFT JOIN contractor_grades g ON g.id = i.grade_id
        WHERE i.sheet_id = ?
        ORDER BY i.sort_order ASC, i.id ASC');
    $it->bind_param('i', $id);
    $it->execute();
    $res = $it->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['days'] = json_decode((string) ($row['days_json'] ?? '{}'), true) ?: [];
        $process = trim(html_entity_decode((string) ($row['product_process'] ?? '')));
        $pname = trim(html_entity_decode((string) ($row['product_name'] ?? '')));
        $row['product_label'] = ($process !== '' && $process !== '-') ? $process : $pname;
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
