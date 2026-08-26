<?php
/**
 * Apply Leave
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();
ensureLeaveTables();

$deptId = (int) ($_GET['department_id'] ?? $_POST['department_id'] ?? 0);
$preEmp = (int) ($_GET['employee_id'] ?? 0);
$year = (int) date('Y');
$employees = leaveEmployeesForSelect($deptId);
$leaveTypes = getActiveLeaveTypes();
$department = $deptId > 0 ? getDepartmentById($deptId) : null;

$pageTitle = 'Apply Leave';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'leave_request';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('leave/index.php?department_id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Leave Requests
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Apply Leave</h1>
                <p>
                    <?php echo $department ? htmlspecialchars($department['department_name']) : 'Select employee'; ?>
                    · Working days only (week-off &amp; holiday excluded) · Checks employee balance
                </p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('leave/save.php'); ?>" class="employee-form">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-grid form-grid-3">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Employee <span class="req">*</span></label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">Select employee</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>" <?php echo $preEmp === (int) $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Leave Type <span class="req">*</span></label>
                    <select name="leave_type_id" class="form-control" required>
                        <option value="">Select type</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?php echo (int) $lt['id']; ?>">
                                <?php
                                echo htmlspecialchars(
                                    ($lt['code'] ? $lt['code'] . ' · ' : '')
                                    . $lt['leave_type']
                                    . ' (' . (int) $lt['days_allowed'] . ' /yr, '
                                    . (($lt['is_paid'] ?? 'Yes') === 'Yes' ? 'Paid' : 'Unpaid') . ')'
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date <span class="req">*</span></label>
                    <input type="date" name="from_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>To Date <span class="req">*</span></label>
                    <input type="date" name="to_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group" style="grid-column: span 3;">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Optional reason"></textarea>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
                <a href="<?php echo app_url('leave/index.php?department_id=' . $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
