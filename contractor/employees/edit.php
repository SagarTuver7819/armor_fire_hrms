<?php
/**
 * Add Jobwork employee: pick department, then Join Employee form (Pay Type locked to Jobwork).
 * Edit: redirected to the employee form.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/master_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    header('Location: ' . app_url('employees/edit.php?id=' . $id . '&from=contractor'));
    exit;
}

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
if ($deptId > 0) {
    header('Location: ' . app_url('employees/edit.php?department_id=' . $deptId . '&pay_type=Jobwork&from=contractor'));
    exit;
}

$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$pageTitle = 'Add Jobwork Employee';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_employees';
require_once __DIR__ . '/../../includes/header.php';
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/employees/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Employee</a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Add Jobwork Employee</h1>
            <p>This employee is created in Join Employee with Pay Type = Jobwork, and then shows in Contractor.</p>
        </div>
        <form method="GET" action="<?php echo app_url('contractor/employees/edit.php'); ?>" class="employee-form">
            <div class="form-section">
                <h3><i class="fa-solid fa-building"></i> Department</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group span-2">
                        <label>Select Department <span class="req">*</span></label>
                        <select name="department_id" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int) $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-arrow-right"></i> Continue</button>
                <a href="<?php echo app_url('contractor/employees/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
