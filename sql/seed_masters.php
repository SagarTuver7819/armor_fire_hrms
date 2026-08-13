<?php
/**
 * Seed sample data for masters (except departments - already seeded)
 * Run: php sql/seed_masters.php
 * Safe re-run: skips if table already has rows.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$conn = getDBConnection();
ensureMasterTables($conn);

function seedIfEmpty(mysqli $conn, $table, $sqlInsert, $label)
{
    $tableSafe = preg_replace('/[^a-z0-9_]/', '', $table);
    $c = (int) $conn->query("SELECT COUNT(*) AS c FROM `{$tableSafe}` WHERE status = 1")->fetch_assoc()['c'];
    if ($c > 0) {
        echo "[SKIP] {$label} already has {$c} rows\n";
        return;
    }
    if ($conn->multi_query($sqlInsert)) {
        while ($conn->more_results() && $conn->next_result()) { /* flush */ }
        echo "[OK] {$label} seeded\n";
    } else {
        echo "[ERR] {$label}: {$conn->error}\n";
    }
}

seedIfEmpty($conn, 'designations', "
INSERT INTO designations (code, name, description, sort_order, status) VALUES
('OPR', 'Operator', 'Shop floor operator', 1, 1),
('SUP', 'Supervisor', 'Line supervisor', 2, 1),
('EXE', 'Executive', 'Office executive', 3, 1),
('MGR', 'Manager', 'Department manager', 4, 1),
('TEC', 'Technician', 'Technical staff', 5, 1);
", 'Designations');

seedIfEmpty($conn, 'shifts', "
INSERT INTO shifts (name, shift_type, start_time, end_time, remarks, status) VALUES
('General Day', 'Day', '09:00:00', '18:00:00', 'Standard day shift', 1),
('Morning', 'Day', '06:00:00', '14:00:00', 'Early morning', 1),
('Evening', 'Day', '14:00:00', '22:00:00', 'Evening shift', 1),
('Night A', 'Night', '22:00:00', '06:00:00', 'Night production', 1),
('Night B', 'Night', '21:00:00', '05:00:00', 'Alternate night', 1);
", 'Shifts');

seedIfEmpty($conn, 'leave_types', "
INSERT INTO leave_types (code, leave_type, days_allowed, is_paid, description, status) VALUES
('CL', 'Casual Leave', 12, 'Yes', 'Short notice leave', 1),
('SL', 'Sick Leave', 10, 'Yes', 'Medical leave', 1),
('EL', 'Earned Leave', 15, 'Yes', 'Accumulated leave', 1),
('LWP', 'Leave Without Pay', 0, 'No', 'Unpaid leave', 1),
('ML', 'Maternity Leave', 180, 'Yes', 'As per policy', 1);
", 'Leave Types');

seedIfEmpty($conn, 'holidays', "
INSERT INTO holidays (title, holiday_type, holiday_date, week_day, remarks, status) VALUES
('Republic Day', 'Holiday', '2026-01-26', NULL, 'National holiday', 1),
('Independence Day', 'Holiday', '2026-08-15', NULL, 'National holiday', 1),
('Diwali', 'Holiday', '2026-11-08', NULL, 'Festival', 1),
('Weekly Off Sunday', 'Week-Off', NULL, 'Sunday', 'Default week off', 1),
('Weekly Off Saturday', 'Week-Off', NULL, 'Saturday', 'Alternate week off', 1);
", 'Holidays');

seedIfEmpty($conn, 'salary_components', "
INSERT INTO salary_components (code, component_name, component_type, calculation, default_value, status) VALUES
('BASIC', 'Basic Salary', 'Earning', 'Fixed', 10000, 1),
('HRA', 'House Rent Allowance', 'Earning', 'Percentage', 40, 1),
('CONV', 'Conveyance', 'Earning', 'Fixed', 1600, 1),
('PF', 'Provident Fund', 'Deduction', 'Percentage', 12, 1),
('PT', 'Professional Tax', 'Deduction', 'Fixed', 200, 1);
", 'Salary Components');

seedIfEmpty($conn, 'document_types', "
INSERT INTO document_types (code, document_name, is_mandatory, validity_days, description, status) VALUES
('AADHAR', 'Aadhar Card', 'Yes', 0, 'Identity proof', 1),
('PAN', 'PAN Card', 'Yes', 0, 'Tax identity', 1),
('BANK', 'Bank Passbook / Cancelled Cheque', 'Yes', 0, 'Salary account', 1),
('PHOTO', 'Passport Photo', 'Yes', 0, 'Employee photo', 1),
('EDU', 'Education Certificate', 'No', 0, 'Qualification proof', 1);
", 'Document Types');

seedIfEmpty($conn, 'assets', "
INSERT INTO assets (asset_code, asset_name, category, serial_no, condition_status, remarks, status) VALUES
('AST001', 'Laptop Dell', 'IT', 'DL-1001', 'Good', 'Office laptop', 1),
('AST002', 'Mobile Phone', 'IT', 'MB-2002', 'New', 'Company SIM device', 1),
('AST003', 'Safety Helmet', 'Safety', 'SH-3003', 'Good', 'Shop floor PPE', 1),
('AST004', 'Tool Kit', 'Tools', 'TK-4004', 'Fair', 'Maintenance kit', 1),
('AST005', 'ID Card Holder', 'Admin', 'ID-5005', 'New', 'Accessories', 1);
", 'Assets');

seedIfEmpty($conn, 'report_catalog', "
INSERT INTO report_catalog (code, report_name, module_name, description, status) VALUES
('EMP-ALL', 'All Employees Report', 'Employees', 'Company-wide employee list', 1),
('EMP-DEPT', 'Department Employee Report', 'Employees', 'Employees by department', 1),
('ATT-DAILY', 'Daily Attendance', 'Attendance', 'Day-wise attendance', 1),
('LEAVE-SUM', 'Leave Summary', 'Leave', 'Leave balance summary', 1),
('SAL-REG', 'Salary Register', 'Salary', 'Monthly salary register', 1);
", 'Report Catalog');

seedIfEmpty($conn, 'products', "
INSERT INTO products (code, product_name, category, unit, description, status) VALUES
('SPR-001', 'Sprinkler Head', 'Sprinkler', 'Nos', 'Fire sprinkler', 1),
('VLV-001', 'Alarm Valve', 'Valve', 'Nos', 'Alarm check valve', 1),
('DLG-001', 'Deluge Valve', 'Valve', 'Nos', 'Deluge system valve', 1),
('RRL-001', 'RRL Hose', 'Hose', 'Mtr', 'Reinforced rubber lined hose', 1),
('FXL-001', 'Flexible Drop', 'Flexible', 'Nos', 'Flexible sprinkler drop', 1);
", 'Products');

$deptCount = (int) $conn->query("SELECT COUNT(*) AS c FROM departments WHERE status = 1")->fetch_assoc()['c'];
echo "[INFO] Departments active: {$deptCount} (managed via Department Master CRUD)\n";
$conn->close();
echo "\nDone. Open masters/index.php\n";
