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

$bundle = cncCloneRegisterBundle($month, $year);
$beforeNov = !empty($bundle['before_nov']);
$mainId = (int) $bundle['main_id'];
$mainLabel = (string) $bundle['main_label'];
$rows = $bundle['rows'];

$sumSalary = 0.0;
$sumGross = 0.0;
$sumPt = 0.0;
$sumNet = 0.0;
foreach ($rows as $r) {
    $sumSalary += $r['salary'];
    $sumGross += $r['gross'];
    $sumPt += $r['pt'];
    $sumNet += $r['net'];
}
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
?>
<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('contractor/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Contractor Hub</a>
        <?php if (!$beforeNov && $mainId > 0 && $rows): ?>
        <div class="toolbar-actions">
            <a class="btn-primary" href="<?php echo app_url('contractor/salary_register_clone/neft.php?' . http_build_query(['month' => $month, 'year' => $year])); ?>">
                <i class="fa-solid fa-building-columns"></i> Clone NEFT
            </a>
        </div>
        <?php endif; ?>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Clone Salary Register</h1>
                <p>CNC · New Operation List only. Main Salary Register is not used.</p>
            </div>
        </div>
        <form method="GET" class="employee-form register-filters">
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
        <?php if ($beforeNov): ?>
            <p class="form-hint">Clone Salary Register starts from November 2026.</p>
        <?php elseif ($mainId <= 0): ?>
            <p class="form-hint">CNC main contractor was not found.</p>
        <?php else: ?>
            <p class="form-hint">P.Days are Present marks from 01-11-2026 through today. Week-off counts only when it is P. Staff Gross follows the New Operation List final total. Gross 12,001 or more cuts P.T. 200, and Net is Gross minus that P.T. Main contractor <?php echo htmlspecialchars($mainLabel); ?> is the last row: his Salary and Gross are the remainder left after the staff split.</p>
        <?php endif; ?>
    </div>

    <?php if (!$beforeNov && $mainId > 0): ?>
    <div class="cnc-clone-sheet">
        <div class="cnc-clone-banner">
            <strong>Salaried Person (Fixed) · CNC</strong>
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
                    <tr><td colspan="25" class="empty-cell">No CNC salary employees for this month.</td></tr>
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
                        <td class="num"><?php echo cncCloneNum($r['salary'], !empty($r['is_main']) ? 2 : 0); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['present'], 1); ?></td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num">0.0</td>
                        <td class="num"><?php echo cncCloneNum($r['total_days'], 1); ?></td>
                        <td class="num"><?php echo cncCloneNum($r['gross'], 2); ?></td>
                        <td class="num">0</td>
                        <td class="num"><?php echo cncCloneNum($r['pt'], 0); ?></td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num"><?php echo cncCloneNum($r['deduction'], 0); ?></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($r['net'], 2); ?></strong></td>
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
                        <td class="num"><strong><?php echo cncCloneNum($sumSalary, 2); ?></strong></td>
                        <td colspan="8"></td>
                        <td class="num"><strong><?php echo cncCloneNum($sumGross, 2); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumPt, 0); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumPt, 0); ?></strong></td>
                        <td class="num">0</td>
                        <td class="num"><strong><?php echo cncCloneNum($sumNet, 2); ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
