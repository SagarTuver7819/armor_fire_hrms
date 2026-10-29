<?php
/**
 * Salary register Excel (HTML .xls) — same columns as on-screen register
 * Department-wise totals + Grand Total; money columns rounded (.50+)
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

$colCount = $isActual ? 21 : 24;
$earnSpan = $isActual ? 3 : 7;
$headers = $isActual
    ? ['Employee Code', 'Employee Name', 'Designation', 'D.O.J.', 'UAN Number', 'Qty', 'Actual Amount', 'Gross Salary']
    : ['Employee Code', 'Employee Name', 'Designation', 'D.O.J.', 'UAN Number', 'Salary', 'Present Days', 'Week Off', 'Holiday', 'P.L.', 'S.L.', 'D.L.', 'Total Days', 'Gross Salary'];
$headers = array_merge($headers, [
    'Employee PF', 'P.T.', 'Loan', 'Advance', 'Total Deduction', 'Salary Arrears', 'Net Salary',
    'Account Number', 'IFSC Code', 'Remarks',
]);

/**
 * Plain Excel cell (normal display — no ="..." formula wrapper).
 */
function registerExcelTd($value)
{
    $v = trim((string) $value);
    if ($v === '' || $v === '-') {
        return '<td></td>';
    }
    return '<td>' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</td>';
}

/**
 * Excel text cell — Account / UAN as text (leading zeros, no 8.66E+12).
 * Uses SpreadsheetML Type=String so value shows clean like software.
 */
function registerExcelTextTd($value)
{
    $v = trim((string) $value);
    if ($v === '' || $v === '-') {
        return '<td></td>';
    }
    $safe = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    return '<td class="txt" style="mso-number-format:\'\\@\';" x:str="' . $safe . '">' . $safe . '</td>';
}

/**
 * Write department / grand total row aligned to money columns.
 */
function registerExcelTotalRow(array $sum, $isActual, $label, $bg)
{
    echo '<tr style="background:' . htmlspecialchars($bg) . ';font-weight:700;">';
    echo '<td colspan="5"><strong>' . htmlspecialchars($label) . '</strong></td>';
    if ($isActual) {
        echo '<td></td>';
        echo '<td align="right"><strong>' . (int) $sum['actual'] . '</strong></td>';
        echo '<td align="right"><strong>' . (int) $sum['gross'] . '</strong></td>';
    } else {
        echo '<td align="right"><strong>' . (int) $sum['salary'] . '</strong></td>';
        echo '<td colspan="7"></td>';
        echo '<td align="right"><strong>' . (int) $sum['gross'] . '</strong></td>';
    }
    echo '<td align="right"><strong>' . (int) $sum['pf'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['pt'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['loan'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['advance'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['total_deduction'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['arrears'] . '</strong></td>';
    echo '<td align="right"><strong>' . (int) $sum['net'] . '</strong></td>';
    echo '<td colspan="3"></td>';
    echo '</tr>';
}

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
echo '<x:Name>Salary Register</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>td.txt{mso-number-format:\\@;}</style>';
echo '</head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="' . count($headers) . '" style="background:#1e3a5f;color:#fff;font-size:16px;">'
    . htmlspecialchars($companyName) . ' — Salary Register — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year)))
    . ' (Month days ' . $monthDays . ')</th></tr>';

$grandRows = [];

foreach ($groups as $g) {
    echo '<tr><th colspan="' . count($headers) . '" style="background:#94a3b8;color:#111;text-align:left;">'
        . htmlspecialchars($g['title'] . ' — ' . $g['subtitle']) . '</th></tr>';
    echo '<tr>';
    echo '<th colspan="5"></th>';
    echo '<th colspan="' . $earnSpan . '" style="background:#86efac;">Earning</th>';
    if (!$isActual) {
        echo '<th colspan="2" style="background:#bbf7d0;">Gross</th>';
    }
    echo '<th colspan="5" style="background:#fde047;">Deduction</th>';
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
        $grandRows[] = $r;
        echo '<tr>';
        echo registerExcelTd($r['employee_code'] ?? '');
        echo registerExcelTd($r['employee_name'] ?? '');
        echo registerExcelTd($r['designation'] ?? '');
        echo registerExcelTd($r['doj'] ?? '');
        echo registerExcelTextTd($r['uan'] ?? '');
        if ($isActual) {
            echo registerExcelTd($r['qty'] ?? '');
            echo '<td>' . (int) registerRoundMoney($r['actual'] ?? 0) . '</td>';
            echo '<td>' . (int) registerRoundMoney($r['gross'] ?? 0) . '</td>';
        } else {
            echo '<td>' . (int) registerRoundMoney($r['salary'] ?? 0) . '</td>';
            echo registerExcelTd($r['present'] ?? '');
            echo registerExcelTd($r['week_off'] ?? '');
            echo registerExcelTd($r['holiday'] ?? 0);
            echo registerExcelTd($r['pl'] ?? '');
            echo registerExcelTd($r['sl'] ?? '');
            echo registerExcelTd($r['dl'] ?? '');
            echo registerExcelTd($r['total_days'] ?? '');
            echo '<td>' . (int) registerRoundMoney($r['gross'] ?? 0) . '</td>';
        }
        echo '<td>' . (int) registerRoundMoney($r['pf'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['pt'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['loan'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['advance'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['total_deduction'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['arrears'] ?? 0) . '</td>';
        echo '<td>' . (int) registerRoundMoney($r['net'] ?? 0) . '</td>';
        echo registerExcelTextTd($r['account'] ?? '');
        echo registerExcelTd($r['ifsc'] ?? '');
        echo registerExcelTd($r['remarks'] ?? '');
        echo '</tr>';
    }
    $deptSum = registerSumMoneyColumns($g['rows']);
    registerExcelTotalRow($deptSum, $isActual, 'Department Total', '#f1f5f9');
}

if ($grandRows) {
    $grandSum = registerSumMoneyColumns($grandRows);
    registerExcelTotalRow($grandSum, $isActual, 'Grand Total', '#1e3a5f;color:#fff');
}

echo '</table></body></html>';
exit;
