<?php
/**
 * C-Off credit history — earned on Week Off / Holiday work
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
$employeeId = (int) ($_GET['employee_id'] ?? 0);
$status = trim((string) ($_GET['status'] ?? ''));
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}
if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}
$allowedStatus = ['', 'Open', 'Partial', 'Used', 'Expired'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$conn = getDBConnection();
$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}
$employees = leaveEmployeesForSelect($deptId, $conn);
coffExpireOverdue($conn, $employeeId);
$rows = coffFetchHistory($employeeId, $fromDate, $toDate, $status, $conn);
if ($deptId > 0) {
    $empIds = [];
    foreach ($employees as $e) {
        $empIds[(int) $e['id']] = true;
    }
    $rows = array_values(array_filter($rows, static function ($r) use ($empIds) {
        return isset($empIds[(int) $r['employee_id']]);
    }));
}
$avail = $employeeId > 0 ? coffAvailableBalance($conn, $employeeId) : null;
$conn->close();

$sumCredit = 0.0;
$sumUsed = 0.0;
$sumRem = 0.0;
$cntOpen = 0;
$cntExpired = 0;
$cntUsed = 0;
foreach ($rows as $r) {
    $sumCredit += (float) ($r['credit_days'] ?? 0);
    $sumUsed += (float) ($r['used_days'] ?? 0);
    $rem = (float) ($r['remaining_days'] ?? 0);
    $st = (string) ($r['status'] ?? '');
    if (in_array($st, ['Open', 'Partial'], true) && ($r['expires_at'] ?? '') >= date('Y-m-d')) {
        $sumRem += max(0, $rem);
        $cntOpen++;
    }
    if ($st === 'Expired') {
        $cntExpired++;
    }
    if ($st === 'Used') {
        $cntUsed++;
    }
}

$pageTitle = 'C-Off History';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'coff_history';
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
            <a href="<?php echo app_url('leave/apply.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Apply Leave
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>C-Off History</h1>
            <p>Credits earned on Week Off / Holiday · Use within 2 months · Dates: DD-MM-YYYY</p>
        </div>

        <?php require __DIR__ . '/_report_tabs.php'; ?>

        <div class="leave-rpt-rules">
            <span class="leave-rpt-rule"><i class="fa-solid fa-clock"></i> 4 hours = 0.5 day</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-calendar-check"></i> 8 hours = 1 full day</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-hourglass-end"></i> Expires in 2 months</span>
        </div>

        <form method="get" class="employee-form leave-rpt-filters">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>" <?php echo $employeeId === (int) $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' · ' . ($e['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        <?php foreach (['Open', 'Partial', 'Used', 'Expired'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>From (work date)</label>
                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($fromDate); ?>">
                </div>
                <div class="form-group">
                    <label>To (work date)</label>
                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($toDate); ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-primary" style="width:100%;">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="leave-rpt-kpis leave-rpt-kpis-5">
            <div class="leave-rpt-kpi kpi-slate">
                <div class="leave-rpt-kpi-label">Records</div>
                <div class="leave-rpt-kpi-value"><?php echo count($rows); ?></div>
                <div class="leave-rpt-kpi-sub">In current filter</div>
            </div>
            <div class="leave-rpt-kpi kpi-blue">
                <div class="leave-rpt-kpi-label">Credited</div>
                <div class="leave-rpt-kpi-value"><?php echo number_format($sumCredit, 2); ?></div>
                <div class="leave-rpt-kpi-sub">Total days earned</div>
            </div>
            <div class="leave-rpt-kpi kpi-amber">
                <div class="leave-rpt-kpi-label">Used</div>
                <div class="leave-rpt-kpi-value"><?php echo number_format($sumUsed, 2); ?></div>
                <div class="leave-rpt-kpi-sub"><?php echo $cntUsed; ?> fully used</div>
            </div>
            <div class="leave-rpt-kpi kpi-green">
                <div class="leave-rpt-kpi-label">Available</div>
                <div class="leave-rpt-kpi-value green"><?php echo number_format($avail !== null ? (float) $avail : $sumRem, 2); ?></div>
                <div class="leave-rpt-kpi-sub"><?php echo $cntOpen; ?> open credits</div>
            </div>
            <div class="leave-rpt-kpi kpi-red">
                <div class="leave-rpt-kpi-label">Expired</div>
                <div class="leave-rpt-kpi-value red"><?php echo $cntExpired; ?></div>
                <div class="leave-rpt-kpi-sub">Wiped after 2 months</div>
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
                        <th class="date">Work Date</th>
                        <th class="ctr">Source</th>
                        <th class="ctr">Hours</th>
                        <th class="ctr">Credit</th>
                        <th class="ctr">Used</th>
                        <th class="ctr">Remaining</th>
                        <th class="date">Earned</th>
                        <th class="date">Expires</th>
                        <th class="ctr">Status</th>
                        <th class="txt">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="14">
                            <div class="leave-rpt-empty">
                                <i class="fa-regular fa-calendar-xmark"></i>
                                No C-Off credits found. Rebuild attendance for months where staff worked on Week Off / Holiday.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                        $hrs = round(((int) ($r['working_minutes'] ?? 0)) / 60, 2);
                        $rem = (float) ($r['remaining_days'] ?? 0);
                        $stLabel = (string) ($r['status'] ?? '');
                        $badgeClass = 'leave-badge-open';
                        if ($stLabel === 'Partial') {
                            $badgeClass = 'leave-badge-partial';
                        } elseif ($stLabel === 'Used') {
                            $badgeClass = 'leave-badge-used';
                        } elseif ($stLabel === 'Expired') {
                            $badgeClass = 'leave-badge-expired';
                        }
                        $src = (string) ($r['source_type'] ?? '');
                        $srcClass = stripos($src, 'Holiday') !== false ? 'leave-badge-holiday' : 'leave-badge-weekoff';
                        $exp = (string) ($r['expires_at'] ?? '');
                        $rowClass = '';
                        if ($stLabel === 'Expired') {
                            $rowClass = 'is-danger';
                        } elseif ($stLabel === 'Open' && $rem > 0 && $exp !== '' && $exp <= $soonLimit) {
                            $rowClass = 'is-highlight';
                        } elseif ($stLabel === 'Open' && $rem > 0) {
                            $rowClass = 'is-ok';
                        }
                        $expClass = 'leave-expiry-ok';
                        if ($stLabel === 'Expired' || ($exp !== '' && $exp < $today)) {
                            $expClass = 'leave-expiry-soon';
                        } elseif ($exp !== '' && $exp <= $soonLimit) {
                            $expClass = 'leave-expiry-soon';
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td class="sr"><?php echo $i + 1; ?></td>
                            <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                            <td class="emp-name"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                            <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                            <td class="date"><?php echo htmlspecialchars(formatDateDisplay($r['work_date'] ?? '')); ?></td>
                            <td class="ctr"><span class="leave-badge <?php echo $srcClass; ?>"><?php echo htmlspecialchars($src); ?></span></td>
                            <td class="ctr"><?php echo number_format($hrs, 2); ?></td>
                            <td class="ctr"><strong><?php echo number_format((float) $r['credit_days'], 2); ?></strong></td>
                            <td class="ctr"><?php echo number_format((float) $r['used_days'], 2); ?></td>
                            <td class="ctr">
                                <?php if ($rem > 0.001 && $stLabel !== 'Expired'): ?>
                                    <span class="leave-rem-hi"><?php echo number_format($rem, 2); ?></span>
                                <?php else: ?>
                                    <span class="leave-rem-zero"><?php echo number_format($rem, 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="date"><?php echo htmlspecialchars(formatDateDisplay($r['earned_at'] ?? '')); ?></td>
                            <td class="date <?php echo $expClass; ?>"><?php echo htmlspecialchars(formatDateDisplay($exp)); ?></td>
                            <td class="ctr"><span class="leave-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($stLabel); ?></span></td>
                            <td class="txt" style="max-width:220px;white-space:normal;font-size:12px;color:#64748b;">
                                <?php echo htmlspecialchars($r['remarks'] ?? ''); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
