-- =====================================================
-- Armor HRMS - Master Tables
-- Safe to run multiple times (IF NOT EXISTS)
-- Departments table already exists from hrms_database.sql
-- =====================================================

USE armor_hrms;

CREATE TABLE IF NOT EXISTS designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_designations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shifts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_types (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    holiday_type ENUM('Holiday','Week-Off') NOT NULL DEFAULT 'Holiday',
    holiday_date DATE DEFAULT NULL,
    week_day VARCHAR(20) DEFAULT NULL,
    remarks TEXT,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_holidays_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salary_components (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_types (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_catalog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) DEFAULT NULL,
    report_name VARCHAR(150) NOT NULL,
    module_name VARCHAR(100) DEFAULT NULL,
    description TEXT,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_report_catalog_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
