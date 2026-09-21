<?php
/**
 * Joining / Exit report Excel export
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
$report = getMonthlyJoiningExitReport($month, $year, $deptId);
$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Joining_Exit_' . $monthLabel . '.xls"');

$headers = ['Section', 'Code', 'Employee', 'Department', 'DOJ', 'Exit Date', 'Eligible Days',
    'Present', 'Week Off', 'Holiday', 'PL', 'SL', 'DL', 'Paid Days', 'Gross', 'Net'];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="' . count($headers) . '" style="background:#1e3a5f;color:#fff;">'
    . htmlspecialchars($companyName) . ' — Joining / Exit Report — ' . htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year)))
    . '</th></tr><tr>';
foreach ($headers as $h) {
    echo '<th style="background:#f58220;color:#fff;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

$dump = static function ($section, $rows) {
    foreach ($rows as $r) {
        echo '<tr>';
        $cells = [
            $section, $r['employee_code'], $r['employee_name'], $r['department'],
            $r['doj'], $r['doe'], $r['eligible_days'], $r['present'], $r['week_off'],
            $r['holiday'], $r['pl'], $r['sl'], $r['dl'], $r['total_days'], $r['gross'], $r['net'],
        ];
        foreach ($cells as $c) {
            echo '<td>' . htmlspecialchars((string) $c) . '</td>';
        }
        echo '</tr>';
    }
};
$dump('Joining', $report['joiners']);
$dump('Exit', $report['exiters']);
if (!$report['joiners'] && !$report['exiters']) {
    echo '<tr><td colspan="' . count($headers) . '">No joinings or exits</td></tr>';
}
echo '</table></body></html>';
exit;
