<?php
/**
 * Employee-wise Leave Balance
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();
ensureLeaveTables();

$deptId = (int) ($_GET['department_id'] ?? 0);
$year = (int) ($_GET['year'] ?? date('Y'));
$employeeId = (int) ($_GET['employee_id'] ?? 0);
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
$employees = leaveEmployeesForSelect($deptId);

if ($employeeId > 0) {
    // Ensure rows exist for viewing
    $conn = getDBConnection();
    foreach (getActiveLeaveTypes($conn) as $lt) {
        leaveEnsureBalanceRow($conn, $employeeId, (int) $lt['id'], $year);
    }
    $conn->close();
    $rows = getEmployeeLeaveBalances($employeeId, $year);
    $emp = getEmployeeById($employeeId);
} else {
    $rows = getDepartmentLeaveBalanceRows($deptId, $year);
    $emp = null;
}

$pageTitle = 'Leave Balance';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'leave_balance';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'allocated') {
    $toast = 'Yearly balances allocated from Leave Master ('
        . (int) ($_GET['created'] ?? 0) . ' created, '
        . (int) ($_GET['updated'] ?? 0) . ' updated).';
}
if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
    $toast = 'Leave balance updated.';
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('leave/index.php?department_id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Leave Requests
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="POST" action="<?php echo app_url('leave/allocate.php'); ?>" style="display:inline;"
                  onsubmit="return confirm('Allocate Leave Master yearly days to employees for <?php echo $year; ?>? Existing used days are kept.');">
                <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <button type="submit" class="btn-secondary">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Allocate Year <?php echo $year; ?>
                </button>
            </form>
            <a href="<?php echo app_url('leave/apply.php?department_id=' . $deptId); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Apply Leave
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Leave Balance</h1>
                <p>
                    Employee-wise · Year <?php echo $year; ?>
                    <?php if ($department): ?> · <?php echo htmlspecialchars($department['department_name']); ?><?php endif; ?>
                    · Opening from Leave Master (CL/SL/EL…)
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
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo $year; ?>" onchange="this.form.submit()">
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All employees (summary)</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>" <?php echo $employeeId === (int) $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <?php if ($employeeId > 0 && $emp): ?>
            <h3 style="margin:0 0 12px;">
                <?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? '')); ?>
            </h3>
            <form method="POST" action="<?php echo app_url('leave/balance_save.php'); ?>">
                <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
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
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars(($r['code'] ? $r['code'] . ' · ' : '') . $r['leave_type']); ?></strong>
                                    <input type="hidden" name="balance_id[]" value="<?php echo (int) $r['id']; ?>">
                                </td>
                                <td><?php echo htmlspecialchars($r['is_paid'] ?? 'Yes'); ?></td>
                                <td><input type="number" step="0.5" min="0" name="opening_days[]" class="form-control" value="<?php echo htmlspecialchars((string) $r['opening_days']); ?>"></td>
                                <td><input type="number" step="0.5" name="credited_days[]" class="form-control" value="<?php echo htmlspecialchars((string) $r['credited_days']); ?>"></td>
                                <td><input type="number" step="0.5" name="adjusted_days[]" class="form-control" value="<?php echo htmlspecialchars((string) $r['adjusted_days']); ?>"></td>
                                <td><?php echo number_format((float) $r['used_days'], 1); ?></td>
                                <td><strong><?php echo number_format((float) $r['remaining_days'], 1); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-actions sticky-actions">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> Save Balance</button>
                </div>
            </form>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Dept</th>
                            <th>Leave</th>
                            <th>Opening</th>
                            <th>Used</th>
                            <th>Remaining</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="empty-cell">No employees / leave types. Use Allocate Year first.</td></tr>
                    <?php endif; ?>
                    <?php
                    $lastEmp = 0;
                    foreach ($rows as $r):
                        $showEmp = ((int) $r['employee_id'] !== $lastEmp);
                        $lastEmp = (int) $r['employee_id'];
                    ?>
                        <tr>
                            <td>
                                <?php if ($showEmp): ?>
                                    <strong><?php echo htmlspecialchars($r['employee_name']); ?></strong>
                                    <div class="sr-code"><?php echo htmlspecialchars($r['employee_code']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $showEmp ? htmlspecialchars($r['department_name'] ?? '-') : ''; ?></td>
                            <td><?php echo htmlspecialchars(($r['code'] ? $r['code'] . ' · ' : '') . $r['leave_type']); ?></td>
                            <td><?php echo number_format((float) $r['opening_days'], 1); ?></td>
                            <td><?php echo number_format((float) $r['used_days'], 1); ?></td>
                            <td><strong><?php echo number_format((float) $r['remaining_days'], 1); ?></strong></td>
                            <td>
                                <?php if ($showEmp): ?>
                                    <a class="action-btn edit" title="Edit balance"
                                       href="<?php echo app_url('leave/balance.php?' . http_build_query([
                                           'department_id' => $deptId,
                                           'year' => $year,
                                           'employee_id' => (int) $r['employee_id'],
                                       ])); ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php if ($toast): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr.success('<?php echo addslashes($toast); ?>');
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
