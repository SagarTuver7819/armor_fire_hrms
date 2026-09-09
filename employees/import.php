<?php
/**
 * Import Employees from Excel / CSV
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : (int) ($_POST['department_id'] ?? 0);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if ($deptId > 0 && !$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$pageTitle = 'Import Employees';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'employees';
$sidebarDeptId = $deptId;
$sidebarActive = $deptId > 0 ? 'join_employee' : 'all_employees';

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
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
                $result = employeeImportFile(
                    $conn,
                    $_FILES['import_file']['tmp_name'],
                    $name,
                    $deptId,
                    $userId
                );
                $conn->close();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$backUrl = $deptId > 0
    ? app_url('employees/index.php?department_id=' . $deptId)
    : app_url('employees/index.php');
$sampleUrl = app_url('employees/import_sample.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''));

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employee List
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo htmlspecialchars($sampleUrl); ?>" class="btn-secondary">
                <i class="fa-solid fa-download"></i> Example Excel Template
            </a>
            <?php if ($deptId > 0): ?>
                <a href="<?php echo app_url('employees/edit.php?department_id=' . $deptId); ?>" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Employee
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Import Employees — Excel Upload</h1>
            <p>
                <?php if ($department): ?>
                    Department: <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>
                    · Rows without department use this department
                <?php else: ?>
                    All departments · Fill <code>department</code> column (exact master name)
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert alert-success" style="margin-bottom:14px;">
                Import complete.
                Added: <strong><?php echo (int) $result['success']; ?></strong>,
                Skipped: <strong><?php echo (int) $result['skipped']; ?></strong>,
                Errors: <strong><?php echo (int) $result['errors']; ?></strong>
            </div>
            <?php if (!empty($result['error_log'])): ?>
                <div class="form-section">
                    <h3>Skipped / Errors</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <?php foreach (array_slice($result['error_log'], 0, 40) as $line): ?>
                            <li><?php echo htmlspecialchars($line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="toolbar-actions" style="margin-bottom:16px;">
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-primary">
                    <i class="fa-solid fa-list"></i> View Employee List
                </a>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h3><i class="fa-solid fa-circle-info"></i> How to Import</h3>
            <ol style="line-height:1.7;margin:0;padding-left:18px;">
                <li>Download the <strong>Example Excel Template</strong></li>
                <li>Keep the header row as-is (do not rename columns)</li>
                <li>Fill employee rows — <code>employee_name</code> is required</li>
                <li>Dates must be <strong>DD-MM-YYYY</strong> (example: 08-09-2026)</li>
                <li>Leave <code>employee_code</code> blank to auto-generate (AS… / JW…)</li>
                <li>Upload .xlsx / .xls / .csv and submit</li>
            </ol>
        </div>

        <form method="POST" enctype="multipart/form-data" class="employee-form">
            <input type="hidden" name="department_id" value="<?php echo (int) $deptId; ?>">
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
                    <h3>Important Columns</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li><code>employee_name</code> — required</li>
                        <li><code>employee_code</code> — optional (auto if blank)</li>
                        <li><code>pay_type</code> — Salary / Jobwork / ContractorMain</li>
                        <li><code>department</code> — required if not importing inside a department</li>
                        <li><code>date_of_joining</code> / <code>date_of_birth</code> — DD-MM-YYYY</li>
                        <li><code>shift_type</code> — Day / Night</li>
                    </ul>
                </div>
                <div class="form-section">
                    <h3>Rules</h3>
                    <ul style="margin:0;padding-left:18px;line-height:1.7;">
                        <li>Duplicate employee code → skipped</li>
                        <li>Blank code → next AS / JW code generated</li>
                        <li>Invalid department name → error for that row</li>
                        <li>Other filled form fields (bank, PF, address…) are optional</li>
                    </ul>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-file-import"></i> Submit Import
                </button>
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
