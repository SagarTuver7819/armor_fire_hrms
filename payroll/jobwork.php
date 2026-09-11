<?php
/**
 * Jobwork production entries (manual) + Operations Rate List (department wise)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

ensurePayrollTables();
$conn = getDBConnection();
ensureEmployeesTable($conn);

$emps = [];
$st = $conn->prepare(
    "SELECT id, employee_code, employee_name FROM employees
     WHERE status = 1 AND department_id = ? AND pay_type = 'Jobwork'
     ORDER BY employee_code ASC"
);
$st->bind_param('i', $deptId);
$st->execute();
$emps = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
$conn->close();

$list = getDepartmentJobworkList($deptId, $month, $year);
$products = getActiveMasterRows('products', 'product_name ASC');
$monthLabel = date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$listTotal = 0.0;
foreach ($list as $row) {
    $listTotal += (float) ($row['amount'] ?? 0);
}

$pageTitle = 'Jobwork Entry';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'jobwork';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Modules
        </a>
        <a href="<?php echo app_url('contractor/operations/index.php'); ?>" class="btn-secondary">
            <i class="fa-solid fa-table"></i> Operations Rate List
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Jobwork Entry</h1>
                <p><?php echo htmlspecialchars($department['department_name']); ?> · Manual entries + Operations Rate List (Qty × Rate)</p>
            </div>
        </div>

        <form method="GET" action="<?php echo app_url('payroll/jobwork.php'); ?>" class="employee-form" style="margin-bottom:18px;">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $m, 1))); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="form-hint" style="padding-top:10px;">
                        Showing <strong><?php echo htmlspecialchars($monthLabel); ?></strong>
                        · Total ₹ <?php echo number_format($listTotal, 2); ?>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST" action="<?php echo app_url('payroll/jobwork_save.php'); ?>" class="employee-form">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($emps as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>">
                                <?php echo htmlspecialchars($e['employee_code'] . ' — ' . $e['employee_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$emps): ?>
                        <small class="form-hint">No Jobwork employees. Set Pay Type = Jobwork on employee form.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Date <small>(DD-MM-YYYY)</small></label>
                    <input type="text" name="work_date" class="form-control js-date" required placeholder="DD-MM-YYYY"
                           value="<?php echo htmlspecialchars(dateInputValue(date('Y-m-d'))); ?>">
                </div>
                <div class="form-group">
                    <label>Item / Product</label>
                    <select name="item_name" class="form-control">
                        <option value="">-- Optional --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['product_name']); ?>">
                                <?php echo htmlspecialchars($p['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" step="0.001" name="quantity" id="jwQty" class="form-control" required value="0">
                </div>
                <div class="form-group">
                    <label>Rate</label>
                    <input type="number" step="0.01" name="rate" id="jwRate" class="form-control" required value="0">
                </div>
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" step="0.01" name="amount" id="jwAmt" class="form-control" readonly value="0">
                </div>
                <div class="form-group span-2">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-control">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary" <?php echo !$emps ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-plus"></i> Add Entry
                </button>
            </div>
        </form>

        <div class="table-wrap" style="margin-top:20px;">
            <table class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Item</th>
                        <th>Source</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$list): ?>
                    <tr><td colspan="8">No jobwork / Operations Rate List entries for <?php echo htmlspecialchars($monthLabel); ?>.</td></tr>
                <?php endif; ?>
                <?php foreach ($list as $row): ?>
                    <?php $isOps = ($row['source'] ?? '') === 'operations'; ?>
                    <tr>
                        <td><?php echo htmlspecialchars(formatDateDisplay($row['work_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['employee_code'] . ' — ' . $row['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name'] ?: '-'); ?></td>
                        <td>
                            <?php if ($isOps): ?>
                                <span class="pay-pill is-jobwork" title="From Operations Rate List">Ops Rate List</span>
                            <?php else: ?>
                                <span class="pay-pill is-salary">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $row['quantity']); ?></td>
                        <td><?php echo htmlspecialchars((string) $row['rate']); ?></td>
                        <td><?php echo htmlspecialchars(number_format((float) $row['amount'], 2, '.', '')); ?></td>
                        <td>
                            <?php if ($isOps && !empty($row['sheet_id'])): ?>
                                <a class="action-btn edit" title="Open Operations Rate List"
                                   href="<?php echo app_url('contractor/operations/edit.php?id=' . (int) $row['sheet_id']); ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            <?php elseif (!$isOps && !empty($row['id'])): ?>
                                <a class="action-btn delete btn-delete" data-name="this entry"
                                   href="<?php echo app_url('payroll/jobwork_delete.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
    (function () {
        var q = document.getElementById('jwQty');
        var r = document.getElementById('jwRate');
        var a = document.getElementById('jwAmt');
        function calc() {
            var qty = parseFloat(q.value || '0');
            var rate = parseFloat(r.value || '0');
            a.value = (qty * rate).toFixed(2);
        }
        if (q && r && a) {
            q.addEventListener('input', calc);
            r.addEventListener('input', calc);
        }
    })();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
