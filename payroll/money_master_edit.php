<?php
/**
 * Add / edit one employee Advance or Loan.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/advance_loan_helper.php';
require_once __DIR__ . '/../includes/date_helper.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

$kind = advanceLoanNormalizeKind($_GET['type'] ?? 'Advance');
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
requireAccess('payroll', 'view', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$conn = getDBConnection();
$row = $id > 0 ? advanceLoanGet($conn, $id) : null;
if ($row && ((int) $row['department_id'] !== $deptId || $row['kind'] !== $kind)) {
    $row = null;
    $id = 0;
}
$employees = advanceLoanEmployees($conn, $deptId, $row ? (int) $row['employee_id'] : 0);
$conn->close();

$isLoan = $kind === 'Loan';
$pageTitle = ($id > 0 ? 'Edit ' : 'Add ') . ($isLoan ? 'Loan' : 'Advance');
$listUrl = app_url('payroll/money_master.php?type=' . strtolower($kind) . '&department_id=' . $deptId);
$icon = $isLoan ? 'fa-landmark' : 'fa-hand-holding-dollar';
$iconColor = $isLoan ? '#7C2D12' : '#B45309';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = $isLoan ? 'loan_master' : 'advance_master';

$txnDate = dateInputValue($row['txn_date'] ?? date('Y-m-d'));
$amount = $row ? (float) $row['amount'] : '';
$installment = $row ? (float) $row['installment_amount'] : '';
$startMonth = $row ? (int) $row['start_month'] : (int) date('n');
$startYear = $row ? (int) $row['start_year'] : (int) date('Y');
$employeeId = $row ? (int) $row['employee_id'] : 0;
$status = $row['status'] ?? 'Open';
$remarks = $row['remarks'] ?? '';
$err = (string) ($_GET['err'] ?? '');
$errMap = [
    'employee' => 'Employee not found in this department.',
    'invalid' => 'Enter a valid date and amount.',
    'installment' => 'Monthly deduction cannot be more than the amount.',
];
$toastMsg = $errMap[$err] ?? '';
$toastType = 'error';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo htmlspecialchars($listUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to <?php echo $isLoan ? 'Loan' : 'Advance'; ?> Master
        </a>
    </div>

    <div class="list-header">
        <div class="master-list-title">
            <div class="master-list-icon" style="background: <?php echo htmlspecialchars($iconColor); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($icon); ?>"></i>
            </div>
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p><?php echo htmlspecialchars($department['department_name']); ?> · Employee wise</p>
            </div>
        </div>
    </div>

    <div class="form-page-card">

        <form method="post" action="<?php echo app_url('payroll/money_master_save.php'); ?>" class="employee-form">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="type" value="<?php echo strtolower($kind); ?>">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">

            <div class="form-section">
                <h3><i class="fa-solid fa-user"></i> Employee</h3>
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label>Employee *</label>
                        <select name="employee_id" class="form-control" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $opt): ?>
                                <option value="<?php echo (int) $opt['id']; ?>" <?php echo $employeeId === (int) $opt['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['employee_code'] . ' — ' . $opt['employee_name']); ?>
                                    <?php echo (int) $opt['status'] !== 1 ? ' (Inactive)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-indian-rupee-sign"></i> <?php echo $isLoan ? 'Loan' : 'Advance'; ?></h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label><?php echo $isLoan ? 'Loan date' : 'Advance date'; ?> *</label>
                        <input type="date" name="txn_date" class="form-control" required value="<?php echo htmlspecialchars($txnDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" class="form-control" required min="0.01" step="0.01" value="<?php echo htmlspecialchars((string) $amount); ?>">
                        <?php if (!$isLoan): ?>
                            <small class="form-hint">Full amount is deducted in this month's salary.</small>
                        <?php endif; ?>
                    </div>
                    <?php if ($isLoan): ?>
                    <div class="form-group">
                        <label>Monthly deduction</label>
                        <input type="number" name="installment_amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $installment); ?>">
                        <small class="form-hint">Monthly amount cut on the salary register from the start month. 0 = full amount in the start month.</small>
                    </div>
                    <div class="form-group">
                        <label>Deduction starts *</label>
                        <select name="start_month" class="form-control" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m === $startMonth ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1, 2026)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year *</label>
                        <select name="start_year" class="form-control" required>
                            <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 3; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $startYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Open" <?php echo $status === 'Open' ? 'selected' : ''; ?>>Open</option>
                            <option value="Closed" <?php echo $status === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($remarks); ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="<?php echo htmlspecialchars($listUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
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
