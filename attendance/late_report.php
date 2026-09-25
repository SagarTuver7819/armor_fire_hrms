<?php
/**
 * Late / Early Flex Report
 * Policy: ≤1 hour late OR early, max 2 flex / month (not both same day).
 * Excess → Half PL; if no PL → Half LWP.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('attendance', 'view', $deptId);

$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$show = isset($_GET['show']) || isset($_GET['department_id']) || isset($_GET['month']);

$conn = getDBConnection();
ensureAttendanceTables($conn);

$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}

$employees = [];
$eq = $conn->query(
    "SELECT id, employee_code, employee_name, department_id
     FROM employees WHERE status = 1
     ORDER BY employee_code ASC"
);
if ($eq) {
    while ($r = $eq->fetch_assoc()) {
        $employees[] = $r;
    }
}

$report = $show ? attendanceLatePunchReport($year, $month, $deptId, $employeeId, $conn) : null;
$conn->close();

$window = attendanceFlexWindowMinutes();
$allowed = attendanceFlexAllowedPerMonth();
$totEmp = (int) ($report['totals']['employees'] ?? 0);
$totLate = (int) ($report['totals']['late_punches'] ?? 0);
$totHalf = (int) ($report['totals']['penalty_half_days'] ?? 0);
$totPl = (float) ($report['totals']['penalty_pl'] ?? 0);
$totLwp = (float) ($report['totals']['penalty_lwp'] ?? 0);

$pageTitle = 'Late / Early Report';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_late';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Attendance
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/report.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-chart-simple"></i> Attendance Report
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Late / Early Flex Report</h1>
            <p>
                <?php echo (int) ($window / 60); ?> hour late <em>or</em> early ·
                <?php echo $allowed; ?> flex / month · not both same day ·
                excess → Half PL, else Half LWP
            </p>
        </div>

        <div class="leave-rpt-rules">
            <span class="leave-rpt-rule"><i class="fa-solid fa-clock"></i> ≤ <?php echo $window; ?> min late after shift</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-door-open"></i> ≤ <?php echo $window; ?> min early before end</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-hashtag"></i> <?php echo $allowed; ?> flex / month (late or early)</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-ban"></i> Late + Early same day = penalty</span>
            <span class="leave-rpt-rule"><i class="fa-solid fa-scale-balanced"></i> Penalty: Half PL → else Half LWP</span>
        </div>

        <form method="GET" class="employee-form leave-rpt-filters">
            <input type="hidden" name="show" value="1">
            <div class="form-grid form-grid-4">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" id="lateDeptFilter" class="form-control">
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
                    <select name="employee_id" id="lateEmpFilter" class="form-control">
                        <option value="0" data-dept="0">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>"
                                    data-dept="<?php echo (int) $e['department_id']; ?>"
                                    <?php echo $employeeId === (int) $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" class="form-control">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m === $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
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
            </div>
            <div class="form-group" style="margin-top:8px;max-width:220px;">
                <button type="submit" class="btn-primary" style="width:100%;">
                    <i class="fa-solid fa-filter"></i> Show Report
                </button>
            </div>
        </form>

        <?php if ($show && $report): ?>
            <div class="leave-rpt-kpis leave-rpt-kpis-5">
                <div class="leave-rpt-kpi kpi-slate">
                    <div class="leave-rpt-kpi-label">Employees</div>
                    <div class="leave-rpt-kpi-value"><?php echo $totEmp; ?></div>
                    <div class="leave-rpt-kpi-sub"><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?></div>
                </div>
                <div class="leave-rpt-kpi kpi-amber">
                    <div class="leave-rpt-kpi-label">Late / Early Events</div>
                    <div class="leave-rpt-kpi-value accent"><?php echo $totLate; ?></div>
                    <div class="leave-rpt-kpi-sub">Within or beyond flex</div>
                </div>
                <div class="leave-rpt-kpi kpi-red">
                    <div class="leave-rpt-kpi-label">Penalties</div>
                    <div class="leave-rpt-kpi-value red"><?php echo $totHalf; ?></div>
                    <div class="leave-rpt-kpi-sub">Half-day deductions</div>
                </div>
                <div class="leave-rpt-kpi kpi-green">
                    <div class="leave-rpt-kpi-label">Half PL</div>
                    <div class="leave-rpt-kpi-value green"><?php echo number_format($totPl, 1); ?></div>
                    <div class="leave-rpt-kpi-sub">From PL balance</div>
                </div>
                <div class="leave-rpt-kpi kpi-blue">
                    <div class="leave-rpt-kpi-label">Half LWP</div>
                    <div class="leave-rpt-kpi-value"><?php echo number_format($totLwp, 1); ?></div>
                    <div class="leave-rpt-kpi-sub">No PL balance</div>
                </div>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-users" style="color:var(--brand);"></i> Employee-wise Summary
            </h3>
            <div class="leave-rpt-table-wrap" style="margin-bottom:22px;">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="txt">Department</th>
                            <th class="ctr">Late</th>
                            <th class="ctr">Early</th>
                            <th class="ctr">Flex Used</th>
                            <th class="ctr">Penalty</th>
                            <th class="num">Half PL</th>
                            <th class="num">Half LWP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report['summary_rows'])): ?>
                        <tr>
                            <td colspan="10">
                                <div class="leave-rpt-empty">
                                    <i class="fa-solid fa-user-check"></i>
                                    No late/early events. Rebuild attendance after import to apply flex rules.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['summary_rows'] as $i => $r): ?>
                            <tr class="<?php echo (int) ($r['penalty_half_days'] ?? 0) > 0 ? 'is-danger' : ''; ?>">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                                <td class="ctr"><?php echo (int) ($r['late_count'] ?? 0); ?></td>
                                <td class="ctr"><?php echo (int) ($r['early_count'] ?? 0); ?></td>
                                <td class="ctr"><strong><?php echo (int) ($r['allowed_used'] ?? 0); ?></strong> / <?php echo $allowed; ?></td>
                                <td class="ctr"><?php echo (int) ($r['penalty_half_days'] ?? 0); ?></td>
                                <td class="num"><?php echo number_format((float) ($r['penalty_pl'] ?? 0), 1); ?></td>
                                <td class="num"><?php echo number_format((float) ($r['penalty_lwp'] ?? 0), 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin:8px 0 12px;font-size:15px;font-weight:800;color:#1a2332;">
                <i class="fa-solid fa-list" style="color:var(--brand);"></i> Day-wise Details
            </h3>
            <div class="leave-rpt-table-wrap">
                <table class="leave-rpt-table">
                    <thead>
                        <tr>
                            <th class="sr">Sr</th>
                            <th class="txt">Date</th>
                            <th class="txt">Code</th>
                            <th class="txt">Employee</th>
                            <th class="txt">Department</th>
                            <th class="ctr">Shift</th>
                            <th class="ctr">In</th>
                            <th class="ctr">Out</th>
                            <th class="num">Late</th>
                            <th class="num">Early</th>
                            <th class="ctr">Flex #</th>
                            <th class="txt">Result</th>
                            <th class="txt">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($report['rows'])): ?>
                        <tr>
                            <td colspan="13">
                                <div class="leave-rpt-empty">
                                    <i class="fa-solid fa-clock"></i>
                                    No detail rows.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['rows'] as $i => $r): ?>
                            <?php
                            $shiftIn = !empty($r['shift_start']) ? date('g:i A', strtotime($r['shift_start'])) : '—';
                            $shiftOut = !empty($r['shift_end']) ? date('g:i A', strtotime($r['shift_end'])) : '—';
                            $inDisp = !empty($r['punch_in']) ? date('g:i A', strtotime($r['punch_in'])) : '—';
                            $outDisp = !empty($r['punch_out']) ? date('g:i A', strtotime($r['punch_out'])) : '—';
                            $penalty = strtoupper(trim((string) ($r['penalty_leave'] ?? '')));
                            $penaltyRow = $penalty !== '';
                            ?>
                            <tr class="<?php echo $penaltyRow ? 'is-danger' : ''; ?>">
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="txt"><?php echo htmlspecialchars(date('d-m-Y', strtotime($r['attendance_date']))); ?></td>
                                <td class="emp-code"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($r['department_name'] ?? ''); ?></td>
                                <td class="ctr" style="font-size:12px;"><?php echo htmlspecialchars($shiftIn . '–' . $shiftOut); ?></td>
                                <td class="ctr"><?php echo htmlspecialchars($inDisp); ?></td>
                                <td class="ctr"><?php echo htmlspecialchars($outDisp); ?></td>
                                <td class="num"><?php echo (int) ($r['late_minutes'] ?? 0); ?></td>
                                <td class="num"><?php echo (int) ($r['early_minutes'] ?? 0); ?></td>
                                <td class="ctr"><?php echo (int) ($r['flex_seq'] ?? 0) ?: '—'; ?></td>
                                <td class="txt">
                                    <?php if ($penalty === 'PL'): ?>
                                        <span style="color:#047857;font-weight:700;">Half PL</span>
                                    <?php elseif ($penalty === 'LWP'): ?>
                                        <span style="color:#b91c1c;font-weight:700;">Half LWP</span>
                                    <?php else: ?>
                                        Flex OK
                                    <?php endif; ?>
                                </td>
                                <td class="txt" style="font-size:12px;"><?php echo htmlspecialchars($r['remarks'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    var dept = document.getElementById('lateDeptFilter');
    var emp = document.getElementById('lateEmpFilter');
    if (!dept || !emp) return;
    function filterEmp() {
        var d = String(dept.value || '0');
        var opts = emp.querySelectorAll('option');
        var keep = emp.value;
        var stillValid = false;
        opts.forEach(function (o) {
            var od = o.getAttribute('data-dept') || '0';
            var show = (o.value === '0') || d === '0' || od === d;
            o.hidden = !show;
            o.disabled = !show;
            if (show && o.value === keep) stillValid = true;
        });
        if (!stillValid) emp.value = '0';
    }
    dept.addEventListener('change', filterEmp);
    filterEmp();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
