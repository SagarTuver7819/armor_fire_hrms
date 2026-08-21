<?php
/**
 * Generate monthly salary from diary (Salary) or jobwork (Jobwork)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensurePayrollTables();
$conn = getDBConnection();
ensureEmployeesTable($conn);

$stmt = $conn->prepare(
    "SELECT e.*, p.net_salary, p.earnings, p.deductions, p.generated_from, p.pay_type AS slip_pay
     FROM employees e
     LEFT JOIN salary_payslips p
       ON p.employee_id = e.id AND p.month_no = ? AND p.year_no = ?
     WHERE e.status = 1 AND e.department_id = ?
     ORDER BY e.employee_code ASC"
);
$stmt->bind_param('iii', $month, $year, $deptId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$pageTitle = 'Generate Salary';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'generate';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'generated') {
    $toast = 'Salary generated for this month.';
}
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
                <h1>Generate Salary</h1>
                <p><?php echo htmlspecialchars($department['department_name']); ?> · Uses attendance/diary days · Gross = Salary × Total Days ÷ Month Days · PF/PT included</p>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:12px;">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
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
                    <input type="number" name="year" class="form-control" value="<?php echo $year; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>

        <form method="POST" action="<?php echo app_url('payroll/generate_save.php'); ?>">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <div class="form-actions" style="margin-bottom:16px;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-calculator"></i> Generate / Recalculate
                </button>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Earnings</th>
                        <th>Deductions</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7">No employees in this department.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['employee_code']); ?></td>
                        <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
                        <td>
                            <span class="pay-pill <?php echo payTypeCssClass($r['pay_type'] ?? 'Salary'); ?>">
                                <?php echo htmlspecialchars(payTypeLabel($r['pay_type'] ?? 'Salary')); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($r['generated_from'] ?: '-'); ?></td>
                        <td><?php echo $r['net_salary'] === null ? '-' : moneyInr($r['earnings']); ?></td>
                        <td><?php echo $r['net_salary'] === null ? '-' : moneyInr($r['deductions']); ?></td>
                        <td><strong><?php echo $r['net_salary'] === null ? '-' : moneyInr($r['net_salary']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>
    window.EMP_TOAST_MSG = <?php echo json_encode($toast); ?>;
</script>
<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
    'assets/js/employees.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
