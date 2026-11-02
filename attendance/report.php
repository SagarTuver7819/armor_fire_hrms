<?php
/**
 * Attendance Report — Excel monthly format (same as Attendance Report.xlsx)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/build_meta.php';

requireLogin();
require_once __DIR__ . '/../includes/permission_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('attendance', 'view', $deptId);
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
$empOptions = [];
$empSql = "SELECT id, employee_code, employee_name
           FROM employees
           WHERE status = 1
             AND (date_of_exit IS NULL OR date_of_exit = '' OR date_of_exit = '0000-00-00'
                  OR date_of_exit >= '" . $conn->real_escape_string(sprintf('%04d-%02d-01', $year, $month)) . "')";
if ($deptId > 0) {
    $empSql .= ' AND department_id = ' . (int) $deptId;
}
$empSql .= ' ORDER BY employee_code ASC, employee_name ASC LIMIT 2000';
$eres = $conn->query($empSql);
if ($eres) {
    while ($r = $eres->fetch_assoc()) {
        $empOptions[] = $r;
    }
}
$grid = $show ? getAttendanceExcelMonthGrid($month, $year, $deptId, $employeeId, $conn) : null;
$conn->close();

$pageTitle = 'Attendance Report';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_report';
$sidebarDeptId = $deptId;
$extraJs = ['assets/js/report_emp_search.js'];

$qs = [
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
    'employee_id' => $employeeId,
];
$navBack = navResolveBack([
    'department_id' => $deptId,
    'fallback' => 'attendance',
    'prefer_dept' => false,
]);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($navBack['url']); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> <?php echo htmlspecialchars($navBack['label']); ?>
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
            <p>Total Days = Total Pay Days (= Salary Register) · Present / Week Off / PL / SL / DL / C-Off / Holiday</p>
        </div>

        <form method="GET" class="employee-form">
            <input type="hidden" name="show" value="1">
            <div class="report-filter-bar">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.employee_id.value='0'; this.form.submit();">
                        <option value="0">All Departments / All Employees</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control">
                        <option value="0">All employees</option>
                        <?php foreach ($empOptions as $eo): ?>
                            <option value="<?php echo (int) $eo['id']; ?>" <?php echo $employeeId === (int) $eo['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($eo['employee_code'] ?? '') . ' — ' . ($eo['employee_name'] ?? '')); ?>
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
                <div class="report-filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <?php if ($show && $grid): ?>
            <div class="report-toolbar-row">
                <div class="ops-live-summary">
                    <span class="ops-chip"><?php echo $deptId > 0 ? 'Department filter' : 'All Employees'; ?></span>
                    <span class="ops-chip"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></span>
                    <span class="ops-chip"><?php echo count($grid['employees']); ?> employees</span>
                    <?php if ($employeeId > 0): ?>
                        <span class="ops-chip">1 employee selected</span>
                    <?php endif; ?>
                </div>
                <div class="report-emp-search">
                    <label class="report-emp-search-box" for="attReportEmpSearch">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="attReportEmpSearch" placeholder="Search employee code or name…"
                               data-report-emp-search=".excel-att-table tbody tr"
                               data-report-emp-count="#attReportEmpSearchCount"
                               autocomplete="off">
                        <button type="button" class="report-emp-search-clear" data-report-emp-clear title="Clear">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </label>
                    <span class="report-emp-search-count" id="attReportEmpSearchCount"></span>
                </div>
            </div>
            <div class="table-wrap excel-att-wrap">
                <?php echo attendanceRenderExcelMonthTableHtml($grid, ['tableClass' => 'data-table excel-att-table']); ?>
            </div>
            <div class="form-hint" style="margin-top:10px;">
                Format:
                <code>9:00 AM | 06:00 PM</code> ·
                <code>FHL</code> / <code>SHL</code> + punch ·
                <code>PL</code>/<code>SL</code> ·
                <code>Absent</code> ·
                <code>Week Off</code>
                · Status labels are bold
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
