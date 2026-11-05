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

/**
 * Gujarati label under operation name (clone daily print).
 */
function contractorOperationGujaratiName($operation)
{
    $map = [
        'CLEANING' => 'ક્લિનિંગ',
        'Lathe Employee wise' => 'લેથ (કર્મચારી પ્રમાણે)',
        'GRINDING' => 'ગ્રાઇન્ડિંગ',
        'CNC' => 'સી.એન.સી.',
        'CUTTING' => 'કટિંગ',
        'ARGON WELDING' => 'આર્ગન વેલ્ડિંગ',
        'BUFF' => 'બફ',
        'COATING' => 'કોટિંગ',
        'CORE' => 'કોર',
        'ASS-1' => 'એસેમ્બલી-૧',
        'ASS-2' => 'એસેમ્બલી-૨',
        'RRL' => 'આર.આર.એલ.',
        'BUTTERFLY' => 'બટરફ્લાય',
        'FLEXIBLE' => 'ફ્લેક્સિબલ',
        'FOUNDRY' => 'ફાઉન્ડ્રી',
    ];
    $op = trim((string) $operation);
    return $map[$op] ?? $op;
}

/**
 * Gujarati under Contract Process / product name (clone daily print).
 */
function contractorProcessGujaratiName($process)
{
    $process = trim((string) $process);
    if ($process === '') {
        return '';
    }
    $map = [
        'HEAVY ADAPTOR' => 'હેવી એડેપ્ટર',
        'LITE ADAPTOR' => 'લાઇટ એડેપ્ટર',
        'LIGHT ADAPTOR' => 'લાઇટ એડેપ્ટર',
        'ARMOR BODY' => 'આરમર બોડી',
        'HEAVY NOZZLE' => 'હેવી નોઝલ',
        'LITE NOZZLE' => 'લાઇટ નોઝલ',
        'LIGHT NOZZLE' => 'લાઇટ નોઝલ',
        'HEAVY NRV' => 'હેવી એન.આર.વી.',
        'LITE NRV' => 'લાઇટ એન.આર.વી.',
        'HEAVY MALE' => 'હેવી મેલ',
        'HEAVY FEMALE' => 'હેવી ફિમેલ',
        'LITE MALE' => 'લાઇટ મેલ',
        'LITE FEMALE' => 'લાઇટ ફિમેલ',
        'HEAVY BRANCH' => 'હેવી બ્રાન્ચ',
        'LITE BRANCH' => 'લાઇટ બ્રાન્ચ',
        'LIGHT BRANCH' => 'લાઇટ બ્રાન્ચ',
        'ARMOR HYDRANT BODY' => 'આરમર હાઇડ્રેન્ટ બોડી',
        'TURNING BUSH' => 'ટર્નિંગ બુશ',
        'LURNING BUSH' => 'ટર્નિંગ બુશ',
        'STANDARD BONNET' => 'સ્ટાન્ડર્ડ બોનેટ',
        'NRV' => 'એન.આર.વી.',
        'BONNET' => 'બોનેટ',
        'STEM' => 'સ્ટેમ',
        'SPINDLE' => 'સ્પિન્ડલ',
        'FLANGE' => 'ફ્લેન્જ',
        'BODY' => 'બોડી',
        'COVER' => 'કવર',
        'HANDLE' => 'હેન્ડલ',
        'VALVE' => 'વાલ્વ',
    ];
    $key = strtoupper($process);
    if (isset($map[$key])) {
        return $map[$key];
    }
    foreach ($map as $en => $gu) {
        if (strcasecmp($en, $process) === 0) {
            return $gu;
        }
    }
    return $process;
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

    $conn->query("CREATE TABLE IF NOT EXISTS contractor_product_rate_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        sheet_id INT NOT NULL DEFAULT 0,
        operation VARCHAR(80) NOT NULL DEFAULT '',
        month_no TINYINT NOT NULL DEFAULT 0,
        year_no SMALLINT NOT NULL DEFAULT 0,
        old_rate DECIMAL(12, 4) NOT NULL DEFAULT 0,
        new_rate DECIMAL(12, 4) NOT NULL DEFAULT 0,
        remark TEXT NOT NULL,
        changed_by INT NOT NULL DEFAULT 0,
        changed_by_name VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cprh_product (product_id, created_at),
        INDEX idx_cprh_sheet (sheet_id),
        INDEX idx_cprh_op (operation, year_no, month_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    contractorEnsureColumn($conn, 'contractor_operation_items', 'grade_id', 'grade_id INT DEFAULT NULL AFTER product_id');
    contractorEnsureColumn($conn, 'contractor_operation_items', 'argon_rate', 'argon_rate DECIMAL(12, 4) NOT NULL DEFAULT 0 AFTER rejection_rate');
    contractorEnsureColumn($conn, 'contractor_products', 'argon_rate', 'argon_rate DECIMAL(12, 4) NOT NULL DEFAULT 0 AFTER rejection_rate');
    contractorEnsureColumn($conn, 'contractor_product_rate_history', 'rate_kind', "rate_kind VARCHAR(20) NOT NULL DEFAULT 'qty' AFTER year_no");
    contractorEnsureColumn($conn, 'contractor_employment', 'sub_department_id', 'sub_department_id INT DEFAULT NULL AFTER department_id');

    if ($closeAfter) {
        $conn->close();
    }
}

/**
 * Log a Contract Process / product rate change with remark (history).
 *
 * @return array{ok:bool,id:int,message:string}
 */
function contractorSaveProductRateHistory($conn, array $data)
{
    ensureContractorTables($conn);
    $productId = (int) ($data['product_id'] ?? 0);
    $remark = trim((string) ($data['remark'] ?? ''));
    $oldRate = (float) ($data['old_rate'] ?? 0);
    $newRate = (float) ($data['new_rate'] ?? 0);
    if ($productId <= 0) {
        return ['ok' => false, 'id' => 0, 'message' => 'Product required.'];
    }
    if ($remark === '') {
        return ['ok' => false, 'id' => 0, 'message' => 'Remark required.'];
    }
    if (abs($oldRate - $newRate) < 0.0000001) {
        return ['ok' => false, 'id' => 0, 'message' => 'Rate unchanged.'];
    }

    $sheetId = (int) ($data['sheet_id'] ?? 0);
    $operation = trim((string) ($data['operation'] ?? ''));
    $monthNo = (int) ($data['month_no'] ?? 0);
    $yearNo = (int) ($data['year_no'] ?? 0);
    $changedBy = (int) ($data['changed_by'] ?? ($_SESSION['user_id'] ?? 0));
    $changedByName = trim((string) ($data['changed_by_name'] ?? ''));
    if ($changedByName === '') {
        $changedByName = trim((string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'));
    }

    $rateKind = strtolower(trim((string) ($data['rate_kind'] ?? 'qty')));
    if (!in_array($rateKind, ['qty', 'rework', 'argon'], true)) {
        $rateKind = 'qty';
    }

    // product_id i, sheet_id i, operation s, month_no i, year_no i, old_rate d, new_rate d, remark s, changed_by i, changed_by_name s, rate_kind s
    $st = $conn->prepare(
        'INSERT INTO contractor_product_rate_history
            (product_id, sheet_id, operation, month_no, year_no, old_rate, new_rate, remark, changed_by, changed_by_name, rate_kind)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    if (!$st) {
        return ['ok' => false, 'id' => 0, 'message' => 'Prepare failed.'];
    }
    $st->bind_param(
        'iisiiddsiss',
        $productId,
        $sheetId,
        $operation,
        $monthNo,
        $yearNo,
        $oldRate,
        $newRate,
        $remark,
        $changedBy,
        $changedByName,
        $rateKind
    );
    $ok = $st->execute();
    $err = $st->error;
    $id = (int) $conn->insert_id;
    $st->close();
    if (!$ok) {
        return ['ok' => false, 'id' => 0, 'message' => $err !== '' ? $err : 'Insert failed.'];
    }

    if (!empty($data['update_master'])) {
        if ($rateKind === 'rework') {
            $up = $conn->prepare('UPDATE contractor_products SET rejection_rate = ? WHERE id = ? AND status = 1');
        } elseif ($rateKind === 'argon') {
            $up = $conn->prepare('UPDATE contractor_products SET argon_rate = ? WHERE id = ? AND status = 1');
        } else {
            $up = $conn->prepare('UPDATE contractor_products SET rate = ? WHERE id = ? AND status = 1');
        }
        if ($up) {
            $up->bind_param('di', $newRate, $productId);
            $up->execute();
            $up->close();
        }
    }

    if ($sheetId > 0) {
        $stItems = $conn->prepare(
            'SELECT id, rate, ot_rate, rejection_rate, argon_rate, days_json
             FROM contractor_operation_items
             WHERE sheet_id = ? AND product_id = ?'
        );
        if ($stItems) {
            $stItems->bind_param('ii', $sheetId, $productId);
            $stItems->execute();
            $itemRes = $stItems->get_result();
            $upd = $conn->prepare(
                'UPDATE contractor_operation_items
                 SET rate = ?, rejection_rate = ?, argon_rate = ?, total_amount = ?
                 WHERE id = ?'
            );
            while ($item = $itemRes->fetch_assoc()) {
                $days = json_decode((string) ($item['days_json'] ?? ''), true);
                if (!is_array($days)) {
                    $days = [];
                }
                $itemRate = (float) ($item['rate'] ?? 0);
                $itemRej = (float) ($item['rejection_rate'] ?? 0);
                $itemArgon = (float) ($item['argon_rate'] ?? 0);
                if ($rateKind === 'rework') {
                    $itemRej = $newRate;
                } elseif ($rateKind === 'argon') {
                    $itemArgon = $newRate;
                } else {
                    $itemRate = $newRate;
                }
                $calc = calculateContractorRow(
                    $operation !== '' ? $operation : 'CNC',
                    $itemRate,
                    (float) ($item['ot_rate'] ?? 0),
                    $itemRej,
                    $days,
                    $itemArgon
                );
                $itemAmt = (float) ($calc['total_amount'] ?? 0);
                $itemId = (int) $item['id'];
                if ($upd) {
                    $upd->bind_param('ddddi', $itemRate, $itemRej, $itemArgon, $itemAmt, $itemId);
                    $upd->execute();
                }
            }
            if ($upd) {
                $upd->close();
            }
            $stItems->close();
            $sum = $conn->prepare(
                'SELECT COALESCE(SUM(total_qty),0) AS q, COALESCE(SUM(total_r),0) AS r, COALESCE(SUM(total_amount),0) AS a
                 FROM contractor_operation_items WHERE sheet_id = ?'
            );
            if ($sum) {
                $sum->bind_param('i', $sheetId);
                $sum->execute();
                $tot = $sum->get_result()->fetch_assoc() ?: [];
                $sum->close();
                $q = (float) ($tot['q'] ?? 0);
                $r = (float) ($tot['r'] ?? 0);
                $a = (float) ($tot['a'] ?? 0);
                $upSheet = $conn->prepare(
                    'UPDATE contractor_operation_sheets SET total_qty = ?, total_r = ?, total_amount = ? WHERE id = ? AND status = 1'
                );
                if ($upSheet) {
                    $upSheet->bind_param('dddi', $q, $r, $a, $sheetId);
                    $upSheet->execute();
                    $upSheet->close();
                }
            }
        }
    }

    return ['ok' => true, 'id' => $id, 'message' => 'Rate remark saved.'];
}

/**
 * @return list<array<string,mixed>>
 */
function contractorProductRateHistoryList($conn, $productId, $limit = 20, $rateKind = '')
{
    ensureContractorTables($conn);
    $productId = (int) $productId;
    $limit = max(1, min(100, (int) $limit));
    $rateKind = strtolower(trim((string) $rateKind));
    if (!in_array($rateKind, ['qty', 'rework', 'argon'], true)) {
        $rateKind = '';
    }
    if ($productId <= 0) {
        return [];
    }
    $sql = 'SELECT id, product_id, sheet_id, operation, month_no, year_no, rate_kind, old_rate, new_rate,
                   remark, changed_by, changed_by_name, created_at
            FROM contractor_product_rate_history
            WHERE product_id = ?';
    if ($rateKind !== '') {
        $sql .= ' AND rate_kind = ?';
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $st = $conn->prepare($sql);
    if (!$st) {
        return [];
    }
    if ($rateKind !== '') {
        $st->bind_param('is', $productId, $rateKind);
    } else {
        $st->bind_param('i', $productId);
    }
    $st->execute();
    $res = $st->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $st->close();
    return $out;
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
function calculateContractorRow($operation, $rate, $otRate, $rejRate, array $days, $argonRate = 0.0)
{
    $repairOps = contractorRepairOps();
    $otRepairOps = contractorOtRepairOps();
    $normalQty = 0.0;
    $rQty = 0.0;
    $otQty = 0.0;
    $apQty = 0.0;

    foreach ($days as $cell) {
        if (!is_array($cell)) {
            continue;
        }
        $normalQty += (float) ($cell['q'] ?? 0);
        $rQty += (float) ($cell['r'] ?? 0);
        $otQty += (float) ($cell['ot'] ?? 0);
        $apQty += (float) ($cell['ap'] ?? 0);
    }

    if (!in_array($operation, $repairOps, true)) {
        $rQty = 0.0;
    }
    if (!in_array($operation, $otRepairOps, true)) {
        $otQty = 0.0;
    }

    // Total Qty is the Q column only. OT is counted in its own total.
    $totalQty = $normalQty;
    $amount = 0.0;
    if ($operation === 'FOUNDRY') {
        // Foundry: Day (q) and Night (r) kept separate; amount = (D+N) × rate
        $amount = ($normalQty + $rQty) * $rate;
    } elseif (strcasecmp((string) $operation, 'BUFF') === 0) {
        // Q × Qty Rate + RW × Rework Rate + AP × Argon Polish Rate
        $amount = ($normalQty * $rate) + ($rQty * $rejRate) + ($apQty * (float) $argonRate);
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
 * Map Ops Rate List operation → Armor ERP Production department name.
 */
function contractorOperationErpDepartmentName($operation)
{
    $map = [
        'CNC' => 'Cnc Department',
        'BUFF' => 'Buff Department',
        'GRINDING' => 'Grinding Department',
        'Lathe Employee wise' => 'Lathe Department',
        'COATING' => 'Coating Department',
        'CUTTING' => 'Knock Out / Cutting Department',
        'CORE' => 'Core Department',
        'FOUNDRY' => 'Foundry-1 (Mahadev) Department',
        'CLEANING' => 'Shot blasting/Cleaning Department',
        'ASS-1' => 'Assembly 1 Department',
        'ASS-2' => 'Assembly 2 Department',
        'RRL' => 'Assembly 3 Department',
        'FLEXIBLE' => 'Assembly 3 Department',
        'BUTTERFLY' => 'Assembly 3 Department',
        'ARGON WELDING' => 'Buff Department',
    ];
    $op = trim((string) $operation);
    return $map[$op] ?? '';
}

/**
 * Canonical CNC Production Planning KRA part names (Armor ERP CNC dept).
 * Merged with live ERP rows so Contract Process stays filled even if local ERP snapshot is thin.
 *
 * @return list<string>
 */
function contractorCncKraBaselinePartNames()
{
    return [
        'Lite Female',
        'Lite male',
        'Heavy Female',
        'Heavy Male',
        'Lite branch',
        'Heavy branch',
        'Lite Nozzle',
        'Heavy Nozzle',
        'Armor Hydrant Body',
        'Heavy Adaptor',
        'Lite Adaptor',
        'Turning Bush',
        'STANDARD BONNET',
        'NRV',
        'Safex',
    ];
}

/**
 * Buff Production Planning KRA part hierarchy (same order as the ERP part-name column).
 *
 * @return list<string>
 */
function contractorBuffKraPartOrder()
{
    return [
        'Lite Female',
        'Lite male',
        'Heavy Female',
        'Heavy Male',
        'Lite branch',
        'Heavy Branch',
        'Lite Nozzel',
        'Heavy Nozzel',
        'Armor Hydrant Body',
        'Safegaurd Hydrant Body',
        'Other Brand body',
        'Heavy Adaptor',
        'Lite Adaptor',
        'Lurning Bush',
    ];
}

/**
 * Sort index for a Buff KRA part. Names outside the hierarchy sort after it.
 */
function contractorBuffOtherBrandLabel($brandName)
{
    $brandName = trim((string) $brandName);
    if ($brandName === '') {
        return '';
    }
    return 'Other Brand — ' . $brandName;
}

function contractorBuffOtherBrandFromLabel($label)
{
    $label = trim((string) $label);
    if (preg_match('/^other\s*brand\s*[—–\-]+\s*(.+)$/iu', $label, $m)) {
        return trim((string) $m[1]);
    }
    return '';
}

/**
 * Other Brand rows selected on Buff Production Planning KRA for this month.
 * KRA always has this slot (dropdown + Add); the part name is "Other Brand — Safex".
 *
 * @return list<string>
 */
function contractorErpBuffOtherBrandLabels($erp, $deptId, $docId, $asOf)
{
    $deptId = (int) $deptId;
    $docId = (int) $docId;
    $labels = [];
    $add = static function ($brand) use (&$labels) {
        $label = contractorBuffOtherBrandLabel($brand);
        if ($label === '') {
            return;
        }
        $k = strtolower($label);
        if (!isset($labels[$k])) {
            $labels[$k] = $label;
        }
    };
    if (!$erp || $deptId <= 0 || $docId <= 0) {
        return [];
    }
    $monthStart = '';
    $monthEnd = '';
    if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', (string) $asOf, $m)) {
        $monthStart = $m[1] . '-' . $m[2] . '-01';
        $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));
    }

    if ($monthStart !== '') {
        $sql = "SELECT DISTINCT TRIM(ob.brand_name) AS brand_name
                FROM production_entries pe
                JOIN sub_items s ON s.id = pe.sub_item_id
                JOIN other_brands_master ob ON ob.id = NULLIF(pe.other_brand_id, 0)
                WHERE pe.department_id = ?
                  AND pe.document_id = ?
                  AND pe.entry_date >= ?
                  AND pe.entry_date < ?
                  AND TRIM(LOWER(s.product_name)) IN ('other brand', 'other brand body')
                  AND TRIM(IFNULL(ob.brand_name, '')) <> ''";
        $st = $erp->prepare($sql);
        if ($st) {
            $st->bind_param('iiss', $deptId, $docId, $monthStart, $monthEnd);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($row = $res->fetch_assoc()) {
                    $add($row['brand_name'] ?? '');
                }
            }
            $st->close();
        }
    }

    $setRes = $erp->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'selected_kra_variant_%'");
    $suffix = '_' . $docId;
    if ($setRes) {
        while ($row = $setRes->fetch_assoc()) {
            $key = (string) ($row['setting_key'] ?? '');
            $prefix = 'selected_kra_variant_';
            if (strpos($key, $prefix) !== 0 || substr($key, -strlen($suffix)) !== $suffix) {
                continue;
            }
            $date = substr($key, strlen($prefix), 10);
            if ($monthStart !== '' && ($date < $monthStart || $date >= $monthEnd)) {
                continue;
            }
            $add($row['setting_value'] ?? '');
        }
    }

    if (!$labels) {
        $st = $erp->prepare('SELECT TRIM(brand_name) AS brand_name FROM other_brands_master WHERE TRIM(IFNULL(brand_name, \'\')) <> \'\' ORDER BY brand_name ASC LIMIT 1');
        if ($st && $st->execute()) {
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $add($row['brand_name'] ?? '');
        }
    }
    return array_values($labels);
}

function contractorBuffKraSortRank($productName)
{
    if (contractorBuffOtherBrandFromLabel($productName) !== '') {
        return 20000;
    }
    static $rank = null;
    if ($rank === null) {
        $rank = [];
        foreach (contractorBuffKraPartOrder() as $i => $pn) {
            $ck = contractorKraCanonicalKey($pn);
            if ($ck !== '' && !isset($rank[$ck])) {
                $rank[$ck] = $i;
            }
            foreach (contractorKraPartNameLookupCandidates($pn) as $al) {
                $k = strtolower(trim((string) $al));
                if ($k !== '' && !isset($rank[$k])) {
                    $rank[$k] = $i;
                }
            }
        }
    }
    $name = trim((string) $productName);
    if ($name === '') {
        return 10000;
    }
    $ck = contractorKraCanonicalKey($name);
    if ($ck !== '' && isset($rank[$ck])) {
        return $rank[$ck];
    }
    $k = strtolower($name);
    return $rank[$k] ?? 10000;
}

/**
 * Canonical key => alternate spellings (ERP typos / HRMS Product Master variants).
 *
 * @return array<string, list<string>>
 */
function contractorKraPartNameAliasMap()
{
    return [
        'armor hydrant body' => ['ARMOR BODY', 'Armor Body', 'ARMOR HYDRANT BODY', 'Armor Hydrant Body'],
        'heavy nozzle' => ['HEAVY NOZZEL', 'Heavy Nozzel', 'HEAVY NOZZLE', 'Heavy Nozzle'],
        'lite nozzle' => ['LITE NOZZEL', 'Lite Nozzel', 'LITE NOZZLE', 'Lite Nozzle'],
        'nrv' => ['HEAVY NRV', 'Heavy NRV', 'NRV'],
        'turning bush' => ['Lurning Bush', 'LURNING BUSH', 'TURNING BUSH', 'Turning Bush'],
        'lite male' => ['LITE MALE', 'Lite Male', 'Lite male'],
        'heavy male' => ['HEAVY MALE', 'Heavy Male'],
        'lite female' => ['LITE FEMALE', 'Lite Female'],
        'heavy female' => ['HEAVY FEMALE', 'Heavy Female'],
        'lite branch' => ['LITE BRANCH', 'Lite Branch'],
        'heavy branch' => ['HEAVY BRANCH', 'Heavy Branch'],
        'heavy adaptor' => ['HEAVY ADAPTOR', 'Heavy Adaptor'],
        'lite adaptor' => ['LITE ADAPTOR', 'Lite Adaptor'],
        'safex' => ['SAFEX', 'Safex'],
        'standard bonnet' => ['STANDARD BONNET', 'Standard Bonnet'],
    ];
}

/**
 * Normalize any ERP/HRMS part spelling to one lowercase canonical key.
 */
function contractorKraCanonicalKey($productName)
{
    $key = strtolower(trim((string) $productName));
    if ($key === '') {
        return '';
    }
    static $rev = null;
    if ($rev === null) {
        $rev = [];
        foreach (contractorKraPartNameAliasMap() as $canon => $aliases) {
            $rev[$canon] = $canon;
            foreach ($aliases as $al) {
                $rev[strtolower(trim((string) $al))] = $canon;
            }
        }
    }
    return $rev[$key] ?? $key;
}

/**
 * Alternate spellings for DB lookup (bidirectional — works for "Lurning Bush" and "Turning Bush").
 *
 * @return list<string>
 */
function contractorKraPartNameAliases($productName)
{
    $map = contractorKraPartNameAliasMap();
    $ck = contractorKraCanonicalKey($productName);
    return isset($map[$ck]) ? array_values($map[$ck]) : [];
}

/**
 * All strings to try when matching contractor_products for a KRA part name.
 *
 * @return list<string>
 */
function contractorKraPartNameLookupCandidates($productName)
{
    $pn = trim((string) $productName);
    $ck = contractorKraCanonicalKey($pn);
    $cands = [];
    if ($pn !== '') {
        $cands[] = $pn;
    }
    if ($ck !== '') {
        $cands[] = $ck;
    }
    foreach (contractorKraPartNameAliases($pn) as $al) {
        $al = trim((string) $al);
        if ($al !== '') {
            $cands[] = $al;
        }
    }
    $out = [];
    $seen = [];
    foreach ($cands as $c) {
        $k = strtolower($c);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $c;
    }
    return $out;
}

/**
 * Pull Production Planning KRA part names from Armor ERP into contractor_products.
 * New ERP part names appear in Contract Process; existing rates are kept.
 *
 * @param string|null $asOfDate Y-m-d — use ERP snapshot on/before this date (e.g. 2026-11-02)
 * @return array{ok:bool,added:int,updated:int,total:int,message:string,names?:list<string>,as_of?:string}
 */
function syncErpKraPartsToContractorProducts($operation, $hrmsConn = null, $asOfDate = null)
{
    $operation = trim((string) $operation);
    $out = [
        'ok' => false,
        'added' => 0,
        'updated' => 0,
        'total' => 0,
        'message' => '',
        'names' => [],
        'as_of' => '',
    ];
    $deptName = contractorOperationErpDepartmentName($operation);
    if ($operation === '' || $deptName === '') {
        $out['message'] = 'Operation has no ERP department mapping.';
        return $out;
    }

    $asOf = '';
    if (is_string($asOfDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
        $asOf = $asOfDate;
    }
    $out['as_of'] = $asOf;

    $appendName = static function (array &$bucket, $pn) {
        $pn = trim((string) $pn);
        if ($pn === '' || preg_match('/^other\s*brand/i', $pn)) {
            return;
        }
        $bucket[] = $pn;
    };

    $names = [];
    $rateMap = [];
    $erpNote = '';
    $erpSnapNames = [];
    $buffOtherBrandLabels = [];

    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        $erpNote = 'ERP database not reachable';
    } else {
        try {
            $deptId = 0;
            $docId = 0;
            $st = $erp->prepare(
                'SELECT id FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(?) LIMIT 1'
            );
            if ($st) {
                $st->bind_param('s', $deptName);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                $deptId = (int) ($row['id'] ?? 0);
            }
            if ($deptId <= 0) {
                $erpNote = 'ERP department not found: ' . $deptName;
            } else {
                $st = $erp->prepare(
                    "SELECT id FROM document_master
                     WHERE department_id = ?
                       AND (
                            LOWER(document_name) LIKE '%production planning kra%'
                            OR LOWER(document_name) LIKE '%planning kra%'
                            OR UPPER(TRIM(document_number)) = 'PRO F 31'
                       )
                     ORDER BY id ASC
                     LIMIT 1"
                );
                if ($st) {
                    $st->bind_param('i', $deptId);
                    $st->execute();
                    $row = $st->get_result()->fetch_assoc();
                    $st->close();
                    $docId = (int) ($row['id'] ?? 0);
                }
                if ($docId <= 0) {
                    $erpNote = 'ERP Production Planning KRA document not found.';
                } else {
                    // Live + as-of: pull latest snapshot AND as-of snapshot so ERP "Add item"
                    // shows in HRMS immediately (even when sheet as_of is frozen to day 02).
                    $effAsOf = '';
                    $effLatest = '';
                    $asOfUse = $asOf !== '' ? $asOf : date('Y-m-d');
                    $pickEff = static function ($erpConn, $deptId, $docId, $asOfLimit) {
                        $eff = '';
                        if ($asOfLimit !== '') {
                            $st = $erpConn->prepare(
                                'SELECT effective_date FROM dept_report_planning_snapshots
                                 WHERE department_id = ? AND document_id = ? AND effective_date <= ?
                                 ORDER BY effective_date DESC LIMIT 1'
                            );
                            if ($st) {
                                $st->bind_param('iis', $deptId, $docId, $asOfLimit);
                                $st->execute();
                                $row = $st->get_result()->fetch_assoc();
                                $st->close();
                                $eff = substr((string) ($row['effective_date'] ?? ''), 0, 10);
                            }
                            if ($eff === '') {
                                $st = $erpConn->prepare(
                                    'SELECT effective_date FROM dept_report_planning
                                     WHERE department_id = ? AND document_id = ? AND effective_date <= ?
                                     ORDER BY effective_date DESC LIMIT 1'
                                );
                                if ($st) {
                                    $st->bind_param('iis', $deptId, $docId, $asOfLimit);
                                    $st->execute();
                                    $row = $st->get_result()->fetch_assoc();
                                    $st->close();
                                    $eff = substr((string) ($row['effective_date'] ?? ''), 0, 10);
                                }
                            }
                        }
                        if ($eff === '') {
                            $st = $erpConn->prepare(
                                'SELECT MAX(effective_date) AS mx FROM dept_report_planning
                                 WHERE department_id = ? AND document_id = ?'
                            );
                            if ($st) {
                                $st->bind_param('ii', $deptId, $docId);
                                $st->execute();
                                $row = $st->get_result()->fetch_assoc();
                                $st->close();
                                $eff = substr((string) ($row['mx'] ?? ''), 0, 10);
                            }
                        }
                        return $eff;
                    };
                    $effAsOf = $pickEff($erp, $deptId, $docId, $asOfUse);
                    $effLatest = $pickEff($erp, $deptId, $docId, '');
                    $out['as_of'] = $effLatest !== '' ? $effLatest : ($effAsOf !== '' ? $effAsOf : $asOfUse);

                    $loadSnapNames = static function ($erpConn, $deptId, $docId, $eff, array &$bucket, $appendName) {
                        if ($eff === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eff)) {
                            return 0;
                        }
                        $n = 0;
                        $st = $erpConn->prepare(
                            'SELECT TRIM(product_name) AS product_name
                             FROM dept_report_planning
                             WHERE department_id = ? AND document_id = ? AND effective_date = ?
                             ORDER BY id ASC'
                        );
                        if ($st) {
                            $st->bind_param('iis', $deptId, $docId, $eff);
                            $st->execute();
                            $res = $st->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $before = count($bucket);
                                $appendName($bucket, $row['product_name'] ?? '');
                                if (count($bucket) > $before) {
                                    $n++;
                                }
                            }
                            $st->close();
                        }
                        return $n;
                    };
                    $nLatest = $loadSnapNames($erp, $deptId, $docId, $effLatest, $erpSnapNames, $appendName);
                    if ($effAsOf !== '' && $effAsOf !== $effLatest) {
                        $loadSnapNames($erp, $deptId, $docId, $effAsOf, $erpSnapNames, $appendName);
                    }

                    // Always merge product master — ERP "Add item" often lands here first
                    $masterCount = 0;
                    $st = $erp->prepare(
                        'SELECT TRIM(product_name) AS product_name
                         FROM dept_report_products
                         WHERE department_id = ? AND document_id = ?
                         ORDER BY id ASC'
                    );
                    if ($st) {
                        $st->bind_param('ii', $deptId, $docId);
                        $st->execute();
                        $res = $st->get_result();
                        while ($row = $res->fetch_assoc()) {
                            $before = count($erpSnapNames);
                            $appendName($erpSnapNames, $row['product_name'] ?? '');
                            if (count($erpSnapNames) > $before) {
                                $masterCount++;
                            }
                        }
                        $st->close();
                    }

                    $chk = $erp->query("SHOW TABLES LIKE 'dept_master_rates'");
                    if ($chk && $chk->num_rows > 0) {
                        $st = $erp->prepare(
                            'SELECT product_key, product_name, rate FROM dept_master_rates WHERE department_id = ?'
                        );
                        if ($st) {
                            $st->bind_param('i', $deptId);
                            $st->execute();
                            $res = $st->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $key = strtolower(trim((string) ($row['product_key'] ?? $row['product_name'] ?? '')));
                                if ($key !== '') {
                                    $rateMap[$key] = (float) ($row['rate'] ?? 0);
                                }
                            }
                            $st->close();
                        }
                    }
                    if (strcasecmp($operation, 'BUFF') === 0 && $deptId > 0 && $docId > 0) {
                        $buffOtherBrandLabels = contractorErpBuffOtherBrandLabels(
                            $erp,
                            $deptId,
                            $docId,
                            $asOfUse
                        );
                    }
                    $uniqSnap = count(array_unique(array_map('strtolower', $erpSnapNames)));
                    $erpNote = 'ERP OK latest=' . ($effLatest !== '' ? $effLatest : 'none')
                        . ' asof=' . ($effAsOf !== '' ? $effAsOf : 'none')
                        . ' snap=' . $nLatest
                        . ' master+=' . $masterCount
                        . ' parts=' . $uniqSnap;
                }
            }
        } catch (Throwable $e) {
            $erpNote = 'ERP error: ' . $e->getMessage();
        }
        // Do NOT close shared getErpDBConnection() handle — other callers reuse the static cache.
    }

    // Prefer ERP snapshot order; then CNC baseline (covers thin local ERP / missing live pull)
    foreach ($erpSnapNames as $pn) {
        $appendName($names, $pn);
    }
    foreach ($buffOtherBrandLabels as $pn) {
        $names[] = $pn;
    }
    if (strcasecmp($operation, 'CNC') === 0) {
        foreach (contractorCncKraBaselinePartNames() as $pn) {
            $appendName($names, $pn);
        }
    }

    // Deduplicate by canonical key (Lurning Bush ≡ Turning Bush); keep first casing (ERP snap first)
    $uniq = [];
    foreach ($names as $pn) {
        $key = contractorKraCanonicalKey($pn);
        if ($key === '') {
            continue;
        }
        if (!isset($uniq[$key])) {
            $uniq[$key] = $pn;
        }
    }
    $names = array_values($uniq);
    $out['names'] = $names;
    $out['total'] = count($names);
    if (!$names) {
        $out['ok'] = true;
        $out['message'] = 'No KRA part names found' . ($erpNote ? (' (' . $erpNote . ')') : '') . '.';
        return $out;
    }

    $closeAfter = false;
    if ($hrmsConn === null) {
        $hrmsConn = getDBConnection();
        $closeAfter = true;
    }
    ensureContractorTables($hrmsConn);

    foreach ($names as $pn) {
        $key = contractorKraCanonicalKey($pn);
        $erpRate = (float) ($rateMap[$key] ?? $rateMap[strtolower($pn)] ?? 0);
        $candidates = contractorKraPartNameLookupCandidates($pn);
        $exist = null;
        foreach ($candidates as $cand) {
            $st = $hrmsConn->prepare(
                "SELECT id, rate FROM contractor_products
                 WHERE status = 1 AND operation = ?
                   AND (UPPER(TRIM(product_name)) = UPPER(?) OR UPPER(TRIM(IFNULL(process,''))) = UPPER(?))
                 LIMIT 1"
            );
            $st->bind_param('sss', $operation, $cand, $cand);
            $st->execute();
            $exist = $st->get_result()->fetch_assoc();
            $st->close();
            if ($exist) {
                break;
            }
        }
        if ($exist) {
            // Keep HRMS rate unless it is 0 and ERP has a rate
            $curRate = (float) ($exist['rate'] ?? 0);
            if ($curRate <= 0 && $erpRate > 0) {
                $up = $hrmsConn->prepare('UPDATE contractor_products SET rate = ? WHERE id = ?');
                $id = (int) $exist['id'];
                $up->bind_param('di', $erpRate, $id);
                $up->execute();
                $up->close();
                $out['updated']++;
            }
            continue;
        }
        $rate = $erpRate;
        $ins = $hrmsConn->prepare(
            "INSERT INTO contractor_products
                (operation, product_name, process, unit, rate, ot_text, rejection_rate, status)
             VALUES (?, ?, ?, 'PCS', ?, NULL, 0, 1)"
        );
        $ins->bind_param('sssd', $operation, $pn, $pn, $rate);
        $ins->execute();
        $ins->close();
        $out['added']++;
    }

    if ($closeAfter) {
        $hrmsConn->close();
    }
    $out['ok'] = true;
    $out['message'] = 'Synced ' . $out['total'] . ' KRA parts'
        . ($out['added'] ? (', +' . $out['added'] . ' new') : '')
        . ($out['updated'] ? (', ' . $out['updated'] . ' rates') : '')
        . ($erpNote ? (' [' . $erpNote . ']') : '') . '.';
    return $out;
}

/**
 * ERP Production Planning KRA — date-wise Total qty (sum of grade cells / actual_qty).
 * Returns map: lowercase part-name => [ '1' => qty, '2' => qty, ... ] for the month.
 *
 * @return array<string, array<string, float>>
 */
function fetchErpKraDateWiseQtys($operation, $year, $month)
{
    $operation = trim((string) $operation);
    $year = (int) $year;
    $month = (int) $month;
    $out = [];

    if ($operation === '' || $year < 2000 || $month < 1 || $month > 12) {
        return $out;
    }
    $deptName = contractorOperationErpDepartmentName($operation);
    if ($deptName === '') {
        return $out;
    }
    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        return $out;
    }

    try {
        $deptId = 0;
        $docId = 0;
        $st = $erp->prepare(
            'SELECT id FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(?) LIMIT 1'
        );
        if ($st) {
            $st->bind_param('s', $deptName);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $deptId = (int) ($row['id'] ?? 0);
        }
        if ($deptId <= 0) {
            return $out;
        }
        $st = $erp->prepare(
            "SELECT id FROM document_master
             WHERE department_id = ?
               AND (
                    LOWER(document_name) LIKE '%production planning kra%'
                    OR LOWER(document_name) LIKE '%planning kra%'
                    OR UPPER(TRIM(document_number)) = 'PRO F 31'
               )
             ORDER BY id ASC LIMIT 1"
        );
        if ($st) {
            $st->bind_param('i', $deptId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $docId = (int) ($row['id'] ?? 0);
        }
        if ($docId <= 0) {
            return $out;
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $toExcl = date('Y-m-d', strtotime($from . ' +1 month'));

        // Row-level fetch (avoid ONLY_FULL_GROUP_BY issues) then aggregate in PHP
        $sql = "SELECT pe.entry_date,
                       TRIM(IFNULL(s.product_name, '')) AS product_name,
                       TRIM(IFNULL(ob.brand_name, '')) AS brand_name,
                       CAST(IFNULL(pe.actual_qty, 0) AS DECIMAL(18,2)) AS qty
                FROM production_entries pe
                LEFT JOIN sub_items s ON s.id = pe.sub_item_id
                LEFT JOIN other_brands_master ob
                       ON ob.id = COALESCE(NULLIF(pe.other_brand_id, 0), NULLIF(s.other_brand_id, 0))
                WHERE pe.department_id = ?
                  AND pe.document_id = ?
                  AND pe.entry_date >= ?
                  AND pe.entry_date < ?
                  AND CAST(IFNULL(pe.actual_qty, 0) AS DECIMAL(18,2)) <> 0";
        $st = $erp->prepare($sql);
        if (!$st) {
            return $out;
        }
        $st->bind_param('iiss', $deptId, $docId, $from, $toExcl);
        if (!$st->execute()) {
            $st->close();
            return $out;
        }
        $res = $st->get_result();
        $rawRows = 0;
        $accumulate = static function (array &$bucket, array $row) {
            $d = (int) date('j', strtotime((string) ($row['entry_date'] ?? '')));
            if ($d < 1 || $d > 31) {
                return 0;
            }
            $qty = (float) ($row['qty'] ?? 0);
            if ($qty == 0.0) {
                return 0;
            }
            $pn = trim((string) ($row['product_name'] ?? ''));
            $brand = trim((string) ($row['brand_name'] ?? ''));
            // One key only — alias fan-out here + again in edit.php caused 3×/4× qty.
            // Other Brand qty belongs to "Other Brand — Safex", not the bare brand name.
            $k = '';
            if ($pn !== '' && preg_match('/^other\s*brand/i', $pn)) {
                $k = $brand !== '' ? strtolower(contractorBuffOtherBrandLabel($brand)) : '';
            } elseif ($pn !== '') {
                $k = strtolower($pn);
            }
            if ($k === '') {
                return 0;
            }
            if (!isset($bucket[$k])) {
                $bucket[$k] = [];
            }
            $dk = (string) $d;
            $bucket[$k][$dk] = ($bucket[$k][$dk] ?? 0) + $qty;
            return 1;
        };
        while ($row = $res->fetch_assoc()) {
            $rawRows += $accumulate($out, $row);
        }
        $st->close();

        // Fallback: same dept + month without document_id (if KRA doc id drifted)
        if ($rawRows === 0) {
            $sql2 = "SELECT pe.entry_date,
                            TRIM(IFNULL(s.product_name, '')) AS product_name,
                            TRIM(IFNULL(ob.brand_name, '')) AS brand_name,
                            CAST(IFNULL(pe.actual_qty, 0) AS DECIMAL(18,2)) AS qty
                     FROM production_entries pe
                     LEFT JOIN sub_items s ON s.id = pe.sub_item_id
                     LEFT JOIN other_brands_master ob
                            ON ob.id = COALESCE(NULLIF(pe.other_brand_id, 0), NULLIF(s.other_brand_id, 0))
                     WHERE pe.department_id = ?
                       AND pe.entry_date >= ?
                       AND pe.entry_date < ?
                       AND CAST(IFNULL(pe.actual_qty, 0) AS DECIMAL(18,2)) <> 0";
            $st2 = $erp->prepare($sql2);
            if ($st2) {
                $st2->bind_param('iss', $deptId, $from, $toExcl);
                if ($st2->execute()) {
                    $res2 = $st2->get_result();
                    while ($row = $res2->fetch_assoc()) {
                        $rawRows += $accumulate($out, $row);
                    }
                }
                $st2->close();
            }
        }
    } catch (Throwable $e) {
        // Keep shared ERP connection open; return whatever was accumulated.
    }
    // Keep shared ERP connection open (static cache in getErpDBConnection).
    return $out;
}

/**
 * Merge ERP date-wise qty into a days map (does not overwrite non-empty saved q).
 *
 * @param array<string,array> $days
 * @param array<string,float> $erpDayQty day => qty
 * @return array<string,array>
 */
function mergeErpKraDaysIntoMap(array $days, array $erpDayQty, $overwriteErpDays = false)
{
    foreach ($erpDayQty as $d => $qty) {
        $d = (string) ((int) $d);
        $qty = (float) $qty;
        if ($qty == 0.0) {
            continue;
        }
        if (!isset($days[$d]) || !is_array($days[$d])) {
            $days[$d] = ['q' => '', 'r' => '', 'ot' => ''];
        }
        $cur = $days[$d]['q'] ?? '';
        $empty = ($cur === '' || $cur === null || (is_numeric($cur) && (float) $cur == 0.0));
        // overwriteErpDays=true: always use ERP KRA Total for that day (fixes alias-tripled bad saves)
        if ($overwriteErpDays || $empty) {
            $days[$d]['q'] = $qty;
        }
    }
    return $days;
}

/**
 * ERP Buff Production Planning KRA — date-wise Rework (RW) and Argon Polish (AP).
 * Returns map: lowercase product_key => [ '1' => ['rw' => qty, 'ap' => qty], ... ].
 *
 * @return array<string, array<string, array{rw:float,ap:float}>>
 */
function fetchErpBuffKraDayExtras($year, $month)
{
    $year = (int) $year;
    $month = (int) $month;
    $out = [];
    if ($year < 2000 || $month < 1 || $month > 12) {
        return $out;
    }
    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        return $out;
    }

    try {
        $deptName = contractorOperationErpDepartmentName('BUFF');
        $deptId = 0;
        $docId = 0;
        $st = $erp->prepare(
            'SELECT id FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(?) LIMIT 1'
        );
        if ($st) {
            $st->bind_param('s', $deptName);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $deptId = (int) ($row['id'] ?? 0);
        }
        if ($deptId <= 0) {
            return $out;
        }
        $st = $erp->prepare(
            "SELECT id FROM document_master
             WHERE department_id = ?
               AND (
                    LOWER(document_name) LIKE '%production planning kra%'
                    OR LOWER(document_name) LIKE '%planning kra%'
                    OR UPPER(TRIM(document_number)) = 'PRO F 31'
               )
             ORDER BY id ASC LIMIT 1"
        );
        if ($st) {
            $st->bind_param('i', $deptId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $docId = (int) ($row['id'] ?? 0);
        }
        if ($docId <= 0) {
            return $out;
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $toExcl = date('Y-m-d', strtotime($from . ' +1 month'));
        $add = static function (array &$bucket, $entryDate, $productKey, $field, $qty) {
            $d = (int) date('j', strtotime((string) $entryDate));
            if ($d < 1 || $d > 31) {
                return;
            }
            $qty = (float) $qty;
            if ($qty == 0.0) {
                return;
            }
            $k = strtolower(trim((string) $productKey));
            if ($k === '') {
                return;
            }
            $dk = (string) $d;
            if (!isset($bucket[$k][$dk])) {
                $bucket[$k][$dk] = ['rw' => 0.0, 'ap' => 0.0];
            }
            $bucket[$k][$dk][$field] = ($bucket[$k][$dk][$field] ?? 0) + $qty;
        };

        $hasRework = $erp->query("SHOW TABLES LIKE 'buff_kra_rework'");
        if ($hasRework && $hasRework->num_rows > 0) {
            $sql = 'SELECT entry_date, product_key, rework_qty
                    FROM buff_kra_rework
                    WHERE department_id = ? AND document_id = ?
                      AND entry_date >= ? AND entry_date < ?
                      AND rework_qty IS NOT NULL AND rework_qty <> 0';
            $st = $erp->prepare($sql);
            if ($st) {
                $st->bind_param('iiss', $deptId, $docId, $from, $toExcl);
                if ($st->execute()) {
                    $res = $st->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $add($out, $row['entry_date'] ?? '', $row['product_key'] ?? '', 'rw', $row['rework_qty'] ?? 0);
                    }
                }
                $st->close();
            }
        }

        $hasArgon = $erp->query("SHOW TABLES LIKE 'buff_kra_argon'");
        if ($hasArgon && $hasArgon->num_rows > 0) {
            $sql = 'SELECT entry_date, product_key, argon_qty
                    FROM buff_kra_argon
                    WHERE department_id = ? AND document_id = ?
                      AND entry_date >= ? AND entry_date < ?
                      AND argon_qty IS NOT NULL AND argon_qty <> 0';
            $st = $erp->prepare($sql);
            if ($st) {
                $st->bind_param('iiss', $deptId, $docId, $from, $toExcl);
                if ($st->execute()) {
                    $res = $st->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $add($out, $row['entry_date'] ?? '', $row['product_key'] ?? '', 'ap', $row['argon_qty'] ?? 0);
                    }
                }
                $st->close();
            }
        }

        $brandById = [];
        $bq = $erp->query('SELECT id, TRIM(brand_name) AS brand_name FROM other_brands_master');
        if ($bq) {
            while ($row = $bq->fetch_assoc()) {
                $brandById[(int) ($row['id'] ?? 0)] = trim((string) ($row['brand_name'] ?? ''));
            }
        }
        $aliased = [];
        foreach ($out as $k => $days) {
            if (!preg_match('/^other brand\|(\d+)$/', (string) $k, $m)) {
                continue;
            }
            $label = strtolower(contractorBuffOtherBrandLabel($brandById[(int) $m[1]] ?? ''));
            if ($label === '') {
                continue;
            }
            if (!isset($aliased[$label])) {
                $aliased[$label] = [];
            }
            foreach ($days as $dk => $pair) {
                if (!isset($aliased[$label][$dk])) {
                    $aliased[$label][$dk] = ['rw' => 0.0, 'ap' => 0.0];
                }
                $aliased[$label][$dk]['rw'] += (float) ($pair['rw'] ?? 0);
                $aliased[$label][$dk]['ap'] += (float) ($pair['ap'] ?? 0);
            }
        }
        foreach ($aliased as $label => $days) {
            $out[$label] = $days;
        }
    } catch (Throwable $e) {
        // Keep shared ERP connection open.
    }
    return $out;
}

/**
 * Rework → r, Argon Polish → ap. Non-zero ERP values overwrite the day cell.
 *
 * @param array<string,array> $days
 * @param array<string,array{rw?:float,ap?:float}> $extras day => pair
 * @return array<string,array>
 */
function mergeErpBuffExtrasIntoMap(array $days, array $extras)
{
    foreach ($extras as $d => $pair) {
        if (!is_array($pair)) {
            continue;
        }
        $d = (string) ((int) $d);
        if (!isset($days[$d]) || !is_array($days[$d])) {
            $days[$d] = ['q' => '', 'r' => '', 'ot' => '', 'ap' => ''];
        }
        if (!array_key_exists('ap', $days[$d])) {
            $days[$d]['ap'] = '';
        }
        $rw = (float) ($pair['rw'] ?? 0);
        $ap = (float) ($pair['ap'] ?? 0);
        if ($rw != 0.0) {
            $days[$d]['r'] = $rw;
        }
        if ($ap != 0.0) {
            $days[$d]['ap'] = $ap;
        }
    }
    return $days;
}

/**
 * Resolve Buff Rework / Argon day map for a KRA part name (canon, then aliases).
 *
 * @param array<string, array<string, array{rw:float,ap:float}>> $erpExtraByPart
 * @return array<string, array{rw:float,ap:float}>
 */
function contractorErpBuffExtrasForPartName($canonName, array $erpExtraByPart)
{
    $seen = [];
    foreach (contractorKraPartNameLookupCandidates($canonName) as $al) {
        $ak = strtolower(trim((string) $al));
        if ($ak === '' || isset($seen[$ak])) {
            continue;
        }
        $seen[$ak] = true;
        if (!empty($erpExtraByPart[$ak]) && is_array($erpExtraByPart[$ak])) {
            return $erpExtraByPart[$ak];
        }
        $ck = contractorKraCanonicalKey($al);
        if ($ck !== '' && $ck !== $ak && !isset($seen[$ck])
            && !empty($erpExtraByPart[$ck]) && is_array($erpExtraByPart[$ck])) {
            $seen[$ck] = true;
            return $erpExtraByPart[$ck];
        }
    }
    return [];
}

/**
 * Resolve ERP day-qty for a KRA part: try canon then unique aliases; first hit wins (no sum).
 *
 * @param array<string, array<string, float>> $erpDayQtyByPart
 * @return array<string, float>
 */
function contractorErpDaysForPartName($canonName, array $erpDayQtyByPart)
{
    $seen = [];
    foreach (contractorKraPartNameLookupCandidates($canonName) as $al) {
        $ak = strtolower(trim((string) $al));
        if ($ak === '' || isset($seen[$ak])) {
            continue;
        }
        $seen[$ak] = true;
        if (!empty($erpDayQtyByPart[$ak]) && is_array($erpDayQtyByPart[$ak])) {
            return $erpDayQtyByPart[$ak];
        }
        $ck = contractorKraCanonicalKey($al);
        if ($ck !== '' && $ck !== $ak && !isset($seen[$ck])
            && !empty($erpDayQtyByPart[$ck]) && is_array($erpDayQtyByPart[$ck])) {
            $seen[$ck] = true;
            return $erpDayQtyByPart[$ck];
        }
    }
    return [];
}

/**
 * Match a contractor_products row against a KRA part-name list (case/alias aware).
 */
function contractorProductMatchesKraNames(array $productRow, array $kraNamesLower)
{
    foreach (['product_name', 'process'] as $col) {
        $v = trim((string) ($productRow[$col] ?? ''));
        if ($v === '') {
            continue;
        }
        $ck = contractorKraCanonicalKey($v);
        if ($ck !== '' && isset($kraNamesLower[$ck])) {
            return true;
        }
        if (isset($kraNamesLower[strtolower($v)])) {
            return true;
        }
        foreach (contractorKraPartNameLookupCandidates($v) as $al) {
            $lk = strtolower(trim((string) $al));
            if ($lk !== '' && isset($kraNamesLower[$lk])) {
                return true;
            }
        }
    }
    return false;
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
    foreach (contractorKraPartNameLookupCandidates($productName) as $cand) {
        $st = $conn->prepare(
            "SELECT id FROM contractor_products
             WHERE status = 1 AND operation = ?
               AND (UPPER(TRIM(product_name)) = UPPER(?) OR UPPER(TRIM(IFNULL(process,''))) = UPPER(?))
             LIMIT 1"
        );
        $st->bind_param('sss', $operation, $cand, $cand);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int) $row['id'];
        }
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
    $operation = trim((string) $operation);
    // Case-insensitive match so Product Master "Flexible" still feeds sheet "FLEXIBLE"
    $stmt = $conn->prepare(
        'SELECT * FROM contractor_products
         WHERE status = 1 AND LOWER(TRIM(operation)) = LOWER(?)
         ORDER BY product_name ASC, process ASC'
    );
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
        $days[(string) $d] = ['q' => '', 'r' => '', 'ot' => '', 'ap' => '', 'disabled' => ($d > $n)];
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
        $base[$k]['r'] = $cell['r'] ?? ($cell['rw'] ?? '');
        $base[$k]['ot'] = $cell['ot'] ?? '';
        $base[$k]['ap'] = $cell['ap'] ?? '';
    }
    return $base;
}

/**
 * Clone Ops Rate List — which operations show Normal Salary staff grid (not Main Contractor).
 */
function opsCloneStaffSalaryOperations()
{
    return ['CNC', 'BUFF'];
}

/**
 * Department name for clone staff grid under an operation.
 */
function opsCloneStaffDepartmentName($operation)
{
    $operation = trim((string) $operation);
    $map = [
        'CNC' => 'CNC',
        'BUFF' => 'BUFF',
        'ASS-2' => 'ASSEMBLY 2',
    ];
    return $map[$operation] ?? $operation;
}

/**
 * Normal Salary employees in the operation's department, excluding Main Contractor.
 * Used only by Clone Operation Rate List (not the live Ops Rate List).
 *
 * @return array{department_name:string,main:array|null,rows:array<int,array>}
 */
/**
 * Short code shown in Clone staff attendance day box.
 */
function opsCloneAttCodeFromDay(array $day = null)
{
    if (!$day || empty($day['day_status'])) {
        return '';
    }
    $status = (string) $day['day_status'];
    if ($status === 'Present') {
        return 'P';
    }
    if ($status === 'Week Off') {
        return 'WO';
    }
    if ($status === 'Holiday') {
        return 'H';
    }
    if ($status === 'Absent') {
        return 'A';
    }
    if ($status === 'Leave' || $status === 'Half Day') {
        if (!function_exists('attendanceParseLeaveRemark') && is_file(__DIR__ . '/attendance_helper.php')) {
            require_once __DIR__ . '/attendance_helper.php';
        }
        $parsed = function_exists('attendanceParseLeaveRemark')
            ? attendanceParseLeaveRemark($day['remarks'] ?? '')
            : ['leave_type' => '', 'leave_half' => ''];
        $type = strtoupper(trim((string) ($parsed['leave_type'] ?? '')));
        $half = strtoupper(trim((string) ($parsed['leave_half'] ?? '')));
        if ($type === '') {
            $type = $status === 'Half Day' ? 'HL' : 'L';
        }
        if (in_array($half, ['FHL', 'SHL'], true)) {
            return $type . ' ' . $half;
        }
        return $type;
    }
    return '';
}

/**
 * Parse day-box code → attendanceSaveManualDay args.
 * @return array{clear:bool,status:string,leave_type:string,leave_half:string}|null
 */
function opsCloneAttParseCode($code)
{
    $code = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $code)));
    if ($code === '') {
        return ['clear' => true, 'status' => '', 'leave_type' => '', 'leave_half' => ''];
    }
    if (in_array($code, ['P', 'PR', 'PRESENT'], true)) {
        return ['clear' => false, 'status' => 'Present', 'leave_type' => '', 'leave_half' => ''];
    }
    if (in_array($code, ['WO', 'W/O', 'WEEKOFF', 'WEEK OFF'], true)) {
        return ['clear' => false, 'status' => 'Week Off', 'leave_type' => '', 'leave_half' => ''];
    }
    if (in_array($code, ['H', 'HO', 'HOLIDAY'], true)) {
        return ['clear' => false, 'status' => 'Holiday', 'leave_type' => '', 'leave_half' => ''];
    }
    if (in_array($code, ['A', 'AB', 'ABSENT', 'LWP'], true)) {
        return ['clear' => false, 'status' => 'Absent', 'leave_type' => '', 'leave_half' => ''];
    }
    $leaveType = '';
    $leaveHalf = '';
    if (preg_match('/^(PL|SL|DL|CL|EL|C-?OFF|COFF)(?:\s+(FHL|SHL|FHF|SHF|FULL))?$/', $code, $m)) {
        $leaveType = strtoupper($m[1]);
        if ($leaveType === 'COFF') {
            $leaveType = 'C-OFF';
        }
        $leaveHalf = strtoupper($m[2] ?? 'FULL');
        if ($leaveHalf === 'FHF') {
            $leaveHalf = 'FHL';
        }
        if ($leaveHalf === 'SHF') {
            $leaveHalf = 'SHL';
        }
        if ($leaveHalf === '') {
            $leaveHalf = 'FULL';
        }
        $status = in_array($leaveHalf, ['FHL', 'SHL'], true) ? 'Half Day' : 'Leave';
        return ['clear' => false, 'status' => $status, 'leave_type' => $leaveType, 'leave_half' => $leaveHalf];
    }
    if ($code === 'L' || $code === 'LEAVE') {
        return ['clear' => false, 'status' => 'Leave', 'leave_type' => 'PL', 'leave_half' => 'FULL'];
    }
    // Unknown → treat as Present mark so user entry is not silently dropped
    return ['clear' => false, 'status' => 'Present', 'leave_type' => '', 'leave_half' => ''];
}

/**
 * ERP CNC Attendance Sheet (HR F 04) P/A marks for a date range.
 * Starts 01-11-2026. Empty cells are skipped. Today and future stay out.
 *
 * @return array<string, array<string, string>> code/name key => [Y-m-d => P|A]
 */
function fetchErpCncAttendanceMarks($fromDate, $toDate, $deptLike = 'cnc%')
{
    $fromDate = date('Y-m-d', strtotime((string) $fromDate));
    $toDate = date('Y-m-d', strtotime((string) $toDate));
    $start = '2026-11-01';
    $today = date('Y-m-d');
    $deptLike = strtolower(trim((string) $deptLike));
    if ($deptLike !== 'buff%') {
        $deptLike = 'cnc%';
    }
    if ($fromDate < $start) {
        $fromDate = $start;
    }
    if ($toDate > $today) {
        $toDate = $today;
    }
    if ($fromDate > $toDate) {
        return [];
    }
    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        return [];
    }

    $sql = "SELECT ae.entry_date, ae.employee_code, ae.employee_name, UPPER(TRIM(ae.in_time)) AS mark
            FROM attendance_entries ae
            INNER JOIN departments d ON d.id = ae.department_id
            INNER JOIN document_master dm ON dm.id = ae.document_id AND dm.department_id = ae.department_id
            WHERE LOWER(TRIM(d.department_name)) LIKE ?
              AND LOWER(dm.document_name) LIKE '%attendance%'
              AND ae.entry_date BETWEEN ? AND ?
              AND UPPER(TRIM(COALESCE(ae.in_time, ''))) IN ('P', 'A', 'WO')
            ORDER BY ae.entry_date ASC, ae.id ASC";
    $st = $erp->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param('sss', $deptLike, $fromDate, $toDate);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $mark = strtoupper(trim((string) ($row['mark'] ?? '')));
            if ($mark !== 'P' && $mark !== 'A' && $mark !== 'WO') {
                continue;
            }
            $date = substr((string) ($row['entry_date'] ?? ''), 0, 10);
            if ($date === '' || $date < $start) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['employee_code'] ?? '')));
            $name = strtolower(trim((string) ($row['employee_name'] ?? '')));
            if ($code !== '') {
                $out['c:' . $code][$date] = $mark;
            }
            if ($name !== '') {
                $out['n:' . $name][$date] = $mark;
            }
        }
    }
    $st->close();
    return $out;
}

/**
 * Buff ERP Attendance Sheet loan amounts for the month, summed per employee.
 * Keys: c:CODE and n:name. Lookup uses one key, so the amount is not doubled.
 *
 * @return array<string, float>
 */
function fetchErpBuffLoanAmounts($year, $month)
{
    $year = (int) $year;
    $month = (int) $month;
    if ($year < 2000 || $month < 1 || $month > 12) {
        return [];
    }
    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        return [];
    }
    $col = $erp->query("SHOW COLUMNS FROM attendance_entries LIKE 'loan_amount'");
    if (!$col || !$col->fetch_assoc()) {
        return [];
    }
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $sql = "SELECT ae.employee_code, ae.employee_name, ae.loan_amount
            FROM attendance_entries ae
            INNER JOIN departments d ON d.id = ae.department_id
            INNER JOIN document_master dm ON dm.id = ae.document_id AND dm.department_id = ae.department_id
            WHERE LOWER(TRIM(d.department_name)) LIKE 'buff%'
              AND LOWER(dm.document_name) LIKE '%attendance%'
              AND ae.entry_date BETWEEN ? AND ?
              AND TRIM(COALESCE(ae.loan_amount, '')) <> ''";
    $st = $erp->prepare($sql);
    if (!$st) {
        return [];
    }
    $st->bind_param('ss', $from, $to);
    $st->execute();
    $res = $st->get_result();
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $raw = str_replace(',', '', trim((string) ($row['loan_amount'] ?? '')));
            $amt = is_numeric($raw) ? round((float) $raw, 2) : 0.0;
            if ($amt <= 0) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['employee_code'] ?? '')));
            $name = strtolower(trim((string) ($row['employee_name'] ?? '')));
            if ($code !== '') {
                $out['c:' . $code] = round(($out['c:' . $code] ?? 0) + $amt, 2);
            }
            if ($name !== '') {
                $out['n:' . $name] = round(($out['n:' . $name] ?? 0) + $amt, 2);
            }
        }
    }
    $st->close();
    return $out;
}

/**
 * ERP Buffing Expense "BUFF EXPENSE" for the month.
 * Same split as the ERP sheet: round the grand total, then half stays with Buff.
 */
function fetchErpBuffDeptExpense($year, $month)
{
    $year = (int) $year;
    $month = (int) $month;
    if ($year < 2000 || $month < 1 || $month > 12) {
        return 0.0;
    }
    if (!function_exists('getErpDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $erp = getErpDBConnection();
    if (!$erp) {
        return 0.0;
    }
    $dept = $erp->query("SELECT id FROM departments WHERE LOWER(TRIM(department_name)) LIKE 'buff%' ORDER BY id ASC LIMIT 1");
    $deptRow = $dept ? $dept->fetch_assoc() : null;
    $deptId = (int) ($deptRow['id'] ?? 0);
    if ($deptId <= 0) {
        return 0.0;
    }
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));

    $rates = [];
    $active = [];
    $hasConsumable = false;
    $col = $erp->query("SHOW COLUMNS FROM sub_items LIKE 'consumable_department_id'");
    if ($col && $col->fetch_assoc()) {
        $hasConsumable = true;
    }
    if ($hasConsumable) {
        $rateSql = "SELECT product_name, variant_name, purchase_cost
                    FROM sub_items
                    WHERE IFNULL(is_consumable, 0) = 1 AND consumable_department_id = ?
                    ORDER BY id ASC";
        $rateSt = $erp->prepare($rateSql);
        if ($rateSt) {
            $rateSt->bind_param('i', $deptId);
            $rateSt->execute();
            $rateRes = $rateSt->get_result();
            if ($rateRes) {
                while ($rateRow = $rateRes->fetch_assoc()) {
                    $rateName = trim((string) ($rateRow['product_name'] ?? ''));
                    $variant = trim((string) ($rateRow['variant_name'] ?? ''));
                    if ($variant !== '') {
                        $rateName .= ' (' . $variant . ')';
                    }
                    $rateKey = strtolower($rateName);
                    if ($rateKey === '') {
                        continue;
                    }
                    $masterRate = (float) ($rateRow['purchase_cost'] ?? 0);
                    if (!isset($rates[$rateKey]) || ($rates[$rateKey] <= 0 && $masterRate > 0)) {
                        $rates[$rateKey] = $masterRate;
                    }
                    $active[$rateKey] = true;
                }
            }
            $rateSt->close();
        }
    }

    $dayCols = [];
    for ($d = 1; $d <= 30; $d++) {
        $dayCols[] = 'fe.day_' . $d;
    }
    $sql = "SELECT fe.id, fe.entry_date, fe.item_name, fe.rate, " . implode(', ', $dayCols) . "
            FROM furnace_expense_entries fe
            INNER JOIN document_master dm ON dm.id = fe.document_id AND dm.department_id = fe.department_id
            WHERE fe.department_id = ?
              AND (LOWER(dm.document_name) LIKE '%expense%' OR LOWER(dm.document_name) LIKE '%expence%')
              AND fe.entry_date BETWEEN ? AND ?
            ORDER BY fe.entry_date ASC, fe.id ASC";
    $st = $erp->prepare($sql);
    if (!$st) {
        return 0.0;
    }
    $st->bind_param('iss', $deptId, $from, $to);
    $st->execute();
    $res = $st->get_result();
    $groups = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $key = strtolower(trim((string) ($row['item_name'] ?? '')));
            if ($key === '') {
                continue;
            }
            $groups[$key][] = $row;
        }
    }
    $st->close();

    $grand = 0.0;
    foreach ($groups as $key => $groupRows) {
        if ($active && !isset($active[$key])) {
            continue;
        }
        $keeper = $groupRows[0];
        foreach ($groupRows as $groupRow) {
            if (substr((string) ($groupRow['entry_date'] ?? ''), 0, 10) === $from) {
                $keeper = $groupRow;
                break;
            }
        }
        $qty = 0.0;
        for ($d = 1; $d <= 30; $d++) {
            $colName = 'day_' . $d;
            $val = (float) ($keeper[$colName] ?? 0);
            if ($val <= 0) {
                foreach ($groupRows as $groupRow) {
                    if ((int) ($groupRow['id'] ?? 0) === (int) ($keeper['id'] ?? 0)) {
                        continue;
                    }
                    $other = (float) ($groupRow[$colName] ?? 0);
                    if ($other > 0) {
                        $val = $other;
                        break;
                    }
                }
            }
            $qty += $val;
        }
        $rate = array_key_exists($key, $rates) ? (float) $rates[$key] : (float) ($keeper['rate'] ?? 0);
        $grand += $qty * $rate;
    }
    $rounded = (int) round($grand);
    if ($rounded <= 0) {
        return 0.0;
    }
    $company = round($rounded / 2, 2);
    return round($rounded - $company, 2);
}

function opsCloneLoanForStaff(array $staff, array $loans)
{
    $code = strtoupper(trim((string) ($staff['employee_code'] ?? '')));
    $name = strtolower(trim((string) ($staff['employee_name'] ?? '')));
    if ($code !== '' && isset($loans['c:' . $code])) {
        return (float) $loans['c:' . $code];
    }
    if ($name !== '' && isset($loans['n:' . $name])) {
        return (float) $loans['n:' . $name];
    }
    return 0.0;
}

/**
 * Cut each staff share by that employee's ERP loan. The pool split then
 * leaves the cut amount with the main contractor.
 *
 * @param array<int,array> $shares
 * @param array<int,array> $staffRows
 * @param array<string,float> $loans
 * @return array{shares:array<int,array>,by_id:array<int,float>}
 */
function opsCloneSubtractLoansFromShares(array $shares, array $staffRows, array $loans)
{
    $byId = [];
    if (!$loans) {
        return ['shares' => $shares, 'by_id' => $byId];
    }
    foreach ($staffRows as $staff) {
        $eid = (int) ($staff['id'] ?? 0);
        if ($eid <= 0 || !isset($shares[$eid])) {
            continue;
        }
        $loan = opsCloneLoanForStaff($staff, $loans);
        if ($loan <= 0) {
            continue;
        }
        $amt = (float) ($shares[$eid]['amount'] ?? 0);
        $cut = round(min($loan, max(0, $amt)), 2);
        if ($cut <= 0) {
            continue;
        }
        $shares[$eid]['amount'] = round($amt - $cut, 2);
        $byId[$eid] = $cut;
    }
    return ['shares' => $shares, 'by_id' => $byId];
}

/**
 * Copy ERP CNC Attendance P/A into the staff day map and attendance_day_status.
 * Leave, Half Day and Holiday already saved in HRMS stay as they are.
 *
 * @param array<int,array> $staffRows
 * @param array<int,array<string,array>> $map
 * @return array<int,array<string,array>>
 */
function applyErpCncAttendanceToStaffMap(array $staffRows, array $map, $month, $year, $conn, $deptLike = 'cnc%')
{
    $month = (int) $month;
    $year = (int) $year;
    if ($year < 2026 || ($year === 2026 && $month < 11) || !$staffRows) {
        return $map;
    }
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);
    $marks = fetchErpCncAttendanceMarks($from, $to, $deptLike);
    if (!$marks) {
        return $map;
    }

    $upsert = $conn->prepare(
        "INSERT INTO attendance_day_status
            (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source, remarks)
         VALUES (?, ?, ?, NULL, NULL, 0, 'erp_att', NULL)
         ON DUPLICATE KEY UPDATE
            day_status = VALUES(day_status),
            punch_in = NULL,
            punch_out = NULL,
            working_minutes = 0,
            source = 'erp_att',
            remarks = NULL"
    );
    if (!$upsert) {
        return $map;
    }

    foreach ($staffRows as $staff) {
        $eid = (int) ($staff['id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        $code = strtoupper(trim((string) ($staff['employee_code'] ?? '')));
        $name = strtolower(trim((string) ($staff['employee_name'] ?? '')));
        $byDate = [];
        if ($code !== '' && isset($marks['c:' . $code])) {
            $byDate = $marks['c:' . $code];
        } elseif ($name !== '' && isset($marks['n:' . $name])) {
            $byDate = $marks['n:' . $name];
        }
        foreach ($byDate as $date => $mark) {
            $want = $mark === 'P' ? 'Present' : 'Absent';
            $cur = $map[$eid][$date]['day_status'] ?? '';
            if (in_array($cur, ['Leave', 'Half Day', 'Holiday'], true)) {
                continue;
            }
            if ($mark !== 'P' && $cur === 'Present') {
                continue;
            }
            if ($cur === $want) {
                continue;
            }
            $upsert->bind_param('iss', $eid, $date, $want);
            $upsert->execute();
            $map[$eid][$date] = [
                'employee_id' => $eid,
                'attendance_date' => $date,
                'day_status' => $want,
                'punch_in' => null,
                'punch_out' => null,
                'remarks' => null,
                'working_minutes' => 0,
                'source' => 'erp_att',
            ];
        }
    }
    $upsert->close();
    return $map;
}

/**
 * Week-off weekday → A in the staff grid, same as CNC.
 * P, Leave, Half Day and Holiday stay. Today is included. Tomorrow stays blank.
 *
 * @param array<int,array> $staffRows
 * @param array<int,array<string,array>> $map
 * @return array<int,array<string,array>>
 */
function applyOpsCloneWeekOffAsAbsent(array $staffRows, array $map, $month, $year, $conn)
{
    $month = (int) $month;
    $year = (int) $year;
    if ($year < 2026 || ($year === 2026 && $month < 11) || !$staffRows || !$conn) {
        return $map;
    }
    if (!function_exists('employeeWeekOffHistoryRows')) {
        require_once __DIR__ . '/employee_helper.php';
    }
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $today = date('Y-m-d');
    $start = '2026-11-01';
    $upsert = $conn->prepare(
        "INSERT INTO attendance_day_status
            (employee_id, attendance_date, day_status, punch_in, punch_out, working_minutes, source, remarks)
         VALUES (?, ?, 'Absent', NULL, NULL, 0, 'erp_att', NULL)
         ON DUPLICATE KEY UPDATE
            day_status = 'Absent',
            punch_in = NULL,
            punch_out = NULL,
            working_minutes = 0,
            source = 'erp_att',
            remarks = NULL"
    );
    if (!$upsert) {
        return $map;
    }
    foreach ($staffRows as $staff) {
        $eid = (int) ($staff['id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        $fallback = trim((string) ($staff['week_off_day'] ?? ''));
        if ($fallback === '') {
            $fallback = 'Sunday';
        }
        $history = employeeWeekOffHistoryRows($eid, $conn);
        for ($d = 1; $d <= $monthDays; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            if ($date < $start || $date > $today) {
                continue;
            }
            $weekOff = employeeWeekOffDayFromHistory($history, $date, $fallback);
            $dow = date('l', mktime(0, 0, 0, $month, $d, $year));
            $normDay = static function ($name) {
                $name = strtolower(trim((string) $name));
                $map = [
                    'sun' => 'sunday', 'sunday' => 'sunday',
                    'mon' => 'monday', 'monday' => 'monday',
                    'tue' => 'tuesday', 'tues' => 'tuesday', 'tuesday' => 'tuesday',
                    'wed' => 'wednesday', 'wednesday' => 'wednesday',
                    'thu' => 'thursday', 'thur' => 'thursday', 'thurs' => 'thursday', 'thursday' => 'thursday',
                    'fri' => 'friday', 'friday' => 'friday',
                    'sat' => 'saturday', 'saturday' => 'saturday',
                ];
                return $map[$name] ?? ($map[substr($name, 0, 3)] ?? $name);
            };
            if ($normDay($dow) !== $normDay($weekOff)) {
                continue;
            }
            $cur = (string) ($map[$eid][$date]['day_status'] ?? '');
            if (in_array($cur, ['Present', 'Leave', 'Half Day', 'Holiday', 'Absent'], true)) {
                continue;
            }
            $upsert->bind_param('is', $eid, $date);
            $upsert->execute();
            $map[$eid][$date] = [
                'employee_id' => $eid,
                'attendance_date' => $date,
                'day_status' => 'Absent',
                'punch_in' => null,
                'punch_out' => null,
                'remarks' => null,
                'working_minutes' => 0,
                'source' => 'erp_att',
            ];
        }
    }
    $upsert->close();
    return $map;
}

/**
 * Attendance day map for clone staff employees (month of the ops sheet).
 *
 * @param array<int,array> $staffRows
 * @return array<int,array<string,array>>
 */
function getOpsCloneStaffAttendanceMap(array $staffRows, $month, $year, $conn = null, $deptLike = 'cnc%')
{
    $month = (int) $month;
    $year = (int) $year;
    $ids = [];
    foreach ($staffRows as $r) {
        $eid = (int) ($r['id'] ?? 0);
        if ($eid > 0) {
            $ids[$eid] = true;
        }
    }
    if (!$ids || $month < 1 || $month > 12 || $year < 2000) {
        return [];
    }
    if (!function_exists('ensureAttendanceTables')) {
        require_once __DIR__ . '/attendance_helper.php';
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureAttendanceTables($conn);
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);
    $idList = implode(',', array_map('intval', array_keys($ids)));
    $map = [];
    $res = $conn->query(
        "SELECT employee_id, attendance_date, day_status, punch_in, punch_out, remarks, working_minutes
         FROM attendance_day_status
         WHERE attendance_date BETWEEN '{$conn->real_escape_string($from)}' AND '{$conn->real_escape_string($to)}'
           AND employee_id IN ({$idList})"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $map[(int) $r['employee_id']][substr((string) $r['attendance_date'], 0, 10)] = $r;
        }
    }
    $map = applyErpCncAttendanceToStaffMap($staffRows, $map, $month, $year, $conn, $deptLike);
    $map = applyOpsCloneWeekOffAsAbsent($staffRows, $map, $month, $year, $conn);
    if ($closeAfter) {
        $conn->close();
    }
    return $map;
}

/**
 * CNC staff pay for the New Operation List.
 * Daily rate = monthly salary / month working days (week-off excluded).
 * Paid days = P from 01-11-2026 through today (or month end). A week-off counts only when it is P.
 *
 * @param array<int,array> $staffRows
 * @param array<int,array<string,array>> $attMap
 * @return array<int,array{working_days:int,paid_days:float,per_day:float,amount:float,salary:float}>
 */
function opsCloneCncSalaryShare(array $staffRows, array $attMap, $month, $year)
{
    $month = (int) $month;
    $year = (int) $year;
    $out = [];
    if ($month < 1 || $month > 12 || $year < 2000) {
        return $out;
    }
    $monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $today = date('Y-m-d');
    $periodStart = sprintf('%04d-%02d-01', $year, $month);
    if ($periodStart < '2026-11-01') {
        $periodStart = '2026-11-01';
    }
    $periodEnd = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);
    if ($periodEnd > $today) {
        $periodEnd = $today;
    }

    foreach ($staffRows as $staff) {
        $eid = (int) ($staff['id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        $salary = (float) ($staff['decided_salary'] ?? 0);
        $weekOff = trim((string) ($staff['week_off_day'] ?? ''));
        if ($weekOff === '') {
            $weekOff = 'Sunday';
        }
        $working = 0;
        for ($d = 1; $d <= $monthDays; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            if ($date < '2026-11-01') {
                continue;
            }
            $dow = date('l', mktime(0, 0, 0, $month, $d, $year));
            if (strcasecmp($dow, $weekOff) === 0) {
                continue;
            }
            $working++;
        }
        $paid = 0.0;
        if ($periodStart <= $periodEnd) {
            $ts = strtotime($periodStart);
            $endTs = strtotime($periodEnd);
            while ($ts !== false && $endTs !== false && $ts <= $endTs) {
                $date = date('Y-m-d', $ts);
                $code = strtoupper(opsCloneAttCodeFromDay($attMap[$eid][$date] ?? null));
                if ($code === 'P') {
                    $paid += 1;
                }
                $ts += 86400;
            }
        }
        $perDay = $working > 0 ? ($salary / $working) : 0.0;
        $out[$eid] = [
            'working_days' => $working,
            'paid_days' => $paid,
            'per_day' => round($perDay, 4),
            'amount' => round($perDay * $paid, 2),
            'salary' => $salary,
        ];
    }
    return $out;
}

/**
 * Fit staff salary amounts into the contractor final total.
 * Leftover stays as the main contractor remainder. If staff pay is higher than the total, scale it down.
 *
 * @param array<int,array> $shares
 * @return array{shares:array<int,array>,remainder:float,distributed:float,pool:float}
 */
function opsCloneApplyContractorPool(array $shares, $pool)
{
    $pool = round((float) $pool, 2);
    $sum = 0.0;
    foreach ($shares as $row) {
        $sum += (float) ($row['amount'] ?? 0);
    }
    $sum = round($sum, 2);
    $scale = 1.0;
    if ($pool <= 0) {
        $scale = 0.0;
    } elseif ($sum > $pool && $sum > 0) {
        $scale = $pool / $sum;
    }
    $distributed = 0.0;
    $out = [];
    $ids = array_keys($shares);
    $n = count($ids);
    foreach ($ids as $i => $eid) {
        $base = (float) ($shares[$eid]['amount'] ?? 0);
        if ($scale === 1.0 || $pool <= 0) {
            $amt = round($base * $scale, 2);
        } elseif ($i === $n - 1) {
            $amt = round($pool - $distributed, 2);
        } else {
            $amt = round($base * $scale, 2);
        }
        if ($amt < 0) {
            $amt = 0.0;
        }
        $distributed += $amt;
        $out[$eid] = $shares[$eid];
        $out[$eid]['final'] = $amt;
    }
    $distributed = round($distributed, 2);
    return [
        'shares' => $out,
        'remainder' => round(max(0, $pool - $distributed), 2),
        'distributed' => $distributed,
        'pool' => $pool,
    ];
}

function getOpsCloneDepartmentStaff($operation, $excludeEmployeeId = 0, $conn = null)
{
    $operation = trim((string) $operation);
    $excludeEmployeeId = (int) $excludeEmployeeId;
    $out = ['department_name' => '', 'main' => null, 'rows' => []];
    if ($operation === '' || !in_array($operation, opsCloneStaffSalaryOperations(), true)) {
        return $out;
    }

    if (!function_exists('resolveDepartmentIdByName')) {
        require_once __DIR__ . '/contractor_split_helper.php';
    }

    $deptName = opsCloneStaffDepartmentName($operation);
    $out['department_name'] = $deptName;
    $deptId = resolveDepartmentIdByName($deptName);
    if ($deptId <= 0) {
        return $out;
    }

    $main = null;
    foreach (contractorAttendanceSplitRules() as $rule) {
        if (strcasecmp(trim((string) ($rule['department_name'] ?? '')), $deptName) !== 0) {
            continue;
        }
        $main = resolveContractorAttendanceSplitMain($rule);
        if ($main) {
            ensureSplitDeptEmployeesUnderMain($rule);
        }
        break;
    }
    if (!$main) {
        $main = resolveMainContractorForDepartment($deptName);
    }
    $out['main'] = $main;
    $mainId = (int) ($main['id'] ?? 0);

    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureEmployeesTable($conn);

    $sql = "SELECT e.id, e.employee_code, e.employee_name, e.decided_salary, e.pay_type, e.designation, e.week_off_day
            FROM employees e
            WHERE e.department_id = ?
              AND e.status = 1
              AND e.pay_type = 'Salary'
              AND e.id <> ?
              AND e.id <> ?
            ORDER BY e.employee_code ASC, e.employee_name ASC";
    $st = $conn->prepare($sql);
    $zero = 0;
    $exMain = $mainId > 0 ? $mainId : $zero;
    $exSheet = $excludeEmployeeId > 0 ? $excludeEmployeeId : $zero;
    $st->bind_param('iii', $deptId, $exMain, $exSheet);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $out['rows'][] = $row;
    }
    $st->close();
    if ($closeAfter) {
        $conn->close();
    }
    return $out;
}
