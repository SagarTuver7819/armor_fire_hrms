<?php
/**
 * Add / Edit Employee Form — same layout for both (hierarchy-wise sections)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';

ensureEmployeesTable();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$empId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$fromContractor = (($_GET['from'] ?? '') === 'contractor');

function empField($employee, $key, $default = '')
{
    if (!$employee || !isset($employee[$key]) || $employee[$key] === null) {
        return $default;
    }
    return $employee[$key];
}

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
$sidebarMode = $fromContractor ? 'contractor' : 'department';
$sidebarDeptId = $deptId;
$sidebarActive = $fromContractor ? 'contractor_employees' : 'join_employee';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/employee_form.js',
    'assets/js/subdept_cascade.js',
];

require_once __DIR__ . '/../includes/header.php';

$departments  = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$designations = getActiveMasterRows('designations', 'sort_order ASC, name ASC');
$shifts       = getActiveMasterRows('shifts', 'name ASC');
$holidays     = getActiveMasterRows('holidays', 'title ASC');
$weekOffDays  = getWeekOffDaysFromMaster($holidays);
$reporters    = getReportingEmployees($empId);

$currentDesignation = empField($employee, 'designation');
$currentPayType     = normalizePayType(empField($employee, 'pay_type', 'Salary'));
if (!$employee && (($_GET['pay_type'] ?? '') === 'Jobwork')) {
    $currentPayType = 'Jobwork';
}
if (!$employee && (($_GET['pay_type'] ?? '') === 'ContractorMain')) {
    $currentPayType = 'ContractorMain';
}
$currentMainContractor = (int) empField($employee, 'main_contractor_id', 0);
$contractorMains = getEmployeesByPayType('ContractorMain', $empId);
$currentEmpCode     = empField($employee, 'employee_code');
if ($currentEmpCode === '') {
    $codeConn = getDBConnection();
    ensureEmployeesTable($codeConn);
    $currentEmpCode = generateEmployeeCode($codeConn, $currentPayType);
    $codeConn->close();
}
$shiftSelection     = resolveShiftSelection($shifts, $employee);
$selectedShiftId    = (int) ($shiftSelection['shift_id'] ?? 0);
$currentShiftType   = (string) ($shiftSelection['shift_type'] ?? '');
$currentShiftTime   = (string) ($shiftSelection['shift_time'] ?? '');
$selectedReporterId = findReportingEmployeeId($reporters, empField($employee, 'reporting_head'));
$selectedWeekOff    = empField($employee, 'week_off_day');
if ($selectedWeekOff === '' && !$employee) {
    $selectedWeekOff = 'Sunday';
}
$familyMembers = $empId > 0 ? getEmployeeFamilyMembers($empId) : [];
$familyCount = count($familyMembers);
$gender = empField($employee, 'gender');
$maritalStatus = empField($employee, 'marital_status');
$photoFileUrl = employeeDocumentPublicUrl(empField($employee, 'photo_file'));
$aadharFileUrl = employeeDocumentPublicUrl(empField($employee, 'aadhar_file'));
$panFileUrl = employeeDocumentPublicUrl(empField($employee, 'pan_file'));
$isExited = !empty(empField($employee, 'date_of_exit')) || (int) empField($employee, 'status', 1) === 0;
$pf = empField($employee, 'pf_deduction', 'No');
$wob = empField($employee, 'week_off_benefits', 'No');
$hb = empField($employee, 'holiday_benefits', 'No');
$ob = empField($employee, 'overtime_benefits', 'No');
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo $fromContractor
            ? app_url('contractor/employees/index.php')
            : app_url('employees/index.php?department_id=' . (int) $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo $fromContractor ? 'Back to Contractor Employee' : 'Back to Employee List'; ?>
        </a>
        <a href="<?php echo app_url('department.php?id=' . (int) $deptId); ?>" class="btn-secondary">
            <i class="fa-solid fa-puzzle-piece"></i> Module Boxes
        </a>
    </div>

    <div class="form-page-card emp-join-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo $employee ? 'Edit Employee' : 'Add Employee'; ?></h1>
                <p>
                    Same form for Add &amp; Edit · Hierarchy-wise sections ·
                    Department: <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>
                </p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('employees/save.php'); ?>" class="employee-form" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int) $empId; ?>">
            <?php if ($fromContractor): ?>
                <input type="hidden" name="from" value="contractor">
            <?php endif; ?>

            <!-- 1. Personal -->
            <div class="form-section">
                <h3><span class="emp-sec-no">1</span><i class="fa-solid fa-user"></i> Personal Information</h3>
                <div class="emp-subhead">Identity &amp; contact</div>
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
                    <div class="form-group">
                        <label>3. Date of Birth <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="date_of_birth" class="form-control js-date" placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue(empField($employee, 'date_of_birth'))); ?>">
                    </div>
                    <div class="form-group">
                        <label>4. Gender</label>
                        <div class="emp-radio-row">
                            <label class="emp-radio-option">
                                <input type="radio" name="gender" value="Male" <?php echo $gender === 'Male' ? 'checked' : ''; ?>>
                                <span>Male</span>
                            </label>
                            <label class="emp-radio-option">
                                <input type="radio" name="gender" value="Female" <?php echo $gender === 'Female' ? 'checked' : ''; ?>>
                                <span>Female</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group marital-status-row">
                        <label>5. Marital Status</label>
                        <div class="marital-inline">
                            <select name="marital_status" id="maritalStatus" class="form-control no-select2">
                                <option value="">— Select —</option>
                                <option value="Married" <?php echo $maritalStatus === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Unmarried" <?php echo $maritalStatus === 'Unmarried' ? 'selected' : ''; ?>>Unmarried</option>
                                <option value="Other" <?php echo $maritalStatus === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="marital-remark-wrap <?php echo $maritalStatus === 'Other' ? 'is-open' : ''; ?>" id="maritalRemarkWrap">
                                <input type="text" name="marital_remark" id="maritalRemark" class="form-control"
                                       maxlength="255" placeholder="Remark"
                                       value="<?php echo htmlspecialchars(empField($employee, 'marital_remark')); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="emp-subhead">Address</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group span-2">
                        <label>6. Permanent Address <small>(As per Aadhar)</small></label>
                        <textarea name="permanent_address" class="form-control" rows="2"><?php echo htmlspecialchars(empField($employee, 'permanent_address')); ?></textarea>
                    </div>
                    <div class="form-group span-2">
                        <label>7. Present Address <small>(Current)</small></label>
                        <textarea name="present_address" class="form-control" rows="2"><?php echo htmlspecialchars(empField($employee, 'present_address')); ?></textarea>
                    </div>
                </div>

                <div class="emp-subhead">Contact</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>8. Mobile Number</label>
                        <input type="text" name="mobile_number" class="form-control" maxlength="15"
                               value="<?php echo htmlspecialchars(empField($employee, 'mobile_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>9. Emergency Mobile</label>
                        <input type="text" name="emergency_mobile" class="form-control" maxlength="15"
                               value="<?php echo htmlspecialchars(empField($employee, 'emergency_mobile')); ?>">
                    </div>
                    <div class="form-group">
                        <label>10. Office Mail ID</label>
                        <input type="email" name="office_email" class="form-control" maxlength="150"
                               placeholder="name@company.com"
                               value="<?php echo htmlspecialchars(empField($employee, 'office_email')); ?>">
                    </div>
                    <div class="form-group">
                        <label>11. Office Mobile Number</label>
                        <input type="text" name="office_mobile" class="form-control" maxlength="15"
                               value="<?php echo htmlspecialchars(empField($employee, 'office_mobile')); ?>">
                    </div>
                </div>

                <div class="emp-subhead">Photo &amp; documents</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>12. Photo Upload</label>
                        <div class="emp-photo-upload">
                            <div class="emp-photo-preview" id="photoPreview">
                                <?php if ($photoFileUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($photoFileUrl); ?>" alt="Employee photo">
                                <?php else: ?>
                                    <span class="emp-photo-placeholder"><i class="fa-solid fa-camera"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="emp-photo-controls">
                                <input type="file" name="photo_file" id="photoFile" class="doc-file-input" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <small class="form-hint">JPG / PNG / WEBP · max 5MB</small>
                                <?php if ($photoFileUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($photoFileUrl); ?>" class="doc-view-link" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-eye"></i> View Photo
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>13. Aadhar Card Number</label>
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
                        <label>14. PAN Card Number</label>
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

            <!-- 2. Job -->
            <div class="form-section">
                <h3><span class="emp-sec-no">2</span><i class="fa-solid fa-briefcase"></i> Job Information</h3>
                <div class="emp-subhead">Employment type &amp; codes</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>15. Pay Type</label>
                        <?php if ($fromContractor): ?>
                            <input type="hidden" name="pay_type" id="payType" value="Jobwork">
                            <input type="text" class="form-control" value="Jobwork" readonly>
                        <?php else: ?>
                        <select name="pay_type" id="payType" class="form-control" required>
                            <option value="Salary" <?php echo $currentPayType === 'Salary' ? 'selected' : ''; ?>>Normal Salary</option>
                            <option value="Jobwork" <?php echo $currentPayType === 'Jobwork' ? 'selected' : ''; ?>>Contractor Jobwork</option>
                            <option value="ContractorMain" <?php echo $currentPayType === 'ContractorMain' ? 'selected' : ''; ?>>Contractor Main</option>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" id="mainContractorWrap" style="<?php echo $currentPayType === 'Jobwork' ? '' : 'display:none;'; ?>">
                        <label>16. Contractor Main</label>
                        <select name="main_contractor_id" id="mainContractorId" class="form-control">
                            <option value="0">— None —</option>
                            <?php foreach ($contractorMains as $cm): ?>
                                <option value="<?php echo (int) $cm['id']; ?>" <?php echo $currentMainContractor === (int) $cm['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($cm['employee_code'] ?? '') . ' — ' . ($cm['employee_name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>17. Employee Code <small>(auto by pay type)</small></label>
                        <div class="code-input-row">
                            <input type="text" name="employee_code" id="employeeCode" class="form-control" required
                                   maxlength="30" value="<?php echo htmlspecialchars($currentEmpCode); ?>">
                            <button type="button" class="btn-secondary" id="btnGenCode" title="Generate code">
                                <i class="fa-solid fa-rotate"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>18. Biometric User ID</label>
                        <input type="text" name="biometric_user_id" class="form-control" maxlength="50"
                               value="<?php echo htmlspecialchars(empField($employee, 'biometric_user_id')); ?>">
                    </div>
                </div>

                <div class="emp-subhead">Department &amp; role</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>19. Department</label>
                        <select name="department_id" class="form-control" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int) $dept['id']; ?>" <?php echo ((int) $dept['id'] === $deptId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>20. Sub Department</label>
                        <select name="sub_department_id" class="form-control"
                                data-selected="<?php echo (int) empField($employee, 'sub_department_id', 0); ?>">
                            <option value="">Select Sub Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>21. Designation</label>
                        <select name="designation" class="form-control">
                            <option value="">— Select designation —</option>
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
                    </div>
                </div>

                <div class="emp-subhead">Joining &amp; status</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>22. Date of Joining <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="date_of_joining" class="form-control js-date" placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue(empField($employee, 'date_of_joining'))); ?>">
                    </div>
                    <div class="form-group">
                        <label>23. Exit Date <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="date_of_exit" id="dateOfExitInput" class="form-control js-date" placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue(empField($employee, 'date_of_exit'))); ?>">
                        <small class="form-hint">Exit date → employee becomes Deactive</small>
                    </div>
                    <div class="form-group">
                        <label>24. Employee Status</label>
                        <select name="status" id="empStatusSelect" class="form-control">
                            <option value="1" <?php echo !$isExited ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $isExited ? 'selected' : ''; ?>>Deactive (Exit / Inactive)</option>
                        </select>
                    </div>
                </div>

                <div class="emp-subhead">Shift</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>25. Shift</label>
                        <select name="shift_id" id="shiftSelect" class="form-control">
                            <option value="">— Select shift —</option>
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
                    </div>
                    <div class="form-group">
                        <label>26. Shift Type</label>
                        <input type="text" id="shiftTypeDisplay" class="form-control" readonly
                               value="<?php echo htmlspecialchars($currentShiftType); ?>">
                        <input type="hidden" name="shift_type" id="shiftType" value="<?php echo htmlspecialchars($currentShiftType); ?>">
                    </div>
                    <div class="form-group">
                        <label>27. Shift Time</label>
                        <input type="text" name="shift_time" id="shiftTime" class="form-control" readonly
                               placeholder="Select shift to auto-fill"
                               value="<?php echo htmlspecialchars($currentShiftTime); ?>">
                    </div>
                </div>

                <div class="emp-subhead">Salary &amp; reporting</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>28. PF Deduction</label>
                        <div class="radio-row">
                            <label><input type="radio" name="pf_deduction" value="Yes" <?php echo $pf === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="pf_deduction" value="No" <?php echo $pf === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>29. PF Start Date <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="pf_start_date" class="form-control js-date" placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue(empField($employee, 'pf_start_date'))); ?>">
                    </div>
                    <div class="form-group">
                        <label>30. UAN Number</label>
                        <input type="text" name="uan_number" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'uan_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>31. Decided Salary</label>
                        <input type="number" step="0.01" name="decided_salary" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'decided_salary')); ?>">
                    </div>
                    <div class="form-group">
                        <label>32. Reporting Person</label>
                        <select name="reporting_employee_id" class="form-control">
                            <option value="">— Select reporting person —</option>
                            <?php foreach ($reporters as $rep): ?>
                                <?php $rid = (int) $rep['id']; ?>
                                <option value="<?php echo $rid; ?>" <?php echo ($selectedReporterId === $rid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(reportingPersonLabel($rep)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Family -->
            <div class="form-section">
                <h3><span class="emp-sec-no">3</span><i class="fa-solid fa-people-roof"></i> Family Details</h3>
                <div class="emp-subhead">Enter member count — rows open below</div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>33. Family Members</label>
                        <input type="number" id="familyMemberCount" class="form-control" min="0" max="10" step="1"
                               value="<?php echo (int) $familyCount; ?>" placeholder="e.g. 3 or 4">
                        <small class="form-hint">0–10 members</small>
                    </div>
                </div>
                <div id="familyMembersList" class="family-members-list">
                    <?php for ($i = 0; $i < max($familyCount, 0); $i++):
                        $fm = $familyMembers[$i] ?? ['member_name' => '', 'relation_name' => '', 'occupation' => ''];
                        ?>
                        <div class="family-member-card" data-index="<?php echo $i; ?>">
                            <div class="family-member-head">Family Member <?php echo $i + 1; ?></div>
                            <div class="form-grid form-grid-3">
                                <div class="form-group">
                                    <label>Name of Family Member</label>
                                    <input type="text" name="family_name[]" class="form-control"
                                           value="<?php echo htmlspecialchars((string) ($fm['member_name'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Relation</label>
                                    <input type="text" name="family_relation[]" class="form-control"
                                           placeholder="Father / Mother / Spouse / Son…"
                                           value="<?php echo htmlspecialchars((string) ($fm['relation_name'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <input type="text" name="family_occupation[]" class="form-control"
                                           value="<?php echo htmlspecialchars((string) ($fm['occupation'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <template id="familyMemberTpl">
                    <div class="family-member-card" data-index="__INDEX__">
                        <div class="family-member-head">Family Member __NUM__</div>
                        <div class="form-grid form-grid-3">
                            <div class="form-group">
                                <label>Name of Family Member</label>
                                <input type="text" name="family_name[]" class="form-control" value="">
                            </div>
                            <div class="form-group">
                                <label>Relation</label>
                                <input type="text" name="family_relation[]" class="form-control" placeholder="Father / Mother / Spouse / Son…" value="">
                            </div>
                            <div class="form-group">
                                <label>Occupation</label>
                                <input type="text" name="family_occupation[]" class="form-control" value="">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- 4. Bank -->
            <div class="form-section">
                <h3><span class="emp-sec-no">4</span><i class="fa-solid fa-building-columns"></i> Bank Information</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>34. Bank Name</label>
                        <input type="text" name="bank_name" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'bank_name')); ?>">
                    </div>
                    <div class="form-group">
                        <label>35. Bank Account Number</label>
                        <input type="text" name="bank_account_number" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'bank_account_number')); ?>">
                    </div>
                    <div class="form-group">
                        <label>36. IFSC Code</label>
                        <input type="text" name="ifsc_code" class="form-control"
                               value="<?php echo htmlspecialchars(empField($employee, 'ifsc_code')); ?>">
                    </div>
                    <div class="form-group full">
                        <label>37. Bank Branch Address</label>
                        <textarea name="bank_branch_address" class="form-control" rows="2"><?php echo htmlspecialchars(empField($employee, 'bank_branch_address')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 5. Other -->
            <div class="form-section">
                <h3><span class="emp-sec-no">5</span><i class="fa-solid fa-clipboard-list"></i> Other Details</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>38. Week-off Day <span class="req">*</span></label>
                        <select name="week_off_day" class="form-control" required>
                            <option value="">— Select —</option>
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
                        <label>39. Week-off Benefits</label>
                        <div class="radio-row">
                            <label><input type="radio" name="week_off_benefits" value="Yes" <?php echo $wob === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="week_off_benefits" value="No" <?php echo $wob === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>40. Holiday Benefits</label>
                        <div class="radio-row">
                            <label><input type="radio" name="holiday_benefits" value="Yes" <?php echo $hb === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="holiday_benefits" value="No" <?php echo $hb === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>41. Overtime Benefits</label>
                        <div class="radio-row">
                            <label><input type="radio" name="overtime_benefits" value="Yes" <?php echo $ob === 'Yes' ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="overtime_benefits" value="No" <?php echo $ob === 'No' ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group span-2">
                        <label>42. Extra Note</label>
                        <textarea name="extra_note" class="form-control" rows="2"><?php echo htmlspecialchars(empField($employee, 'extra_note')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Employee
                </button>
                <a href="<?php echo $fromContractor
                    ? app_url('contractor/employees/index.php')
                    : app_url('employees/index.php?department_id=' . (int) $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
    window.EMP_NEXT_CODE_URL = <?php echo json_encode(app_url('employees/next_code.php')); ?>;
    window.EMP_CHECK_CODE_URL = <?php echo json_encode(app_url('employees/check_code.php')); ?>;
    window.EMP_IS_NEW = <?php echo $empId > 0 ? 'false' : 'true'; ?>;
    window.EMP_ID = <?php echo (int) $empId; ?>;
    window.SUBDEPT_URL = <?php echo json_encode(app_url('masters/sub_departments/by_department.php')); ?>;
    window.SUBDEPT_SELECTED = <?php echo json_encode((string) empField($employee, 'sub_department_id', '0')); ?>;
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
