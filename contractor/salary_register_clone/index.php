<?php
/**
 * CNC Clone Salary Register.
 * Reads only the New Operation List split. Does not load or write the payroll salary register.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';
require_once __DIR__ . '/data.php';

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$pageTitle = 'Clone Salary Register';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_salary_clone';
require_once __DIR__ . '/../../includes/header.php';

$dept = strtoupper(trim((string) ($_GET['dept'] ?? 'ALL')));
if (!in_array($dept, ['ALL', 'CNC', 'BUFF'], true)) {
    $dept = 'ALL';
}
$ops = $dept === 'ALL' ? ['CNC', 'BUFF'] : [$dept];
$bundles = [];
foreach ($ops as $opName) {
    $bundles[$opName] = cncCloneRegisterBundle($month, $year, $opName);
}
$beforeNov = !empty($bundles[$ops[0]]['before_nov']);
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$neftSource = [];
foreach ($bundles as $opName => $one) {
    if ($beforeNov || (int) ($one['main_id'] ?? 0) <= 0) {
        continue;
    }
    foreach ($one['rows'] as $r) {
        $r['department'] = $opName;
        $neftSource[] = $r;
    }
}
$neftRows = $neftSource ? cncCloneNeftRows($neftSource) : [];
$neftSec = cncCloneNeftSummary($neftRows);
$neftExport = cncCloneNfpsExport($neftRows, $month, $year);
$neftQs = http_build_query(['month' => $month, 'year' => $year, 'dept' => $dept]);
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <?php if ($neftRows): ?>
        <div class="toolbar-actions">
            <a class="btn-secondary" href="#cncNeftSheet"><i class="fa-solid fa-check"></i> Check</a>
            <a class="btn-secondary" href="<?php echo app_url('contractor/salary_register_clone/neft_excel.php?' . $neftQs); ?>"><i class="fa-solid fa-download"></i> Download</a>
            <a class="btn-primary" href="<?php echo app_url('contractor/salary_register_clone/neft_print.php?' . $neftQs); ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Print PDF</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Clone Salary Register</h1>
                <p>CNC and BUFF · New Operation List only. Main Salary Register is not used.</p>
            </div>
        </div>
        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="dept" class="form-control" onchange="this.form.submit()">
                        <option value="ALL" <?php echo $dept === 'ALL' ? 'selected' : ''; ?>>CNC + BUFF</option>
                        <option value="CNC" <?php echo $dept === 'CNC' ? 'selected' : ''; ?>>CNC</option>
                        <option value="BUFF" <?php echo $dept === 'BUFF' ? 'selected' : ''; ?>>BUFF</option>
                    </select>
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
        <?php if ($beforeNov): ?>
            <p class="form-hint">Clone Salary Register starts from November 2026.</p>
        <?php else: ?>
            <p class="form-hint">P.Days are Present marks from 01-11-2026 through today. Week-off counts only when it is P. Staff Gross follows the New Operation List final total, then Gross and Net round to the nearest rupee (50 paise and above rounds up). Gross 12,001 or more cuts P.T. 200. The main contractor is the last row: his Salary and Gross are the remainder left after the staff split. BUFF uses the Buff job-report amount (Q × Qty Rate + RW × Rework Rate + AP × Argon Polish Rate).</p>
        <?php endif; ?>
    </div>

    <?php if ($neftRows): ?>
    <div class="form-page-card" id="cncNeftSheet" style="margin-top:16px;">
        <div class="form-page-header">
            <div>
                <h1>NEFT Sheet</h1>
                <p><?php echo htmlspecialchars($dept === 'ALL' ? 'CNC + BUFF' : $dept); ?> · <?php echo htmlspecialchars($monthLabel); ?> · Amount is Clone Register Net · CREDIT_NARR <?php echo htmlspecialchars($neftExport['credit_narr']); ?> · PYMT_DATE <?php echo htmlspecialchars($neftExport['pay_date']); ?></p>
            </div>
        </div>
        <div class="ops-live-summary">
            <span class="ops-chip"><strong><?php echo count($neftRows); ?> employees · ₹ <?php echo cncCloneNum($neftSec['final_total'], 0); ?></strong></span>
            <?php if ((int) $neftSec['missing_bank'] > 0): ?>
                <span class="ops-chip" style="color:#b45309;"><?php echo (int) $neftSec['missing_bank']; ?> missing bank A/C</span>
            <?php endif; ?>
        </div>
        <div class="table-wrap" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
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
                <?php foreach ($neftRows as $nr): ?>
                    <?php $missBank = !empty($nr['missing_bank']); ?>
                    <tr<?php echo $missBank ? ' style="background:#fff7ed;"' : ''; ?>>
                        <td><?php echo (int) $nr['sr']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(cncCloneBeneName($nr['employee_name'])); ?></strong>
                            <div class="sr-code"><?php echo htmlspecialchars($nr['employee_code']); ?><?php echo $missBank ? ' · No bank A/C' : ''; ?></div>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($nr['department'] ?? 'CNC')); ?></td>
                        <td><?php echo htmlspecialchars($missBank ? '—' : $nr['account']); ?></td>
                        <td><?php echo htmlspecialchars($nr['ifsc'] !== '' ? $nr['ifsc'] : '-'); ?></td>
                        <td class="num"><strong><?php echo cncCloneNum($nr['amount'], 0); ?></strong></td>
                        <td><?php echo htmlspecialchars($neftExport['credit_narr']); ?></td>
                        <td><?php echo htmlspecialchars($neftExport['pay_date']); ?></td>
                        <td><?php echo htmlspecialchars($neftExport['cfg']['email_id']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#fef3c7;">
                        <td colspan="5"><strong>NEFT Total (<?php echo count($neftRows); ?>)</strong></td>
                        <td class="num"><strong><?php echo cncCloneNum($neftSec['final_total'], 0); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($bundles as $opName => $bundle): ?>
    <?php
        $rows = $bundle['rows'];
        $mainId = (int) $bundle['main_id'];
        $mainLabel = (string) $bundle['main_label'];
        if ($beforeNov || $mainId <= 0) {
            continue;
        }
        $sumSalary = 0.0;
        $sumGross = 0.0;
        $sumPt = 0.0;
        $sumLoan = 0.0;
        $sumNet = 0.0;
        foreach ($rows as $r) {
            $sumSalary += $r['salary'];
            $sumGross += $r['gross'];
            $sumPt += $r['pt'];
            $sumLoan += (float) ($r['loan'] ?? 0);
            $sumNet += $r['net'];
        }
    ?>
    <div class="cnc-clone-sheet" style="margin-top:16px;">
        <div class="cnc-clone-banner">
            <strong>Salaried Person (Fixed) · <?php echo htmlspecialchars($opName); ?></strong>
            <span>New Operation List · <?php echo htmlspecialchars($monthLabel); ?></span>
        </div>
        <div class="table-wrap">
            <table class="cnc-clone-table">
                <thead>
                    <tr class="cnc-clone-group">
                        <th colspan="5"></th>
                        <th colspan="8" class="cnc-clone-earn">Earning</th>
                        <th colspan="2" class="cnc-clone-gross">Gross</th>
                        <th colspan="4" class="cnc-clone-ded">Deduction</th>
                        <th colspan="3" class="cnc-clone-net">NET</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th class="cnc-clone-sticky cnc-clone-sticky-1">Emp Code</th>
                        <th class="cnc-clone-sticky cnc-clone-sticky-2">Employee Name</th>
                        <th>Desig.</th>
                        <th>D.O.J.</th>
                        <th>UAN</th>
                        <th>Salary</th>
                        <th>P.Days</th>
                        <th>W.Off</th>
                        <th>Holiday</th>
                        <th>P.L.</th>
                        <th>S.L.</th>
                        <th>D.L.</th>
                        <th>C-Off</th>
                        <th>Total Days</th>
                        <th>Gross</th>
                        <th>Emp PF</th>
                        <th>P.T.</th>
                        <th>Loan</th>
                        <th>Adv.</th>
                        <th>Tot. Ded.</th>
                        <th>Arrears</th>
                        <th>Net</th>
                        <th>A/C No.</th>
                        <th>IFSC</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="25" class="empty-cell">No <?php echo htmlspecialchars($opName); ?> salary employees for this month.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?php echo !empty($r['is_main']) ? 'cnc-clone-main' : ''; ?>">
                        <td class="cnc-clone-sticky cnc-clone-sticky-1"><?php echo htmlspecialchars($r['code'] !== '' ? $r['code'] : '-'); ?></td>
                        <td class="cnc-clone-sticky cnc-clone-sticky-2">
                            <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                            <?php if (!empty($r['is_main'])): ?>
                                <div class="cnc-clone-code">Main Contractor</div>
                            <?php elseif ($mainLabel !== ''): ?>
                                <div class="cnc-clone-code">Under: <?php echo htmlspecialchars($mainLabel); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($r['designation'] !== '' ? $r['designation'] : '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['doj']); ?></td>
                        <td><?php echo htmlspecialchars($r['uan'] !== '' ? $r['uan'] : '-'); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['salary'], 0); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['present'], 1); ?></td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num"><?php echo cncCloneNum($r['total_days'], 1); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['gross'], 0); ?></td>
                        <td class="num">0</td>
                        <td class="num"><?php echo cncCloneNum($r['pt'], 0); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['loan'] ?? 0, 0); ?></td>
                        <td class="num">0</td>
                        <td class="num"><?php echo cncCloneNum($r['deduction'], 0); ?></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($r['net'], 0); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['account'] !== '' ? $r['account'] : '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['ifsc'] !== '' ? $r['ifsc'] : '-'); ?></td>
                        <td>-</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr>
                        <td colspan="5"><strong>Department Total</strong></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumSalary, 0); ?></strong></td>
                        <td colspan="8"></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumGross, 0); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumPt, 0); ?></strong></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumLoan, 0); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumPt + $sumLoan, 0); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumNet, 0); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
