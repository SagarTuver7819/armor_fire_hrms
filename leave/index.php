<?php
/**
 * Leave Requests — list + filters
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

$deptId = (int) ($_GET['department_id'] ?? 0);
requireAccess('leave', 'view', $deptId);
ensureLeaveTables();

if (empty($_SESSION['role_code'])) {
    refreshHeadedDepartmentsSession();
}

$status = trim((string) ($_GET['status'] ?? 'All'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$canApproveAny = canAccess('leave', 'edit', $deptId > 0 ? $deptId : 0);
$selfOnly = function_exists('isEmployee') && isEmployee() && !$canApproveAny && !isDeptHeadRole();

$department = $deptId > 0 ? getDepartmentById($deptId) : null;

if ($selfOnly && $sessionEmpId > 0) {
    $rows = fetchLeaveRequests(0, $status, $year, null, $sessionEmpId);
} elseif (isDeptHeadRole()) {
    $headed = array_map('intval', $_SESSION['headed_department_ids'] ?? []);
    if ($deptId > 0) {
        if (!in_array($deptId, $headed, true)) {
            header('Location: ' . app_url('hr/dashboard.php') . '?msg=denied');
            exit;
        }
        $rows = fetchLeaveRequests($deptId, $status, $year);
    } else {
        $rows = fetchLeaveRequests(0, $status, $year, null, 0, $headed);
    }
} else {
    $allowed = allowedDepartmentsFor('leave', 'view');
    if (is_array($allowed) && $allowed !== []) {
        if ($deptId > 0 && !in_array($deptId, $allowed, true)) {
            header('Location: ' . app_url('hr/dashboard.php') . '?msg=denied');
            exit;
        }
        if ($deptId > 0) {
            $rows = fetchLeaveRequests($deptId, $status, $year);
        } else {
            $rows = fetchLeaveRequests(0, $status, $year, null, 0, $allowed);
        }
    } elseif (is_array($allowed) && $allowed === []) {
        $rows = [];
    } else {
        $rows = fetchLeaveRequests($deptId, $status, $year);
    }
}

$pageTitle = $selfOnly ? 'My Leave Requests' : 'Leave Request';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'leave_request';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'applied' => 'Leave request submitted.',
        'approved' => 'Leave approved. Balance & attendance updated.',
        'rejected' => 'Leave rejected.',
        'cancelled' => 'Approved leave cancelled. Balance restored.',
        'allocated' => 'Yearly leave balances allocated.',
        'error' => (string) ($_GET['err'] ?? 'Something went wrong.'),
    ];
    $toast = $map[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'error') {
        $toastType = 'error';
    }
}

$backUrl = $deptId > 0
    ? app_url('department.php?id=' . $deptId)
    : app_url('dashboard.php');
$qsBase = http_build_query([
    'department_id' => $deptId,
    'status' => $status,
    'year' => $year,
]);
$canAddLeave = canAccess('leave', 'add', $deptId);
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo $deptId > 0 ? 'Back to Modules' : 'Back to Dashboard'; ?>
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if (!$selfOnly && canAccess('leave', 'view', $deptId)): ?>
            <a href="<?php echo app_url('leave/balance.php?' . http_build_query(['department_id' => $deptId, 'year' => $year])); ?>" class="btn-secondary">
                <i class="fa-solid fa-scale-balanced"></i> Leave Balance
            </a>
            <?php endif; ?>
            <?php if ($canAddLeave): ?>
            <a href="<?php echo app_url('leave/apply.php?' . http_build_query(['department_id' => $deptId])); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> <?php echo $selfOnly ? 'Apply My Leave' : 'Apply Leave'; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Leave Request</h1>
                <p>
                    <?php echo $department ? htmlspecialchars($department['department_name']) : 'All departments'; ?>
                    · Employee-wise balance · Approve updates attendance + salary PL/SL
                </p>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All departments</option>
                        <?php foreach (getActiveMasterRows('departments', 'sort_order ASC, department_name ASC') as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <?php foreach (['All', 'Pending', 'Approved', 'Rejected', 'Cancelled'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo $year; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Employee</th>
                        <th>Dept</th>
                        <th>Leave</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" class="empty-cell">No leave requests found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['employee_name']); ?></strong>
                            <div class="sr-code"><?php echo htmlspecialchars($r['employee_code']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($r['department_name'] ?? '-'); ?></td>
                        <td>
                            <?php echo htmlspecialchars(($r['code'] ? $r['code'] . ' · ' : '') . $r['leave_type']); ?>
                            <div class="sr-code">
                                <?php echo htmlspecialchars($r['is_paid'] === 'Yes' ? 'Paid' : 'Unpaid'); ?>
                                <?php
                                $half = leaveNormalizeHalf($r['leave_half'] ?? 'FULL');
                                if ($half === 'FHL' || $half === 'SHL') {
                                    echo ' · ' . htmlspecialchars(leaveHalfLabel($half));
                                }
                                ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['from_date'])); ?></td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['to_date'])); ?></td>
                        <td><?php echo number_format((float) $r['days'], 1); ?></td>
                        <td>
                            <?php
                            $st = $r['status'];
                            $cls = $st === 'Approved' ? 'color:#047857' : ($st === 'Pending' ? 'color:#b45309' : ($st === 'Rejected' ? 'color:#b91c1c' : 'color:#64748b'));
                            ?>
                            <strong style="<?php echo $cls; ?>"><?php echo htmlspecialchars($st); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($r['reason'] ?: '-'); ?></td>
                        <td>
                            <?php
                            $rowDeptId = (int) ($r['department_id'] ?? 0);
                            $canAct = canAccess('leave', 'edit', $rowDeptId);
                            ?>
                            <?php if ($canAct && $r['status'] === 'Pending'): ?>
                                <a class="action-btn edit" title="Approve"
                                   href="<?php echo app_url('leave/action.php?id=' . (int) $r['id'] . '&do=approve&' . $qsBase); ?>"
                                   onclick="return confirm('Approve this leave? Balance will be deducted.');">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                                <a class="action-btn delete" title="Reject"
                                   href="<?php echo app_url('leave/action.php?id=' . (int) $r['id'] . '&do=reject&' . $qsBase); ?>"
                                   onclick="return confirm('Reject this leave request?');">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                            <?php elseif ($canAct && $r['status'] === 'Approved'): ?>
                                <a class="action-btn delete" title="Cancel approved"
                                   href="<?php echo app_url('leave/action.php?id=' . (int) $r['id'] . '&do=cancel&' . $qsBase); ?>"
                                   onclick="return confirm('Cancel approved leave and restore balance?');">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php if ($toast): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>('<?php echo addslashes($toast); ?>');
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
