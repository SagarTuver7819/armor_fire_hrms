<?php
/**
 * Attendance list
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
ensureAttendanceTables();

$pageTitle = 'Attendance';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_list';
$extraCss = [
    'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/import.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-file-import"></i> Import Attendance
            </a>
            <a href="<?php echo app_url('attendance/report.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-chart-simple"></i> Attendance Report
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Attendance</h1>
            <p>Punch list from import · Department-wise salary uses monthly totals</p>
        </div>

        <div class="table-wrap">
            <table class="data-table display" id="attendanceTable" style="width:100%;">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Punch In</th>
                        <th>Punch Out</th>
                        <th>Status</th>
                        <th>Hours</th>
                        <th>Source</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</main>

<script>
window.ATT_AJAX_URL = <?php echo json_encode(app_url('attendance/ajax_list.php')); ?>;
</script>
<?php
$extraJs = [
    'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
    'assets/js/attendance_list.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
