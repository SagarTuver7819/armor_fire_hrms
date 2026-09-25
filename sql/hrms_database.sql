-- =====================================================
-- ARMOR HRMS - Database Setup
-- Import this file in phpMyAdmin or run via MySQL CLI
-- =====================================================

CREATE DATABASE IF NOT EXISTS armor_hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE armor_hrms;

-- -----------------------------------------------------
-- Table: departments (Department Master)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    icon_class VARCHAR(80) NOT NULL DEFAULT 'fa-building',
    icon_color VARCHAR(20) NOT NULL DEFAULT '#4A90E2',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: users (Admin / HR login portals)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'hr',
    department_id INT NULL,
    custom_role_id INT NULL,
    employee_id INT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_users_custom_role (custom_role_id),
    INDEX idx_users_employee (employee_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Insert Department Master
-- -----------------------------------------------------
INSERT INTO departments (department_name, icon_class, icon_color, sort_order) VALUES
('ADMINISTRATION', 'fa-landmark', '#5B6CFF', 1),
('HUMAN RESOURCE MANAGEMENT', 'fa-users', '#E85D75', 2),
('ACCOUNTS AND FINANCE', 'fa-file-invoice-dollar', '#2ECC71', 3),
('COLLECTION', 'fa-hand-holding-dollar', '#F39C12', 4),
('SALES & MARKETING - BACK OFFICE', 'fa-headset', '#9B59B6', 5),
('SALES & MARKETING - ON FIELD', 'fa-handshake', '#1ABC9C', 6),
('IT AND NETWORKING', 'fa-network-wired', '#3498DB', 7),
('TENDER', 'fa-file-contract', '#E67E22', 8),
('PRODUCT COMPLIANCE', 'fa-clipboard-list', '#14B8A6', 9),
('QA AND QC', 'fa-clipboard-check', '#16A085', 10),
('QMS', 'fa-certificate', '#0D9488', 11),
('NPD', 'fa-lightbulb', '#F1C40F', 12),
('DESIGN', 'fa-ruler-combined', '#8E44AD', 13),
('PRODUCTION', 'fa-industry', '#E74C3C', 14),
('PURCHASE', 'fa-cart-shopping', '#2980B9', 15),
('STORE', 'fa-warehouse', '#D35400', 16),
('LABORATORY', 'fa-flask', '#27AE60', 17),
('CORE', 'fa-cubes', '#7F8C8D', 18),
('MELTING 1', 'fa-fire', '#C0392B', 19),
('MELTING 2', 'fa-fire-flame-curved', '#E74C3C', 20),
('CUTTING', 'fa-scissors', '#34495E', 21),
('GRINDING', 'fa-gear', '#95A5A6', 22),
('LATHE', 'fa-gears', '#2C3E50', 23),
('CNC', 'fa-microchip', '#1ABC9C', 24),
('BUFF', 'fa-star', '#F39C12', 25),
('CLEANING', 'fa-broom', '#3498DB', 26),
('ASSEMBLY 1 & COATING', 'fa-layer-group', '#9B59B6', 27),
('ASSEMBLY 2', 'fa-object-group', '#8E44AD', 28),
('ASSEMBLY 3 RRL & FLEXIBLE', 'fa-diagram-project', '#6C5CE7', 29),
('ASSEMBLY 4 ALARM & DELUGE VALVE', 'fa-bell', '#E17055', 30),
('SPRINKLER', 'fa-shower', '#00CEC9', 31),
('ARGON', 'fa-atom', '#0984E3', 32),
('MAINTENANCE', 'fa-wrench', '#FD79A8', 33),
('CANTEEN', 'fa-utensils', '#FDCB6E', 34),
('DISPATCH', 'fa-truck', '#00B894', 35),
('TRANSPORT', 'fa-truck-fast', '#636E72', 36),
('BUTTERFLY VALVE', 'fa-circle-dot', '#00B894', 37),
('DRUM', 'fa-drum', '#6C5CE7', 38);

-- -----------------------------------------------------
-- Table: company_settings (Logo + Company Name)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS company_settings (
    id INT PRIMARY KEY DEFAULT 1,
    company_name VARCHAR(150) NOT NULL DEFAULT 'Armor Fire',
    company_logo VARCHAR(255) DEFAULT NULL,
    login_logo VARCHAR(255) DEFAULT NULL,
    dashboard_logo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO company_settings (id, company_name, company_logo, login_logo, dashboard_logo)
VALUES (1, 'Armor Fire', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE id = id;

-- -----------------------------------------------------
-- Table: circulars (HR/Admin scanned company circulars)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS circulars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    circular_no VARCHAR(100) DEFAULT NULL,
    circular_date DATE NOT NULL,
    added_date DATE NOT NULL,
    remarks TEXT DEFAULT NULL,
    pdf_file VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    apply_all_departments TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_circular_date (circular_date),
    INDEX idx_circular_added (added_date),
    INDEX idx_circular_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS circular_departments (
    circular_id INT NOT NULL,
    department_id INT NOT NULL,
    PRIMARY KEY (circular_id, department_id),
    INDEX idx_cd_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS circular_reads (
    circular_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (circular_id, user_id),
    INDEX idx_cr_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: policies (HR/Admin scanned company policies)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    policy_no VARCHAR(100) DEFAULT NULL,
    policy_date DATE NOT NULL,
    added_date DATE NOT NULL,
    remarks TEXT DEFAULT NULL,
    pdf_file VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    apply_all_departments TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_policy_date (policy_date),
    INDEX idx_policy_added (added_date),
    INDEX idx_policy_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_departments (
    policy_id INT NOT NULL,
    department_id INT NOT NULL,
    PRIMARY KEY (policy_id, department_id),
    INDEX idx_pd_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_reads (
    policy_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (policy_id, user_id),
    INDEX idx_pr_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Custom Roles & Permissions (Admin configures)
-- department_id = 0 means All Departments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_name (name),
    INDEX idx_roles_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users.custom_role_id / employee_id added via db_sync / ensureRoleTables (ALTER)

-- -----------------------------------------------------
-- Department Heads (1 employee per department)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS department_heads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    employee_id INT NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    set_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dh_department (department_id),
    INDEX idx_dh_employee (employee_id),
    INDEX idx_dh_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded roles (via ensureDepartmentHeadTables / seedStandardRoles):
-- HR_HEAD, PAYROLL_HEAD, DEPT_HEAD, OFFICE_STAFF

-- -----------------------------------------------------
-- Default Users
-- Password for both: password123 (plain text for simple demo)
-- Later you can switch to password_hash() easily
-- -----------------------------------------------------
INSERT INTO users (username, password, full_name, role, department_id, status) VALUES
('admin', 'password123', 'System Admin', 'admin', 1, 1),
('hr', 'password123', 'HR Manager', 'hr', 2, 1);
