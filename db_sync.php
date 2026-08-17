<?php
/**
 * DB Sync — apply schema + seeds on live (Admin only).
 * Local:  http://localhost/armor_new_hrms/db_sync.php
 * Live:   https://armor-hrms.oceanhub.co.in/db_sync.php
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/employee_helper.php';
require_once __DIR__ . '/includes/master_helper.php';
require_once __DIR__ . '/includes/contractor_helper.php';
require_once __DIR__ . '/includes/payroll_helper.php';
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
    $log[] = 'Employees table + extra columns ready (pay_type, aadhar_file, pan_file, sub_department_id).';

    ensureMasterTables($conn);
    $log[] = 'Masters tables ready. Sub Department seeded from Department names where missing.';

    ensureContractorTables($conn);
    $log[] = 'Contractor tables ready (grades, products, employment, operations rate list).';

    ensurePayrollTables($conn);
    $log[] = 'Payroll tables ready (diary, jobwork, payslips).';

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
        'contractor_employment.sub_department_id' => dbSyncHasColumn($conn, 'contractor_employment', 'sub_department_id'),
        'contractor_operation_items.grade_id' => dbSyncHasColumn($conn, 'contractor_operation_items', 'grade_id'),
        'contractor_products table' => dbSyncHasTable($conn, 'contractor_products'),
        'contractor_grades table' => dbSyncHasTable($conn, 'contractor_grades'),
        'contractor_operation_sheets table' => dbSyncHasTable($conn, 'contractor_operation_sheets'),
    ];

    $counts = [
        'Departments' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM departments WHERE status = 1'),
        'Sub Departments' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM sub_departments WHERE status = 1'),
        'Contractor Products' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_products WHERE status = 1'),
        'Contractor Grades' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_grades WHERE status = 1'),
        'Jobwork Employees' => dbSyncCount($conn, "SELECT COUNT(*) AS c FROM employees WHERE status = 1 AND pay_type = 'Jobwork'"),
        'Operations Rate Lists' => dbSyncCount($conn, 'SELECT COUNT(*) AS c FROM contractor_operation_sheets WHERE status = 1'),
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
            <p>Safe re-run. Tables / columns are created if missing. Product &amp; grade seed updates existing rows.</p>
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
            <a href="<?php echo app_url('dashboard.php'); ?>" class="btn-secondary">Dashboard</a>
        </div>
    </div>
</main>
<style>
    .db-sync-log { margin: 0; padding-left: 18px; line-height: 1.7; }
    .db-sync-log li { margin: 4px 0; }
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
