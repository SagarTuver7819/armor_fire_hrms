<?php
/**
 * Salary register Excel (HTML .xls) — same columns as on-screen register
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$type = (string) ($_GET['type'] ?? 'salary');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if (!isset(salaryRegisterTypes()[$type])) {
    $type = 'salary';
}

$groups = getSalaryRegisterData($type, $month, $year, $deptId, $employeeId);
$isActual = ($type === 'jobwork_actual');
$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';

$filename = 'Salary_Register_' . $type . '_' . $monthLabel . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$earnSpan = $isActual ? 3 : 6;
$headers = $isActual
    ? ['Department', 'Designation', 'D.O.J.', 'UAN Number', 'Employee Name', 'Qty', 'Actual Amount', 'Gross Salary']
    : ['Department', 'Designation', 'D.O.J.', 'UAN Number', 'Employee Name', 'Salary', 'Present Days', 'Week Off', 'P.L.', 'S.L.', 'D.L.', 'Total Days', 'Gross Salary'];
$headers = array_merge($headers, [
    'P.F.', 'P.T.', 'Loan', 'Advance', 'Total Deduction', 'Salary Arrears', 'Net Salary',
    'Account Number', 'IFSC Code', 'Remarks',
]);

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="' . count($headers) . '" style="background:#1e3a5f;color:#fff;font-size:16px;">'
    . htmlspecialchars($companyName) . ' — Salary Register — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year)))
    . ' (Month days ' . $monthDays . ')</th></tr>';

foreach ($groups as $g) {
    echo '<tr><th colspan="' . count($headers) . '" style="background:#94a3b8;color:#111;text-align:left;">'
        . htmlspecialchars($g['title'] . ' — ' . $g['subtitle']) . '</th></tr>';
    echo '<tr>';
    echo '<th colspan="5"></th>';
    echo '<th colspan="' . $earnSpan . '" style="background:#86efac;">Earning</th>';
    if (!$isActual) {
        echo '<th colspan="2" style="background:#bbf7d0;">Gross</th>';
    }
    echo '<th colspan="4" style="background:#fde047;">Deduction</th>';
    echo '<th colspan="3" style="background:#facc15;">NET</th>';
    echo '<th colspan="3"></th>';
    echo '</tr><tr>';
    foreach ($headers as $h) {
        echo '<th style="background:#e2e8f0;">' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    if (!$g['rows']) {
        echo '<tr><td colspan="' . count($headers) . '">No employees</td></tr>';
        continue;
    }
    foreach ($g['rows'] as $r) {
        $cells = [
            $r['department'], $r['designation'], $r['doj'], $r['uan'], $r['employee_name'],
        ];
        if ($isActual) {
            $cells[] = $r['qty'];
            $cells[] = $r['actual'];
            $cells[] = $r['gross'];
        } else {
            $cells = array_merge($cells, [
                $r['salary'], $r['present'], $r['week_off'], $r['pl'], $r['sl'], $r['dl'], $r['total_days'], $r['gross'],
            ]);
        }
        $cells = array_merge($cells, [
            $r['pf'], $r['pt'], $r['loan'], $r['advance'], $r['total_deduction'], $r['arrears'], $r['net'],
            $r['account'], $r['ifsc'], $r['remarks'],
        ]);
        echo '<tr>';
        foreach ($cells as $c) {
            echo '<td>' . htmlspecialchars((string) $c) . '</td>';
        }
        echo '</tr>';
    }
}
echo '</table></body></html>';
exit;
