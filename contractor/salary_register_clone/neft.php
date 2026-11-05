<?php
/**
 * CNC Clone NEFT — same bank-sheet columns as the payroll NEFT.
 * Amount is the clone register Net (Gross minus PT). Does not read the payroll NEFT.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/data.php';

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$pageTitle = 'Clone NEFT';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_salary_clone_neft';
require_once __DIR__ . '/../../includes/header.php';

$bundle = cncCloneRegisterBundle($month, $year);
$neftRows = cncCloneNeftRows($bundle['rows']);
$sec = cncCloneNeftSummary($neftRows);
$export = cncCloneNfpsExport($neftRows, $month, $year);
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';
$qs = http_build_query(['month' => $month, 'year' => $year]);
$missingBank = (int) $sec['missing_bank'];
$readyBank = max(0, count($neftRows) - $missingBank);
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/salary_register_clone/index.php?' . $qs); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Clone Salary Register
        </a>
        <?php if ($neftRows): ?>
        <div class="toolbar-actions">
            <a class="btn-secondary" href="<?php echo app_url('contractor/salary_register_clone/neft_excel.php?' . $qs); ?>">
                <i class="fa-solid fa-file-excel"></i> NFPS Excel
            </a>
            <a class="btn-primary" href="<?php echo app_url('contractor/salary_register_clone/neft_print.php?' . $qs); ?>" target="_blank">
                <i class="fa-solid fa-print"></i> Print
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Clone NEFT / Bank Transfer Sheet</h1>
                <p><?php echo htmlspecialchars($companyName); ?> · CNC · <?php echo htmlspecialchars($monthLabel); ?></p>
            </div>
        </div>
        <p class="form-hint">
            Amount is the Clone Salary Register Net. Format:
            <code>PYMT_PROD_TYPE_CODE</code>=<?php echo htmlspecialchars($export['cfg']['pymt_prod_type_code']); ?>,
            <code>PYMT_MODE</code>=<?php echo htmlspecialchars($export['cfg']['pymt_mode']); ?>,
            <code>DEBIT_ACC_NO</code>=<?php echo htmlspecialchars($export['cfg']['debit_acc_no']); ?>,
            CREDIT_NARR=<code><?php echo htmlspecialchars($export['credit_narr']); ?></code>,
            PYMT_DATE=<code><?php echo htmlspecialchars($export['pay_date']); ?></code>,
            EMAIL=<code><?php echo htmlspecialchars($export['cfg']['email_id']); ?></code>.
            Payroll NEFT is not used.
        </p>
        <form method="GET" class="employee-form">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" value="CNC" readonly>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo (int) $year; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>

        <?php if (!empty($bundle['before_nov'])): ?>
            <p class="form-hint">Clone NEFT starts from November 2026.</p>
        <?php elseif ((int) $bundle['main_id'] <= 0): ?>
            <p class="form-hint">CNC main contractor was not found.</p>
        <?php elseif (!$neftRows): ?>
            <p class="form-hint">No clone net amounts for this month.</p>
        <?php else: ?>
            <div class="ops-live-summary" style="margin-top:12px;">
                <span class="ops-chip"><strong><?php echo count($neftRows); ?> total</strong></span>
                <span class="ops-chip">Normal <?php echo (int) $sec['normal_count']; ?> · ₹ <?php echo cncCloneNum($sec['normal_total'], 2); ?></span>
                <span class="ops-chip">Jobwork <?php echo (int) $sec['jobwork_count']; ?> · ₹ <?php echo cncCloneNum($sec['jobwork_total'], 2); ?></span>
                <span class="ops-chip"><?php echo (int) $readyBank; ?> bank-ready</span>
                <?php if ($missingBank > 0): ?>
                    <span class="ops-chip" style="color:#b45309;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo (int) $missingBank; ?> missing bank A/C</span>
                <?php endif; ?>
                <span class="ops-chip"><strong>Final ₹ <?php echo cncCloneNum($sec['final_total'], 2); ?></strong></span>
            </div>
            <div class="table-wrap" style="margin-top:14px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sr</th>
                            <th>Type</th>
                            <th>BNF_NAME</th>
                            <th>Department</th>
                            <th>BENE_ACC_NO</th>
                            <th>BENE_IFSC</th>
                            <th class="num">AMOUNT</th>
                            <th>CREDIT_NARR</th>
                            <th>PYMT_DATE</th>
                            <th>EMAIL_ID</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($neftRows as $r): ?>
                        <?php $missBank = !empty($r['missing_bank']); ?>
                        <tr<?php echo $missBank ? ' style="background:#fff7ed;"' : ''; ?>>
                            <td><?php echo (int) $r['sr']; ?></td>
                            <td><span class="ops-chip" style="margin:0;"><?php echo htmlspecialchars($r['register_kind']); ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars(cncCloneBeneName($r['employee_name'])); ?></strong>
                                <div class="sr-code"><?php echo htmlspecialchars($r['employee_code']); ?><?php echo $missBank ? ' · <span style="color:#b45309;">No bank A/C</span>' : ''; ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($r['department']); ?></td>
                            <td><?php echo htmlspecialchars($missBank ? '—' : $r['account']); ?></td>
                            <td><?php echo htmlspecialchars($r['ifsc'] !== '' ? $r['ifsc'] : '-'); ?></td>
                            <td class="num"><strong><?php echo cncCloneNum($r['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($export['credit_narr']); ?></td>
                            <td><?php echo htmlspecialchars($export['pay_date']); ?></td>
                            <td><?php echo htmlspecialchars($export['cfg']['email_id']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#ecfdf5;">
                            <td colspan="6"><strong>Normal Salary Total (<?php echo (int) $sec['normal_count']; ?>)</strong></td>
                            <td class="num"><strong><?php echo cncCloneNum($sec['normal_total'], 2); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                        <tr style="background:#eff6ff;">
                            <td colspan="6"><strong>Jobwork Total (<?php echo (int) $sec['jobwork_count']; ?>)</strong></td>
                            <td class="num"><strong><?php echo cncCloneNum($sec['jobwork_total'], 2); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                        <tr style="background:#fef3c7;">
                            <td colspan="6"><strong>Final NEFT Total (Normal + Jobwork)</strong></td>
                            <td class="num"><strong><?php echo cncCloneNum($sec['final_total'], 2); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
