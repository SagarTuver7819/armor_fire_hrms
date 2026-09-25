<?php
/**
 * NEFT sheet — generated when Salary Register is finalized & locked
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
$type = (string) ($_GET['type'] ?? 'salary');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if (!isset(salaryRegisterTypes()[$type])) {
    $type = 'salary';
}

$data = getNeftSheetData($month, $year, $type, $deptId);
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$types = salaryRegisterTypes();

$pageTitle = 'NEFT Sheet';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'neft_sheet';

$qs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
]);

$toast = '';
if (isset($_GET['lock_msg']) && $_GET['lock_msg'] === 'locked') {
    $toast = 'Salary Register finalized & locked. NEFT sheet ready (' . (int) ($_GET['neft_count'] ?? 0) . ' transfers).';
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('payroll/register.php?' . $qs); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Salary Register
        </a>
        <div class="toolbar-actions">
            <?php if ($data): ?>
                <a class="btn-secondary" href="<?php echo app_url('payroll/neft_excel.php?' . $qs); ?>">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </a>
                <a class="btn-primary" href="<?php echo app_url('payroll/neft_print.php?' . $qs); ?>" target="_blank">
                    <i class="fa-solid fa-print"></i> Print
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($toast): ?>
        <div class="form-page-card" style="margin-bottom:12px;border-left:4px solid #16a085;">
            <p style="margin:0;"><i class="fa-solid fa-circle-check" style="color:#16a085;"></i> <?php echo htmlspecialchars($toast); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>NEFT / Bank Transfer Sheet</h1>
                <p><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?></p>
            </div>
        </div>

        <form method="GET" class="employee-form">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Register Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($types as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $type === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

        <?php if (!$data): ?>
            <p class="form-hint" style="margin-top:16px;">
                NEFT sheet is created only after you <strong>Finalize &amp; Lock</strong> the Salary Register for this month / type.
                <a href="<?php echo app_url('payroll/register.php?' . $qs); ?>">Open Salary Register →</a>
            </p>
        <?php else: ?>
            <?php
            $lock = $data['lock'];
            $rows = $data['rows'];
            $neftTotal = $data['neft_total'];
            ?>
            <div class="ops-live-summary" style="margin-top:12px;">
                <span class="ops-chip"><i class="fa-solid fa-lock"></i> Locked <?php echo htmlspecialchars(formatDateDisplay(substr((string) $lock['locked_at'], 0, 10)) . ' ' . substr((string) $lock['locked_at'], 11, 5)); ?></span>
                <span class="ops-chip"><?php echo count($rows); ?> NEFT rows</span>
                <span class="ops-chip"><strong>Total ₹ <?php echo number_format($neftTotal, 2); ?></strong></span>
            </div>

            <div class="table-wrap" style="margin-top:14px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sr</th>
                            <th>Code</th>
                            <th>Beneficiary Name</th>
                            <th>Department</th>
                            <th>Bank</th>
                            <th>Account No.</th>
                            <th>IFSC</th>
                            <th class="num">Amount (₹)</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="empty-cell">No bank accounts found for NEFT (net &gt; 0 with account number).</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo (int) $r['sr']; ?></td>
                                <td><span class="code-badge"><?php echo htmlspecialchars($r['employee_code']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['department'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($r['bank_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($r['account']); ?></td>
                                <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                                <td class="num"><strong><?php echo number_format((float) $r['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['remarks'] ?? 'Salary'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($rows): ?>
                    <tfoot>
                        <tr>
                            <td colspan="7"><strong>Total NEFT Amount</strong></td>
                            <td class="num"><strong><?php echo number_format($neftTotal, 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
