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
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

$deptId = (int) ($_GET['department_id'] ?? 0);
requirePayrollRegisterAccess($deptId);
ensurePayrollApprovalTables();
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$type = (string) ($_GET['type'] ?? 'all');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
// Always show Normal + Jobwork together on NEFT sheet
$type = 'all';
$types = ['all' => 'All Employees (Normal + Jobwork)'];
$data = getCombinedNeftSheetData($month, $year, $deptId);
$neftStatus = $data ? (string) ($data['lock']['approval_status'] ?? 'approved') : '';
$neftFinal = in_array($neftStatus, ['approved', 'draft_live'], true);
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$nfpsCfg = neftNfpsSettings();
$creditNarrPreview = 'SALARY ' . strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year))) . ' ' . $year;
$payDatePreview = '';
if ($data && !empty($data['lock'])) {
    $payRaw = (string) ($data['lock']['approved_at'] ?? $data['lock']['locked_at'] ?? '');
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $payRaw, $pm)) {
        $payDatePreview = $pm[3] . '-' . $pm[2] . '-' . $pm[1];
    }
}
if ($payDatePreview === '') {
    $payDatePreview = date('d-m-Y');
}
$missingBankCount = (int) ($data['missing_bank_count'] ?? 0);
$readyBankCount = $data ? max(0, count($data['rows'] ?? []) - $missingBankCount) : 0;
$normalCount = (int) ($data['normal_count'] ?? 0);
$jobworkCount = (int) ($data['jobwork_count'] ?? 0);
if ($data && $normalCount === 0 && $jobworkCount === 0) {
    foreach ($data['rows'] ?? [] as $rr) {
        if (($rr['register_kind'] ?? '') === 'Jobwork') {
            $jobworkCount++;
        } else {
            $normalCount++;
        }
    }
}

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
            <?php if ($data && $neftFinal): ?>
                <a class="btn-secondary" href="<?php echo app_url('payroll/neft_excel.php?' . $qs); ?>">
                    <i class="fa-solid fa-file-excel"></i> NFPS Excel (Bank Upload)
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
                <p><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?> · NFPS bank format</p>
            </div>
        </div>

        <p class="form-hint" style="margin-bottom:12px;">
            Excel format: <strong>PYMT_PROD_TYPE_CODE / PYMT_MODE / DEBIT_ACC_NO</strong> fixed
            (<code><?php echo htmlspecialchars($nfpsCfg['pymt_prod_type_code']); ?></code>,
            <code><?php echo htmlspecialchars($nfpsCfg['pymt_mode']); ?></code>,
            <code><?php echo htmlspecialchars($nfpsCfg['debit_acc_no']); ?></code>).
            Name + Account + IFSC + Amount = Salary Register Net (incl. Arrears).
            Credit Narration = <code><?php echo htmlspecialchars($creditNarrPreview); ?></code>.
            Payment Date = NEFT final date (<code><?php echo htmlspecialchars($payDatePreview); ?></code>).
            Email = <code><?php echo htmlspecialchars($nfpsCfg['email_id']); ?></code>.
        </p>

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
                No salary / jobwork employees found for this month.
                Open <a href="<?php echo app_url('payroll/register.php?' . $qs); ?>">Salary Register</a>
                (Normal + Jobwork) and ensure attendance / ops qty is filled.
            </p>
        <?php else: ?>
            <?php
            $lock = $data['lock'];
            $rows = $data['rows'];
            $neftTotal = $data['neft_total'];
            $neftLabels = payrollApprovalStatusLabels();
            ?>
            <?php if (!$neftFinal): ?>
                <div class="form-page-card" style="margin-top:12px;border-left:4px solid #f59e0b;background:#fffbeb;">
                    <p style="margin:0;">
                        <i class="fa-solid fa-hourglass-half" style="color:#b45309;"></i>
                        <strong>Draft · <?php echo htmlspecialchars($neftLabels[$neftStatus] ?? $neftStatus); ?></strong>
                        — Excel / Print will be available after Accounts marks it Done.
                    </p>
                </div>
            <?php endif; ?>
            <div class="ops-live-summary" style="margin-top:12px;">
                <span class="ops-chip"><i class="fa-solid fa-lock"></i>
                    <?php echo $neftStatus === 'draft_live'
                        ? 'Live register (not locked)'
                        : ('Locked ' . htmlspecialchars(formatDateDisplay(substr((string) ($lock['locked_at'] ?? ''), 0, 10)) . ' ' . substr((string) ($lock['locked_at'] ?? ''), 11, 5))); ?>
                </span>
                <span class="ops-chip"><strong><?php echo count($rows); ?> total</strong></span>
                <span class="ops-chip">Normal <?php echo (int) $normalCount; ?></span>
                <span class="ops-chip">Jobwork <?php echo (int) $jobworkCount; ?></span>
                <span class="ops-chip"><?php echo (int) $readyBankCount; ?> bank-ready</span>
                <?php if ($missingBankCount > 0): ?>
                    <span class="ops-chip" style="color:#b45309;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo (int) $missingBankCount; ?> missing bank A/C</span>
                <?php endif; ?>
                <span class="ops-chip"><strong>Total ₹ <?php echo number_format($neftTotal, 2); ?></strong></span>
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
                    <?php if (!$rows): ?>
                        <tr><td colspan="10" class="empty-cell">No employees with Net &gt; 0 for this month. Fill attendance / Jobwork qty in Salary Register first.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $missBank = !empty($r['missing_bank']) || trim((string) ($r['account'] ?? '')) === '' || trim((string) ($r['account'] ?? '')) === '-';
                            $kind = (string) ($r['register_kind'] ?? 'Normal');
                            ?>
                            <tr<?php echo $missBank ? ' style="background:#fff7ed;"' : ''; ?>>
                                <td><?php echo (int) $r['sr']; ?></td>
                                <td><span class="ops-chip" style="margin:0;"><?php echo htmlspecialchars($kind); ?></span></td>
                                <td><strong><?php echo htmlspecialchars(neftNfpsBeneName($r['employee_name'] ?? '')); ?></strong>
                                    <div class="sr-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?>
                                        <?php if ($missBank): ?> · <span style="color:#b45309;">No bank A/C</span><?php endif; ?>
                                        <?php if (!empty($r['contractor_main_share']) && (float) $r['contractor_main_share'] > 0): ?>
                                            · <span style="color:#047857;">+ share ₹ <?php echo number_format((float) $r['contractor_main_share'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($r['department'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($missBank ? '—' : $r['account']); ?></td>
                                <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                                <td class="num"><strong><?php echo number_format((float) $r['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($creditNarrPreview); ?></td>
                                <td><?php echo htmlspecialchars($payDatePreview); ?></td>
                                <td><?php echo htmlspecialchars($nfpsCfg['email_id']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($rows): ?>
                    <tfoot>
                        <tr>
                            <td colspan="6"><strong>Final NEFT Total (Normal + Jobwork)</strong></td>
                            <td class="num"><strong><?php echo number_format($neftTotal, 2); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
