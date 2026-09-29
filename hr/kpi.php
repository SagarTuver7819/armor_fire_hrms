<?php
/**
 * HR / Admin — KPI submissions list + today count
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

requireStaff();
if (function_exists('isOfficeStaffRole')) {
    require_once __DIR__ . '/../includes/department_head_helper.php';
    if (isOfficeStaffRole()) {
        header('Location: ' . app_url('employee/kpi.php'));
        exit;
    }
}

$today = date('Y-m-d');
$date = isset($_GET['date']) ? substr((string) $_GET['date'], 0, 10) : $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
$deptId = (int) ($_GET['department_id'] ?? 0);

$conn = getDBConnection();
ensureKpiTables($conn);
ensureMasterTables($conn);
ensureEmployeesTable($conn);

$countToday = kpiCountSubmittedOnDate($today, $conn);
$countDate = kpiCountSubmittedOnDate($date, $conn);
$rows = kpiListSubmitted($date, $deptId, 500, $conn);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');

$activeEmp = 0;
if ($deptId > 0) {
    $st = $conn->prepare('SELECT COUNT(*) AS c FROM employees WHERE status = 1 AND department_id = ?');
    $st->bind_param('i', $deptId);
    $st->execute();
    $activeEmp = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
} else {
    $r = $conn->query('SELECT COUNT(*) AS c FROM employees WHERE status = 1');
    $activeEmp = $r ? (int) ($r->fetch_assoc()['c'] ?? 0) : 0;
}
$pendingDate = max(0, $activeEmp - $countDate);
$conn->close();

$dateDisp = function_exists('formatDateDisplay') ? formatDateDisplay($date) : formatDateDisplay($date);
$isToday = ($date === $today);

$pageTitle = 'KPI Reports';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'hr_kpi';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('hr/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to HR Dashboard
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Employee KPI Submissions</h1>
            <p>Shift-wise hourly KPI · Filter by date &amp; department · Open report in paper format</p>
        </div>

        <div class="hr-kpi-rpt-stats">
            <div class="hr-kpi-rpt-stat is-red">
                <span>Today Submitted</span>
                <strong><?php echo (int) $countToday; ?></strong>
            </div>
            <div class="hr-kpi-rpt-stat is-blue">
                <span><?php echo $isToday ? 'Selected (Today)' : 'Selected Date'; ?></span>
                <strong><?php echo (int) $countDate; ?></strong>
            </div>
            <div class="hr-kpi-rpt-stat is-slate">
                <span>Active Employees<?php echo $deptId > 0 ? ' (Dept)' : ''; ?></span>
                <strong><?php echo (int) $activeEmp; ?></strong>
            </div>
            <div class="hr-kpi-rpt-stat is-amber">
                <span>Not Submitted</span>
                <strong><?php echo (int) $pendingDate; ?></strong>
            </div>
        </div>

        <form method="GET" class="hr-kpi-rpt-filters" action="<?php echo htmlspecialchars(app_url('hr/kpi.php')); ?>">
            <div class="hr-kpi-rpt-field">
                <label for="kpiRptDate">Date</label>
                <input type="date" id="kpiRptDate" name="date" class="form-control no-select2"
                       value="<?php echo htmlspecialchars($date); ?>">
            </div>
            <div class="hr-kpi-rpt-field is-dept">
                <label for="kpiRptDept">Department</label>
                <select id="kpiRptDept" name="department_id" class="form-control no-select2">
                    <option value="0">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hr-kpi-rpt-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <a href="<?php echo app_url('hr/kpi.php'); ?>" class="btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Today
                </a>
            </div>
            <div class="hr-kpi-rpt-date-label">
                Showing: <strong><?php echo htmlspecialchars($dateDisp); ?></strong>
                · <?php echo (int) count($rows); ?> record(s)
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table hr-kpi-rpt-table">
                <thead>
                    <tr>
                        <th style="width:56px;">Sr</th>
                        <th>Emp Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Shift</th>
                        <th>Submitted At</th>
                        <th style="width:110px;">Report</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="empty-cell">
                            No KPI submitted for <?php echo htmlspecialchars($dateDisp); ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $sr = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td><strong><?php echo htmlspecialchars((string) ($r['employee_code'] ?? '')); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['department_name'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['designation'] ?? '—')); ?></td>
                            <td><?php echo htmlspecialchars(trim(($r['shift_in'] ?? '') . ' – ' . ($r['shift_out'] ?? ''))); ?></td>
                            <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(formatDateTimeDisplay($r['submitted_at'])) : '—'; ?></td>
                            <td>
                                <a class="btn-secondary btn-sm" href="<?php echo app_url('employee/kpi_report.php?id=' . (int) $r['id']); ?>">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
