<?php
/**
 * Attendance Report — Excel monthly format (same as Attendance Report.xlsx)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$show = isset($_GET['show']) || isset($_GET['department_id']) || isset($_GET['month']);

$conn = getDBConnection();
ensureAttendanceTables($conn);
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$grid = $show ? getAttendanceExcelMonthGrid($month, $year, $deptId, $employeeId, $conn) : null;
$conn->close();

$pageTitle = 'Attendance Report';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_report';
$sidebarDeptId = $deptId;

$qs = [
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
    'employee_id' => $employeeId,
];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Attendance
        </a>
        <div class="toolbar-actions">
            <?php if ($show): ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(app_url('attendance/muster.php?' . http_build_query($qs))); ?>">
                    <i class="fa-solid fa-table-cells"></i> Muster Report
                </a>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(app_url('attendance/report_excel.php?' . http_build_query($qs))); ?>">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
                <a class="btn-primary" href="<?php echo htmlspecialchars(app_url('attendance/manual.php?' . http_build_query([
                    'department_id' => $deptId,
                    'month' => $month,
                    'year' => $year,
                    'show' => 1,
                ]))); ?>">
                    <i class="fa-solid fa-pen-to-square"></i> Manual Entry (All)
                </a>
            <?php endif; ?>
            <a href="<?php echo app_url('attendance/import.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-import"></i> Import
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Attendance Report</h1>
            <p>Same Excel format · Employee rows · Days 1–31 with In/Out · PL / SL / C-Off / DL / LWP · Export</p>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:16px;">
            <input type="hidden" name="show" value="1">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0">All Departments / All Employees</option>
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
                <span class="ops-chip"><?php echo $deptId > 0 ? 'Department filter' : 'All Employees'; ?></span>
                <span class="ops-chip"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></span>
                <span class="ops-chip"><?php echo count($grid['employees']); ?> employees</span>
            </div>
            <div class="table-wrap excel-att-wrap">
                <?php echo attendanceRenderExcelMonthTableHtml($grid, ['tableClass' => 'data-table excel-att-table']); ?>
            </div>
            <div class="form-hint" style="margin-top:10px;">
                Format same as Excel:
                <code>9:00 AM | 06:00 PM</code> ·
                <code>PL SHF</code> ·
                <code>PL FHF</code> ·
                <code>PL</code>/<code>SL</code> ·
                <code>week off</code>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
