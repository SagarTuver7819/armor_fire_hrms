<?php
/**
 * Salary Register — Print / PDF preview
 * Same pattern as Operations Rate List print page.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

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

$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$isActual = ($type === 'jobwork_actual');
$typeLabel = salaryRegisterTypes()[$type] ?? $type;
$deptLabel = $department ? (string) $department['department_name'] : 'All Departments';
$viewLabel = $view === 'employee' ? 'Employee wise' : 'Department wise';

$backQs = http_build_query([
    'type' => $type,
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
    'employee_id' => $employeeId,
    'view' => $view,
]);

$totalRows = 0;
foreach ($groups as $g) {
    $totalRows += count($g['rows']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Register — Print · <?php echo htmlspecialchars($deptLabel); ?> · <?php echo htmlspecialchars($monthLabel); ?></title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 18px; background: #fff; }
        h1 { margin: 0 0 4px; font-size: 18px; text-align: center; }
        .period-head {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            margin: 6px 0 10px;
            padding: 8px 12px;
            background: #fff4e8;
            border: 1px solid #f5c89a;
            border-radius: 6px;
            color: #d96a0f;
        }
        .sub-head {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            margin: 0 0 14px;
            color: #334155;
        }
        .sheet { margin-bottom: 18px; }
        .sheet-banner {
            background: #94a3b8;
            color: #0f172a;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #64748b;
            border-bottom: 0;
        }
        .sheet-banner span { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; table-layout: fixed; }
        th, td { border: 1px solid #94a3b8; padding: 3px 2px; text-align: left; word-wrap: break-word; vertical-align: middle; }
        th { background: #e2e8f0; font-weight: 700; color: #0f172a; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .sr-earn { background: #86efac !important; }
        .sr-gross { background: #bbf7d0 !important; }
        .sr-ded { background: #fde047 !important; }
        .sr-net { background: #facc15 !important; }
        .sr-code { font-size: 7px; color: #64748b; font-weight: 600; }
        tfoot td { font-weight: bold; background: #f8fafc; }
        .meta {
            color: #555;
            margin: 12px 0 0;
            font-size: 11px;
            text-align: right;
        }
        .toolbar { margin-bottom: 12px; text-align: left; }
        .toolbar button, .toolbar a {
            display: inline-block; padding: 8px 14px; margin-right: 8px;
            border: 0; border-radius: 6px; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .btn-print { background: #F58220; color: #fff; }
        .btn-back { background: #e2e8f0; color: #111; }
        @media print {
            .toolbar { display: none !important; }
            body { margin: 0 !important; }
            .period-head { background: #fff4e8 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet-banner, th, .sr-earn, .sr-gross, .sr-ded, .sr-net, tfoot td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            table { font-size: 7px !important; }
            .sheet { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save PDF</button>
        <a class="btn-back" href="<?php echo htmlspecialchars(app_url('payroll/register.php?' . $backQs)); ?>">Back</a>
    </div>

    <h1><?php echo htmlspecialchars($companyName); ?> — Salary Register</h1>
    <div class="period-head">
        <?php echo htmlspecialchars($deptLabel); ?> · <?php echo htmlspecialchars($monthLabel); ?>
        · Month days <?php echo $monthDays; ?>
    </div>
    <div class="sub-head">
        <?php echo htmlspecialchars($typeLabel); ?> · <?php echo htmlspecialchars($viewLabel); ?>
        <?php if ($employeeId > 0): ?>
            · Selected employee
        <?php endif; ?>
    </div>

    <?php foreach ($groups as $g): ?>
        <?php
        $rows = $g['rows'];
        $sumGross = 0;
        $sumNet = 0;
        foreach ($rows as $r) {
            $sumGross += (float) $r['gross'];
            $sumNet += (float) $r['net'];
        }
        $colSpanLead = $isActual ? 7 : 12;
        ?>
        <div class="sheet">
            <div class="sheet-banner">
                <strong><?php echo htmlspecialchars($g['title']); ?></strong>
                <span><?php echo htmlspecialchars($g['subtitle']); ?> · <?php echo htmlspecialchars($monthLabel); ?></span>
            </div>
            <table>
                <thead>
                    <tr>
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
                            <th class="num">Qty</th>
                            <th class="num">Actual Amt</th>
                            <th class="num">Gross</th>
                        <?php else: ?>
                            <th class="num">Salary</th>
                            <th class="num">P.Days</th>
                            <th class="num">W.Off</th>
                            <th class="num">P.L.</th>
                            <th class="num">S.L.</th>
                            <th class="num">D.L.</th>
                            <th class="num">Total Days</th>
                            <th class="num">Gross</th>
                        <?php endif; ?>
                        <th class="num">P.F.</th>
                        <th class="num">P.T.</th>
                        <th class="num">Loan</th>
                        <th class="num">Adv.</th>
                        <th class="num">Tot. Ded.</th>
                        <th class="num">Arrears</th>
                        <th class="num">Net</th>
                        <th>A/C No.</th>
                        <th>IFSC</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="23">No employees for this register.</td></tr>
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
                            <?php if (!empty($r['main_contractor'])): ?>
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
                        <td colspan="<?php echo $colSpanLead; ?>"><strong>Total</strong></td>
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
    <?php endforeach; ?>

    <div class="meta">Printed <?php echo date('d/m/Y H:i'); ?> · Employee rows: <?php echo $totalRows; ?></div>
</body>
</html>
