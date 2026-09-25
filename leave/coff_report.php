<?php
/**
 * Employee-wise C-Off report — earned / used / expired / available
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/coff_helper.php';

requireLogin();
requireAccess('leave', 'view');
ensureCoffTables();

$deptId = (int) ($_GET['department_id'] ?? 0);
$show = true; // always show (default all depts)

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$rows = coffEmployeeReport($deptId, $conn);
$conn->close();

$totEarned = 0.0;
$totUsed = 0.0;
$totExpired = 0.0;
$totAvail = 0.0;
foreach ($rows as $r) {
    $totEarned += (float) ($r['total_earned'] ?? 0);
    $totUsed += (float) ($r['total_used'] ?? 0);
    $totExpired += (float) ($r['total_expired'] ?? 0);
    $totAvail += (float) ($r['available'] ?? 0);
}
$totAll = max(0.001, $totEarned);

$pageTitle = 'C-Off Report';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'coff_report';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';

$today = date('Y-m-d');
$soonLimit = date('Y-m-d', strtotime('+14 days'));
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('leave/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Leave Requests
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('leave/balance.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-scale-balanced"></i> Leave Balance
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>C-Off Report</h1>
            <p>Employee-wise compensatory off summary · 4 hrs = 0.5 · 8 hrs = 1 day · 2-month expiry</p>
        </div>

        <?php require __DIR__ . '/_report_tabs.php'; ?>

        <div class="leave-rpt-rules">
            <span class="leave-rpt-rule"><i class="fa-solid fa-clock"></i> 4 hours = half day</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-calendar-check"></i> 8 hours = full day</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-ban"></i> Unused after 2 months = wipe</span>
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
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Show Report
                    </button>
                </div>
            </div>
        </form>

        <div class="leave-rpt-kpis">
            <div class="leave-rpt-kpi kpi-slate">
                <div class="leave-rpt-kpi-label">Employees</div>
                <div class="leave-rpt-kpi-value"><?php echo count($rows); ?></div>
                <div class="leave-rpt-kpi-sub">With C-Off activity</div>
            </div>
            <div class="leave-rpt-kpi kpi-blue">
                <div class="leave-rpt-kpi-label">Total Earned</div>
                <div class="leave-rpt-kpi-value"><?php echo number_format($totEarned, 2); ?></div>
                <div class="leave-rpt-kpi-sub">Days credited</div>
            </div>
            <div class="leave-rpt-kpi kpi-amber">
                <div class="leave-rpt-kpi-label">Used</div>
                <div class="leave-rpt-kpi-value"><?php echo number_format($totUsed, 2); ?></div>
                <div class="leave-rpt-kpi-sub"><?php echo number_format(($totUsed / $totAll) * 100, 0); ?>% of earned</div>
            </div>
            <div class="leave-rpt-kpi kpi-red">
                <div class="leave-rpt-kpi-label">Expired</div>
                <div class="leave-rpt-kpi-value red"><?php echo number_format($totExpired, 2); ?></div>
                <div class="leave-rpt-kpi-sub">Wiped balance</div>
            </div>
        </div>
        <div class="leave-rpt-kpis" style="grid-template-columns:1fr;margin-top:-6px;">
            <div class="leave-rpt-kpi kpi-green">
                <div class="leave-rpt-kpi-label">Available Balance</div>
                <div class="leave-rpt-kpi-value green"><?php echo number_format($totAvail, 2); ?> days</div>
                <div class="leave-rpt-kpi-sub" style="margin-top:10px;">
                    <div class="leave-bar" title="Used / Expired / Available">
                        <span class="leave-bar-used" style="width:<?php echo max(0, min(100, ($totUsed / $totAll) * 100)); ?>%;"></span>
                        <span class="leave-bar-expired" style="width:<?php echo max(0, min(100, ($totExpired / $totAll) * 100)); ?>%;"></span>
                        <span class="leave-bar-avail" style="width:<?php echo max(0, min(100, ($totAvail / $totAll) * 100)); ?>%;"></span>
                    </div>
                    <div class="leave-bar-meta">
                        <span style="color:#2563eb;">■ Used</span>
                        &nbsp; <span style="color:#dc2626;">■ Expired</span>
                        &nbsp; <span style="color:#059669;">■ Available</span>
                    </div>
                </div>
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
                        <th class="ctr">Earned</th>
                        <th class="ctr">Used</th>
                        <th class="ctr">Expired</th>
                        <th class="ctr">Available</th>
                        <th class="ctr">Usage Mix</th>
                        <th class="date">Next Expiry</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="11">
                            <div class="leave-rpt-empty">
                                <i class="fa-regular fa-file-lines"></i>
                                No C-Off activity found for selected filter.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                        $earned = (float) ($r['total_earned'] ?? 0);
                        $used = (float) ($r['total_used'] ?? 0);
                        $expired = (float) ($r['total_expired'] ?? 0);
                        $availR = (float) ($r['available'] ?? 0);
                        $base = max(0.001, $earned);
                        $nx = $r['next_expiry'] ?? '';
                        $rowClass = '';
                        if ($availR > 0) {
                            $rowClass = 'is-ok';
                        }
                        if ($nx !== '' && $nx !== null && $nx <= $soonLimit && $availR > 0) {
                            $rowClass = 'is-highlight';
                        }
                        if ($expired > 0 && $availR <= 0) {
                            $rowClass = 'is-danger';
                        }
                        $expClass = 'leave-expiry-ok';
                        if ($nx !== '' && $nx !== null && $nx <= $soonLimit) {
                            $expClass = 'leave-expiry-soon';
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="sr"><?php echo $i + 1; ?></td>
                            <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                            <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                            <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                            <td class="ctr"><?php echo number_format($earned, 2); ?></td>
                            <td class="ctr"><?php echo number_format($used, 2); ?></td>
                            <td class="ctr">
                                <?php if ($expired > 0): ?>
                                    <span class="leave-badge leave-badge-expired"><?php echo number_format($expired, 2); ?></span>
                                <?php else: ?>
                                    0.00
                                <?php endif; ?>
                            </td>
                            <td class="ctr">
                                <?php if ($availR > 0): ?>
                                    <span class="leave-rem-hi"><?php echo number_format($availR, 2); ?></span>
                                <?php else: ?>
                                    <span class="leave-rem-zero">0.00</span>
                                <?php endif; ?>
                            </td>
                            <td class="ctr" style="min-width:120px;">
                                <div class="leave-bar">
                                    <span class="leave-bar-used" style="width:<?php echo ($used / $base) * 100; ?>%;"></span>
                                    <span class="leave-bar-expired" style="width:<?php echo ($expired / $base) * 100; ?>%;"></span>
                                    <span class="leave-bar-avail" style="width:<?php echo ($availR / $base) * 100; ?>%;"></span>
                                </div>
                            </td>
                            <td class="date <?php echo $expClass; ?>">
                                <?php
                                echo ($nx !== '' && $nx !== null)
                                    ? htmlspecialchars(formatDateDisplay($nx))
                                    : '—';
                                ?>
                            </td>
                            <td class="actions">
                                <a class="btn-secondary" style="padding:5px 10px;font-size:12px;"
                                   href="<?php echo app_url('leave/coff_history.php?employee_id=' . (int) $r['id'] . ($deptId ? '&department_id=' . $deptId : '')); ?>">
                                    <i class="fa-solid fa-clock-rotate-left"></i> History
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr>
                        <td class="txt" colspan="4">Total</td>
                        <td class="ctr"><?php echo number_format($totEarned, 2); ?></td>
                        <td class="ctr"><?php echo number_format($totUsed, 2); ?></td>
                        <td class="ctr"><?php echo number_format($totExpired, 2); ?></td>
                        <td class="ctr"><?php echo number_format($totAvail, 2); ?></td>
                        <td class="ctr" colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
