<?php
/**
 * NEFT sheet Excel export
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
$type = (string) ($_GET['type'] ?? 'salary');
$data = getNeftSheetData($month, $year, $type, $deptId);
if (!$data) {
    header('Location: ' . app_url('payroll/neft.php?' . http_build_query([
        'type' => $type, 'month' => $month, 'year' => $year, 'department_id' => $deptId,
    ])));
    exit;
}

$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="NEFT_' . $type . '_' . $monthLabel . '.xls"');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="9" style="background:#1e3a5f;color:#fff;">'
    . htmlspecialchars($companyName) . ' — NEFT Sheet — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year)))
    . '</th></tr>';
$headers = ['Sr', 'Employee Code', 'Beneficiary Name', 'Department', 'Bank Name', 'Account Number', 'IFSC', 'Amount', 'Remarks'];
echo '<tr>';
foreach ($headers as $h) {
    echo '<th style="background:#f58220;color:#fff;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($data['rows'] as $r) {
    echo '<tr>';
    foreach ([
        $r['sr'], $r['employee_code'], $r['employee_name'], $r['department'],
        $r['bank_name'], $r['account'], $r['ifsc'], $r['amount'], $r['remarks'] ?? 'Salary',
    ] as $c) {
        echo '<td>' . htmlspecialchars((string) $c) . '</td>';
    }
    echo '</tr>';
}
echo '<tr style="font-weight:700;"><td colspan="7">TOTAL</td><td>' . number_format($data['neft_total'], 2) . '</td><td></td></tr>';
echo '</table></body></html>';
exit;
