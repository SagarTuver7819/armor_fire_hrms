<?php
/**
 * Add / Edit Employee Form
 * Fields match English Employee Information Form document.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$empId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$employee = null;
if ($empId > 0) {
    $employee = getEmployeeById($empId);
    if ($employee) {
        $deptId = (int) $employee['department_id'];
    }
}

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$pageTitle = $employee ? 'Edit Employee' : 'Add Employee';

$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'join_employee';

require_once __DIR__ . '/../includes/header.php';

// Helper to get old value from employee row
function empField($employee, $key, $default = '')
{
    if (!$employee || !isset($employee[$key]) || $employee[$key] === null) {
        return $default;
    }
    return $employee[$key];
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employee List
        </a>
        <a href="<?php echo app_url('department.php?id=' . (int) $deptId); ?>" class="btn-secondary">
            <i class="fa-solid fa-puzzle-piece"></i> Module Boxes
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo $employee ? 'Edit Employee' : 'Add Employee'; ?></h1>
                <p>
                    Department: <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>
                    <?php if ($employee): ?>
                        · Code: <strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('employees/save.php'); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $empId; ?>">
            <input type="hidden" name="department_id" value="<?php echo (int) $deptId; ?>">

            <!-- Section 1 -->
            <div class="form-section">
                <h3><i class="fa-solid fa-user"></i> Personal Information</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group span-2">
                        <label>1. Employee Name <small>(As per Aadhar)</small></label>
                        <input type="text" name="employee_name" class="form-control" required
                               value="<?php echo htmlspecialchars(empField($employee, 'employee_name')); ?>">
                    </div>
                    <div class="form-group">
                        <label>2. Father / Husband Name</label>
                        <input type="text" name="father_husband_name" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'father_husband_name')); ?>">
                    </div>
                    <div class="form-group span-2">
                        <label>3. Permanent Address <small>(As per Aadhar)</small></label>
                        <textarea name="permanent_address" class="form-control" rows="1"><?php echo htmlspecialchars(empField($employee, 'permanent_address')); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>9. Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'date_of_birth')); ?>">
                    </div>
                    <div class="form-group span-2">
                        <label>4. Present Address <small>(Current)</small></label>
                        <textarea name="present_address" class="form-control" rows="1"><?php echo htmlspecialchars(empField($employee, 'present_address')); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>5. Mobile Number</label>
                        <input type="text" name="mobile_number" class="form-control" maxlength="15"
                               value="<?php echo htmlspecialchars(empField($employee, 'mobile_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>6. Emergency Mobile</label>
                        <input type="text" name="emergency_mobile" class="form-control" maxlength="15"
                               value="<?php echo htmlspecialchars(empField($employee, 'emergency_mobile')); ?>">
                    </div>
                    <div class="form-group">
                        <label>7. Aadhar Card Number</label>
                        <input type="text" name="aadhar_number" class="form-control" maxlength="20"
                               value="<?php echo htmlspecialchars(empField($employee, 'aadhar_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>8. PAN Card Number</label>
                        <input type="text" name="pan_number" class="form-control" maxlength="20"
                               value="<?php echo htmlspecialchars(empField($employee, 'pan_number')); ?>">
                    </div>
                </div>
            </div>

            <!-- Section 2 -->
            <div class="form-section">
                <h3><i class="fa-solid fa-briefcase"></i> Job Information</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>10. Department</label>
                        <input type="text" class="form-control" readonly
                               value="<?php echo htmlspecialchars($department['department_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>11. Designation</label>
                        <input type="text" name="designation" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'designation')); ?>">
                    </div>
                    <div class="form-group">
                        <label>12. Date of Joining</label>
                        <input type="date" name="date_of_joining" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'date_of_joining')); ?>">
                    </div>
                    <div class="form-group">
                        <label>13. Shift Type</label>
                        <div class="radio-row">
                            <?php $shift = empField($employee, 'shift_type', 'Day'); ?>
                            <label><input type="radio" name="shift_type" value="Day" <?php echo $shift === 'Day' ? 'checked' : ''; ?>> Day</label>
                            <label><input type="radio" name="shift_type" value="Night" <?php echo $shift === 'Night' ? 'checked' : ''; ?>> Night</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>13. Shift Time</label>
                        <input type="text" name="shift_time" class="form-control" placeholder="e.g. 09:00 AM - 06:00 PM"
                               value="<?php echo htmlspecialchars(empField($employee, 'shift_time')); ?>">
                    </div>
                    <div class="form-group">
                        <label>14. PF Deduction</label>
                        <div class="radio-row">
                            <?php $pf = empField($employee, 'pf_deduction', 'No'); ?>
                            <label><input type="radio" name="pf_deduction" value="Yes" <?php echo $pf === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="pf_deduction" value="No" <?php echo $pf === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>15. UAN Number</label>
                        <input type="text" name="uan_number" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'uan_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>20. Decided Salary</label>
                        <input type="number" step="0.01" name="decided_salary" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'decided_salary')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Reporting Head</label>
                        <input type="text" name="reporting_head" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'reporting_head')); ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3 -->
            <div class="form-section">
                <h3><i class="fa-solid fa-building-columns"></i> Bank Information</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>16. Bank Name</label>
                        <input type="text" name="bank_name" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'bank_name')); ?>">
                    </div>
                    <div class="form-group">
                        <label>17. Bank Account Number</label>
                        <input type="text" name="bank_account_number" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'bank_account_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>18. IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'ifsc_code')); ?>">
                    </div>
                    <div class="form-group full">
                        <label>19. Bank Branch Address</label>
                        <textarea name="bank_branch_address" class="form-control" rows="1"><?php echo htmlspecialchars(empField($employee, 'bank_branch_address')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 4 -->
            <div class="form-section">
                <h3><i class="fa-solid fa-clipboard-list"></i> Other Details</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>22. Week-off Day</label>
                        <select name="week_off_day" class="form-control">
                            <?php
                            $days = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $wod = empField($employee, 'week_off_day');
                            foreach ($days as $day) {
                                $sel = ($wod === $day) ? 'selected' : '';
                                $label = $day === '' ? '-- Select --' : $day;
                                echo '<option value="' . htmlspecialchars($day) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>23. Week-off Benefits</label>
                        <div class="radio-row">
                            <?php $wob = empField($employee, 'week_off_benefits', 'No'); ?>
                            <label><input type="radio" name="week_off_benefits" value="Yes" <?php echo $wob === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="week_off_benefits" value="No" <?php echo $wob === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>24. Holiday Benefits</label>
                        <div class="radio-row">
                            <?php $hb = empField($employee, 'holiday_benefits', 'No'); ?>
                            <label><input type="radio" name="holiday_benefits" value="Yes" <?php echo $hb === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="holiday_benefits" value="No" <?php echo $hb === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Overtime Benefits</label>
                        <div class="radio-row">
                            <?php $ob = empField($employee, 'overtime_benefits', 'No'); ?>
                            <label><input type="radio" name="overtime_benefits" value="Yes" <?php echo $ob === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="overtime_benefits" value="No" <?php echo $ob === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group span-2">
                        <label>21. Extra Note</label>
                        <textarea name="extra_note" class="form-control" rows="1"><?php echo htmlspecialchars(empField($employee, 'extra_note')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Employee
                </button>
                <a href="<?php echo app_url('employees/index.php?department_id=' . (int) $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
