<?php
/**
 * Apply Leave — staff pick employee, or employee self-apply
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();
ensureLeaveTables();

if (empty($_SESSION['role_code'])) {
    refreshHeadedDepartmentsSession();
}

$deptId = (int) ($_GET['department_id'] ?? $_POST['department_id'] ?? 0);
$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$selfApply = function_exists('isEmployee') && isEmployee() && $sessionEmpId > 0
    && !canAccess('leave', 'edit', 0) && !isDeptHeadRole();

requireAccess('leave', 'add', $deptId);

$preEmp = (int) ($_GET['employee_id'] ?? 0);
$year = (int) date('Y');
$leaveTypes = getActiveLeaveTypes();

$selfEmployee = null;
if ($selfApply) {
    $selfEmployee = getEmployeeById($sessionEmpId);
    if (!$selfEmployee) {
        header('Location: ' . app_url('leave/index.php?msg=error&err=' . rawurlencode('Employee profile not linked.')));
        exit;
    }
    $deptId = (int) ($selfEmployee['department_id'] ?? 0);
    $preEmp = $sessionEmpId;
    $employees = [$selfEmployee];
} else {
    if (isDeptHeadRole()) {
        $headed = array_map('intval', $_SESSION['headed_department_ids'] ?? []);
        if ($deptId <= 0 && count($headed) === 1) {
            $deptId = $headed[0];
        }
        if ($deptId > 0 && !in_array($deptId, $headed, true)) {
            header('Location: ' . app_url('hr/dashboard.php') . '?msg=denied');
            exit;
        }
    }
    $employees = leaveEmployeesForSelect($deptId);
}

$department = $deptId > 0 ? getDepartmentById($deptId) : null;

$pageTitle = $selfApply ? 'Apply My Leave' : 'Apply Leave';
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
                <h1><?php echo $selfApply ? 'Apply My Leave' : 'Apply Leave'; ?></h1>
                <p>
                    <?php
                    if ($selfApply && $selfEmployee) {
                        echo htmlspecialchars(($selfEmployee['employee_code'] ?? '') . ' — ' . ($selfEmployee['employee_name'] ?? ''));
                    } else {
                        echo $department ? htmlspecialchars($department['department_name']) : 'Select employee';
                    }
                    ?>
                    · Working days only (week-off &amp; holiday excluded) · Checks employee balance
                </p>
            </div>
        </div>

        <form method="POST" action="<?php echo app_url('leave/save.php'); ?>" class="employee-form" enctype="multipart/form-data">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <?php if ($selfApply): ?>
                <input type="hidden" name="employee_id" value="<?php echo $sessionEmpId; ?>">
            <?php endif; ?>
            <div class="form-grid form-grid-3">
                <?php if (!$selfApply): ?>
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
                <?php else: ?>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Employee</label>
                    <input type="text" class="form-control" readonly
                           value="<?php echo htmlspecialchars(($selfEmployee['employee_code'] ?? '') . ' — ' . ($selfEmployee['employee_name'] ?? '')); ?>">
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Leave Type <span class="req">*</span></label>
                    <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                        <option value="">Select type</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <?php
                            $code = strtoupper(trim((string) ($lt['code'] ?? '')));
                            $hint = '';
                            if ($code === 'SL') {
                                $hint = ' · 2/month, unused wipes';
                            } elseif ($code === 'PL') {
                                $hint = ' · 24/yr monthly CF';
                            }
                            ?>
                            <option value="<?php echo (int) $lt['id']; ?>"
                                    data-code="<?php echo htmlspecialchars($code); ?>">
                                <?php
                                echo htmlspecialchars(
                                    ($lt['code'] ? $lt['code'] . ' · ' : '')
                                    . $lt['leave_type']
                                    . ' (' . (int) $lt['days_allowed'] . ' /yr, '
                                    . (($lt['is_paid'] ?? 'Yes') === 'Yes' ? 'Paid' : 'Unpaid') . ')'
                                    . $hint
                                );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Date <span class="req">*</span></label>
                    <input type="date" name="from_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>To Date <span class="req">*</span></label>
                    <input type="date" name="to_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Half Day</label>
                    <select name="leave_half" class="form-control">
                        <option value="FULL">Full Day</option>
                        <option value="FHL">First Half</option>
                        <option value="SHL">Second Half</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Optional"></textarea>
                </div>
                <div class="form-group" id="leave_attachment_group" style="grid-column: 1 / -1;">
                    <label>Attachment <span style="font-weight:500;color:#64748b;">(Optional — recommended for Sick Leave)</span></label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,application/pdf,image/*">
                    <small class="form-hint">PDF / Image · Max 5 MB · Optional for all leave types</small>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Submit Leave Request
                </button>
                <a href="<?php echo app_url('leave/index.php?department_id=' . $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    var sel = document.getElementById('leave_type_id');
    var grp = document.getElementById('leave_attachment_group');
    if (!sel || !grp) return;
    function sync() {
        var opt = sel.options[sel.selectedIndex];
        var code = (opt && opt.getAttribute('data-code')) || '';
        grp.style.outline = code === 'SL' ? '2px solid #fdba74' : 'none';
        grp.style.borderRadius = '10px';
        grp.style.padding = code === 'SL' ? '8px' : '0';
        grp.style.background = code === 'SL' ? '#fff7ed' : 'transparent';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
