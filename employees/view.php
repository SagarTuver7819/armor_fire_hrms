<?php
/**
 * employees/view.php
 * Employee Details — professional HRMS profile card
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$emp = $id > 0 ? getEmployeeById($id) : null;

if (!$emp || (int) $emp['status'] !== 1) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) $emp['department_id'];
$pageTitle = 'Employee Details';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'join_employee';

require_once __DIR__ . '/../includes/header.php';

$toastMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $toastMsg = 'Employee updated successfully.';
}

function showVal($v)
{
    if ($v === null || $v === '') {
        return '-';
    }
    return htmlspecialchars((string) $v);
}

function empInitials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'E';
    }
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return strtoupper($first . $last);
}

$salaryShow = ($emp['decided_salary'] !== null && $emp['decided_salary'] !== '')
    ? '₹ ' . number_format((float) $emp['decided_salary'], 2)
    : '-';

$shiftClass = (strtolower((string) $emp['shift_type']) === 'night') ? 'is-night' : 'is-day';
$backUrl = app_url('employees/index.php?department_id=' . $deptId);
if (isset($_GET['from']) && $_GET['from'] === 'all') {
    $backUrl = app_url('employees/index.php');
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employee List
        </a>
        <?php if (!isset($_GET['from']) || $_GET['from'] !== 'all'): ?>
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="btn-secondary">
            <i class="fa-solid fa-puzzle-piece"></i> Module Boxes
        </a>
        <?php endif; ?>
    </div>

    <!-- Professional Employee ID Card -->
    <section class="emp-id-card">
        <div class="emp-id-top">
            <div class="emp-id-identity">
                <div class="emp-avatar" aria-hidden="true">
                    <span><?php echo htmlspecialchars(empInitials($emp['employee_name'])); ?></span>
                </div>
                <div class="emp-id-copy">
                    <div class="emp-id-tags">
                        <span class="code-badge"><?php echo showVal($emp['employee_code']); ?></span>
                        <span class="status-pill status-active"><i class="fa-solid fa-circle"></i> Active</span>
                    </div>
                    <h1><?php echo showVal($emp['employee_name']); ?></h1>
                    <p class="emp-id-role">
                        <span><i class="fa-solid fa-briefcase"></i> <?php echo showVal($emp['designation']); ?></span>
                        <span class="sep">|</span>
                        <span><i class="fa-solid fa-building"></i> <?php echo showVal($emp['department_name']); ?></span>
                        <span class="sep">|</span>
                        <span class="pay-pill <?php echo (($emp['pay_type'] ?? '') === 'Jobwork') ? 'is-jobwork' : 'is-salary'; ?>">
                            <?php echo htmlspecialchars(($emp['pay_type'] ?? 'Salary') === 'Jobwork' ? 'Jobwork' : 'Salary'); ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="emp-id-actions">
                <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=en'); ?>" target="_blank" class="btn-ghost">
                    <i class="fa-solid fa-file-pdf"></i> PDF EN
                </a>
                <a href="<?php echo app_url('employees/pdf.php?id=' . (int) $emp['id'] . '&lang=hi'); ?>" target="_blank" class="btn-ghost">
                    <i class="fa-solid fa-file-pdf"></i> PDF HI
                </a>
                <a href="<?php echo app_url('employees/salary.php?id=' . (int) $emp['id']); ?>" class="btn-ghost">
                    <i class="fa-solid fa-indian-rupee-sign"></i> Salary Details
                </a>
                <a href="<?php echo app_url('employees/edit.php?id=' . (int) $emp['id'] . '&department_id=' . $deptId); ?>" class="btn-primary">
                    <i class="fa-solid fa-pen"></i> Edit Employee
                </a>
            </div>
        </div>

        <div class="emp-id-stats">
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <span>Joining Date</span>
                    <strong><?php echo showVal(formatDateDisplay($emp['date_of_joining'])); ?></strong>
                </div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <span>Shift</span>
                    <strong class="shift-pill <?php echo $shiftClass; ?>"><?php echo showVal($emp['shift_type']); ?></strong>
                </div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-phone"></i></div>
                <div>
                    <span>Mobile</span>
                    <strong><?php echo showVal($emp['mobile_number']); ?></strong>
                </div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div>
                    <span>Salary</span>
                    <strong><?php echo $salaryShow; ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="view-grid">
        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-id-card"></i>
                <div>
                    <h3>Personal Information</h3>
                    <p>Identity &amp; contact details</p>
                </div>
            </div>
            <dl class="info-list">
                <div class="info-row"><dt>Father / Husband Name</dt><dd><?php echo showVal($emp['father_husband_name']); ?></dd></div>
                <div class="info-row"><dt>Date of Birth</dt><dd><?php echo showVal(formatDateDisplay($emp['date_of_birth'])); ?></dd></div>
                <div class="info-row"><dt>Mobile Number</dt><dd><?php echo showVal($emp['mobile_number']); ?></dd></div>
                <div class="info-row"><dt>Emergency Mobile</dt><dd><?php echo showVal($emp['emergency_mobile']); ?></dd></div>
                <div class="info-row">
                    <dt>Aadhar Number</dt>
                    <dd>
                        <?php echo showVal($emp['aadhar_number']); ?>
                        <?php echo employeeDocumentViewHtml($emp['aadhar_file'] ?? ''); ?>
                    </dd>
                </div>
                <div class="info-row">
                    <dt>PAN Number</dt>
                    <dd>
                        <?php echo showVal($emp['pan_number']); ?>
                        <?php echo employeeDocumentViewHtml($emp['pan_file'] ?? ''); ?>
                    </dd>
                </div>
                <div class="info-row"><dt>Permanent Address</dt><dd><?php echo nl2br(showVal($emp['permanent_address'])); ?></dd></div>
                <div class="info-row"><dt>Present Address</dt><dd><?php echo nl2br(showVal($emp['present_address'])); ?></dd></div>
            </dl>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <div>
                    <h3>Job Information</h3>
                    <p>Role, shift &amp; payroll basics</p>
                </div>
            </div>
            <dl class="info-list">
                <div class="info-row"><dt>Department</dt><dd><?php echo showVal($emp['department_name']); ?></dd></div>
                <div class="info-row"><dt>Pay Type</dt><dd><?php echo showVal($emp['pay_type'] ?? 'Salary'); ?></dd></div>
                <div class="info-row"><dt>Designation</dt><dd><?php echo showVal($emp['designation']); ?></dd></div>
                <div class="info-row"><dt>Date of Joining</dt><dd><?php echo showVal(formatDateDisplay($emp['date_of_joining'])); ?></dd></div>
                <div class="info-row"><dt>Shift Type</dt><dd><span class="shift-pill <?php echo $shiftClass; ?>"><?php echo showVal($emp['shift_type']); ?></span></dd></div>
                <div class="info-row"><dt>Shift Time</dt><dd><?php echo showVal($emp['shift_time']); ?></dd></div>
                <div class="info-row"><dt>PF Deduction</dt><dd><?php echo showVal($emp['pf_deduction']); ?></dd></div>
                <div class="info-row"><dt>UAN Number</dt><dd><?php echo showVal($emp['uan_number']); ?></dd></div>
                <div class="info-row"><dt>Reporting Head</dt><dd><?php echo showVal($emp['reporting_head']); ?></dd></div>
                <div class="info-row"><dt>Decided Salary</dt><dd><?php echo $salaryShow; ?></dd></div>
            </dl>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-building-columns"></i>
                <div>
                    <h3>Bank Information</h3>
                    <p>Salary account details</p>
                </div>
            </div>
            <dl class="info-list">
                <div class="info-row"><dt>Bank Name</dt><dd><?php echo showVal($emp['bank_name']); ?></dd></div>
                <div class="info-row"><dt>Account Number</dt><dd><?php echo showVal($emp['bank_account_number']); ?></dd></div>
                <div class="info-row"><dt>IFSC Code</dt><dd><?php echo showVal($emp['ifsc_code']); ?></dd></div>
                <div class="info-row"><dt>Branch Address</dt><dd><?php echo nl2br(showVal($emp['bank_branch_address'])); ?></dd></div>
            </dl>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-clipboard-list"></i>
                <div>
                    <h3>Other Details</h3>
                    <p>Benefits &amp; notes</p>
                </div>
            </div>
            <dl class="info-list">
                <div class="info-row"><dt>Week-off Day</dt><dd><?php echo showVal($emp['week_off_day']); ?></dd></div>
                <div class="info-row"><dt>Week-off Benefits</dt><dd><?php echo showVal($emp['week_off_benefits']); ?></dd></div>
                <div class="info-row"><dt>Holiday Benefits</dt><dd><?php echo showVal($emp['holiday_benefits']); ?></dd></div>
                <div class="info-row"><dt>Overtime Benefits</dt><dd><?php echo showVal($emp['overtime_benefits']); ?></dd></div>
                <div class="info-row"><dt>Extra Note</dt><dd><?php echo nl2br(showVal($emp['extra_note'])); ?></dd></div>
            </dl>
        </div>
    </div>
</main>

<script>
    window.EMP_TOAST_MSG  = <?php echo json_encode($toastMsg); ?>;
    window.EMP_TOAST_TYPE = 'success';
</script>

<?php
$extraJs = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/employees.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
