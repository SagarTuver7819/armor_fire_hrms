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

$date = isset($_GET['date']) ? substr((string) $_GET['date'], 0, 10) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$deptId = (int) ($_GET['department_id'] ?? 0);

$conn = getDBConnection();
ensureKpiTables($conn);
ensureMasterTables($conn);
$countToday = kpiCountSubmittedOnDate(date('Y-m-d'), $conn);
$countDate = kpiCountSubmittedOnDate($date, $conn);
$rows = kpiListSubmitted($date, $deptId, 300, $conn);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$conn->close();

$pageTitle = 'KPI Submissions';
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
        <div class="form-page-header flex-between" style="align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
                <h1>Employee KPI Submissions</h1>
                <p>Today submitted: <strong><?php echo (int) $countToday; ?></strong> · Selected date: <strong><?php echo (int) $countDate; ?></strong></p>
            </div>
            <form method="GET" class="employee-form" style="display:flex;gap:8px;flex-wrap:wrap;margin:0;align-items:flex-end;">
                <div class="form-group" style="margin:0;">
                    <label>Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
                </div>
                <div class="form-group" style="margin:0;min-width:180px;">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0">All</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Emp Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Shift</th>
                        <th>Submitted At</th>
                        <th>Report</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty-cell">No KPI submitted for this date.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars((string) ($r['employee_code'] ?? '')); ?></strong></td>
                        <td><?php echo htmlspecialchars((string) ($r['employee_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['department_name'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['designation'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars(trim(($r['shift_in'] ?? '') . ' – ' . ($r['shift_out'] ?? ''))); ?></td>
                        <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(date('d-m-Y H:i', strtotime($r['submitted_at']))) : '—'; ?></td>
                        <td>
                            <a class="btn-secondary" href="<?php echo app_url('employee/kpi_report.php?id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
