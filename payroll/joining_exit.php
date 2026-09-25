<?php
/**
 * Monthly Joining / Exit Report — prorated salary days (WO / Holiday / Leave)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';

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

$report = getMonthlyJoiningExitReport($month, $year, $deptId);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));

$pageTitle = 'Joining / Exit Report';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'joining_exit_report';

$qs = http_build_query([
    'department_id' => $deptId,
    'month' => $month,
    'year' => $year,
]);

require_once __DIR__ . '/../includes/header.php';

function jeNum($n, $d = 1)
{
    return number_format((float) $n, $d);
}
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
        <a class="btn-secondary" href="<?php echo app_url('payroll/joining_exit_excel.php?' . $qs); ?>">
            <i class="fa-solid fa-file-excel"></i> Excel
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Monthly Joining / Exit Report</h1>
                <p><?php echo htmlspecialchars($monthLabel); ?> · Salary only for employment days · Week Off / Holiday / Leave counted in range</p>
            </div>
        </div>

        <form method="GET" class="employee-form">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All departments</option>
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

        <div class="ops-live-summary" style="margin-top:12px;">
            <span class="ops-chip"><strong><?php echo (int) $report['join_count']; ?></strong> Joined</span>
            <span class="ops-chip"><strong><?php echo (int) $report['exit_count']; ?></strong> Exited</span>
            <span class="ops-chip">Month days <?php echo (int) $report['month_days']; ?></span>
        </div>
    </div>

    <?php
    $sections = [
        ['title' => 'New Joinings', 'icon' => 'fa-user-plus', 'rows' => $report['joiners'], 'empty' => 'No joinings in this month.'],
        ['title' => 'Exits', 'icon' => 'fa-user-xmark', 'rows' => $report['exiters'], 'empty' => 'No exits in this month.'],
    ];
    foreach ($sections as $sec):
    ?>
        <div class="form-page-card" style="margin-top:16px;">
            <div class="form-page-header">
                <div>
                    <h2 style="margin:0;font-size:1.15rem;"><i class="fa-solid <?php echo $sec['icon']; ?>"></i> <?php echo htmlspecialchars($sec['title']); ?></h2>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>DOJ</th>
                            <th>Exit</th>
                            <th class="num">Eligible</th>
                            <th class="num">Present</th>
                            <th class="num">W.Off</th>
                            <th class="num">Holiday</th>
                            <th class="num">PL</th>
                            <th class="num">SL</th>
                            <th class="num">DL</th>
                            <th class="num">Paid Days</th>
                            <th class="num">Gross</th>
                            <th class="num">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$sec['rows']): ?>
                        <tr><td colspan="15" class="empty-cell"><?php echo htmlspecialchars($sec['empty']); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($sec['rows'] as $r): ?>
                            <tr>
                                <td><span class="code-badge"><?php echo htmlspecialchars($r['employee_code']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['department'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($r['doj'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($r['doe'] ?: '-'); ?></td>
                                <td class="num"><?php echo jeNum($r['eligible_days']); ?></td>
                                <td class="num"><?php echo jeNum($r['present']); ?></td>
                                <td class="num"><?php echo jeNum($r['week_off']); ?></td>
                                <td class="num"><?php echo jeNum($r['holiday']); ?></td>
                                <td class="num"><?php echo jeNum($r['pl']); ?></td>
                                <td class="num"><?php echo jeNum($r['sl']); ?></td>
                                <td class="num"><?php echo jeNum($r['dl']); ?></td>
                                <td class="num"><strong><?php echo jeNum($r['total_days']); ?></strong></td>
                                <td class="num"><?php echo number_format($r['gross'], 2); ?></td>
                                <td class="num"><strong><?php echo number_format($r['net'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="form-hint" style="margin-top:10px;">
                Paid days = Present + paid Week Off + paid Holiday + PL + SL + DL — only between joining and exit date inside this month.
                Gross = Salary × Paid Days ÷ <?php echo (int) $report['month_days']; ?>.
            </p>
        </div>
    <?php endforeach; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
