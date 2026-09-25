<?php
/**
 * DB Sync — apply schema + seeds on live (Admin only).
 * Local:  http://localhost/armor_new_hrms/db_sync.php
 * Live:   https://armor-hrms.oceanhub.co.in/db_sync.php
 * Bulk employee logins (Office Staff):
 *   .../db_sync.php?bulk_logins=1
 *   Username = Employee Code · Password = FirstName@123
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/employee_helper.php';
require_once __DIR__ . '/includes/master_helper.php';
require_once __DIR__ . '/includes/contractor_helper.php';
require_once __DIR__ . '/includes/payroll_helper.php';
require_once __DIR__ . '/includes/payroll_reports_helper.php';
require_once __DIR__ . '/includes/attendance_helper.php';
require_once __DIR__ . '/includes/biometric_helper.php';
require_once __DIR__ . '/includes/department_icons.php';
require_once __DIR__ . '/includes/department_helper.php';
require_once __DIR__ . '/includes/leave_helper.php';
require_once __DIR__ . '/includes/coff_helper.php';
require_once __DIR__ . '/includes/circular_helper.php';
require_once __DIR__ . '/includes/policy_helper.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';
require_once __DIR__ . '/sql/seed_contractor_masters.php';

requireAdmin();

$pageTitle = 'DB Sync';
$useSidebar = true;
$sidebarActive = 'settings';

$log = [];
$ok = true;

function dbSyncCount($conn, $sql)
{
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

function dbSyncHasColumn($conn, $table, $column)
{
    $table = preg_replace('/[^a-z0-9_]/', '', $table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}

function dbSyncHasTable($conn, $table)
{
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

$conn = getDBConnection();

try {
    ensureEmployeesTable($conn);
    $log[] = 'Employees table + extra columns ready (pay_type, office fields, gender, marital, photo, pf_start_date, pf contributions, family).';

    ensureMasterTables($conn);
    $log[] = 'Masters tables ready (incl. holidays.from/to dates). Sub Department seeded from Department names where missing.';

    ensureContractorTables($conn);
    $log[] = 'Contractor tables ready (grades, products, employment, operations rate list).';

    ensurePayrollTables($conn);
    $log[] = 'Payroll tables ready (diary, jobwork, payslips).';

    ensurePayrollReportTables($conn);
    $log[] = 'Payroll report tables ready (salary_register_locks for Finalize/NEFT).';

    ensureAttendanceTables($conn);
    $log[] = 'Attendance tables ready (punches, day status, import batches, biometric_user_id).';
    $log[] = 'Attendance flex: late/early columns (is_late, is_early, flex_seq, penalty_leave, shift_start/end) — 1h window, 2 flex/month, Half PL → Half LWP.';

    ensureBiometricTables($conn);
    seedArmorBiometricMachinesOnce($conn);
    $log[] = 'Biometric machines ready (tables + one-time default seed if empty; delete will not recreate).';

    ensureLeaveTables($conn);
    $log[] = 'Leave tables ready (balances, requests, attachment_path).';
    $log[] = 'Leave types: PL (24/yr monthly CF), SL (2/month wipe), DL (duty), LWP (unpaid), C-OFF (via coff helper).';

    ensureCoffTables($conn);
    $log[] = 'C-Off tables ready (credits ledger, usage, C-OFF leave type; 4h=0.5 / 8h=1 on WO/Holiday, expire 2 months).';

    // Seed / refresh current-year leave balances for all active employees
    $yearNow = (int) date('Y');
    try {
        $alloc = leaveAllocateYearlyBalances($yearNow, 0, false, $conn);
        $log[] = 'Leave balances year ' . $yearNow . ': created/refreshed '
            . (int) ($alloc['created'] ?? 0) . ' + updated ' . (int) ($alloc['updated'] ?? 0)
            . ' row(s) (PL monthly CF, SL monthly wipe, DL/LWP/C-Off tracked).';
    } catch (Throwable $e) {
        $log[] = 'Leave balance allocate warning: ' . $e->getMessage();
    }

    ensureCircularTables($conn);
    $log[] = 'Circulars table ready (scanned PDF circulars for HR/Admin).';

    ensurePolicyTables($conn);
    $log[] = 'Policies table ready (scanned PDF policies for HR/Admin).';

    ensureRoleTables($conn);
    $log[] = 'Roles & permissions tables ready (custom roles, role_permissions, users.custom_role_id, users.employee_id).';

    ensureDepartmentHeadTables($conn);
    $log[] = 'Department heads + seeded roles ready (HR_HEAD, PAYROLL_HEAD, DEPT_HEAD, OFFICE_STAFF).';

    // Optional: ?bulk_logins=1 → create/reset Office Staff logins (Username=Code, Password=FirstName@123)
    if (isset($_GET['bulk_logins']) && (string) $_GET['bulk_logins'] === '1') {
        $bulk = bulkProvisionOfficeStaffLogins(true);
        $log[] = 'Bulk employee logins: created ' . (int) ($bulk['created'] ?? 0)
            . ', updated ' . (int) ($bulk['updated'] ?? 0)
            . ', skipped ' . (int) ($bulk['skipped'] ?? 0)
            . ' · Username=EmployeeCode · Password=FirstName@123';
        if (!empty($bulk['errors'])) {
            foreach (array_slice($bulk['errors'], 0, 10) as $err) {
                $log[] = 'Login error: ' . $err;
            }
        }
    }

    $deptMerge = mergeDuplicateDepartments($conn);
    foreach ($deptMerge['log'] as $line) {
        $log[] = 'Departments: ' . $line;
    }
    if (!$deptMerge['ok']) {
        $ok = false;
    }

    $iconUpdated = syncDepartmentIcons($conn);
    $log[] = 'Department dashboard icons updated: ' . $iconUpdated . ' row(s).';

    $seed = seedContractorProductMasters($conn);
    if ($seed['ok']) {
        $log[] = 'Contractor grades: inserted ' . $seed['grades_inserted'] . ', active ' . $seed['grades_active'] . '.';
        $log[] = 'Contractor products: inserted ' . $seed['products_inserted']
            . ', updated ' . $seed['products_updated']
            . ', skipped ' . $seed['products_skipped']
            . ', active ' . $seed['products_active'] . '.';
    } else {
        $ok = false;
        $log[] = 'Product seed: ' . $seed['error'];
    }

    $checks = [
        'sub_departments table' => dbSyncHasTable($conn, 'sub_departments'),
        'employees.sub_department_id' => dbSyncHasColumn($conn, 'employees', 'sub_department_id'),
        'employees.pf_start_date' => dbSyncHasColumn($conn, 'employees', 'pf_start_date'),
        'employees.pf_employee_contribution' => dbSyncHasColumn($conn, 'employees', 'pf_employee_contribution'),
        'employees.pf_employer_contribution' => dbSyncHasColumn($conn, 'employees', 'pf_employer_contribution'),
        'employees.office_email' => dbSyncHasColumn($conn, 'employees', 'office_email'),
        'employees.office_mobile' => dbSyncHasColumn($conn, 'employees', 'office_mobile'),
        'employees.photo_file' => dbSyncHasColumn($conn, 'employees', 'photo_file'),
        'employee_family_members table' => dbSyncHasTable($conn, 'employee_family_members'),
        'holidays.department_id' => dbSyncHasColumn($conn, 'holidays', 'department_id'),
        'holidays.holiday_to_date' => dbSyncHasColumn($conn, 'holidays', 'holiday_to_date'),
        'contractor_employment.sub_department_id' => dbSyncHasColumn($conn, 'contractor_employment', 'sub_department_id'),
        'contractor_operation_items.grade_id' => dbSyncHasColumn($conn, 'contractor_operation_items', 'grade_id'),
        'contractor_products table' => dbSyncHasTable($conn, 'contractor_products'),
        'contractor_grades table' => dbSyncHasTable($conn, 'contractor_grades'),
        'contractor_operation_sheets table' => dbSyncHasTable($conn, 'contractor_operation_sheets'),
        'attendance_punches table' => dbSyncHasTable($conn, 'attendance_punches'),
        'attendance_day_status table' => dbSyncHasTable($conn, 'attendance_day_status'),
        'attendance_day_status.is_late' => dbSyncHasColumn($conn, 'attendance_day_status', 'is_late'),
        'attendance_day_status.is_early' => dbSyncHasColumn($conn, 'attendance_day_status', 'is_early'),
        'attendance_day_status.flex_seq' => dbSyncHasColumn($conn, 'attendance_day_status', 'flex_seq'),
        'attendance_day_status.penalty_leave' => dbSyncHasColumn($conn, 'attendance_day_status', 'penalty_leave'),
        'attendance_day_status.shift_start' => dbSyncHasColumn($conn, 'attendance_day_status', 'shift_start'),
        'attendance_day_status.shift_end' => dbSyncHasColumn($conn, 'attendance_day_status', 'shift_end'),
        'employees.biometric_user_id' => dbSyncHasColumn($conn, 'employees', 'biometric_user_id'),
        'employee_leave_balances table' => dbSyncHasTable($conn, 'employee_leave_balances'),
        'leave_requests table' => dbSyncHasTable($conn, 'leave_requests'),
        'leave_requests.attachment_path' => dbSyncHasColumn($conn, 'leave_requests', 'attachment_path'),
        'leave_types.PL' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM leave_types WHERE status=1 AND UPPER(TRIM(code))='PL'") > 0,
        'leave_types.SL (2/mo wipe)' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM leave_types WHERE status=1 AND UPPER(TRIM(code))='SL'") > 0,
        'leave_types.DL' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM leave_types WHERE status=1 AND UPPER(TRIM(code))='DL'") > 0,
        'leave_types.LWP' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM leave_types WHERE status=1 AND UPPER(TRIM(code))='LWP'") > 0,
        'leave_types.C-OFF' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM leave_types WHERE status=1 AND (UPPER(TRIM(code))='C-OFF' OR UPPER(TRIM(code))='COFF')") > 0,
        'coff_credits table' => dbSyncHasTable($conn, 'coff_credits'),
        'coff_usage table' => dbSyncHasTable($conn, 'coff_usage'),
        'salary_diary.lwp_days' => dbSyncHasColumn($conn, 'salary_diary', 'lwp_days'),
        'salary_diary.sl_days' => dbSyncHasColumn($conn, 'salary_diary', 'sl_days'),
        'salary_diary.dl_days' => dbSyncHasColumn($conn, 'salary_diary', 'dl_days'),
        'circulars table' => dbSyncHasTable($conn, 'circulars'),
        'circular_departments table' => dbSyncHasTable($conn, 'circular_departments'),
        'circular_reads table' => dbSyncHasTable($conn, 'circular_reads'),
        'policies table' => dbSyncHasTable($conn, 'policies'),
        'policy_departments table' => dbSyncHasTable($conn, 'policy_departments'),
        'policy_reads table' => dbSyncHasTable($conn, 'policy_reads'),
        'roles table' => dbSyncHasTable($conn, 'roles'),
        'role_permissions table' => dbSyncHasTable($conn, 'role_permissions'),
        'users.custom_role_id' => dbSyncHasColumn($conn, 'users', 'custom_role_id'),
        'users.employee_id' => dbSyncHasColumn($conn, 'users', 'employee_id'),
        'department_heads table' => dbSyncHasTable($conn, 'department_heads'),
        'salary_register_locks table' => dbSyncHasTable($conn, 'salary_register_locks'),
    ];

    $counts = [
        'Departments' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM departments WHERE status = 1'),
        'Sub Departments' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM sub_departments WHERE status = 1'),
        'Contractor Products' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_products WHERE status = 1'),
        'Contractor Grades' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_grades WHERE status = 1'),
        'Jobwork Employees' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM employees WHERE status = 1 AND pay_type = 'Jobwork'"),
        'Operations Rate Lists' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_operation_sheets WHERE status = 1'),
        'Attendance Day Rows' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM attendance_day_status'),
        'Attendance Punches' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM attendance_punches'),
        'Leave Types (active)' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM leave_types WHERE status = 1'),
        'Leave Balance Rows' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM employee_leave_balances'),
        'Leave Requests' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM leave_requests'),
        'C-Off Credits' => dbSyncHasTable($conn, 'coff_credits') ? dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM coff_credits') : 0,
        'Circulars' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM circulars WHERE status = 1'),
        'Policies' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM policies WHERE status = 1'),
        'Custom Roles' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM roles WHERE status = 1'),
        'Employee Portal Logins' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'employee' AND status = 1"),
        'Department Heads' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM department_heads WHERE status = 1'),
    ];
} catch (Throwable $e) {
    $ok = false;
    $log[] = 'Error: ' . $e->getMessage();
    $checks = $checks ?? [];
    $counts = $counts ?? [];
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $ok ? 'DB Sync complete' : 'DB Sync finished with issues'; ?></h1>
            <p>
                Safe re-run for live. Creates missing tables/columns · seeds PL/SL/DL/LWP/C-Off ·
                attendance late/early flex · leave attachments · leave balances for current year.
            </p>
        </div>

        <div class="form-section">
            <h3><i class="fa-solid fa-list-check"></i> What ran</h3>
            <ul class="db-sync-log">
                <?php foreach ($log as $line): ?>
                    <li><?php echo htmlspecialchars($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($checks)): ?>
        <div class="form-section">
            <h3><i class="fa-solid fa-database"></i> Schema checks</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tbody>
                        <?php foreach ($checks as $label => $pass): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($label); ?></td>
                                <td><?php echo $pass ? 'OK' : 'MISSING'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($counts)): ?>
        <div class="form-section">
            <h3><i class="fa-solid fa-hashtag"></i> Record counts</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tbody>
                        <?php foreach ($counts as $label => $n): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($label); ?></td>
                                <td><?php echo (int) $n; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <a href="<?php echo app_url('db_sync.php'); ?>" class="btn-primary"><i class="fa-solid fa-rotate"></i> Run again</a>
            <a href="<?php echo app_url('db_sync.php?bulk_logins=1'); ?>" class="btn-secondary"
               onclick="return confirm('Create/reset Office Staff logins for all active employees?\nUsername=EmployeeCode\nPassword=FirstName@123');">
                <i class="fa-solid fa-users-gear"></i> Sync + Bulk Employee Logins
            </a>
            <a href="<?php echo app_url('roles/bulk_employee_logins.php'); ?>" class="btn-secondary">Bulk Logins Page</a>
            <a href="<?php echo app_url('dashboard.php'); ?>" class="btn-secondary">Dashboard</a>
        </div>
    </div>
</main>
<style>
    .db-sync-log { margin: 0; padding-left: 18px; line-height: 1.7; }
    .db-sync-log li { margin: 4px 0; }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
