-- =====================================================
-- Employees / Join Employee Module
-- Run this once in phpMyAdmin or MySQL
-- =====================================================

USE armor_hrms;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Auto code like EMP0001
    employee_code VARCHAR(30) NOT NULL UNIQUE,

    -- Link to Department Master
    department_id INT NOT NULL,

    -- 1 to 12 Personal / Job
    employee_name VARCHAR(150) NOT NULL COMMENT 'As per Aadhar Card',
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

    -- 13 Shift
    shift_type ENUM('Day', 'Night') DEFAULT 'Day',
    shift_time VARCHAR(50) DEFAULT NULL,

    -- 14-15 PF
    pf_deduction ENUM('Yes', 'No') DEFAULT 'No',
    uan_number VARCHAR(30) DEFAULT NULL,

    -- 16-19 Bank
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_account_number VARCHAR(40) DEFAULT NULL,
    ifsc_code VARCHAR(20) DEFAULT NULL,
    bank_branch_address TEXT,

    -- 20-24 Other
    decided_salary DECIMAL(12, 2) DEFAULT NULL,
    reporting_head VARCHAR(150) DEFAULT NULL,
    extra_note TEXT,
    week_off_day VARCHAR(30) DEFAULT NULL,
    week_off_benefits ENUM('Yes', 'No') DEFAULT 'No',
    holiday_benefits ENUM('Yes', 'No') DEFAULT 'No',
    overtime_benefits ENUM('Yes', 'No') DEFAULT 'No',

    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id),
    INDEX idx_employees_department (department_id),
    INDEX idx_employees_name (employee_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
