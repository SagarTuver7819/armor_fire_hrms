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
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
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
('QA AND QC', 'fa-clipboard-check', '#16A085', 9),
('QMS', 'fa-certificate', '#0D9488', 10),
('NPD', 'fa-lightbulb', '#F1C40F', 11),
('DESIGN', 'fa-ruler-combined', '#8E44AD', 12),
('PRODUCTION', 'fa-industry', '#E74C3C', 13),
('PURCHASE', 'fa-cart-shopping', '#2980B9', 14),
('STORE', 'fa-warehouse', '#D35400', 15),
('LABORATORY', 'fa-flask', '#27AE60', 16),
('CORE', 'fa-cubes', '#7F8C8D', 17),
('MELTING 1', 'fa-fire', '#C0392B', 18),
('MELTING 2', 'fa-fire-flame-curved', '#E74C3C', 19),
('CUTTING', 'fa-scissors', '#34495E', 20),
('GRINDING', 'fa-gear', '#95A5A6', 21),
('LATHE', 'fa-gears', '#2C3E50', 22),
('CNC', 'fa-microchip', '#1ABC9C', 23),
('BUFF', 'fa-star', '#F39C12', 24),
('CLEANING', 'fa-broom', '#3498DB', 25),
('ASSEMBLY 1 & COATING', 'fa-layer-group', '#9B59B6', 26),
('ASSEMBLY 2', 'fa-object-group', '#8E44AD', 27),
('ASSEMBLY 3 RRL & FLEXIBLE', 'fa-diagram-project', '#6C5CE7', 28),
('ASSEMBLY 4 ALARM & DELUGE VALVE', 'fa-bell', '#E17055', 29),
('SPRINKLER', 'fa-shower', '#00CEC9', 30),
('ARGON', 'fa-atom', '#0984E3', 31),
('MAINTENANCE', 'fa-wrench', '#FD79A8', 32),
('CANTEEN', 'fa-utensils', '#FDCB6E', 33),
('DISPATCH', 'fa-truck', '#00B894', 34),
('TRANSPORT', 'fa-truck-fast', '#636E72', 35),
('BUTTERFLY VALVE', 'fa-circle-dot', '#00B894', 36),
('DRUM', 'fa-drum', '#6C5CE7', 37);

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
-- Default Users
-- Password for both: password123 (plain text for simple demo)
-- Later you can switch to password_hash() easily
-- -----------------------------------------------------
INSERT INTO users (username, password, full_name, role, department_id, status) VALUES
('admin', 'password123', 'System Admin', 'admin', 1, 1),
('hr', 'password123', 'HR Manager', 'hr', 2, 1);
