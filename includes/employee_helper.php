<?php
/**
 * Employee Helper Functions
 * Easy reusable functions for Join Employee module.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Make sure employees table exists (safe for first run)
 */
function ensureEmployeesTable($conn = null)
{
    static $ready = false;
    if ($ready && $conn === null) {
        return;
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_code VARCHAR(30) NOT NULL UNIQUE,
        department_id INT NOT NULL,
        employee_name VARCHAR(150) NOT NULL,
        father_husband_name VARCHAR(150) DEFAULT NULL,
        permanent_address TEXT,
        present_address TEXT,
        mobile_number VARCHAR(15) DEFAULT NULL,
        emergency_mobile VARCHAR(15) DEFAULT NULL,
        aadhar_number VARCHAR(20) DEFAULT NULL,
        pan_number VARCHAR(20) DEFAULT NULL,
        date_of_birth DATE DEFAULT NULL,
        designation VARCHAR(100) DEFAULT NULL,
        date_of_joining DATE DEFAULT NULL,
        shift_type ENUM('Day', 'Night') DEFAULT 'Day',
        shift_time VARCHAR(50) DEFAULT NULL,
        pf_deduction ENUM('Yes', 'No') DEFAULT 'No',
        uan_number VARCHAR(30) DEFAULT NULL,
        bank_name VARCHAR(100) DEFAULT NULL,
        bank_account_number VARCHAR(40) DEFAULT NULL,
        ifsc_code VARCHAR(20) DEFAULT NULL,
        bank_branch_address TEXT,
        decided_salary DECIMAL(12, 2) DEFAULT NULL,
        reporting_head VARCHAR(150) DEFAULT NULL,
        extra_note TEXT,
        week_off_day VARCHAR(30) DEFAULT NULL,
        week_off_benefits ENUM('Yes', 'No') DEFAULT 'No',
        holiday_benefits ENUM('Yes', 'No') DEFAULT 'No',
        overtime_benefits ENUM('Yes', 'No') DEFAULT 'No',
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employees_department (department_id),
        INDEX idx_employees_name (employee_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    ensureEmployeeColumn($conn, 'pay_type', "pay_type ENUM('Salary','Jobwork','ContractorMain') NOT NULL DEFAULT 'Salary' AFTER designation");
    ensureEmployeePayTypeEnum($conn);
    ensureEmployeeColumn($conn, 'aadhar_file', "aadhar_file VARCHAR(255) DEFAULT NULL AFTER aadhar_number");
    ensureEmployeeColumn($conn, 'pan_file', "pan_file VARCHAR(255) DEFAULT NULL AFTER pan_number");
    ensureEmployeeColumn($conn, 'sub_department_id', "sub_department_id INT DEFAULT NULL AFTER department_id");
    ensureEmployeeColumn($conn, 'main_contractor_id', "main_contractor_id INT DEFAULT NULL AFTER pay_type");
    ensureEmployeeColumn($conn, 'date_of_exit', "date_of_exit DATE DEFAULT NULL AFTER date_of_joining");
    ensureEmployeeColumn($conn, 'office_email', "office_email VARCHAR(150) DEFAULT NULL AFTER emergency_mobile");
    ensureEmployeeColumn($conn, 'office_mobile', "office_mobile VARCHAR(15) DEFAULT NULL AFTER office_email");
    ensureEmployeeColumn($conn, 'gender', "gender VARCHAR(20) DEFAULT NULL AFTER office_mobile");
    ensureEmployeeColumn($conn, 'marital_status', "marital_status VARCHAR(20) DEFAULT NULL AFTER date_of_birth");
    ensureEmployeeColumn($conn, 'marital_remark', "marital_remark VARCHAR(255) DEFAULT NULL AFTER marital_status");
    ensureEmployeeColumn($conn, 'photo_file', "photo_file VARCHAR(255) DEFAULT NULL AFTER pan_file");
    ensureEmployeeColumn($conn, 'pf_start_date', "pf_start_date DATE DEFAULT NULL AFTER pf_deduction");
    ensureEmployeeColumn($conn, 'pf_employee_contribution', "pf_employee_contribution DECIMAL(12,2) DEFAULT NULL AFTER pf_start_date");
    ensureEmployeeColumn($conn, 'pf_employer_contribution', "pf_employer_contribution DECIMAL(12,2) DEFAULT NULL AFTER pf_employee_contribution");
    ensureEmployeeColumn($conn, 'assigned_state_id', "assigned_state_id INT DEFAULT NULL AFTER sub_department_id");
    ensureEmployeeColumn($conn, 'assigned_location_id', "assigned_location_id INT DEFAULT NULL AFTER assigned_state_id");

    // Auto-sync: Exit date reached (today or past) → Deactive. Future exit date keeps Active.
    $conn->query(
        "UPDATE employees SET status = 0
         WHERE status = 1
           AND date_of_exit IS NOT NULL
           AND date_of_exit != ''
           AND date_of_exit != '0000-00-00'
           AND date_of_exit <= CURDATE()"
    );

    ensureEmployeeFamilyTable($conn);
    ensureEmployeeSalaryHistoryTable($conn);
    ensureEmployeeLocationHistoryTable($conn);

    $ready = true;
    if ($closeAfter) {
        $conn->close();
    }
}

function ensureEmployeeFamilyTable($conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_family_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            member_name VARCHAR(150) NOT NULL DEFAULT '',
            relation_name VARCHAR(100) NOT NULL DEFAULT '',
            occupation VARCHAR(150) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_emp_family_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Salary revision history (vadharo / ghatado) with effective date.
 */
function ensureEmployeeSalaryHistoryTable($conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_salary_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            old_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            change_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            new_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
            effective_date DATE NOT NULL,
            remarks VARCHAR(255) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sal_hist_emp (employee_id),
            INDEX idx_sal_hist_eff (employee_id, effective_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Assigned State / Location change history (Sales On Field).
 */
function ensureEmployeeLocationHistoryTable($conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_location_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            old_state_id INT DEFAULT NULL,
            old_location_id INT DEFAULT NULL,
            new_state_id INT DEFAULT NULL,
            new_location_id INT DEFAULT NULL,
            old_state_name VARCHAR(120) DEFAULT NULL,
            old_location_name VARCHAR(150) DEFAULT NULL,
            new_state_name VARCHAR(120) DEFAULT NULL,
            new_location_name VARCHAR(150) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_loc_hist_emp (employee_id),
            INDEX idx_loc_hist_created (employee_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * @return array<int, array>
 */
function getEmployeeLocationHistory($employeeId, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureEmployeeLocationHistoryTable($conn);
    $employeeId = (int) $employeeId;
    $rows = [];
    if ($employeeId > 0) {
        $stmt = $conn->prepare(
            'SELECT h.*,
                    u.username AS changed_by_login,
                    u.full_name AS changed_by_name
             FROM employee_location_history h
             LEFT JOIN users u ON u.id = h.created_by
             WHERE h.employee_id = ?
             ORDER BY h.created_at DESC, h.id DESC'
        );
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $login = trim((string) ($row['changed_by_login'] ?? ''));
            $name = trim((string) ($row['changed_by_name'] ?? ''));
            if ($login !== '' && $name !== '') {
                $row['changed_by_label'] = $name . ' (@' . $login . ')';
            } elseif ($login !== '') {
                $row['changed_by_label'] = '@' . $login;
            } elseif ($name !== '') {
                $row['changed_by_label'] = $name;
            } else {
                $row['changed_by_label'] = ((int) ($row['created_by'] ?? 0) > 0) ? ('User #' . (int) $row['created_by']) : '—';
            }
            $rows[] = $row;
        }
        $stmt->close();
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

function employeeResolveStateLocationNames($conn, $stateId, $locationId)
{
    $stateId = (int) $stateId;
    $locationId = (int) $locationId;
    $stateName = '';
    $locName = '';
    if ($stateId > 0) {
        $st = $conn->prepare('SELECT name FROM assigned_states WHERE id = ? LIMIT 1');
        $st->bind_param('i', $stateId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $stateName = (string) ($r['name'] ?? '');
    }
    if ($locationId > 0) {
        $st = $conn->prepare('SELECT name FROM assigned_locations WHERE id = ? LIMIT 1');
        $st->bind_param('i', $locationId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $locName = (string) ($r['name'] ?? '');
    }
    return [$stateName, $locName];
}

/**
 * Record location assignment change if state/location actually changed.
 */
function employeeRecordLocationChange($conn, $employeeId, $oldStateId, $oldLocationId, $newStateId, $newLocationId, $createdBy = null)
{
    $employeeId = (int) $employeeId;
    $oldStateId = (int) $oldStateId;
    $oldLocationId = (int) $oldLocationId;
    $newStateId = (int) $newStateId;
    $newLocationId = (int) $newLocationId;
    if ($employeeId <= 0) {
        return false;
    }
    if ($oldStateId === $newStateId && $oldLocationId === $newLocationId) {
        return false;
    }
    // Ignore empty → empty
    if ($oldStateId <= 0 && $oldLocationId <= 0 && $newStateId <= 0 && $newLocationId <= 0) {
        return false;
    }
    ensureEmployeeLocationHistoryTable($conn);
    if (function_exists('ensureMasterTables')) {
        require_once __DIR__ . '/master_helper.php';
        ensureMasterTables($conn);
    }
    list($oldStateName, $oldLocName) = employeeResolveStateLocationNames($conn, $oldStateId, $oldLocationId);
    list($newStateName, $newLocName) = employeeResolveStateLocationNames($conn, $newStateId, $newLocationId);
    $by = $createdBy !== null ? (int) $createdBy : null;
    $os = $oldStateId > 0 ? $oldStateId : null;
    $ol = $oldLocationId > 0 ? $oldLocationId : null;
    $ns = $newStateId > 0 ? $newStateId : null;
    $nl = $newLocationId > 0 ? $newLocationId : null;
    // bind_param needs variables; use 0 for null IDs and store names always
    $osI = $os ?: 0;
    $olI = $ol ?: 0;
    $nsI = $ns ?: 0;
    $nlI = $nl ?: 0;
    $stmt = $conn->prepare(
        'INSERT INTO employee_location_history
            (employee_id, old_state_id, old_location_id, new_state_id, new_location_id,
             old_state_name, old_location_name, new_state_name, new_location_name, created_by)
         VALUES (?, NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, NULLIF(?,0))'
    );
    $byI = $by ?: 0;
    $stmt->bind_param(
        'iiiiissssi',
        $employeeId,
        $osI,
        $olI,
        $nsI,
        $nlI,
        $oldStateName,
        $oldLocName,
        $newStateName,
        $newLocName,
        $byI
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * @return array<int, array>
 */
function getEmployeeSalaryHistory($employeeId, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureEmployeeSalaryHistoryTable($conn);
    $employeeId = (int) $employeeId;
    $rows = [];
    if ($employeeId > 0) {
        $stmt = $conn->prepare(
            'SELECT h.id, h.employee_id, h.old_salary, h.change_amount, h.new_salary, h.effective_date,
                    h.remarks, h.created_by, h.created_at,
                    u.username AS changed_by_login,
                    u.full_name AS changed_by_name
             FROM employee_salary_history h
             LEFT JOIN users u ON u.id = h.created_by
             WHERE h.employee_id = ?
             ORDER BY h.effective_date DESC, h.id DESC'
        );
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $login = trim((string) ($row['changed_by_login'] ?? ''));
            $name = trim((string) ($row['changed_by_name'] ?? ''));
            if ($login !== '' && $name !== '') {
                $row['changed_by_label'] = $name . ' (@' . $login . ')';
            } elseif ($login !== '') {
                $row['changed_by_label'] = '@' . $login;
            } elseif ($name !== '') {
                $row['changed_by_label'] = $name;
            } else {
                $row['changed_by_label'] = ((int) ($row['created_by'] ?? 0) > 0) ? ('User #' . (int) $row['created_by']) : '—';
            }
            $rows[] = $row;
        }
        $stmt->close();
    }
    if ($closeAfter) {
        $conn->close();
    }
    return $rows;
}

/**
 * Decided salary applicable on a given date (from history, else employees.decided_salary).
 */
function employeeDecidedSalaryAsOf($employeeId, $asOfDate, $conn = null, $fallback = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    ensureEmployeeSalaryHistoryTable($conn);
    $employeeId = (int) $employeeId;
    $asOfDate = substr((string) $asOfDate, 0, 10);
    $salary = null;

    if ($employeeId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
        $stmt = $conn->prepare(
            'SELECT new_salary FROM employee_salary_history
             WHERE employee_id = ? AND effective_date <= ?
             ORDER BY effective_date DESC, id DESC
             LIMIT 1'
        );
        $stmt->bind_param('is', $employeeId, $asOfDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $salary = (float) $row['new_salary'];
        }
    }

    if ($salary === null) {
        if ($fallback !== null) {
            $salary = (float) $fallback;
        } else {
            $st = $conn->prepare('SELECT decided_salary FROM employees WHERE id = ? LIMIT 1');
            $st->bind_param('i', $employeeId);
            $st->execute();
            $er = $st->get_result()->fetch_assoc();
            $st->close();
            $salary = (float) ($er['decided_salary'] ?? 0);
        }
    }

    if ($closeAfter) {
        $conn->close();
    }
    return round((float) $salary, 2);
}

/**
 * Apply salary as of today onto employees.decided_salary (pending future revisions stay in history).
 */
function employeeSyncDecidedSalaryFromHistory($conn, $employeeId)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return;
    }
    ensureEmployeeSalaryHistoryTable($conn);
    $today = date('Y-m-d');
    $current = employeeDecidedSalaryAsOf($employeeId, $today, $conn);
    $stmt = $conn->prepare('UPDATE employees SET decided_salary = ? WHERE id = ?');
    $stmt->bind_param('di', $current, $employeeId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Seed baseline history row if none exist (joining / first salary).
 */
function employeeSeedSalaryHistoryIfEmpty($conn, $employeeId, $salary, $effectiveDate = null, $createdBy = null)
{
    $employeeId = (int) $employeeId;
    $salary = round((float) $salary, 2);
    if ($employeeId <= 0 || $salary <= 0) {
        return;
    }
    ensureEmployeeSalaryHistoryTable($conn);
    $chk = $conn->prepare('SELECT id FROM employee_salary_history WHERE employee_id = ? LIMIT 1');
    $chk->bind_param('i', $employeeId);
    $chk->execute();
    $exists = (bool) $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($exists) {
        return;
    }
    $eff = $effectiveDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $effectiveDate)
        ? $effectiveDate
        : date('Y-m-d');
    $zero = 0.0;
    $remark = 'Initial salary';
    $by = $createdBy !== null ? (int) $createdBy : null;
    $stmt = $conn->prepare(
        'INSERT INTO employee_salary_history
            (employee_id, old_salary, change_amount, new_salary, effective_date, remarks, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('idddssi', $employeeId, $zero, $salary, $salary, $eff, $remark, $by);
    $stmt->execute();
    $stmt->close();
}

/**
 * Record salary revision (increase / decrease) and sync current decided_salary.
 *
 * @return array{old_salary:float,change_amount:float,new_salary:float,effective_date:string,history:array}
 */
function employeeReviseSalary($conn, $employeeId, $changeAmount, $effectiveDate, $createdBy = null, $remarks = '')
{
    ensureEmployeeSalaryHistoryTable($conn);
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        throw new RuntimeException('Invalid employee.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $effectiveDate)) {
        throw new RuntimeException('Valid effective date is required.');
    }
    $changeAmount = round((float) $changeAmount, 2);
    if (abs($changeAmount) < 0.001) {
        throw new RuntimeException('Enter increase / decrease amount (not zero).');
    }

    $st = $conn->prepare('SELECT decided_salary, date_of_joining FROM employees WHERE id = ? LIMIT 1');
    $st->bind_param('i', $employeeId);
    $st->execute();
    $emp = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$emp) {
        throw new RuntimeException('Employee not found.');
    }

    $current = (float) ($emp['decided_salary'] ?? 0);
    employeeSeedSalaryHistoryIfEmpty(
        $conn,
        $employeeId,
        $current > 0 ? $current : max(0, $current + $changeAmount),
        !empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00'
            ? substr((string) $emp['date_of_joining'], 0, 10)
            : date('Y-m-d'),
        $createdBy
    );

    // Old salary = rate just before this effective date
    $dayBefore = date('Y-m-d', strtotime($effectiveDate . ' -1 day'));
    $oldSalary = employeeDecidedSalaryAsOf($employeeId, $dayBefore, $conn, $current);
    $newSalary = round($oldSalary + $changeAmount, 2);
    if ($newSalary < 0) {
        throw new RuntimeException('New salary cannot be negative.');
    }

    $remark = trim((string) $remarks);
    if ($remark === '') {
        $remark = $changeAmount >= 0 ? 'Salary increase' : 'Salary decrease';
    }
    $by = $createdBy !== null ? (int) $createdBy : null;
    $stmt = $conn->prepare(
        'INSERT INTO employee_salary_history
            (employee_id, old_salary, change_amount, new_salary, effective_date, remarks, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('idddssi', $employeeId, $oldSalary, $changeAmount, $newSalary, $effectiveDate, $remark, $by);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Could not save salary history: ' . $err);
    }
    $stmt->close();

    employeeSyncDecidedSalaryFromHistory($conn, $employeeId);

    $st2 = $conn->prepare('SELECT decided_salary FROM employees WHERE id = ? LIMIT 1');
    $st2->bind_param('i', $employeeId);
    $st2->execute();
    $synced = (float) ($st2->get_result()->fetch_assoc()['decided_salary'] ?? $newSalary);
    $st2->close();

    return [
        'old_salary' => $oldSalary,
        'change_amount' => $changeAmount,
        'new_salary' => $newSalary,
        'current_salary' => $synced,
        'effective_date' => $effectiveDate,
        'history' => getEmployeeSalaryHistory($employeeId, $conn),
    ];
}

/**
 * @return array<int, array{id?:int, member_name:string, relation_name:string, occupation:string}>
 */
function getEmployeeFamilyMembers($employeeId, $conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
        ensureEmployeesTable($conn);
    }
    $employeeId = (int) $employeeId;
    $rows = [];
    if ($employeeId > 0) {
        $stmt = $conn->prepare(
            'SELECT id, member_name, relation_name, occupation, sort_order
             FROM employee_family_members
             WHERE employee_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->bind_param('i', $employeeId);
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

/**
 * Replace family members for an employee from POST-style arrays.
 *
 * @param array $names
 * @param array $relations
 * @param array $occupations
 */
function saveEmployeeFamilyMembers($conn, $employeeId, array $names, array $relations, array $occupations)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return;
    }
    ensureEmployeeFamilyTable($conn);

    $del = $conn->prepare('DELETE FROM employee_family_members WHERE employee_id = ?');
    $del->bind_param('i', $employeeId);
    $del->execute();
    $del->close();

    $ins = $conn->prepare(
        'INSERT INTO employee_family_members (employee_id, member_name, relation_name, occupation, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    );
    $count = max(count($names), count($relations), count($occupations));
    $sort = 0;
    for ($i = 0; $i < $count; $i++) {
        $name = trim((string) ($names[$i] ?? ''));
        $relation = trim((string) ($relations[$i] ?? ''));
        $occupation = trim((string) ($occupations[$i] ?? ''));
        if ($name === '' && $relation === '' && $occupation === '') {
            continue;
        }
        $sort++;
        $ins->bind_param('isssi', $employeeId, $name, $relation, $occupation, $sort);
        $ins->execute();
    }
    $ins->close();
}

function ensureEmployeeColumn($conn, $column, $definition)
{
    $column = preg_replace('/[^a-z0-9_]/', '', $column);
    if ($column === '' || $definition === '') {
        return;
    }
    $res = $conn->query("SHOW COLUMNS FROM employees LIKE '" . $conn->real_escape_string($column) . "'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN " . $definition);
    }
}

function ensureEmployeePayTypeEnum($conn)
{
    $res = $conn->query("SHOW COLUMNS FROM employees LIKE 'pay_type'");
    $col = $res ? $res->fetch_assoc() : null;
    $type = strtolower((string) ($col['Type'] ?? ''));
    if ($type !== '' && strpos($type, 'contractormain') === false) {
        $conn->query("ALTER TABLE employees MODIFY pay_type ENUM('Salary','Jobwork','ContractorMain') NOT NULL DEFAULT 'Salary'");
    }
}

function normalizePayType($value)
{
    $value = (string) $value;
    if ($value === 'Jobwork') {
        return 'Jobwork';
    }
    if ($value === 'ContractorMain') {
        return 'ContractorMain';
    }
    return 'Salary';
}

function payTypeLabel($value)
{
    $type = normalizePayType($value);
    if ($type === 'Jobwork') {
        return 'Jobwork';
    }
    if ($type === 'ContractorMain') {
        return 'Contractor Main';
    }
    return 'Salary';
}

function payTypeCssClass($value)
{
    $type = normalizePayType($value);
    if ($type === 'Jobwork') {
        return 'is-jobwork';
    }
    if ($type === 'ContractorMain') {
        return 'is-contractor-main';
    }
    return 'is-salary';
}

function getEmployeesByPayType($payType, $excludeId = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $payType = normalizePayType($payType);
    $excludeId = (int) $excludeId;
    $rows = [];
    $stmt = $conn->prepare(
        "SELECT id, employee_code, employee_name, department_id
         FROM employees
         WHERE status = 1 AND pay_type = ? AND id <> ?
         ORDER BY employee_name ASC"
    );
    $stmt->bind_param('si', $payType, $excludeId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

/**
 * Get one department by ID
 */
function getDepartmentById($departmentId)
{
    $conn = getDBConnection();
    $stmt = $conn->prepare(
        "SELECT id, department_name, icon_class, icon_color
         FROM departments
         WHERE id = ? AND status = 1
         LIMIT 1"
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row;
}

/**
 * Prefix used when auto-generating employee codes.
 */
function employeeCodePrefix($payType = 'Salary')
{
    $payType = normalizePayType($payType);
    if ($payType === 'Jobwork') {
        return 'JW';
    }
    if ($payType === 'ContractorMain') {
        return 'CM';
    }
    return 'AS';
}

/**
 * Generate next unique employee code: AS76007 / JW0001 / CM0001
 */
function generateEmployeeCode($conn, $payType = 'Salary')
{
    $payType = normalizePayType($payType);
    $prefix = employeeCodePrefix($payType);
    $max = 0;
    $pad = 4;
    $like = $conn->real_escape_string($prefix) . '%';
    $result = $conn->query("SELECT employee_code FROM employees WHERE UPPER(employee_code) LIKE '{$like}'");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $code = strtoupper(trim((string) $row['employee_code']));
            if (preg_match('/^' . preg_quote(strtoupper($prefix), '/') . '(\d+)$/', $code, $m)) {
                $max = max($max, (int) $m[1]);
                $pad = max($pad, strlen($m[1]));
            }
        }
        $result->free();
    }

    for ($attempt = 0; $attempt < 1000; $attempt++) {
        $code = $prefix . str_pad((string) ($max + 1 + $attempt), $pad, '0', STR_PAD_LEFT);
        if (isEmployeeCodeUnique($conn, $code, 0)) {
            return $code;
        }
    }

    return $prefix . str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT) . 'X';
}

function isEmployeeCodeUnique($conn, $code, $excludeId = 0)
{
    $code = strtoupper(trim((string) $code));
    $excludeId = (int) $excludeId;
    $stmt = $conn->prepare(
        "SELECT id FROM employees
         WHERE UPPER(TRIM(employee_code)) = ? AND id <> ?
         LIMIT 1"
    );
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return empty($row);
}

/**
 * Find employee by code (active or inactive). Used for duplicate checks.
 */
function findEmployeeByCode($conn, $code, $excludeId = 0)
{
    $code = strtoupper(trim((string) $code));
    $excludeId = (int) $excludeId;
    if ($code === '') {
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT id, employee_code, employee_name, status, department_id
         FROM employees
         WHERE UPPER(TRIM(employee_code)) = ? AND id <> ?
         LIMIT 1"
    );
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get one employee by ID
 */
function getEmployeeById($employeeId)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    if (function_exists('ensureMasterTables')) {
        require_once __DIR__ . '/master_helper.php';
        ensureMasterTables($conn);
    }

    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name,
                ast.name AS assigned_state_name,
                aloc.name AS assigned_location_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN assigned_states ast ON ast.id = e.assigned_state_id
         LEFT JOIN assigned_locations aloc ON aloc.id = e.assigned_location_id
         WHERE e.id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row;
}

/**
 * SALES & MARKETING - ON FIELD department check.
 */
function isSalesOnFieldDepartment($department)
{
    if (is_array($department)) {
        $name = (string) ($department['department_name'] ?? '');
    } else {
        $name = (string) $department;
    }
    $n = strtoupper(trim(preg_replace('/[^A-Z0-9]+/', ' ', $name)));
    $n = trim(preg_replace('/\s+/', ' ', $n));
    if ($n === '') {
        return false;
    }
    return $n === 'SALES MARKETING ON FIELD'
        || (strpos($n, 'SALES') !== false && strpos($n, 'ON FIELD') !== false);
}

function isSalesOnFieldDepartmentId($departmentId, $conn = null)
{
    $departmentId = (int) $departmentId;
    if ($departmentId <= 0) {
        return false;
    }
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }
    $stmt = $conn->prepare('SELECT department_name FROM departments WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($closeAfter) {
        $conn->close();
    }
    return isSalesOnFieldDepartment($row ?: []);
}

/**
 * Determine if employee is Deactive / Exited
 */
function isEmployeeDeactive($emp)
{
    if (!$emp) {
        return false;
    }
    if (isset($emp['status']) && (int) $emp['status'] === 0) {
        return true;
    }
    $exit = (string) ($emp['date_of_exit'] ?? '');
    if ($exit !== '' && $exit !== '0000-00-00') {
        $exitYmd = substr($exit, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $exitYmd) && $exitYmd <= date('Y-m-d')) {
            return true;
        }
    }
    return false;
}

/**
 * Count active employees in a department (or across all departments if deptId <= 0)
 */
function countActiveEmployeesByDepartment($departmentId = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $departmentId = (int) $departmentId;

    if ($departmentId > 0) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM employees
             WHERE department_id = ? AND status = 1
               AND (date_of_exit IS NULL OR date_of_exit = '' OR date_of_exit = '0000-00-00' OR date_of_exit > CURDATE())"
        );
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $res = $conn->query(
            "SELECT COUNT(*) AS total FROM employees
             WHERE status = 1
               AND (date_of_exit IS NULL OR date_of_exit = '' OR date_of_exit = '0000-00-00' OR date_of_exit > CURDATE())"
        );
        $row = $res ? $res->fetch_assoc() : null;
    }
    $conn->close();

    return (int) ($row['total'] ?? 0);
}

/**
 * Count exit / deactive employees in a department (or across all departments if deptId <= 0)
 */
function countExitEmployeesByDepartment($departmentId = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $departmentId = (int) $departmentId;

    if ($departmentId > 0) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM employees
             WHERE department_id = ? AND (
                status = 0
                OR (
                    date_of_exit IS NOT NULL AND date_of_exit != '' AND date_of_exit != '0000-00-00'
                    AND date_of_exit <= CURDATE()
                )
             )"
        );
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $res = $conn->query(
            "SELECT COUNT(*) AS total FROM employees
             WHERE status = 0
                OR (
                    date_of_exit IS NOT NULL AND date_of_exit != '' AND date_of_exit != '0000-00-00'
                    AND date_of_exit <= CURDATE()
                )"
        );
        $row = $res ? $res->fetch_assoc() : null;
    }
    $conn->close();

    return (int) ($row['total'] ?? 0);
}

/**
 * Count active employees in a department (kept for backward compatibility)
 */
function countEmployeesByDepartment($departmentId)
{
    return countActiveEmployeesByDepartment($departmentId);
}

/**
 * Format date for display d-m-Y
 * @deprecated Use formatDateDisplay from includes/date_helper.php (loaded via app.php)
 */
if (!function_exists('formatDateDisplay')) {
    function formatDateDisplay($date)
    {
        if (empty($date) || $date === '0000-00-00') {
            return '';
        }
        return date('d-m-Y', strtotime($date));
    }
}

function formatMasterTime($time)
{
    if (empty($time) || $time === '00:00:00') {
        return '';
    }
    return date('h:i A', strtotime($time));
}

function formatShiftTimeRange(array $shift)
{
    $start = formatMasterTime($shift['start_time'] ?? '');
    $end = formatMasterTime($shift['end_time'] ?? '');
    if ($start !== '' && $end !== '') {
        return $start . ' - ' . $end;
    }
    return $start !== '' ? $start : $end;
}

function formatShiftOptionLabel(array $shift)
{
    $name = trim((string) ($shift['name'] ?? ''));
    $type = trim((string) ($shift['shift_type'] ?? ''));
    $range = formatShiftTimeRange($shift);
    $label = $name;
    if ($range !== '') {
        $label .= ' (' . $range . ')';
    }
    if ($type !== '') {
        $label .= ' · ' . $type;
    }
    return $label;
}

/**
 * Employees for Reporting Person dropdown (code + name)
 */
function getReportingEmployees($excludeId = 0)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $excludeId = (int) $excludeId;

    $sql = "SELECT e.id, e.employee_code, e.employee_name, d.department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.status = 1";
    if ($excludeId > 0) {
        $sql .= " AND e.id <> " . $excludeId;
    }
    $sql .= " ORDER BY e.employee_code ASC, e.employee_name ASC";

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

function reportingPersonLabel(array $emp)
{
    $code = trim((string) ($emp['employee_code'] ?? ''));
    $name = trim((string) ($emp['employee_name'] ?? ''));
    $dept = trim((string) ($emp['department_name'] ?? ''));
    $label = trim($code . ' — ' . $name, " —");
    if ($dept !== '') {
        $label .= ' (' . $dept . ')';
    }
    return $label;
}

function findMatchingShiftId($shifts, $shiftType, $shiftTime)
{
    $shiftType = trim((string) $shiftType);
    $shiftTime = trim((string) $shiftTime);
    foreach ($shifts as $shift) {
        $range = formatShiftTimeRange($shift);
        if ($shiftTime !== '' && ($shiftTime === $range || $shiftTime === ($shift['name'] ?? ''))) {
            return (int) $shift['id'];
        }
    }
    if ($shiftType !== '' && $shiftTime !== '') {
        foreach ($shifts as $shift) {
            $range = formatShiftTimeRange($shift);
            if (($shift['shift_type'] ?? '') === $shiftType && $range === $shiftTime) {
                return (int) $shift['id'];
            }
        }
    }
    return 0;
}

/**
 * Resolve shift dropdown + type/time fields for add/edit form.
 */
function resolveShiftSelection(array $shifts, $employee)
{
    $shiftType = '';
    $shiftTime = '';
    $selectedId = 0;

    if ($employee) {
        $shiftType = trim((string) ($employee['shift_type'] ?? ''));
        $shiftTime = trim((string) ($employee['shift_time'] ?? ''));
        $selectedId = findMatchingShiftId(
            $shifts,
            $shiftType !== '' ? $shiftType : 'Day',
            $shiftTime
        );
    }

    if ($selectedId > 0) {
        foreach ($shifts as $shift) {
            if ((int) ($shift['id'] ?? 0) === $selectedId) {
                $shiftType = (string) ($shift['shift_type'] ?? 'Day');
                $shiftTime = formatShiftTimeRange($shift);
                break;
            }
        }
    } else {
        $shiftType = '';
        $shiftTime = '';
    }

    return [
        'shift_id' => $selectedId,
        'shift_type' => $shiftType,
        'shift_time' => $shiftTime,
    ];
}

function findReportingEmployeeId($employees, $reportingHead)
{
    $reportingHead = trim((string) $reportingHead);
    if ($reportingHead === '') {
        return 0;
    }
    foreach ($employees as $emp) {
        $label = reportingPersonLabel($emp);
        $codeName = trim(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? ''));
        if ($reportingHead === $label || $reportingHead === $codeName || $reportingHead === ($emp['employee_name'] ?? '')) {
            return (int) $emp['id'];
        }
        if ($emp['employee_code'] !== '' && strpos($reportingHead, $emp['employee_code']) === 0) {
            return (int) $emp['id'];
        }
    }
    return 0;
}

function getWeekOffDaysFromMaster($holidays)
{
    // Always allow all 7 days so Join Employee can set employee-wise week-off.
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($holidays as $row) {
        if (($row['holiday_type'] ?? '') !== 'Week-Off') {
            continue;
        }
        $day = trim((string) ($row['week_day'] ?? ''));
        if ($day !== '' && !in_array($day, $days, true)) {
            $days[] = $day;
        }
    }
    return $days;
}

function employeeDocumentPublicUrl($relativePath)
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return '';
    }
    if (strpos($relativePath, 'assets/uploads/docs/') !== 0) {
        return '';
    }
    return function_exists('app_url') ? app_url($relativePath) : ('/' . ltrim($relativePath, '/'));
}

function employeeDocumentViewHtml($relativePath, $label = 'View')
{
    $url = employeeDocumentPublicUrl($relativePath);
    if ($url === '') {
        return '';
    }
    return '<a class="doc-view-link" href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener">'
        . '<i class="fa-solid fa-eye"></i> ' . htmlspecialchars($label)
        . '</a>';
}

function deleteEmployeeDocument($relativePath)
{
    $relativePath = str_replace('\\', '/', trim((string) $relativePath));
    if ($relativePath === '' || strpos($relativePath, 'assets/uploads/docs/') !== 0 || strpos($relativePath, '..') !== false) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function saveEmployeeDocument(array $file, $employeeId, $kind)
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return '';
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Aadhar/PAN attachment could not be uploaded. Please try again.');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $kind = preg_replace('/[^a-z]/', '', strtolower((string) $kind));
    $allowed = ($kind === 'photo')
        ? ['jpg', 'jpeg', 'png', 'webp']
        : ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException(
            $kind === 'photo'
                ? 'Photo must be JPG, PNG, or WEBP.'
                : 'Aadhar/PAN file must be JPG, PNG, PDF, or WEBP.'
        );
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException(
            $kind === 'photo'
                ? 'Photo must be 5MB or smaller.'
                : 'Aadhar/PAN file must be 5MB or smaller.'
        );
    }
    $dir = dirname(__DIR__) . '/assets/uploads/docs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create document upload folder.');
    }
    $name = 'emp_' . (int) $employeeId . '_' . ($kind !== '' ? $kind : 'doc') . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save Aadhar/PAN attachment.');
    }
    return 'assets/uploads/docs/' . $name;
}

function applyEmployeeDocumentUpload($fileKey, $employeeId, $kind, $currentPath)
{
    $currentPath = (string) $currentPath;
    if (!isset($_FILES[$fileKey])) {
        return $currentPath;
    }
    $newPath = saveEmployeeDocument($_FILES[$fileKey], $employeeId, $kind);
    if ($newPath === '') {
        return $currentPath;
    }
    if ($currentPath !== '' && $currentPath !== $newPath) {
        deleteEmployeeDocument($currentPath);
    }
    return $newPath;
}

/**
 * Column headers for employee Excel import / sample template
 * @param bool $omitDepartment when importing inside a fixed department page
 */
function employeeImportHeaders($omitDepartment = false)
{
    $headers = [
        'employee_code',
        'biometric_user_id',
        'pay_type',
        'employee_name',
        'father_husband_name',
        'department',
        'designation',
        'date_of_birth',
        'date_of_joining',
        'date_of_exit',
        'mobile_number',
        'emergency_mobile',
        'aadhar_number',
        'pan_number',
        'permanent_address',
        'present_address',
        'shift_type',
        'shift_time',
        'pf_deduction',
        'uan_number',
        'bank_name',
        'bank_account_number',
        'ifsc_code',
        'bank_branch_address',
        'decided_salary',
        'reporting_head',
        'week_off_day',
        'week_off_benefits',
        'holiday_benefits',
        'overtime_benefits',
        'extra_note',
    ];
    if ($omitDepartment) {
        $headers = array_values(array_filter($headers, static function ($h) {
            return $h !== 'department';
        }));
    }
    return $headers;
}

function employeeImportYesNo($value, $default = 'No')
{
    $v = strtolower(trim((string) $value));
    if ($v === '' || $v === '-') {
        return $default;
    }
    if (in_array($v, ['yes', 'y', '1', 'true'], true)) {
        return 'Yes';
    }
    if (in_array($v, ['no', 'n', '0', 'false'], true)) {
        return 'No';
    }
    return $default;
}

function employeeImportGet(array $row, $keys)
{
    foreach ((array) $keys as $key) {
        $key = strtolower(str_replace([' ', '-'], '_', (string) $key));
        if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }
    return '';
}

function employeeImportFindDepartmentId($conn, $departmentName, $fallbackDeptId = 0)
{
    $fallbackDeptId = (int) $fallbackDeptId;
    $name = trim((string) $departmentName);
    if ($name !== '') {
        $stmt = $conn->prepare(
            "SELECT id FROM departments
             WHERE status = 1 AND UPPER(TRIM(department_name)) = UPPER(TRIM(?))
             LIMIT 1"
        );
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int) $row['id'];
        }
    }
    return $fallbackDeptId > 0 ? $fallbackDeptId : 0;
}

/**
 * Import employees from CSV / XLS / XLSX.
 * @return array{success:int,skipped:int,errors:int,error_log:string[]}
 */
function employeeImportFile($conn, $filePath, $originalName, $defaultDeptId = 0, $createdBy = 0)
{
    require_once __DIR__ . '/attendance_helper.php';
    require_once __DIR__ . '/date_helper.php';

    ensureEmployeesTable($conn);
    $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
    $rows = attendanceReadSpreadsheet($filePath, $ext);

    $success = 0;
    $skipped = 0;
    $errors = 0;
    $errorLog = [];
    $lineNo = 1; // header = 1, first data = 2

    $insertSql = "INSERT INTO employees (
        employee_code, biometric_user_id, pay_type, department_id, sub_department_id, employee_name, father_husband_name,
        permanent_address, present_address, mobile_number, emergency_mobile,
        aadhar_number, pan_number, date_of_birth, designation, date_of_joining, date_of_exit,
        shift_type, shift_time, pf_deduction, uan_number,
        bank_name, bank_account_number, ifsc_code, bank_branch_address,
        decided_salary, reporting_head, extra_note, week_off_day,
        week_off_benefits, holiday_benefits, overtime_benefits, main_contractor_id, created_by, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare employee insert: ' . $conn->error);
    }

    foreach ($rows as $row) {
        $lineNo++;
        $name = employeeImportGet($row, ['employee_name', 'name']);
        if ($name === '') {
            // empty row
            if (count(array_filter($row, static function ($v) {
                return trim((string) $v) !== '';
            })) === 0) {
                continue;
            }
            $errors++;
            $errorLog[] = "Row {$lineNo}: Employee Name is required.";
            continue;
        }

        $deptName = employeeImportGet($row, ['department', 'department_name']);
        // Department Join Employee import → always lock to that department
        if ($defaultDeptId > 0) {
            $departmentId = (int) $defaultDeptId;
        } else {
            $departmentId = employeeImportFindDepartmentId($conn, $deptName, 0);
        }
        if ($departmentId <= 0) {
            $errors++;
            $errorLog[] = "Row {$lineNo}: Department missing/invalid for \"{$name}\".";
            continue;
        }

        $payType = normalizePayType(employeeImportGet($row, ['pay_type', 'type', 'punch_type']) ?: 'Salary');
        $empCode = strtoupper(employeeImportGet($row, ['employee_code', 'emp_code', 'code']));
        if ($empCode === '') {
            $empCode = generateEmployeeCode($conn, $payType);
        } elseif (!isEmployeeCodeUnique($conn, $empCode, 0)) {
            $skipped++;
            $errorLog[] = "Row {$lineNo}: Code {$empCode} already exists — skipped.";
            continue;
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_\/]{0,29}$/i', $empCode)) {
            $errors++;
            $errorLog[] = "Row {$lineNo}: Invalid employee code \"{$empCode}\".";
            continue;
        }

        $biometricId = employeeImportGet($row, ['biometric_user_id', 'biometric_id', 'bio_id']);
        if ($biometricId === '') {
            $biometricId = $empCode;
        }

        $dob = parseDateInput(employeeImportGet($row, ['date_of_birth', 'dob', 'birth_date']));
        $doj = parseDateInput(employeeImportGet($row, ['date_of_joining', 'doj', 'joining_date']));
        $doe = parseDateInput(employeeImportGet($row, ['date_of_exit', 'exit_date', 'doe']));

        $shiftTypeRaw = employeeImportGet($row, ['shift_type', 'shift']);
        $shiftType = (stripos($shiftTypeRaw, 'night') !== false) ? 'Night' : 'Day';
        $shiftTime = employeeImportGet($row, ['shift_time']);
        $pf = employeeImportYesNo(employeeImportGet($row, ['pf_deduction', 'pf']), 'No');
        $weekOffBen = employeeImportYesNo(employeeImportGet($row, ['week_off_benefits']), 'No');
        $holidayBen = employeeImportYesNo(employeeImportGet($row, ['holiday_benefits']), 'No');
        $overtimeBen = employeeImportYesNo(employeeImportGet($row, ['overtime_benefits']), 'No');

        $salaryRaw = employeeImportGet($row, ['decided_salary', 'salary']);
        $salary = ($salaryRaw === '' || !is_numeric(str_replace(',', '', $salaryRaw)))
            ? null
            : (string) (float) str_replace(',', '', $salaryRaw);

        $father = employeeImportGet($row, ['father_husband_name', 'father_name', 'husband_name']);
        $designation = employeeImportGet($row, ['designation']);
        $mobile = employeeImportGet($row, ['mobile_number', 'mobile']);
        $emergency = employeeImportGet($row, ['emergency_mobile', 'emergency']);
        $aadhar = employeeImportGet($row, ['aadhar_number', 'aadhar']);
        $pan = employeeImportGet($row, ['pan_number', 'pan']);
        $permAddr = employeeImportGet($row, ['permanent_address']);
        $presAddr = employeeImportGet($row, ['present_address']);
        $uan = employeeImportGet($row, ['uan_number', 'uan']);
        $bankName = employeeImportGet($row, ['bank_name']);
        $bankAccount = employeeImportGet($row, ['bank_account_number', 'account_number']);
        $ifsc = employeeImportGet($row, ['ifsc_code', 'ifsc']);
        $bankBranch = employeeImportGet($row, ['bank_branch_address', 'bank_branch']);
        $reporting = employeeImportGet($row, ['reporting_head', 'reporting_person']);
        $weekOffDay = employeeImportGet($row, ['week_off_day']);
        $extraNote = employeeImportGet($row, ['extra_note', 'remarks', 'remark']);
        $subDeptId = 0;
        $mainContractorId = 0;
        $createdByInt = (int) $createdBy;

        $stmt->bind_param(
            'sssiisssssssssssssssssssssssssssii',
            $empCode,
            $biometricId,
            $payType,
            $departmentId,
            $subDeptId,
            $name,
            $father,
            $permAddr,
            $presAddr,
            $mobile,
            $emergency,
            $aadhar,
            $pan,
            $dob,
            $designation,
            $doj,
            $doe,
            $shiftType,
            $shiftTime,
            $pf,
            $uan,
            $bankName,
            $bankAccount,
            $ifsc,
            $bankBranch,
            $salary,
            $reporting,
            $extraNote,
            $weekOffDay,
            $weekOffBen,
            $holidayBen,
            $overtimeBen,
            $mainContractorId,
            $createdByInt
        );

        if ($stmt->execute()) {
            $success++;
        } else {
            $errors++;
            $errorLog[] = "Row {$lineNo}: DB error for \"{$name}\" — " . $stmt->error;
        }
    }

    $stmt->close();

    return [
        'success' => $success,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_log' => $errorLog,
    ];
}

