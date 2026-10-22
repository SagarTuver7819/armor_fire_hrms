<?php
/**
 * Advance Master / Loan Master — employee list for one department.
 * ?type=advance|loan&department_id=
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/advance_loan_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

$kind = advanceLoanNormalizeKind($_GET['type'] ?? 'Advance');
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('payroll', 'view', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$viewMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$viewYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($viewMonth < 1 || $viewMonth > 12) {
    $viewMonth = (int) date('n');
}
if ($viewYear < 2000 || $viewYear > 2100) {
    $viewYear = (int) date('Y');
}

$conn = getDBConnection();
$rows = advanceLoanList($conn, $deptId, $kind);
$conn->close();

$isLoan = $kind === 'Loan';
$pageTitle = $isLoan ? 'Loan Master' : 'Advance Master';
$sidebarActive = $isLoan ? 'loan_master' : 'advance_master';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$msg = (string) ($_GET['msg'] ?? '');
$label = $isLoan ? 'Loan' : 'Advance';
$toastMap = [
    'added' => $label . ' added successfully.',
    'updated' => $label . ' updated successfully.',
    'saved' => $label . ' saved successfully.',
    'deleted' => $label . ' deleted successfully.',
];
$toastMsg = $toastMap[$msg] ?? '';
$toastType = $msg === 'deleted' ? 'success' : 'success';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$listUrl = app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId);
$icon = $isLoan ? 'fa-landmark' : 'fa-hand-holding-dollar';
$iconColor = $isLoan ? '#7C2D12' : '#B45309';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Modules
        </a>
        <a href="<?php echo app_url('payroll/money_master_edit.php?type=' . strtolower($kind) . '&department_id=' . $deptId); ?>" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Add <?php echo $isLoan ? 'Loan' : 'Advance'; ?>
        </a>
    </div>

    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background: <?php echo htmlspecialchars($iconColor); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($icon); ?>"></i>
            </div>
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p>
                    <?php echo htmlspecialchars($department['department_name']); ?>
                    · Employee wise
                    · <?php echo $isLoan
                        ? 'Monthly installment is deducted on the salary register'
                        : 'Full advance amount is deducted in that month\'s salary'; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="data-card data-card-pad">
        <form method="get" class="employee-form" style="margin-bottom:14px;">
            <input type="hidden" name="type" value="<?php echo strtolower($kind); ?>">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Show deduction for</label>
                    <select name="month" class="form-control">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $viewMonth ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1, 2026)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $viewYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="align-self:end;">
                    <button type="submit" class="btn-secondary">Show</button>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table money-grid">
                <thead>
                    <tr>
                        <th>Sr.</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th class="num">Amount</th>
                        <?php if ($isLoan): ?>
                            <th class="num">Monthly</th>
                            <th>Starts</th>
                            <th class="num">This month</th>
                            <th class="num">Recovered</th>
                            <th class="num">Balance</th>
                        <?php else: ?>
                            <th>Salary month</th>
                            <th class="num">Cut this month</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?php echo $isLoan ? 11 : 8; ?>" class="empty-msg">No <?php echo strtolower($kind); ?> records for this department.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                        $sch = advanceLoanSchedule(
                            $r['amount'],
                            $r['installment_amount'],
                            $r['start_month'],
                            $r['start_year'],
                            $viewMonth,
                            $viewYear,
                            $r['status']
                        );
                        $editUrl = app_url('payroll/money_master_edit.php?type=' . strtolower($kind) . '&department_id=' . $deptId . '&id=' . (int) $r['id']);
                        $delUrl = app_url('payroll/money_master_delete.php?type=' . strtolower($kind) . '&department_id=' . $deptId . '&id=' . (int) $r['id']);
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <span class="code-badge"><?php echo htmlspecialchars($r['employee_code']); ?></span>
                                <?php echo htmlspecialchars($r['employee_name']); ?>
                                <?php if (trim((string) ($r['remarks'] ?? '')) !== ''): ?>
                                    <div class="form-hint"><?php echo htmlspecialchars($r['remarks']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($r['txn_date']))); ?></td>
                            <td class="num"><?php echo number_format((float) $r['amount'], 2); ?></td>
                            <?php if ($isLoan): ?>
                                <td class="num"><?php echo (float) $r['installment_amount'] > 0 ? number_format((float) $r['installment_amount'], 2) : 'Full'; ?></td>
                                <td><?php echo htmlspecialchars(advanceLoanMonthLabel($r['start_month'], $r['start_year'])); ?></td>
                                <td class="num"><?php echo number_format($sch['this'], 2); ?></td>
                                <td class="num"><?php echo number_format($sch['recovered'], 2); ?></td>
                                <td class="num"><?php echo number_format($sch['balance'], 2); ?></td>
                            <?php else: ?>
                                <?php $cut = advanceFullMonthCut($r, $viewMonth, $viewYear); ?>
                                <td><?php echo htmlspecialchars(date('M Y', strtotime($r['txn_date']))); ?></td>
                                <td class="num"><?php echo number_format($cut, 2); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if (($r['status'] ?? '') === 'Closed'): ?>
                                    <span class="status-pill status-inactive"><i class="fa-solid fa-circle"></i> Closed</span>
                                <?php else: ?>
                                    <span class="status-pill status-active"><i class="fa-solid fa-circle"></i> Open</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-links">
                                    <a href="<?php echo htmlspecialchars($editUrl); ?>" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <a href="<?php echo htmlspecialchars($delUrl); ?>" class="action-btn delete btn-delete" data-name="<?php echo htmlspecialchars($label . ' · ' . $r['employee_name']); ?>" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    window.MONEY_TOAST_MSG = <?php echo json_encode($toastMsg); ?>;
    window.MONEY_TOAST_TYPE = <?php echo json_encode($toastType); ?>;
</script>

<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/money_toast.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
