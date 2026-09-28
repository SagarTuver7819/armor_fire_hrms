<?php
/**
 * Employee — past submitted KPI reports (visible from next day)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

requireLogin();

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !isEmployee()) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$conn = getDBConnection();
ensureKpiTables($conn);
$rows = kpiListForEmployee($empId, true, 90, $conn);
$conn->close();

$pageTitle = 'My KPI Reports';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'my_kpi';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/kpi.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Today KPI
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Past KPI Reports</h1>
            <p>Submit thayelu KPI next day thi ahiya report format ma jovashe.</p>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="4" class="empty-cell">No past KPI reports yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(formatDateDisplay($r['kpi_date'])); ?></strong></td>
                        <td><?php echo htmlspecialchars(trim(($r['shift_in'] ?? '') . ' TO ' . ($r['shift_out'] ?? ''))); ?></td>
                        <td><?php echo !empty($r['submitted_at']) ? htmlspecialchars(date('d-m-Y H:i', strtotime($r['submitted_at']))) : '—'; ?></td>
                        <td>
                            <a class="btn-secondary" href="<?php echo app_url('employee/kpi_report.php?id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-file-lines"></i> View Report
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
