<?php
/**
 * Duty Leave (DL) report — usage only, no balance / no carry-forward
 * Paid · Official duty outside company premises · Biometric not expected
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
$month = (int) ($_GET['month'] ?? 0);
$deptId = (int) ($_GET['department_id'] ?? 0);
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 0 || $month > 12) {
    $month = 0;
}

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$report = leaveDutyLeaveReport($year, $month, $deptId, $conn);
$conn->close();

$lt = $report['leave_type'] ?? null;
$totEmp = (int) ($report['totals']['employees'] ?? 0);
$totApproved = (float) ($report['totals']['approved_days'] ?? 0);
$totPending = (float) ($report['totals']['pending_days'] ?? 0);
$totReq = (int) ($report['totals']['requests'] ?? 0);

$pageTitle = 'Duty Leave Report';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'dl_report';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';

$monthNames = [
    0 => 'All Months',
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
            <a href="<?php echo app_url('leave/apply.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Apply Leave
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Duty Leave Report</h1>
            <p>Official duty outside company premises · Balance = 0 · No carry-forward · Paid · Biometric not required</p>
        </div>

        <?php require __DIR__ . '/_report_tabs.php'; ?>

        <div class="leave-rpt-rules">
            <span class="leave-rpt-rule"><i class="fa-solid fa-scale-balanced"></i> Balance always 0</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-ban"></i> No carry-forward</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-indian-rupee-sign"></i> Paid in salary (DL days)</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-fingerprint"></i> Biometric not expected</span>
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
            <div class="alert alert-error">Duty Leave (DL) type not found. Run db_sync or open Leave Master.</div>
        <?php else: ?>
            <div class="leave-rpt-kpis">
                <div class="leave-rpt-kpi kpi-slate">
                    <div class="leave-rpt-kpi-label">Employees</div>
                    <div class="leave-rpt-kpi-value"><?php echo $totEmp; ?></div>
                    <div class="leave-rpt-kpi-sub">With DL activity</div>
                </div>
                <div class="leave-rpt-kpi kpi-blue">
                    <div class="leave-rpt-kpi-label">Requests</div>
                    <div class="leave-rpt-kpi-value"><?php echo $totReq; ?></div>
                    <div class="leave-rpt-kpi-sub"><?php echo htmlspecialchars($monthNames[$month] ?? ''); ?> · <?php echo $year; ?></div>
                </div>
                <div class="leave-rpt-kpi kpi-green">
                    <div class="leave-rpt-kpi-label">Approved Days</div>
                    <div class="leave-rpt-kpi-value green"><?php echo number_format($totApproved, 2); ?></div>
                    <div class="leave-rpt-kpi-sub">Paid in salary</div>
                </div>
                <div class="leave-rpt-kpi kpi-amber">
                    <div class="leave-rpt-kpi-label">Pending Days</div>
                    <div class="leave-rpt-kpi-value"><?php echo number_format($totPending, 2); ?></div>
                    <div class="leave-rpt-kpi-sub">Awaiting approval</div>
                </div>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-users" style="color:var(--brand);"></i> Employee-wise Usage
            </h3>
            <div class="leave-rpt-table-wrap" style="margin-bottom:22px;">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="txt">Department</th>
                            <th class="ctr">Approved Count</th>
                            <th class="ctr">Approved Days</th>
                            <th class="ctr">Pending Days</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$report['rows']): ?>
                        <tr>
                            <td colspan="7">
                                <div class="leave-rpt-empty">
                                    <i class="fa-solid fa-briefcase"></i>
                                    No Duty Leave usage found for selected filter.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['rows'] as $i => $r): ?>
                            <?php $ad = (float) ($r['approved_days'] ?? 0); ?>
                            <tr class="<?php echo $ad > 0 ? 'is-ok' : ''; ?>">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                                <td class="ctr"><?php echo (int) ($r['approved_count'] ?? 0); ?></td>
                                <td class="ctr"><span class="leave-rem-hi"><?php echo number_format($ad, 2); ?></span></td>
                                <td class="ctr"><?php echo number_format((float) ($r['pending_days'] ?? 0), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <?php if ($report['rows']): ?>
                    <tfoot>
                        <tr>
                            <td class="txt" colspan="5">Total</td>
                            <td class="ctr"><?php echo number_format($totApproved, 2); ?></td>
                            <td class="ctr"><?php echo number_format($totPending, 2); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-list" style="color:var(--brand);"></i> Request History
            </h3>
            <div class="leave-rpt-table-wrap">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="date">From</th>
                            <th class="date">To</th>
                            <th class="ctr">Days</th>
                            <th class="ctr">Half</th>
                            <th class="ctr">Status</th>
                            <th class="txt">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$report['detail']): ?>
                        <tr>
                            <td colspan="9">
                                <div class="leave-rpt-empty">
                                    <i class="fa-regular fa-folder-open"></i>
                                    No Duty Leave requests in this period.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['detail'] as $i => $r): ?>
                            <?php
                            $st = (string) ($r['status'] ?? '');
                            $badge = 'leave-badge-used';
                            $rowClass = '';
                            if ($st === 'Approved') {
                                $badge = 'leave-badge-open';
                                $rowClass = 'is-ok';
                            } elseif ($st === 'Pending') {
                                $badge = 'leave-badge-partial';
                                $rowClass = 'is-highlight';
                            } elseif ($st === 'Rejected' || $st === 'Cancelled') {
                                $badge = 'leave-badge-expired';
                            }
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="date"><?php echo htmlspecialchars(formatDateDisplay($r['from_date'] ?? '')); ?></td>
                                <td class="date"><?php echo htmlspecialchars(formatDateDisplay($r['to_date'] ?? '')); ?></td>
                                <td class="ctr"><strong><?php echo number_format((float) ($r['days'] ?? 0), 2); ?></strong></td>
                                <td class="ctr"><?php echo htmlspecialchars(leaveHalfLabel($r['leave_half'] ?? 'FULL')); ?></td>
                                <td class="ctr"><span class="leave-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                <td class="txt" style="max-width:240px;white-space:normal;font-size:12px;color:#64748b;">
                                    <?php echo htmlspecialchars($r['reason'] ?? ''); ?>
                                </td>
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
