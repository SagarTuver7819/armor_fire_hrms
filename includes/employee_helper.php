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

    if ($closeAfter) {
        $conn->close();
    }
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
 * Generate next employee code: EMP0001, EMP0002...
 */
function generateEmployeeCode($conn)
{
    $result = $conn->query("SELECT employee_code FROM employees ORDER BY id DESC LIMIT 1");
    $last = $result ? $result->fetch_assoc() : null;

    $nextNum = 1;
    if ($last && preg_match('/(\d+)/', $last['employee_code'], $m)) {
        $nextNum = ((int) $m[1]) + 1;
    }

    return 'EMP' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
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
