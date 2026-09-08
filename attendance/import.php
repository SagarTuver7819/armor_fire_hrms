<?php
/**
 * Attendance Import
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
ensureAttendanceTables();

$pageTitle = 'Attendance Import';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_import';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['import_file']['tmp_name'])) {
        $error = 'Please choose an Excel / CSV file.';
    } else {
        $name = (string) ($_FILES['import_file']['name'] ?? 'upload.csv');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
            $error = 'Only .xlsx, .xls or .csv files are allowed.';
        } else {
            try {
                $conn = getDBConnection();
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $result = attendanceImportFile($conn, $_FILES['import_file']['tmp_name'], $name, $userId);
                $conn->close();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Attendance
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/history.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-clock-rotate-left"></i> Import History
            </a>
            <a href="<?php echo app_url('attendance/sample.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-download"></i> Sample Excel Template
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Import Attendance — Bulk Upload</h1>
            <p>Upload punch Excel/CSV · Auto-match employees · Sync Salary Diary for salary calculation</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert alert-success" style="margin-bottom:14px;">
                Import complete. Success: <strong><?php echo (int) $result['success']; ?></strong>,
                Skipped: <strong><?php echo (int) $result['skipped']; ?></strong>,
                Errors: <strong><?php echo (int) $result['errors']; ?></strong>,
                Employees synced to diary: <strong><?php echo (int) $result['employees_synced']; ?></strong>
                · Batch #<?php echo (int) $result['batch_id']; ?>
            </div>
            <?php if (!empty($result['error_log'])): ?>
                <div class="form-section">
                    <h3>Errors (first rows)</h3>
                    <ul>
                        <?php foreach (array_slice($result['error_log'], 0, 30) as $line): ?>
                            <li><?php echo htmlspecialchars($line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="form-section">
            <h3><i class="fa-solid fa-circle-info"></i> How to Import</h3>
            <ol style="line-height:1.7;margin:0;padding-left:18px;">
                <li>Download the sample Excel template</li>
                <li>Fill minimum columns: employee identifier, attendance_date, punch_in_time</li>
                <li>Match using <code>biometric_user_id</code>, <code>employee_code</code>, or <code>employee_name</code></li>
                <li>Upload .xlsx / .xls / .csv and submit</li>
                <li>System rebuilds day status and updates Salary Diary (Present / Week Off) for salary</li>
            </ol>
        </div>

        <form method="POST" enctype="multipart/form-data" class="employee-form">
            <div class="form-section">
                <h3><i class="fa-solid fa-file-arrow-up"></i> Upload File</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Import File <span class="req">*</span></label>
                        <input type="file" name="import_file" class="form-control" accept=".xlsx,.xls,.csv,text/csv" required>
                        <small>Supported: Excel (.xls, .xlsx) and CSV</small>
                    </div>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-section">
                    <h3>Required Columns</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li><code>employee_code</code> OR <code>biometric_user_id</code> OR <code>employee_name</code></li>
                        <li><code>attendance_date</code> — DD-MM-YYYY or YYYY-MM-DD</li>
                        <li><code>punch_in_time</code> — HH:MM or HH:MM:SS</li>
                        <li><code>attendance_type</code> — in / out (optional)</li>
                        <li><code>punch_out_time</code> — optional same-row out punch</li>
                    </ul>
                </div>
                <div class="form-section">
                    <h3>Common Errors</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li>Employee not found (check code / biometric id)</li>
                        <li>Invalid date/time formats</li>
                        <li>Duplicate punches (auto-skipped)</li>
                        <li>Missing required fields</li>
                    </ul>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Submit Import</button>
                <a href="<?php echo app_url('attendance/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
