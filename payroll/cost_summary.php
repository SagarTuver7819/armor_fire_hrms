<?php
/**
 * Department-wise monthly cost + full company final summary
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();
require_once __DIR__ . '/../includes/permission_helper.php';

$deptId = (int) ($_GET['department_id'] ?? 0);
requireAccess('payroll', 'view', $deptId);
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$data = getDepartmentMonthlyCostSummary($month, $year, $deptId);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$sum = $data['summary'];

$pageTitle = 'Department Cost Summary';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'dept_cost_summary';

$qs = http_build_query([
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
]);

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <?php if ($deptId > 0): ?>
            <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Modules
            </a>
        <?php else: ?>
            <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
        <a class="btn-secondary" href="<?php echo app_url('payroll/cost_summary_excel.php?' . $qs); ?>">
            <i class="fa-solid fa-file-excel"></i> Excel
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Department Monthly Cost</h1>
                <p><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?> · Salary + Jobwork Govt + Jobwork Actual</p>
            </div>
        </div>

        <form method="GET" class="employee-form">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All departments (full summary)</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
    </div>

    <div class="form-page-card" style="margin-top:16px;">
        <div class="form-page-header">
            <div><h2 style="margin:0;font-size:1.15rem;">Department-wise Cost</h2></div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th class="num">Employees</th>
                        <th class="num">Fixed Gross</th>
                        <th class="num">JW Govt</th>
                        <th class="num">JW Actual</th>
                        <th class="num">Total Gross</th>
                        <th class="num">PF</th>
                        <th class="num">PT</th>
                        <th class="num">Loan+Adv</th>
                        <th class="num">Deductions</th>
                        <th class="num">Net Cost</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="11" class="empty-cell">No payroll cost for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($data['rows'] as $r): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($r['department']); ?></strong></td>
                            <td class="num"><?php echo (int) $r['employees']; ?></td>
                            <td class="num"><?php echo number_format($r['by_type']['salary']['gross'], 2); ?></td>
                            <td class="num"><?php echo number_format($r['by_type']['jobwork_govt']['gross'], 2); ?></td>
                            <td class="num"><?php echo number_format($r['by_type']['jobwork_actual']['gross'], 2); ?></td>
                            <td class="num"><strong><?php echo number_format($r['gross'], 2); ?></strong></td>
                            <td class="num"><?php echo number_format($r['pf'], 2); ?></td>
                            <td class="num"><?php echo number_format($r['pt'], 2); ?></td>
                            <td class="num"><?php echo number_format($r['loan'] + $r['advance'], 2); ?></td>
                            <td class="num"><?php echo number_format($r['deduction'], 2); ?></td>
                            <td class="num"><strong><?php echo number_format($r['net'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-page-card" style="margin-top:16px;border:2px solid #1e3a5f;">
        <div class="form-page-header">
            <div>
                <h2 style="margin:0;font-size:1.2rem;"><i class="fa-solid fa-file-invoice-dollar"></i> Full Final Summary</h2>
                <p style="margin:4px 0 0;"><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?></p>
            </div>
        </div>
        <div class="ops-live-summary" style="margin:0 0 16px;">
            <span class="ops-chip"><?php echo (int) $sum['departments']; ?> Departments</span>
            <span class="ops-chip"><?php echo (int) $sum['employees']; ?> Employees</span>
            <span class="ops-chip">Gross ₹ <?php echo number_format($sum['gross'], 2); ?></span>
            <span class="ops-chip">Deductions ₹ <?php echo number_format($sum['deduction'], 2); ?></span>
            <span class="ops-chip"><strong>Net Payable ₹ <?php echo number_format($sum['net'], 2); ?></strong></span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <tbody>
                    <tr><th>Total Gross Earnings</th><td class="num"><strong>₹ <?php echo number_format($sum['gross'], 2); ?></strong></td></tr>
                    <tr><th>Employee PF</th><td class="num">₹ <?php echo number_format($sum['pf'], 2); ?></td></tr>
                    <tr><th>Professional Tax</th><td class="num">₹ <?php echo number_format($sum['pt'], 2); ?></td></tr>
                    <tr><th>Loan</th><td class="num">₹ <?php echo number_format($sum['loan'], 2); ?></td></tr>
                    <tr><th>Advance</th><td class="num">₹ <?php echo number_format($sum['advance'], 2); ?></td></tr>
                    <tr><th>Total Deductions</th><td class="num"><strong>₹ <?php echo number_format($sum['deduction'], 2); ?></strong></td></tr>
                    <tr><th>Salary Arrears</th><td class="num">₹ <?php echo number_format($sum['arrears'], 2); ?></td></tr>
                    <tr style="background:#eef6ff;"><th>Final Net Payable (Company Cost to Bank)</th><td class="num"><strong style="font-size:1.1rem;">₹ <?php echo number_format($sum['net'], 2); ?></strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
