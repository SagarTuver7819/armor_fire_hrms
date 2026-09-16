<?php
/**
 * employees/view.php
 * Employee Details — profile + tab-wise activity (Leave / Attendance / Salary)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

ensureEmployeesTable();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$emp = $id > 0 ? getEmployeeById($id) : null;

if (!$emp) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) $emp['department_id'];
$isDeactive = isEmployeeDeactive($emp);
$tab = strtolower(trim((string) ($_GET['tab'] ?? 'profile')));
if (!in_array($tab, ['profile', 'leave', 'history', 'attendance', 'salary'], true)) {
    $tab = 'profile';
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$pageTitle = 'Employee Details' . ($isDeactive ? ' (Deactive)' : '');
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = $isDeactive ? 'exit_employee' : 'join_employee';

ensureLeaveTables();
$conn = getDBConnection();
ensureAttendanceTables($conn);
foreach (getActiveLeaveTypes($conn) as $lt) {
    leaveEnsureBalanceRow($conn, $id, (int) $lt['id'], $year);
}
$leaveBalances = getEmployeeLeaveBalances($id, $year);
$leaveHistory = fetchLeaveRequests($deptId, 'All', $year, $conn, $id);
$attGrid = null;
if ($tab === 'attendance') {
    $attGrid = getAttendanceExcelMonthGrid($month, $year, $deptId, $id, $conn);
}
$conn->close();

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
$photoUrl = employeeDocumentPublicUrl($emp['photo_file'] ?? '');

$from = (string) ($_GET['from'] ?? '');
if ($from === 'exit') {
    $backUrl = app_url('employees/exit_list.php' . ($deptId > 0 ? '?department_id=' . $deptId : ''));
    $backText = 'Back to Exit Employee List';
} elseif ($from === 'all') {
    $backUrl = app_url('employees/index.php');
    $backText = 'Back to All Employees';
} elseif ($isDeactive) {
    $backUrl = app_url('employees/exit_list.php' . ($deptId > 0 ? '?department_id=' . $deptId : ''));
    $backText = 'Back to Exit Employee List';
} else {
    $backUrl = app_url('employees/index.php?department_id=' . $deptId);
    $backText = 'Back to Employee List';
}

$baseQs = array_filter([
    'id' => $id,
    'from' => $from !== '' ? $from : null,
]);
$tabUrl = function ($t) use ($baseQs, $year, $month) {
    $q = $baseQs + ['tab' => $t];
    if ($t === 'attendance') {
        $q['month'] = $month;
        $q['year'] = $year;
    } elseif (in_array($t, ['leave', 'history'], true)) {
        $q['year'] = $year;
    }
    return app_url('employees/view.php?' . http_build_query($q));
};
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> <?php echo htmlspecialchars($backText); ?>
        </a>
        <?php if (!isset($_GET['from']) || $_GET['from'] !== 'all'): ?>
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="btn-secondary">
            <i class="fa-solid fa-puzzle-piece"></i> Module Boxes
        </a>
        <?php endif; ?>
    </div>

    <section class="emp-id-card">
        <div class="emp-id-top">
            <div class="emp-id-identity">
                <div class="emp-avatar" aria-hidden="true">
                    <?php if ($photoUrl !== ''): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars(empInitials($emp['employee_name'])); ?></span>
                    <?php endif; ?>
                </div>
                <div class="emp-id-copy">
                    <div class="emp-id-tags">
                        <span class="code-badge"><?php echo showVal($emp['employee_code']); ?></span>
                        <?php if ($isDeactive): ?>
                            <span class="status-pill status-deactive" title="<?php echo !empty($emp['date_of_exit']) ? ('Exit Date: ' . htmlspecialchars(formatDateDisplay($emp['date_of_exit']))) : 'Deactive'; ?>">
                                <i class="fa-solid fa-circle"></i> Deactive
                            </span>
                        <?php else: ?>
                            <span class="status-pill status-active"><i class="fa-solid fa-circle"></i> Active</span>
                        <?php endif; ?>
                    </div>
                    <h1><?php echo showVal($emp['employee_name']); ?></h1>
                    <p class="emp-id-role">
                        <span><i class="fa-solid fa-briefcase"></i> <?php echo showVal($emp['designation']); ?></span>
                        <span class="sep">|</span>
                        <span><i class="fa-solid fa-building"></i> <?php echo showVal($emp['department_name']); ?></span>
                        <span class="sep">|</span>
                        <span class="pay-pill <?php echo payTypeCssClass($emp['pay_type'] ?? 'Salary'); ?>">
                            <?php echo htmlspecialchars(payTypeLabel($emp['pay_type'] ?? 'Salary')); ?>
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
            <?php if (!empty($emp['date_of_exit']) && $emp['date_of_exit'] !== '0000-00-00'): ?>
            <div class="emp-stat-item is-exit-stat">
                <div class="emp-stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="fa-solid fa-door-open"></i></div>
                <div>
                    <span>Exit Date</span>
                    <strong style="color:#dc2626;"><?php echo showVal(formatDateDisplay($emp['date_of_exit'])); ?></strong>
                </div>
            </div>
            <?php endif; ?>
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

    <div class="emp-activity-tabs">
        <a class="emp-activity-tab <?php echo $tab === 'profile' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('profile')); ?>">
            <i class="fa-solid fa-id-card"></i> Profile
        </a>
        <a class="emp-activity-tab <?php echo $tab === 'leave' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('leave')); ?>">
            <i class="fa-solid fa-scale-balanced"></i> Leave Balance
        </a>
        <a class="emp-activity-tab <?php echo $tab === 'history' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('history')); ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> Leave History
            <span class="emp-tab-badge"><?php echo count($leaveHistory); ?></span>
        </a>
        <a class="emp-activity-tab <?php echo $tab === 'attendance' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('attendance')); ?>">
            <i class="fa-solid fa-user-check"></i> Attendance
        </a>
        <a class="emp-activity-tab <?php echo $tab === 'salary' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl('salary')); ?>">
            <i class="fa-solid fa-indian-rupee-sign"></i> Salary
        </a>
    </div>

    <?php if ($tab === 'leave'): ?>
        <div class="form-page-card">
            <div class="form-page-header flex-between" style="align-items:center;">
                <div>
                    <h3 style="margin:0;">Leave Balance — <?php echo $year; ?></h3>
                    <p style="margin:4px 0 0;">Remaining days by leave type</p>
                </div>
                <a class="btn-secondary" href="<?php echo app_url('leave/balance.php?' . http_build_query([
                    'department_id' => $deptId,
                    'employee_id' => $id,
                    'year' => $year,
                    'tab' => 'balance',
                ])); ?>">
                    <i class="fa-solid fa-pen"></i> Edit Balance
                </a>
            </div>
            <div class="leave-balance-cards">
                <?php if (!$leaveBalances): ?>
                    <p class="empty-msg">No leave balance rows. Allocate year from Leave Balance page.</p>
                <?php endif; ?>
                <?php foreach ($leaveBalances as $r): ?>
                    <div class="leave-balance-card">
                        <div class="lb-code"><?php echo htmlspecialchars($r['code'] ?: 'LEAVE'); ?></div>
                        <div class="lb-name"><?php echo htmlspecialchars($r['leave_type']); ?></div>
                        <div class="lb-remain"><?php echo number_format((float) $r['remaining_days'], 1); ?></div>
                        <div class="lb-meta">
                            Opening <?php echo number_format((float) $r['opening_days'], 1); ?>
                            · Used <strong><?php echo number_format((float) $r['used_days'], 1); ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Paid</th>
                            <th>Opening</th>
                            <th>Credited</th>
                            <th>Adjusted</th>
                            <th>Used</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($leaveBalances as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(($r['code'] ? $r['code'] . ' · ' : '') . $r['leave_type']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['is_paid'] ?? 'Yes'); ?></td>
                            <td><?php echo number_format((float) $r['opening_days'], 1); ?></td>
                            <td><?php echo number_format((float) $r['credited_days'], 1); ?></td>
                            <td><?php echo number_format((float) $r['adjusted_days'], 1); ?></td>
                            <td><strong><?php echo number_format((float) $r['used_days'], 1); ?></strong></td>
                            <td><strong><?php echo number_format((float) $r['remaining_days'], 1); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'history'): ?>
        <div class="form-page-card">
            <div class="form-page-header flex-between" style="align-items:center;">
                <div>
                    <h3 style="margin:0;">Leave History — <?php echo $year; ?></h3>
                    <p style="margin:4px 0 0;">All leave requests for this employee</p>
                </div>
                <a class="btn-primary" href="<?php echo app_url('leave/apply.php?department_id=' . $deptId . '&employee_id=' . $id); ?>">
                    <i class="fa-solid fa-plus"></i> Apply Leave
                </a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sr</th>
                            <th>Leave</th>
                            <th>Half</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Applied</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$leaveHistory): ?>
                        <tr><td colspan="9" class="empty-cell">No leave history.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($leaveHistory as $i => $r): ?>
                        <?php
                        $half = leaveNormalizeHalf($r['leave_half'] ?? 'FULL');
                        $st = $r['status'];
                        $cls = $st === 'Approved' ? 'color:#047857' : ($st === 'Pending' ? 'color:#b45309' : ($st === 'Rejected' ? 'color:#b91c1c' : 'color:#64748b'));
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars(($r['code'] ? $r['code'] . ' · ' : '') . $r['leave_type']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($half === 'FULL' ? 'Full' : $half); ?></strong></td>
                            <td><?php echo htmlspecialchars(formatDateDisplay($r['from_date'])); ?></td>
                            <td><?php echo htmlspecialchars(formatDateDisplay($r['to_date'])); ?></td>
                            <td><strong><?php echo number_format((float) $r['days'], 1); ?></strong></td>
                            <td><strong style="<?php echo $cls; ?>"><?php echo htmlspecialchars($st); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['reason'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($r['created_at']) ? date('d-m-Y', strtotime($r['created_at'])) : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'attendance'): ?>
        <div class="form-page-card">
            <div class="form-page-header">
                <h3 style="margin:0 0 10px;">Attendance — <?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></h3>
                <form method="GET" class="employee-form" style="margin:0;">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="hidden" name="tab" value="attendance">
                    <?php if ($from !== ''): ?><input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>"><?php endif; ?>
                    <div class="form-grid form-grid-3">
                        <div class="form-group">
                            <label>Month</label>
                            <select name="month" class="form-control" onchange="this.form.submit()">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <select name="year" class="form-control" onchange="this.form.submit()">
                                <?php for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                            <a class="btn-secondary" href="<?php echo app_url('attendance/report.php?' . http_build_query([
                                'show' => 1,
                                'department_id' => $deptId,
                                'employee_id' => $id,
                                'month' => $month,
                                'year' => $year,
                            ])); ?>">Full Report</a>
                            <a class="btn-primary" href="<?php echo app_url('attendance/manual.php?' . http_build_query([
                                'department_id' => $deptId,
                                'month' => $month,
                                'year' => $year,
                                'show' => 1,
                            ])); ?>">Manual Entry</a>
                        </div>
                    </div>
                </form>
            </div>
            <?php if ($attGrid): ?>
                <div class="table-wrap excel-att-wrap">
                    <?php echo attendanceRenderExcelMonthTableHtml($attGrid, ['tableClass' => 'data-table excel-att-table']); ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'salary'): ?>
        <div class="form-page-card">
            <div class="form-page-header flex-between" style="align-items:center;">
                <div>
                    <h3 style="margin:0;">Salary Overview</h3>
                    <p style="margin:4px 0 0;">Quick view · open full salary details to edit</p>
                </div>
                <a class="btn-primary" href="<?php echo app_url('employees/salary.php?id=' . $id); ?>">
                    <i class="fa-solid fa-indian-rupee-sign"></i> Open Salary Details
                </a>
            </div>
            <dl class="info-list">
                <div class="info-row"><dt>Pay Type</dt><dd><?php echo showVal(payTypeLabel($emp['pay_type'] ?? 'Salary')); ?></dd></div>
                <div class="info-row"><dt>Decided Salary</dt><dd><strong><?php echo $salaryShow; ?></strong></dd></div>
                <div class="info-row"><dt>PF Deduction</dt><dd><?php echo showVal($emp['pf_deduction']); ?></dd></div>
                <div class="info-row"><dt>UAN Number</dt><dd><?php echo showVal($emp['uan_number']); ?></dd></div>
                <div class="info-row"><dt>Bank Name</dt><dd><?php echo showVal($emp['bank_name']); ?></dd></div>
                <div class="info-row"><dt>Account Number</dt><dd><?php echo showVal($emp['bank_account_number']); ?></dd></div>
                <div class="info-row"><dt>IFSC Code</dt><dd><?php echo showVal($emp['ifsc_code']); ?></dd></div>
            </dl>
        </div>

    <?php else: ?>
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
                    <div class="info-row"><dt>Office Mail ID</dt><dd><?php echo showVal($emp['office_email'] ?? ''); ?></dd></div>
                    <div class="info-row"><dt>Office Mobile</dt><dd><?php echo showVal($emp['office_mobile'] ?? ''); ?></dd></div>
                    <div class="info-row">
                        <dt>Marital Status</dt>
                        <dd>
                            <?php
                            $ms = (string) ($emp['marital_status'] ?? '');
                            echo showVal($ms);
                            if ($ms === 'Other' && !empty($emp['marital_remark'])) {
                                echo ' — ' . showVal($emp['marital_remark']);
                            }
                            ?>
                        </dd>
                    </div>
                    <div class="info-row">
                        <dt>Photo</dt>
                        <dd>
                            <?php if ($photoUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($photoUrl); ?>" target="_blank" rel="noopener" class="doc-view-link">
                                    <i class="fa-solid fa-image"></i> View Photo
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
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
                    <div class="info-row"><dt>Pay Type</dt><dd><?php echo showVal(payTypeLabel($emp['pay_type'] ?? 'Salary')); ?></dd></div>
                    <div class="info-row"><dt>Designation</dt><dd><?php echo showVal($emp['designation']); ?></dd></div>
                    <div class="info-row"><dt>Date of Joining</dt><dd><?php echo showVal(formatDateDisplay($emp['date_of_joining'])); ?></dd></div>
                    <div class="info-row"><dt>Exit Date</dt><dd><?php echo showVal(formatDateDisplay($emp['date_of_exit'] ?? '')); ?></dd></div>
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
    <?php endif; ?>
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
