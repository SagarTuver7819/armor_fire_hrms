<?php
/**
 * Monthly salary diary (attendance days)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensurePayrollTables();
$conn = getDBConnection();
ensureEmployeesTable($conn);
$stmt = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, e.pay_type, e.week_off_day,
            d.working_days, d.present_days, d.week_off_days, d.pl_days, d.sl_days, d.dl_days,
            d.overtime_hours, d.loan_amount, d.advance_amount, d.arrears_amount
     FROM employees e
     LEFT JOIN salary_diary d
       ON d.employee_id = e.id AND d.month_no = ? AND d.year_no = ?
     WHERE e.status = 1 AND e.department_id = ? AND e.pay_type IN ('Salary','Jobwork')
     ORDER BY e.pay_type ASC, e.employee_code ASC"
);
$stmt->bind_param('iii', $month, $year, $deptId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$pageTitle = 'Salary Diary';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'diary';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Modules
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Salary Diary</h1>
                <p><?php echo htmlspecialchars($department['department_name']); ?> · Present, week off, leaves, loan / advance (Salary + Jobwork)</p>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:16px;">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-grid form-grid-3">
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

        <form method="POST" action="<?php echo app_url('payroll/diary_save.php'); ?>" class="employee-form">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            <div class="table-wrap">
                <table class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Working</th>
                            <th>Present</th>
                            <th>Week Off</th>
                            <th>P.L.</th>
                            <th>S.L.</th>
                            <th>D.L.</th>
                            <th>OT Hours</th>
                            <th>Loan</th>
                            <th>Advance</th>
                            <th>Arrears</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="13">No Salary / Jobwork employees in this department.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $woDefault = countWeekOffDaysInMonth($month, $year, $r['week_off_day'] ?? 'Sunday');
                        $presentDefault = max(0, $monthDays - $woDefault);
                        $workingVal = $r['working_days'] ?? $monthDays;
                        $presentVal = ((float) ($r['present_days'] ?? 0) > 0) ? $r['present_days'] : $presentDefault;
                        $woVal = ((float) ($r['week_off_days'] ?? 0) > 0) ? $r['week_off_days'] : $woDefault;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['employee_code']); ?></td>
                            <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars(function_exists('payTypeLabel') ? payTypeLabel($r['pay_type']) : $r['pay_type']); ?></td>
                            <td>
                                <input type="hidden" name="emp_id[]" value="<?php echo (int) $r['id']; ?>">
                                <input type="number" step="0.5" name="working[]" class="form-control" value="<?php echo htmlspecialchars((string) $workingVal); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="present[]" class="form-control" value="<?php echo htmlspecialchars((string) $presentVal); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="week_off[]" class="form-control" value="<?php echo htmlspecialchars((string) $woVal); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="pl[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['pl_days'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="sl[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['sl_days'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="dl[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['dl_days'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.5" name="ot[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['overtime_hours'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="loan[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['loan_amount'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="advance[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['advance_amount'] ?? 0)); ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="arrears[]" class="form-control" value="<?php echo htmlspecialchars((string) ($r['arrears_amount'] ?? 0)); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Diary</button>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
