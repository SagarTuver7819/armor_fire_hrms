<?php
/**
 * Add / Edit Employee Form
 * Fields match English Employee Information Form document.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';

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
$extraJs = ['assets/js/employee_form.js'];

require_once __DIR__ . '/../includes/header.php';

$departments  = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$designations = getActiveMasterRows('designations', 'sort_order ASC, name ASC');
$shifts       = getActiveMasterRows('shifts', 'name ASC');
$holidays     = getActiveMasterRows('holidays', 'title ASC');
$weekOffDays  = getWeekOffDaysFromMaster($holidays);
$reporters    = getReportingEmployees($empId);

$currentDesignation = empField($employee, 'designation');
$currentPayType     = empField($employee, 'pay_type', 'Salary');
if ($currentPayType !== 'Jobwork') {
    $currentPayType = 'Salary';
}
$currentEmpCode     = empField($employee, 'employee_code');
if ($currentEmpCode === '') {
    $codeConn = getDBConnection();
    ensureEmployeesTable($codeConn);
    $currentEmpCode = generateEmployeeCode($codeConn, $currentPayType);
    $codeConn->close();
}
$currentShiftType   = empField($employee, 'shift_type', 'Day');
$currentShiftTime   = empField($employee, 'shift_time');
$selectedShiftId    = findMatchingShiftId($shifts, $currentShiftType, $currentShiftTime);
$selectedReporterId = findReportingEmployeeId($reporters, empField($employee, 'reporting_head'));
$selectedWeekOff    = empField($employee, 'week_off_day');

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
                </p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('employees/save.php'); ?>" class="employee-form" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int) $empId; ?>">

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
                    <?php
                    $aadharFileUrl = employeeDocumentPublicUrl(empField($employee, 'aadhar_file'));
                    $panFileUrl    = employeeDocumentPublicUrl(empField($employee, 'pan_file'));
                    ?>
                    <div class="form-group">
                        <label>7. Aadhar Card Number</label>
                        <input type="text" name="aadhar_number" class="form-control" maxlength="20"
                               value="<?php echo htmlspecialchars(empField($employee, 'aadhar_number')); ?>">
                        <div class="doc-attach-row">
                            <span class="doc-attach-caption">Attachment</span>
                            <input type="file" name="aadhar_file" class="doc-file-input" accept=".jpg,.jpeg,.png,.pdf,.webp">
                            <?php if ($aadharFileUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($aadharFileUrl); ?>" class="doc-view-link" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>8. PAN Card Number</label>
                        <input type="text" name="pan_number" class="form-control" maxlength="20"
                               value="<?php echo htmlspecialchars(empField($employee, 'pan_number')); ?>">
                        <div class="doc-attach-row">
                            <span class="doc-attach-caption">Attachment</span>
                            <input type="file" name="pan_file" class="doc-file-input" accept=".jpg,.jpeg,.png,.pdf,.webp">
                            <?php if ($panFileUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($panFileUrl); ?>" class="doc-view-link" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2 -->
            <div class="form-section">
                <h3><i class="fa-solid fa-briefcase"></i> Job Information</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Pay Type <small>(Salary / Jobwork)</small></label>
                        <select name="pay_type" id="payType" class="form-control" required>
                            <option value="Salary" <?php echo $currentPayType === 'Salary' ? 'selected' : ''; ?>>Salary</option>
                            <option value="Jobwork" <?php echo $currentPayType === 'Jobwork' ? 'selected' : ''; ?>>Jobwork</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employee Code <small>(editable · auto by pay type)</small></label>
                        <div class="code-input-row">
                            <input type="text" name="employee_code" id="employeeCode" class="form-control" required
                                   maxlength="30" value="<?php echo htmlspecialchars($currentEmpCode); ?>">
                            <button type="button" class="btn-secondary" id="btnGenCode" title="Generate code">
                                <i class="fa-solid fa-rotate"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>10. Department <small>(Department Master)</small></label>
                        <select name="department_id" class="form-control" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int) $dept['id']; ?>" <?php echo ((int) $dept['id'] === $deptId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>11. Designation <small>(Designation Master)</small></label>
                        <select name="designation" class="form-control">
                            <option value="">-- Select designation --</option>
                            <?php foreach ($designations as $des): ?>
                                <?php $desName = (string) ($des['name'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($desName); ?>" <?php echo ($currentDesignation === $desName) ? 'selected' : ''; ?>>
                                    <?php
                                    $desCode = trim((string) ($des['code'] ?? ''));
                                    echo htmlspecialchars($desCode !== '' ? ($desCode . ' — ' . $desName) : $desName);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($currentDesignation !== '' && !in_array($currentDesignation, array_column($designations, 'name'), true)): ?>
                                <option value="<?php echo htmlspecialchars($currentDesignation); ?>" selected>
                                    <?php echo htmlspecialchars($currentDesignation); ?> (current)
                                </option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$designations): ?>
                            <small class="form-hint">No designations found. Add them in Masters.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>12. Date of Joining</label>
                        <input type="date" name="date_of_joining" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'date_of_joining')); ?>">
                    </div>
                    <div class="form-group">
                        <label>13. Shift <small>(Shift Master)</small></label>
                        <select name="shift_id" id="shiftSelect" class="form-control">
                            <option value="">-- Select shift --</option>
                            <?php foreach ($shifts as $shift): ?>
                                <?php
                                $sid = (int) $shift['id'];
                                $range = formatShiftTimeRange($shift);
                                $stype = (string) ($shift['shift_type'] ?? 'Day');
                                ?>
                                <option value="<?php echo $sid; ?>"
                                        data-type="<?php echo htmlspecialchars($stype); ?>"
                                        data-time="<?php echo htmlspecialchars($range); ?>"
                                    <?php echo ($selectedShiftId === $sid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(formatShiftOptionLabel($shift)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$shifts): ?>
                            <small class="form-hint">No shifts found. Add them in Masters.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>13. Shift Type</label>
                        <input type="text" id="shiftTypeDisplay" class="form-control" readonly
                               value="<?php echo htmlspecialchars($currentShiftType); ?>">
                        <input type="hidden" name="shift_type" id="shiftType" value="<?php echo htmlspecialchars($currentShiftType); ?>">
                    </div>
                    <div class="form-group">
                        <label>13. Shift Time</label>
                        <input type="text" name="shift_time" id="shiftTime" class="form-control" readonly
                               placeholder="Select shift to auto-fill"
                               value="<?php echo htmlspecialchars($currentShiftTime); ?>">
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
                        <label>Reporting Person <small>(from Employees · with employee code)</small></label>
                        <select name="reporting_employee_id" class="form-control">
                            <option value="">-- Select reporting person --</option>
                            <?php foreach ($reporters as $rep): ?>
                                <?php $rid = (int) $rep['id']; ?>
                                <option value="<?php echo $rid; ?>" <?php echo ($selectedReporterId === $rid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(reportingPersonLabel($rep)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$reporters): ?>
                            <small class="form-hint">Add employees first — reporting person loads from existing staff.</small>
                        <?php endif; ?>
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
                        <label>22. Week-off Day <small>(Holiday / Week-Off Master)</small></label>
                        <select name="week_off_day" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($weekOffDays as $day): ?>
                                <option value="<?php echo htmlspecialchars($day); ?>" <?php echo ($selectedWeekOff === $day) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($day); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($selectedWeekOff !== '' && !in_array($selectedWeekOff, $weekOffDays, true)): ?>
                                <option value="<?php echo htmlspecialchars($selectedWeekOff); ?>" selected>
                                    <?php echo htmlspecialchars($selectedWeekOff); ?> (current)
                                </option>
                            <?php endif; ?>
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

        <script>
            window.EMP_NEXT_CODE_URL = <?php echo json_encode(app_url('employees/next_code.php')); ?>;
            window.EMP_IS_NEW = <?php echo $empId > 0 ? 'false' : 'true'; ?>;
        </script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
