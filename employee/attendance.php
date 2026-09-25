<?php
/**
 * Employee portal — My Attendance (self only, month-wise)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !isEmployee()) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

// Office Staff / employee self-view — no need for module attendance matrix
$emp = getEmployeeById($empId);
if (!$emp) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$isCurrentMonth = ($month === (int) date('n') && $year === (int) date('Y'));

$conn = getDBConnection();
ensureAttendanceTables($conn);
$grid = getAttendanceExcelMonthGrid($month, $year, 0, $empId, $conn);
$conn->close();

$pageTitle = 'My Attendance';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'my_attendance';

require_once __DIR__ . '/../includes/header.php';

$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$totals = $grid['leave_totals'][$empId] ?? [];
$presentShow = $totals['present_days'] ?? $totals['present'] ?? null;
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn-secondary"
               href="<?php echo app_url('employee/attendance.php?month=' . (int) date('n') . '&year=' . (int) date('Y')); ?>">
                <i class="fa-solid fa-calendar-day"></i> Current Month
            </a>
            <?php
            $prevM = $month - 1;
            $prevY = $year;
            if ($prevM < 1) {
                $prevM = 12;
                $prevY--;
            }
            ?>
            <a class="btn-secondary"
               href="<?php echo app_url('employee/attendance.php?month=' . $prevM . '&year=' . $prevY); ?>">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php
            $nextM = $month + 1;
            $nextY = $year;
            if ($nextM > 12) {
                $nextM = 1;
                $nextY++;
            }
            $canNext = ($nextY < (int) date('Y')) || ($nextY === (int) date('Y') && $nextM <= (int) date('n'));
            if ($canNext):
            ?>
            <a class="btn-secondary"
               href="<?php echo app_url('employee/attendance.php?month=' . $nextM . '&year=' . $nextY); ?>">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0F766E;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <h1>My Attendance</h1>
                    <p>
                        <?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? '')); ?>
                        · Month-wise report (your record only)
                    </p>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters" style="margin-bottom:16px;">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <?php for ($y = (int) date('Y') - 3; $y <= (int) date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show
                    </button>
                </div>
            </div>
        </form>

        <div class="ops-live-summary" style="margin-bottom:12px;">
            <span class="ops-chip"><?php echo htmlspecialchars($monthLabel); ?></span>
            <?php if ($isCurrentMonth): ?>
                <span class="ops-chip" style="background:#ECFDF5;color:#047857;">Current month</span>
            <?php endif; ?>
            <?php if ($presentShow !== null && $presentShow !== ''): ?>
                <span class="ops-chip">Present days: <?php echo htmlspecialchars((string) $presentShow); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($grid && !empty($grid['employees'])): ?>
            <div class="table-wrap excel-att-wrap">
                <?php echo attendanceRenderExcelMonthTableHtml($grid, ['tableClass' => 'data-table excel-att-table']); ?>
            </div>
            <div class="form-hint" style="margin-top:10px;">
                Format:
                <code>9:00 AM | 06:00 PM</code> ·
                <code>FHL</code> / <code>SHL</code> ·
                <code>PL</code>/<code>SL</code> ·
                <code>Absent</code> ·
                <code>Week Off</code>
            </div>
        <?php else: ?>
            <p class="text-muted" style="text-align:center;padding:28px;">
                No attendance data found for <?php echo htmlspecialchars($monthLabel); ?>.
            </p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
