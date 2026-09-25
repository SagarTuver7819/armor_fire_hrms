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
        @page { size: A4 landscape; margin: 5mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 14px; background: #fff; }
        h1 { margin: 0 0 4px; font-size: 16px; text-align: center; font-weight: 700; }
        .period-head {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            margin: 6px 0 8px;
            padding: 6px 10px;
            background: #fff4e8;
            border: 1px solid #f5c89a;
            border-radius: 6px;
            color: #d96a0f;
        }
        .sub-head {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            margin: 0 0 10px;
            color: #334155;
        }
        .sheet { margin-bottom: 14px; overflow-x: auto; }
        .sheet-banner {
            background: #94a3b8;
            color: #0f172a;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid #64748b;
            border-bottom: 0;
        }
        .sheet-banner span { font-weight: 600; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
            table-layout: auto;
        }
        th, td {
            border: 1px solid #94a3b8;
            padding: 4px 5px;
            text-align: center;
            vertical-align: middle;
            font-weight: 600;
            white-space: nowrap;
            word-break: keep-all;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th { background: #e2e8f0; font-weight: 700; color: #0f172a; }
        td.num, th.num { text-align: center; font-variant-numeric: tabular-nums; }
        td.emp-cell {
            text-align: center;
            white-space: nowrap;
            max-width: 140px;
        }
        td.dept-cell { max-width: 110px; }
        td.remarks-cell { max-width: 90px; }
        .sr-earn { background: #86efac !important; }
        .sr-gross { background: #bbf7d0 !important; }
        .sr-ded { background: #fde047 !important; }
        .sr-net { background: #facc15 !important; }
        .sr-code {
            display: inline;
            font-size: 7px;
            color: #64748b;
            font-weight: 600;
            margin-left: 4px;
        }
        .sr-code::before { content: "("; }
        .sr-code::after { content: ")"; }
        tfoot td { font-weight: 700; background: #f8fafc; text-align: center; white-space: nowrap; }
        .meta {
            color: #555;
            margin: 10px 0 0;
            font-size: 10px;
            text-align: right;
            font-weight: 600;
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
            table { font-size: 6.5px !important; }
            th, td { padding: 3px 4px !important; white-space: nowrap !important; }
            .sheet { page-break-inside: avoid; overflow: visible; }
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
        $colSpanLead = $isActual ? 7 : 13;
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
                                <th colspan="7" class="sr-earn">Earning</th>
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
                            <th class="num">Holiday</th>
                            <th class="num">P.L.</th>
                            <th class="num">S.L.</th>
                            <th class="num">D.L.</th>
                            <th class="num">Total Days</th>
                            <th class="num">Gross</th>
                        <?php endif; ?>
                        <th class="num">Emp PF</th>
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
                    <tr><td colspan="25">No employees for this register.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $deptShow = trim((string) ($r['department'] ?: '-'));
                    $remarksShow = trim((string) ($r['remarks'] ?: '-'));
                    if (function_exists('mb_strlen')) {
                        if (mb_strlen($deptShow) > 28) {
                            $deptShow = mb_substr($deptShow, 0, 26) . '…';
                        }
                        if (mb_strlen($remarksShow) > 24) {
                            $remarksShow = mb_substr($remarksShow, 0, 22) . '…';
                        }
                    } else {
                        if (strlen($deptShow) > 28) {
                            $deptShow = substr($deptShow, 0, 26) . '...';
                        }
                        if (strlen($remarksShow) > 24) {
                            $remarksShow = substr($remarksShow, 0, 22) . '...';
                        }
                    }
                    $empLabel = trim((string) ($r['employee_name'] ?? ''));
                    $empCode = trim((string) ($r['employee_code'] ?? ''));
                    if ($empCode !== '') {
                        $empLabel .= ' (' . $empCode . ')';
                    }
                    if (!empty($r['main_contractor'])) {
                        $empLabel .= ' · ' . $r['main_contractor'];
                    }
                    ?>
                    <tr>
                        <td class="dept-cell" title="<?php echo htmlspecialchars($r['department'] ?: '-'); ?>"><?php echo htmlspecialchars($deptShow); ?></td>
                        <td><?php echo htmlspecialchars($r['designation']); ?></td>
                        <td><?php echo htmlspecialchars($r['doj']); ?></td>
                        <td><?php echo htmlspecialchars($r['uan'] ?: '-'); ?></td>
                        <td class="emp-cell" title="<?php echo htmlspecialchars($empLabel); ?>"><?php echo htmlspecialchars($empLabel); ?></td>
                        <?php if ($isActual): ?>
                            <td class="num"><?php echo registerNum($r['qty'], 2); ?></td>
                            <td class="num"><?php echo registerNum($r['actual']); ?></td>
                            <td class="num"><?php echo registerNum($r['gross']); ?></td>
                        <?php else: ?>
                            <td class="num"><?php echo registerNum($r['salary']); ?></td>
                            <td class="num"><?php echo registerNum($r['present'], 1); ?></td>
                            <td class="num"><?php echo registerNum($r['week_off'], 1); ?></td>
                            <td class="num"><?php echo registerNum($r['holiday'] ?? 0, 1); ?></td>
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
                        <td class="remarks-cell" title="<?php echo htmlspecialchars($r['remarks'] ?: '-'); ?>"><?php echo htmlspecialchars($remarksShow); ?></td>
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

    <div class="meta">Printed <?php echo date('d-m-Y H:i'); ?> · Employee rows: <?php echo $totalRows; ?></div>
</body>
</html>
