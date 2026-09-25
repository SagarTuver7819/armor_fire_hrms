<?php
/**
 * Year-end leave encashment report — employee wise (PL default)
 * Amount = Remaining days × (Decided Salary ÷ 30)
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
$deptId = (int) ($_GET['department_id'] ?? 0);
$leaveTypeId = (int) ($_GET['leave_type_id'] ?? 0);
$show = isset($_GET['show']) || isset($_GET['year']);

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$leaveTypes = getActiveLeaveTypes($conn);
$report = $show ? leaveEncashmentReport($year, $leaveTypeId, $deptId, $conn) : null;
$conn->close();

$lt = $report['leave_type'] ?? null;
$totEmp = (int) ($report['totals']['employees'] ?? 0);
$totRem = (float) ($report['totals']['remaining'] ?? 0);
$totEnc = (float) ($report['totals']['encashment'] ?? 0);
$totUsed = (float) ($report['totals']['used'] ?? 0);
$totAcc = (float) ($report['totals']['accrued'] ?? 0);

$pageTitle = 'Leave Encashment Report';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'leave_encashment';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('leave/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Leave Requests
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('masters/leaves/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-umbrella-beach"></i> Leave Master
            </a>
            <a href="<?php echo app_url('leave/balance.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-scale-balanced"></i> Leave Balance
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Leave Encashment Report</h1>
            <p>Year-end payout · Remaining days × (Salary ÷ 30) · PL: 24/year · monthly carry-forward · max 6 at once</p>
        </div>

        <?php require __DIR__ . '/_report_tabs.php'; ?>

        <form method="get" class="employee-form leave-rpt-filters">
            <input type="hidden" name="show" value="1">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Leave Type</label>
                    <select name="leave_type_id" class="form-control">
                        <option value="0">PL (Privileged Leave)</option>
                        <?php foreach ($leaveTypes as $t): ?>
                            <option value="<?php echo (int) $t['id']; ?>" <?php echo $leaveTypeId === (int) $t['id'] ? 'selected' : ''; ?>>
                                <?php
                                echo htmlspecialchars(($t['code'] ? $t['code'] . ' · ' : '') . $t['leave_type']);
                                if (!empty($t['allow_encashment'])) {
                                    echo ' ★';
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <?php if ($show && $report): ?>
            <?php if (!$lt): ?>
                <div class="alert alert-error">Leave type not found. Create PL in Leave Master first.</div>
            <?php else: ?>
                <div class="leave-rpt-rules">
                    <span class="leave-rpt-rule"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars(($lt['code'] ?? '') . ' · ' . ($lt['leave_type'] ?? '')); ?></span>
                    <span class="leave-rpt-rule"><i class="fa-solid fa-calendar"></i> Year <?php echo (int) $report['year']; ?></span>
                    <span class="leave-rpt-rule"><i class="fa-solid fa-layer-group"></i> Quota <?php echo (float) ($lt['days_allowed'] ?? 0); ?> days</span>
                    <?php if (!empty($lt['monthly_carry_forward'])): ?>
                        <span class="leave-rpt-rule"><i class="fa-solid fa-arrow-right-arrow-left"></i> Monthly carry-forward</span>
                    <?php endif; ?>
                    <?php if ((int) ($lt['max_concurrent_applicants'] ?? 0) > 0): ?>
                        <span class="leave-rpt-rule"><i class="fa-solid fa-users"></i> Max <?php echo (int) $lt['max_concurrent_applicants']; ?> at once</span>
                    <?php endif; ?>
                </div>

                <div class="leave-rpt-kpis">
                    <div class="leave-rpt-kpi kpi-slate">
                        <div class="leave-rpt-kpi-label">Employees</div>
                        <div class="leave-rpt-kpi-value"><?php echo $totEmp; ?></div>
                        <div class="leave-rpt-kpi-sub">Active in filter</div>
                    </div>
                    <div class="leave-rpt-kpi kpi-blue">
                        <div class="leave-rpt-kpi-label">Accrued</div>
                        <div class="leave-rpt-kpi-value"><?php echo number_format($totAcc, 2); ?></div>
                        <div class="leave-rpt-kpi-sub">Days credited</div>
                    </div>
                    <div class="leave-rpt-kpi kpi-amber">
                        <div class="leave-rpt-kpi-label">Used</div>
                        <div class="leave-rpt-kpi-value"><?php echo number_format($totUsed, 2); ?></div>
                        <div class="leave-rpt-kpi-sub">Days consumed</div>
                    </div>
                    <div class="leave-rpt-kpi kpi-green">
                        <div class="leave-rpt-kpi-label">Remaining</div>
                        <div class="leave-rpt-kpi-value green"><?php echo number_format($totRem, 2); ?></div>
                        <div class="leave-rpt-kpi-sub">Days for encash</div>
                    </div>
                </div>
                <div class="leave-rpt-kpis" style="grid-template-columns:1fr;margin-top:-6px;">
                    <div class="leave-rpt-kpi">
                        <div class="leave-rpt-kpi-label">Total Encashment Amount</div>
                        <div class="leave-rpt-kpi-value accent">₹ <?php echo number_format($totEnc, 2); ?></div>
                        <div class="leave-rpt-kpi-sub">Remaining × (Salary ÷ 30)</div>
                    </div>
                </div>

                <div class="leave-rpt-table-wrap">
                    <table class="leave-rpt-table">
                        <thead>
                            <tr>
                                <th class="sr">Sr</th>
                                <th class="txt">Code</th>
                                <th class="txt">Employee</th>
                                <th class="txt">Department</th>
                                <th class="num">Salary</th>
                                <th class="ctr">Quota</th>
                                <th class="ctr">Accrued</th>
                                <th class="ctr">Used</th>
                                <th class="ctr">Remaining</th>
                                <th class="num">Rate / Day</th>
                                <th class="num">Encash Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$report['rows']): ?>
                            <tr><td colspan="11"><div class="leave-rpt-empty"><i class="fa-regular fa-folder-open"></i>No active employees found.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($report['rows'] as $i => $r): ?>
                                <?php
                                $rem = (float) $r['remaining'];
                                $amt = (float) $r['encashment_amount'];
                                $rowClass = $amt > 0 ? 'is-highlight' : '';
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td class="sr"><?php echo $i + 1; ?></td>
                                    <td class="emp-code"><?php echo htmlspecialchars($r['employee_code']); ?></td>
                                    <td class="emp-name"><?php echo htmlspecialchars($r['employee_name']); ?></td>
                                    <td class="txt"><?php echo htmlspecialchars($r['department_name']); ?></td>
                                    <td class="num"><?php echo number_format((float) $r['decided_salary'], 2); ?></td>
                                    <td class="ctr"><?php echo number_format((float) $r['quota'], 1); ?></td>
                                    <td class="ctr"><?php echo number_format((float) $r['accrued'], 2); ?></td>
                                    <td class="ctr"><?php echo number_format((float) $r['used'], 2); ?></td>
                                    <td class="ctr">
                                        <?php if ($rem > 0): ?>
                                            <span class="leave-rem-hi"><?php echo number_format($rem, 2); ?></span>
                                        <?php else: ?>
                                            <span class="leave-rem-zero">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num"><?php echo number_format((float) $r['daily_rate'], 2); ?></td>
                                    <td class="num"><span class="leave-amt">₹ <?php echo number_format($amt, 2); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if ($report['rows']): ?>
                        <tfoot>
                            <tr>
                                <td class="txt" colspan="5">Total</td>
                                <td class="ctr"><?php echo number_format((float) $report['totals']['quota'], 1); ?></td>
                                <td class="ctr"><?php echo number_format($totAcc, 2); ?></td>
                                <td class="ctr"><?php echo number_format($totUsed, 2); ?></td>
                                <td class="ctr"><?php echo number_format($totRem, 2); ?></td>
                                <td class="num"></td>
                                <td class="num">₹ <?php echo number_format($totEnc, 2); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="form-hint" style="margin-top:12px;">
                    Formula: <code>Encash Amount = Remaining Days × (Decided Salary ÷ 30)</code>
                    · PL monthly credit = 24 ÷ 12 = <strong>2 days / month</strong>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
