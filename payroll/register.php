<?php
/**
 * Monthly salary register — Normal / Jobwork Govt / Jobwork Actual / Contractor Main
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/settings.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$type = (string) ($_GET['type'] ?? 'salary');
$view = (($_GET['view'] ?? 'department') === 'employee') ? 'employee' : 'department';
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if (!isset(salaryRegisterTypes()[$type])) {
    $type = 'salary';
}

$department = $deptId > 0 ? getDepartmentById($deptId) : null;
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$groups = getSalaryRegisterData($type, $month, $year, $deptId, $employeeId);
if ($view === 'employee' && $type !== 'contractor_main') {
    $flat = [];
    foreach ($groups as $g) {
        foreach ($g['rows'] as $row) {
            $flat[] = $row;
        }
    }
    usort($flat, function ($a, $b) {
        return strcasecmp($a['employee_name'], $b['employee_name']);
    });
    $groups = [[
        'title' => salaryRegisterTypes()[$type] . ' · Employee wise',
        'subtitle' => $department ? $department['department_name'] : 'All departments',
        'mode' => $type,
        'rows' => $flat,
    ]];
}

$filterPay = 'Salary';
if ($type === 'jobwork_govt' || $type === 'jobwork_actual') {
    $filterPay = 'Jobwork';
} elseif ($type === 'contractor_main') {
    $filterPay = 'ContractorMain';
}
$empOptions = fetchSalaryRegisterEmployees($deptId, 0, [$filterPay]);

$pageTitle = 'Salary Register';
$useSidebar = true;
$sidebarMode = $deptId > 0 ? 'department' : 'workspace';
$sidebarDeptId = $deptId;
$sidebarActive = 'salary_register';
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$isActual = ($type === 'jobwork_actual');

$excelQs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'view' => $view,
]);

require_once __DIR__ . '/../includes/header.php';
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
        <div class="register-toolbar-actions">
            <a class="btn-secondary" href="<?php echo app_url('payroll/register_excel.php?' . $excelQs); ?>">
                <i class="fa-solid fa-file-excel"></i> Excel
            </a>
            <button type="button" class="btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="sr-print-heading">
        <strong><?php echo htmlspecialchars($companyName); ?></strong>
        — Salary Register · <?php echo htmlspecialchars($monthLabel); ?>
    </div>

    <div class="form-page-card no-print-shadow">
        <div class="form-page-header">
            <div>
                <h1>Salary Register</h1>
                <p><?php echo htmlspecialchars($companyName); ?> · <?php echo htmlspecialchars($monthLabel); ?> · Month days <?php echo $monthDays; ?></p>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Register Type</label>
                    <select name="type" class="form-control" onchange="this.form.submit()">
                        <?php foreach (salaryRegisterTypes() as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $type === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>View</label>
                    <select name="view" class="form-control" onchange="this.form.submit()">
                        <option value="department" <?php echo $view === 'department' ? 'selected' : ''; ?>>Department wise</option>
                        <option value="employee" <?php echo $view === 'employee' ? 'selected' : ''; ?>>Employee wise</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All departments</option>
                        <?php foreach (getActiveMasterRows('departments', 'sort_order ASC, department_name ASC') as $d): ?>
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
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All employees</option>
                        <?php foreach ($empOptions as $eo): ?>
                            <option value="<?php echo (int) $eo['id']; ?>" <?php echo $employeeId === (int) $eo['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($eo['employee_code'] ?? '') . ' — ' . ($eo['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($type === 'jobwork_govt'): ?>
            <p class="form-hint">Jobwork <strong>Government</strong>: Actual (Ops Qty × Rate) ÷ <?php echo $monthDays; ?> month days × paid days from <strong>Attendance</strong> (Present + Week Off + P.L. + S.L. + D.L.). Enter Manual Attendance first, then open this register / Generate Salary.</p>
        <?php elseif ($type === 'jobwork_actual'): ?>
            <p class="form-hint">Jobwork <strong>Regular / Actual</strong>: full production amount (Qty × Rate) from Operations Rate List. No attendance day split — contract base earning.</p>
        <?php elseif ($type === 'contractor_main'): ?>
            <p class="form-hint">Contractor Main: under employees, government-style day split using each member’s attendance. Link Jobwork employees to a Contractor Main on the employee form.</p>
        <?php else: ?>
            <p class="form-hint">Normal <strong>Fixed Salary</strong>: Gross = Decided Salary × (Present + Week Off + P.L. + S.L. + D.L.) ÷ month days — from Manual Attendance / Import.</p>
        <?php endif; ?>
    </div>

    <?php foreach ($groups as $g): ?>
        <?php
        $rows = $g['rows'];
        $sumGross = 0;
        $sumNet = 0;
        $sumActual = 0;
        foreach ($rows as $r) {
            $sumGross += (float) $r['gross'];
            $sumNet += (float) $r['net'];
            $sumActual += (float) $r['actual'];
        }
        ?>
        <div class="salary-register-sheet">
            <div class="salary-register-banner">
                <strong><?php echo htmlspecialchars($g['title']); ?></strong>
                <span><?php echo htmlspecialchars($g['subtitle']); ?> · <?php echo htmlspecialchars($monthLabel); ?></span>
            </div>
            <div class="table-wrap">
                <table class="salary-register-table">
                    <thead>
                        <tr class="sr-group-row">
                            <th colspan="5"></th>
                            <?php if ($isActual): ?>
                                <th colspan="3" class="sr-earn">Earning</th>
                            <?php else: ?>
                                <th colspan="6" class="sr-earn">Earning</th>
                                <th colspan="2" class="sr-gross">Gross</th>
                            <?php endif; ?>
                            <th colspan="4" class="sr-ded">Deduction</th>
                            <th colspan="3" class="sr-net">NET</th>
                            <th colspan="3"></th>
                        </tr>
                        <tr>
                            <th>Dept</th>
                            <th>Desig.</th>
                            <th>D.O.J.</th>
                            <th>UAN</th>
                            <th>Employee Name</th>
                            <?php if ($isActual): ?>
                                <th>Qty</th>
                                <th>Actual Amt</th>
                                <th>Gross</th>
                            <?php else: ?>
                                <th>Salary</th>
                                <th>P.Days</th>
                                <th>W.Off</th>
                                <th>P.L.</th>
                                <th>S.L.</th>
                                <th>D.L.</th>
                                <th>Total Days</th>
                                <th>Gross</th>
                            <?php endif; ?>
                            <th>P.F.</th>
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
                        <tr><td colspan="23" class="empty-cell">No employees for this register.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['department'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['designation']); ?></td>
                            <td><?php echo htmlspecialchars($r['doj']); ?></td>
                            <td><?php echo htmlspecialchars($r['uan'] ?: '-'); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['employee_name']); ?></strong>
                                <div class="sr-code"><?php echo htmlspecialchars($r['employee_code']); ?></div>
                                <?php if ($r['main_contractor']): ?>
                                    <div class="sr-code">Under: <?php echo htmlspecialchars($r['main_contractor']); ?></div>
                                <?php endif; ?>
                            </td>
                            <?php if ($isActual): ?>
                                <td class="num"><?php echo registerNum($r['qty'], 2); ?></td>
                                <td class="num"><?php echo registerNum($r['actual']); ?></td>
                                <td class="num"><?php echo registerNum($r['gross']); ?></td>
                            <?php else: ?>
                                <td class="num"><?php echo registerNum($r['salary']); ?></td>
                                <td class="num"><?php echo registerNum($r['present'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['week_off'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['pl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['sl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['dl'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['total_days'], 1); ?></td>
                                <td class="num"><?php echo registerNum($r['gross']); ?></td>
                            <?php endif; ?>
                            <td class="num"><?php echo registerNum($r['pf']); ?></td>
                            <td class="num"><?php echo registerNum($r['pt']); ?></td>
                            <td class="num"><?php echo registerNum($r['loan']); ?></td>
                            <td class="num"><?php echo registerNum($r['advance']); ?></td>
                            <td class="num"><?php echo registerNum($r['total_deduction']); ?></td>
                            <td class="num"><?php echo registerNum($r['arrears']); ?></td>
                            <td class="num"><strong><?php echo registerNum($r['net']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['account'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['remarks'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php if ($rows): ?>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo $isActual ? 7 : 12; ?>"><strong>Total</strong></td>
                            <td class="num"><strong><?php echo registerNum($sumGross); ?></strong></td>
                            <td colspan="4"></td>
                            <td></td>
                            <td></td>
                            <td class="num"><strong><?php echo registerNum($sumNet); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
