<?php
/**
 * Employee portal — My Salary Slips (month-wise, from joining date)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireLogin();

if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !isEmployee()) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$emp = getEmployeeById($empId);
if (!$emp) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$dojRaw = trim((string) ($emp['date_of_joining'] ?? ''));
$dojYmd = '';
if ($dojRaw !== '' && $dojRaw !== '0000-00-00' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dojRaw, $m)) {
    $dojYmd = $m[1] . '-' . $m[2] . '-' . $m[3];
}
$joinYear = $dojYmd !== '' ? (int) substr($dojYmd, 0, 4) : (int) date('Y');
$joinMonth = $dojYmd !== '' ? (int) substr($dojYmd, 5, 2) : 1;
if ($joinYear < 2000) {
    $joinYear = (int) date('Y');
    $joinMonth = 1;
}
if ($joinMonth < 1 || $joinMonth > 12) {
    $joinMonth = 1;
}

$curYear = (int) date('Y');
$curMonth = (int) date('n');
$minYm = $joinYear * 12 + $joinMonth;
$maxYm = $curYear * 12 + $curMonth;

$month = (int) ($_GET['month'] ?? $curMonth);
$year = (int) ($_GET['year'] ?? $curYear);
if ($month < 1 || $month > 12) {
    $month = $curMonth;
}
if ($year < $joinYear || $year > $curYear) {
    $year = min(max($year, $joinYear), $curYear);
}

$selYm = $year * 12 + $month;
if ($selYm < $minYm) {
    $year = $joinYear;
    $month = $joinMonth;
    $selYm = $minYm;
}
if ($selYm > $maxYm) {
    $year = $curYear;
    $month = $curMonth;
    $selYm = $maxYm;
}

// Month options for selected year (respect joining + current)
$monthStart = ($year === $joinYear) ? $joinMonth : 1;
$monthEnd = ($year === $curYear) ? $curMonth : 12;
if ($month < $monthStart) {
    $month = $monthStart;
}
if ($month > $monthEnd) {
    $month = $monthEnd;
}

ensurePayrollTables();
$conn = getDBConnection();
$slip = null;
$st = $conn->prepare(
    'SELECT * FROM salary_payslips
     WHERE employee_id = ? AND month_no = ? AND year_no = ?
     LIMIT 1'
);
$st->bind_param('iii', $empId, $month, $year);
$st->execute();
$slip = $st->get_result()->fetch_assoc() ?: null;
$st->close();

// Recent slips (only from joining month onward)
$recent = [];
$stR = $conn->prepare(
    'SELECT id, month_no, year_no, pay_type, present_days, working_days,
            earnings, deductions, net_salary, created_at
     FROM salary_payslips
     WHERE employee_id = ?
       AND (year_no > ? OR (year_no = ? AND month_no >= ?))
     ORDER BY year_no DESC, month_no DESC
     LIMIT 24'
);
$stR->bind_param('iiii', $empId, $joinYear, $joinYear, $joinMonth);
$stR->execute();
$resR = $stR->get_result();
while ($row = $resR->fetch_assoc()) {
    $recent[] = $row;
}
$stR->close();
$conn->close();

$breakup = [];
if ($slip && !empty($slip['breakup_json'])) {
    $decoded = json_decode((string) $slip['breakup_json'], true);
    if (is_array($decoded)) {
        $breakup = $decoded;
    }
}

$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$dojDisplay = $dojYmd !== '' && function_exists('formatDateDisplay')
    ? formatDateDisplay($dojYmd)
    : ($dojYmd !== '' ? date('d-m-Y', strtotime($dojYmd)) : '—');

$pageTitle = 'My Salary Slip';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'my_salary_slip';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn-secondary"
               href="<?php echo app_url('employee/salary_slips.php?month=' . $curMonth . '&year=' . $curYear); ?>">
                <i class="fa-solid fa-calendar-day"></i> Current Month
            </a>
            <?php if ($slip): ?>
            <button type="button" class="btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card emp-slip-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#047857;">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <h1>My Salary Slip</h1>
                    <p>
                        <?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? '')); ?>
                        · Joining <?php echo htmlspecialchars($dojDisplay); ?>
                        · Filter from joining date only
                    </p>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form emp-slip-filters" action="<?php echo app_url('employee/salary_slips.php'); ?>">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control" onchange="this.form.submit()">
                        <?php for ($m = $monthStart; $m <= $monthEnd; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <?php for ($y = $curYear; $y >= $joinYear; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Slip
                    </button>
                </div>
            </div>
        </form>

        <?php if (!$slip): ?>
            <div class="emp-slip-empty">
                <i class="fa-solid fa-file-circle-question"></i>
                <strong>No salary slip for <?php echo htmlspecialchars($monthLabel); ?></strong>
                <p>Slip appears after HR generates salary for this month. Months before your joining date are not available.</p>
            </div>
        <?php else: ?>
            <div class="emp-slip-sheet" id="empSlipPrint">
                <div class="emp-slip-sheet-head">
                    <div>
                        <div class="emp-slip-sheet-title">Salary Slip</div>
                        <div class="emp-slip-sheet-sub"><?php echo htmlspecialchars($monthLabel); ?></div>
                    </div>
                    <div class="emp-slip-net-pill">
                        <span>Net Pay</span>
                        <strong><?php echo htmlspecialchars(moneyInr($slip['net_salary'] ?? 0)); ?></strong>
                    </div>
                </div>

                <div class="emp-slip-meta">
                    <div><span>Employee</span><b><?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? '')); ?></b></div>
                    <div><span>Pay Type</span><b><?php echo htmlspecialchars((string) ($slip['pay_type'] ?? 'Salary')); ?></b></div>
                    <div><span>Present / Working</span><b>
                        <?php
                        $pd = $slip['present_days'];
                        $wd = $slip['working_days'];
                        echo htmlspecialchars(
                            ($pd !== null && $pd !== '' ? number_format((float) $pd, 1) : '—')
                            . ' / '
                            . ($wd !== null && $wd !== '' ? number_format((float) $wd, 1) : '—')
                        );
                        ?>
                    </b></div>
                    <div><span>Generated</span><b><?php
                        $ca = (string) ($slip['created_at'] ?? '');
                        echo $ca !== '' ? htmlspecialchars(date('d-m-Y H:i', strtotime($ca))) : '—';
                    ?></b></div>
                </div>

                <div class="emp-slip-cols">
                    <div class="emp-slip-col is-earn">
                        <h3><i class="fa-solid fa-plus"></i> Earnings</h3>
                        <ul>
                            <?php
                            $hasEarn = false;
                            foreach ($breakup as $line):
                                if (($line['type'] ?? '') !== 'Earning') {
                                    continue;
                                }
                                $hasEarn = true;
                                ?>
                                <li>
                                    <span><?php echo htmlspecialchars((string) ($line['label'] ?? 'Earning')); ?></span>
                                    <b><?php echo htmlspecialchars(moneyInr($line['amount'] ?? 0)); ?></b>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$hasEarn): ?>
                                <li>
                                    <span>Earnings</span>
                                    <b><?php echo htmlspecialchars(moneyInr($slip['earnings'] ?? 0)); ?></b>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="emp-slip-col-total">
                            <span>Total Earnings</span>
                            <strong><?php echo htmlspecialchars(moneyInr($slip['earnings'] ?? 0)); ?></strong>
                        </div>
                    </div>
                    <div class="emp-slip-col is-ded">
                        <h3><i class="fa-solid fa-minus"></i> Deductions</h3>
                        <ul>
                            <?php
                            $hasDed = false;
                            foreach ($breakup as $line):
                                if (($line['type'] ?? '') !== 'Deduction') {
                                    continue;
                                }
                                $hasDed = true;
                                ?>
                                <li>
                                    <span><?php echo htmlspecialchars((string) ($line['label'] ?? 'Deduction')); ?></span>
                                    <b><?php echo htmlspecialchars(moneyInr($line['amount'] ?? 0)); ?></b>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$hasDed): ?>
                                <li>
                                    <span>Deductions</span>
                                    <b><?php echo htmlspecialchars(moneyInr($slip['deductions'] ?? 0)); ?></b>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="emp-slip-col-total">
                            <span>Total Deductions</span>
                            <strong><?php echo htmlspecialchars(moneyInr($slip['deductions'] ?? 0)); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="emp-slip-footer-net">
                    <span>Net Salary Payable</span>
                    <strong><?php echo htmlspecialchars(moneyInr($slip['net_salary'] ?? 0)); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <div class="form-page-header" style="margin-bottom:10px;">
            <div>
                <h2 style="margin:0;font-size:1.05rem;">Recent Salary Slips</h2>
                <p style="margin:4px 0 0;color:#64748b;font-size:13px;">After joining date · Click month to open</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Month</th>
                        <th>Pay Type</th>
                        <th>Days</th>
                        <th>Earnings</th>
                        <th>Deductions</th>
                        <th>Net</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$recent): ?>
                    <tr><td colspan="8" class="empty-cell">No generated salary slips yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $i => $r):
                        $ml = date('F Y', mktime(0, 0, 0, (int) $r['month_no'], 1, (int) $r['year_no']));
                        $isSel = ((int) $r['month_no'] === $month && (int) $r['year_no'] === $year);
                        ?>
                        <tr class="<?php echo $isSel ? 'is-selected-row' : ''; ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($ml); ?></strong></td>
                            <td><?php echo htmlspecialchars((string) ($r['pay_type'] ?? '—')); ?></td>
                            <td><?php
                                $pd = $r['present_days'];
                                $wd = $r['working_days'];
                                echo htmlspecialchars(
                                    ($pd !== null && $pd !== '' ? number_format((float) $pd, 1) : '—')
                                    . ' / '
                                    . ($wd !== null && $wd !== '' ? number_format((float) $wd, 1) : '—')
                                );
                            ?></td>
                            <td><?php echo htmlspecialchars(moneyInr($r['earnings'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars(moneyInr($r['deductions'] ?? 0)); ?></td>
                            <td><strong><?php echo htmlspecialchars(moneyInr($r['net_salary'] ?? 0)); ?></strong></td>
                            <td>
                                <a class="action-btn edit" title="View slip"
                                   href="<?php echo app_url('employee/salary_slips.php?month=' . (int) $r['month_no'] . '&year=' . (int) $r['year_no']); ?>">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
.emp-slip-filters { margin-bottom: 16px; }
.emp-slip-empty {
    text-align: center;
    padding: 36px 16px;
    color: #64748b;
    background: #F8FAFC;
    border-radius: 12px;
    border: 1px dashed #CBD5E1;
}
.emp-slip-empty i { font-size: 28px; color: #94A3B8; display: block; margin-bottom: 10px; }
.emp-slip-empty strong { display: block; color: #0f172a; font-size: 15px; margin-bottom: 6px; }
.emp-slip-empty p { margin: 0; font-size: 13px; }
.emp-slip-sheet {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
}
.emp-slip-sheet-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    padding: 16px 18px;
    background: linear-gradient(135deg, #ECFDF5 0%, #FFFFFF 70%);
    border-bottom: 1px solid #D1FAE5;
}
.emp-slip-sheet-title { font-size: 18px; font-weight: 800; color: #065F46; }
.emp-slip-sheet-sub { font-size: 13px; font-weight: 600; color: #047857; margin-top: 2px; }
.emp-slip-net-pill {
    text-align: right;
    background: #065F46;
    color: #fff;
    border-radius: 12px;
    padding: 10px 14px;
    min-width: 140px;
}
.emp-slip-net-pill span { display: block; font-size: 11px; opacity: 0.85; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.emp-slip-net-pill strong { font-size: 18px; }
.emp-slip-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding: 14px 18px;
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
}
.emp-slip-meta span { display: block; font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
.emp-slip-meta b { font-size: 13px; color: #0f172a; }
.emp-slip-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}
.emp-slip-col { padding: 14px 18px 10px; }
.emp-slip-col + .emp-slip-col { border-left: 1px solid #E2E8F0; }
.emp-slip-col h3 {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 8px;
}
.emp-slip-col.is-earn h3 { color: #047857; }
.emp-slip-col.is-ded h3 { color: #B91C1C; }
.emp-slip-col ul { list-style: none; margin: 0; padding: 0; }
.emp-slip-col li {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed #E2E8F0;
    font-size: 13px;
}
.emp-slip-col li span { color: #334155; }
.emp-slip-col li b { color: #0f172a; white-space: nowrap; }
.emp-slip-col-total {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid #E2E8F0;
    font-size: 13px;
    font-weight: 700;
}
.emp-slip-col.is-earn .emp-slip-col-total strong { color: #047857; }
.emp-slip-col.is-ded .emp-slip-col-total strong { color: #B91C1C; }
.emp-slip-footer-net {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #0F172A;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
}
.emp-slip-footer-net strong { font-size: 20px; color: #6EE7B7; }
tr.is-selected-row td { background: #ECFDF5 !important; }
@media (max-width: 800px) {
    .emp-slip-meta { grid-template-columns: 1fr 1fr; }
    .emp-slip-cols { grid-template-columns: 1fr; }
    .emp-slip-col + .emp-slip-col { border-left: none; border-top: 1px solid #E2E8F0; }
}
@media print {
    .app-sidebar, .page-toolbar, .emp-slip-filters, .top-bar, .sidebar-toggle,
    .form-page-card + .form-page-card { display: none !important; }
    .emp-slip-card { box-shadow: none; border: none; }
    .dashboard-main { margin: 0; padding: 0; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
