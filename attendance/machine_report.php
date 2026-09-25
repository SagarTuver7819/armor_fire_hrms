<?php
/**
 * Machine Wise Attendance Report
 * Format like Attendance Report · data ONLY from machine_attendance_logs
 * Does NOT affect Attendance Report / Manual / Import
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('attendance', 'view', $deptId);

$machineId = (int) ($_GET['machine_id'] ?? 0);
$employeeId = (int) ($_GET['employee_id'] ?? 0);
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$show = isset($_GET['show']) || isset($_GET['month']) || isset($_GET['machine_id']);

ensureBiometricTables();
$machines = fetchBiometricMachines(false);
$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$grid = $show ? getMachineWiseMonthGrid($month, $year, $machineId, $deptId, $employeeId, $conn) : null;
$conn->close();

$stats = biometricDashboardStats();

$pageTitle = 'Machine Wise Report';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_machine_report';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/machines.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Machines
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/machine_logs.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-link"></i> Link Employees / Logs
            </a>
            <a href="<?php echo app_url('attendance/machine_sync.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-rotate"></i> Sync
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Machine Wise Attendance Report</h1>
            <p>
                Same layout as Attendance Report · Multiple Punch IN / OUT from machines only ·
                <strong>Separate data</strong> — does not change Attendance Report, Manual Entry, or Import
            </p>
        </div>

        <div class="bio-stat-grid bio-stat-grid-3" style="margin-bottom:14px;">
            <div class="bio-stat">
                <span class="bio-stat-label">Machine punches</span>
                <span class="bio-stat-value"><?php echo number_format((int) $stats['logs_total']); ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Linked to employees</span>
                <span class="bio-stat-value"><?php echo number_format((int) $stats['logs_matched']); ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Unlinked</span>
                <span class="bio-stat-value"><?php echo number_format(max(0, (int) $stats['logs_total'] - (int) $stats['logs_matched'])); ?></span>
                <span class="bio-stat-sub">link optional — report shows all codes</span>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:16px;">
            <input type="hidden" name="show" value="1">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Machine</label>
                    <select name="machine_id" class="form-control">
                        <option value="0">All machines</option>
                        <?php foreach ($machines as $m): ?>
                            <option value="<?php echo (int) $m['id']; ?>" <?php echo $machineId === (int) $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['machine_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <?php for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <?php if ($show && $grid): ?>
            <div class="ops-live-summary" style="margin-bottom:12px;">
                <span class="ops-chip">Machine Wise (separate)</span>
                <span class="ops-chip"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></span>
                <span class="ops-chip"><?php echo count($grid['employees']); ?> codes with punches</span>
                <span class="ops-chip">Click employee code → day sheet (O/X + multi punch)</span>
            </div>
            <div class="table-wrap excel-att-wrap">
                <?php echo machineWiseRenderMonthTableHtml($grid); ?>
            </div>
            <div class="form-hint" style="margin-top:10px;">
                Cell format (multiple punches):
                <code>Present</code> then
                <code>9:12 AM | 1:05 PM</code>
                · next pair on next line ·
                only machine sync data
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
