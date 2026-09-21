<?php
/**
 * Department cost summary Excel
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$deptId = (int) ($_GET['department_id'] ?? 0);
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$data = getDepartmentMonthlyCostSummary($month, $year, $deptId);
$sum = $data['summary'];
$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Dept_Cost_' . $monthLabel . '.xls"');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="11" style="background:#1e3a5f;color:#fff;">'
    . htmlspecialchars($companyName) . ' — Department Cost — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year)))
    . '</th></tr>';
$headers = ['Department', 'Employees', 'Fixed Gross', 'JW Govt', 'JW Actual', 'Total Gross', 'PF', 'PT', 'Loan+Adv', 'Deductions', 'Net Cost'];
echo '<tr>';
foreach ($headers as $h) {
    echo '<th style="background:#f58220;color:#fff;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($data['rows'] as $r) {
    echo '<tr>';
    $cells = [
        $r['department'], $r['employees'],
        $r['by_type']['salary']['gross'], $r['by_type']['jobwork_govt']['gross'], $r['by_type']['jobwork_actual']['gross'],
        $r['gross'], $r['pf'], $r['pt'], $r['loan'] + $r['advance'], $r['deduction'], $r['net'],
    ];
    foreach ($cells as $c) {
        echo '<td>' . htmlspecialchars((string) $c) . '</td>';
    }
    echo '</tr>';
}
echo '<tr style="font-weight:700;background:#eef6ff;">';
$tot = ['TOTAL', $sum['employees'], '', '', '', $sum['gross'], $sum['pf'], $sum['pt'], $sum['loan'] + $sum['advance'], $sum['deduction'], $sum['net']];
foreach ($tot as $c) {
    echo '<td>' . htmlspecialchars((string) $c) . '</td>';
}
echo '</tr>';
echo '<tr><td colspan="11"></td></tr>';
echo '<tr><th colspan="2">Final Net Payable</th><td colspan="9"><strong>' . number_format($sum['net'], 2) . '</strong></td></tr>';
echo '</table></body></html>';
exit;
