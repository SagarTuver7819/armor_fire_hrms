<?php
/**
 * NEFT Excel — ICICI NFPS format (same headings as NFPS_FMT SALARY FOUNDARY AND OTHERS.xlsx)
 *
 * Always one combined file: Normal Salary Register + Jobwork Register (all employees with Net > 0).
 *
 * Columns:
 * PYMT_PROD_TYPE_CODE | PYMT_MODE | DEBIT_ACC_NO | BNF_NAME | BENE_ACC_NO | BENE_IFSC |
 * AMOUNT | CREDIT_NARR | PYMT_DATE | MOBILE_NUM | EMAIL_ID | REMARK | REF_NO
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$deptId = (int) ($_GET['department_id'] ?? 0);
requirePayrollRegisterAccess($deptId);
ensurePayrollApprovalTables();
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

// Always Normal + Jobwork together (Salary Register stays separate screens)
$data = getCombinedNeftSheetData($month, $year, $deptId);

$backQs = http_build_query([
    'type' => 'all',
    'month' => $month,
    'year' => $year,
    'department_id' => $deptId,
]);

if (!$data || empty($data['rows'])) {
    header('Location: ' . app_url('payroll/neft.php?' . $backQs));
    exit;
}

$neftRows = enrichNeftRowsWithEmployeeContact($data['rows'] ?? []);
$payRaw = (string) ($data['lock']['approved_at'] ?? $data['lock']['locked_at'] ?? '');
$export = buildNfpsNeftExport($neftRows, $month, $year, $payRaw);
$sections = summarizeNeftRows($neftRows);
$normalTotal = (float) ($data['normal_total'] ?? $sections['normal_total']);
$jobworkTotal = (float) ($data['jobwork_total'] ?? $sections['jobwork_total']);
$finalTotal = (float) ($data['final_total'] ?? $export['total']);
$normalCount = (int) ($data['normal_count'] ?? $sections['normal_count']);
$jobworkCount = (int) ($data['jobwork_count'] ?? $sections['jobwork_count']);

$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$filename = 'NFPS_FMT_SALARY_' . strtoupper($monthLabel) . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="3">';

// Header row — exact NFPS column names
echo '<tr>';
foreach ($export['headers'] as $h) {
    echo '<th style="background:#ffffff;color:#c00000;font-weight:700;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

foreach ($export['rows'] as $row) {
    echo '<tr>';
    foreach ($row as $i => $cell) {
        // AMOUNT as number; account/IFSC/debit/mobile as text so Excel keeps leading zeros
        if ($i === 6) {
            echo '<td style="mso-number-format:0.00;">' . htmlspecialchars(number_format((float) $cell, 2, '.', '')) . '</td>';
        } elseif ($i === 4 || $i === 5 || $i === 2 || $i === 9) {
            echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars((string) $cell) . '</td>';
        } else {
            echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
        }
    }
    echo '</tr>';
}

// Same footer figures as NEFT screen / print
echo '<tr style="font-weight:700;background:#ecfdf5;">';
echo '<td></td><td></td><td></td>';
echo '<td>NORMAL TOTAL (' . (int) $normalCount . ')</td><td></td><td></td>';
echo '<td style="mso-number-format:0.00;">' . htmlspecialchars(number_format($normalTotal, 2, '.', '')) . '</td>';
echo '<td colspan="6"></td>';
echo '</tr>';
echo '<tr style="font-weight:700;background:#eff6ff;">';
echo '<td></td><td></td><td></td>';
echo '<td>JOBWORK TOTAL (' . (int) $jobworkCount . ')</td><td></td><td></td>';
echo '<td style="mso-number-format:0.00;">' . htmlspecialchars(number_format($jobworkTotal, 2, '.', '')) . '</td>';
echo '<td colspan="6"></td>';
echo '</tr>';
echo '<tr style="font-weight:700;background:#fff2cc;">';
echo '<td></td><td></td><td></td>';
echo '<td>FINAL TOTAL (NORMAL + JOBWORK)</td><td></td><td></td>';
echo '<td style="mso-number-format:0.00;">' . htmlspecialchars(number_format($finalTotal, 2, '.', '')) . '</td>';
echo '<td colspan="6"></td>';
echo '</tr>';

echo '</table></body></html>';
exit;
