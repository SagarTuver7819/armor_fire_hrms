<?php
/**
 * Jobwork production entries
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

$from = date('Y-m-01');
$to = date('Y-m-t');
$list = [];
$lq = $conn->prepare(
    "SELECT j.*, e.employee_code, e.employee_name
     FROM jobwork_entries j
     INNER JOIN employees e ON e.id = j.employee_id
     WHERE e.department_id = ? AND j.work_date BETWEEN ? AND ?
     ORDER BY j.work_date DESC, j.id DESC"
);
$lq->bind_param('iss', $deptId, $from, $to);
$lq->execute();
$list = $lq->get_result()->fetch_all(MYSQLI_ASSOC);
$lq->close();
$conn->close();

$products = getActiveMasterRows('products', 'product_name ASC');

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
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Jobwork Entry</h1>
                <p><?php echo htmlspecialchars($department['department_name']); ?> · Current month entries (qty × rate)</p>
            </div>
        </div>

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
                    <tr><th>Date</th><th>Employee</th><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$list): ?>
                    <tr><td colspan="7">No jobwork entries this month.</td></tr>
                <?php endif; ?>
                <?php foreach ($list as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(formatDateDisplay($row['work_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['employee_code'] . ' — ' . $row['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['item_name'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($row['rate']); ?></td>
                        <td><?php echo htmlspecialchars($row['amount']); ?></td>
                        <td>
                            <a class="action-btn delete btn-delete" data-name="this entry"
                               href="<?php echo app_url('payroll/jobwork_delete.php?id=' . (int) $row['id'] . '&department_id=' . $deptId); ?>">
                                <i class="fa-solid fa-trash"></i>
                            </a>
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
