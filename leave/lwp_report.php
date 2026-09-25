<?php
/**
 * LWP Report — unpaid days when no attendance / not WO / not holiday / no other leave
 * Affects attendance display and salary (excluded from paid days)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';

requireLogin();
requireAccess('leave', 'view');
ensureLeaveTables();

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? (int) date('n'));
$deptId = (int) ($_GET['department_id'] ?? 0);
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 0 || $month > 12) {
    $month = (int) date('n');
}

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$report = leaveLwpReport($year, $month, $deptId, $conn);
$conn->close();

$lt = $report['leave_type'] ?? null;
$totEmp = (int) ($report['totals']['employees'] ?? 0);
$totDays = (float) ($report['totals']['lwp_days'] ?? 0);
$totImpact = (float) ($report['totals']['salary_impact'] ?? 0);

$pageTitle = 'LWP Report';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'lwp_report';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';

$monthNames = [
    0 => 'Full Year',
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('leave/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Leave Requests
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/report.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-calendar-check"></i> Attendance Report
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>LWP Report</h1>
            <p>Leave Without Pay · Auto when no punch, not holiday/week-off, and no other leave · Unpaid · Salary &amp; attendance impact</p>
        </div>

        <?php require __DIR__ . '/_report_tabs.php'; ?>

        <div class="leave-rpt-rules">
            <span class="leave-rpt-rule"><i class="fa-solid fa-user-xmark"></i> No attendance punch</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-calendar-xmark"></i> Not holiday / week-off</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-ban"></i> No other leave taken</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-indian-rupee-sign"></i> Unpaid — salary reduced</span>
        </div>

        <form method="get" class="employee-form leave-rpt-filters">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control">
                        <?php for ($y = (int) date('Y') - 2; $y <= (int) date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control">
                        <?php foreach ($monthNames as $m => $label): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <?php if (!$lt): ?>
            <div class="alert alert-error">LWP leave type not found. Open Leave Master or run db_sync.</div>
        <?php else: ?>
            <div class="leave-rpt-kpis">
                <div class="leave-rpt-kpi kpi-slate">
                    <div class="leave-rpt-kpi-label">Employees</div>
                    <div class="leave-rpt-kpi-value"><?php echo $totEmp; ?></div>
                    <div class="leave-rpt-kpi-sub">With LWP days</div>
                </div>
                <div class="leave-rpt-kpi kpi-red">
                    <div class="leave-rpt-kpi-label">LWP Days</div>
                    <div class="leave-rpt-kpi-value red"><?php echo number_format($totDays, 2); ?></div>
                    <div class="leave-rpt-kpi-sub"><?php echo htmlspecialchars($monthNames[$month] ?? ''); ?> · <?php echo $year; ?></div>
                </div>
                <div class="leave-rpt-kpi kpi-amber">
                    <div class="leave-rpt-kpi-label">Est. Salary Impact</div>
                    <div class="leave-rpt-kpi-value accent">₹ <?php echo number_format($totImpact, 2); ?></div>
                    <div class="leave-rpt-kpi-sub">Days × (Salary ÷ 30)</div>
                </div>
                <div class="leave-rpt-kpi kpi-blue">
                    <div class="leave-rpt-kpi-label">Type</div>
                    <div class="leave-rpt-kpi-value" style="font-size:18px;">Unpaid</div>
                    <div class="leave-rpt-kpi-sub">Not in Total Pay Days</div>
                </div>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-users" style="color:var(--brand);"></i> Employee-wise LWP
            </h3>
            <div class="leave-rpt-table-wrap" style="margin-bottom:22px;">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="txt">Department</th>
                            <th class="ctr">LWP Days</th>
                            <th class="num">Salary</th>
                            <th class="num">Rate / Day</th>
                            <th class="num">Est. Deduction</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$report['rows']): ?>
                        <tr>
                            <td colspan="8">
                                <div class="leave-rpt-empty">
                                    <i class="fa-solid fa-user-check"></i>
                                    No LWP days found. Rebuild attendance to auto-mark absent working days as LWP.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['rows'] as $i => $r): ?>
                            <tr class="is-danger">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                                <td class="ctr"><span class="leave-badge leave-badge-expired"><?php echo number_format((float) $r['lwp_days'], 2); ?></span></td>
                                <td class="num"><?php echo number_format((float) ($r['decided_salary'] ?? 0), 2); ?></td>
                                <td class="num"><?php echo number_format((float) ($r['daily_rate'] ?? 0), 2); ?></td>
                                <td class="num"><span class="leave-amt">₹ <?php echo number_format((float) ($r['salary_impact'] ?? 0), 2); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($report['rows']): ?>
                    <tfoot>
                        <tr>
                            <td class="txt" colspan="4">Total</td>
                            <td class="ctr"><?php echo number_format($totDays, 2); ?></td>
                            <td class="num" colspan="2"></td>
                            <td class="num">₹ <?php echo number_format($totImpact, 2); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-list" style="color:var(--brand);"></i> Day-wise LWP Marks
            </h3>
            <div class="leave-rpt-table-wrap">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="date">Date</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="txt">Department</th>
                            <th class="ctr">Status</th>
                            <th class="ctr">Source</th>
                            <th class="txt">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $detailRows = array_values(array_filter($report['detail'] ?? [], static function ($r) {
                        $src = (string) ($r['source'] ?? '');
                        $rem = strtoupper((string) ($r['remarks'] ?? ''));
                        return $src === 'auto_lwp' || strpos($rem, 'LWP') !== false;
                    }));
                    ?>
                    <?php if (!$detailRows): ?>
                        <tr>
                            <td colspan="8">
                                <div class="leave-rpt-empty">
                                    <i class="fa-regular fa-calendar"></i>
                                    No day-wise LWP marks in this period.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($detailRows as $i => $r): ?>
                            <tr class="is-danger">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="date"><?php echo htmlspecialchars(formatDateDisplay($r['attendance_date'] ?? '')); ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                                <td class="ctr"><span class="leave-badge leave-badge-expired">LWP</span></td>
                                <td class="ctr"><?php echo htmlspecialchars($r['source'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
