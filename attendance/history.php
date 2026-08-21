<?php
/**
 * Import history
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
$conn = getDBConnection();
ensureAttendanceTables($conn);
$batches = [];
$res = $conn->query('SELECT * FROM attendance_import_batches ORDER BY id DESC LIMIT 100');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $batches[] = $r;
    }
}
$conn->close();

$pageTitle = 'Import History';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_import';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/import.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Import
        </a>
        <a href="<?php echo app_url('attendance/import.php'); ?>" class="btn-primary">
            <i class="fa-solid fa-file-import"></i> New Import
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Attendance Import History</h1>
            <p>Recent bulk upload batches</p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File</th>
                        <th>Total</th>
                        <th>Success</th>
                        <th>Skipped</th>
                        <th>Errors</th>
                        <th>Date</th>
                        <th>Error Log</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$batches): ?>
                        <tr><td colspan="8">No imports yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><?php echo (int) $b['id']; ?></td>
                                <td><?php echo htmlspecialchars($b['file_name']); ?></td>
                                <td><?php echo (int) $b['total_rows']; ?></td>
                                <td><?php echo (int) $b['success_rows']; ?></td>
                                <td><?php echo (int) $b['skipped_rows']; ?></td>
                                <td><?php echo (int) $b['error_rows']; ?></td>
                                <td><?php echo htmlspecialchars($b['created_at']); ?></td>
                                <td style="max-width:280px;white-space:pre-wrap;font-size:12px;">
                                    <?php echo htmlspecialchars(mb_substr((string) ($b['error_log'] ?? ''), 0, 300)); ?>
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
