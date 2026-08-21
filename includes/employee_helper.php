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

    if ($closeAfter) {
        $conn->close();
    }
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
 * Generate next employee code: SAL0001 / JW0001
 */
function generateEmployeeCode($conn, $payType = 'Salary')
{
    $payType = normalizePayType($payType);
    $prefix = 'SAL';
    if ($payType === 'Jobwork') {
        $prefix = 'JW';
    } elseif ($payType === 'ContractorMain') {
        $prefix = 'CM';
    }
    $max = 0;
    $like = $conn->real_escape_string($prefix) . '%';
    $result = $conn->query("SELECT employee_code FROM employees WHERE employee_code LIKE '{$like}'");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (preg_match('/(\d+)/', (string) $row['employee_code'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
    }
    return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

function isEmployeeCodeUnique($conn, $code, $excludeId = 0)
{
    $code = trim((string) $code);
    $excludeId = (int) $excludeId;
    $stmt = $conn->prepare("SELECT id FROM employees WHERE employee_code = ? AND id <> ? LIMIT 1");
    $stmt->bind_param('si', $code, $excludeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return empty($row);
}

/**
 * Get one employee by ID
 */
function getEmployeeById($employeeId)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);

    $stmt = $conn->prepare(
        "SELECT e.*, d.department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
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
 * Count employees in a department
 */
function countEmployeesByDepartment($departmentId)
{
    $conn = getDBConnection();
    ensureEmployeesTable($conn);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total FROM employees WHERE department_id = ? AND status = 1"
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    return (int) ($row['total'] ?? 0);
}

/**
 * Format date for display d-m-Y
 */
function formatDateDisplay($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    return date('d-m-Y', strtotime($date));
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
    if ($shiftType !== '') {
        foreach ($shifts as $shift) {
            if (($shift['shift_type'] ?? '') === $shiftType) {
                return (int) $shift['id'];
            }
        }
    }
    return 0;
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
    $days = [];
    foreach ($holidays as $row) {
        if (($row['holiday_type'] ?? '') !== 'Week-Off') {
            continue;
        }
        $day = trim((string) ($row['week_day'] ?? ''));
        if ($day !== '' && !in_array($day, $days, true)) {
            $days[] = $day;
        }
    }
    if (!$days) {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
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
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Aadhar/PAN file must be JPG, PNG, PDF, or WEBP.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Aadhar/PAN file must be 5MB or smaller.');
    }
    $dir = dirname(__DIR__) . '/assets/uploads/docs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create document upload folder.');
    }
    $kind = preg_replace('/[^a-z]/', '', strtolower((string) $kind));
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
