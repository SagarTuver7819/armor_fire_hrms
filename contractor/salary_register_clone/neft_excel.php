<?php
/**
 * CNC Clone NEFT Excel — ICICI NFPS columns, amounts = clone register Net.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';
require_once __DIR__ . '/data.php';

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$bundle = cncCloneRegisterBundle($month, $year);
$neftRows = cncCloneNeftRows($bundle['rows']);
$sec = cncCloneNeftSummary($neftRows);
$export = cncCloneNfpsExport($neftRows, $month, $year);
if (!$export['rows']) {
    header('Location: ' . app_url('contractor/salary_register_clone/neft.php?' . http_build_query(['month' => $month, 'year' => $year])));
    exit;
}

$monthLabel = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
$filename = 'NFPS_FMT_CNC_CLONE_SALARY_' . strtoupper($monthLabel) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="3">';
echo '<tr>';
foreach ($export['headers'] as $h) {
    echo '<th style="background:#ffffff;color:#c00000;font-weight:700;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($export['rows'] as $row) {
    echo '<tr>';
    foreach ($row as $i => $cell) {
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
echo '<tr style="font-weight:700;background:#fff2cc;">';
echo '<td></td><td></td><td></td>';
echo '<td>TOTAL (' . count($export['rows']) . ')</td><td></td><td></td>';
echo '<td style="mso-number-format:0.00;">' . htmlspecialchars(number_format((float) $sec['final_total'], 2, '.', '')) . '</td>';
echo '<td colspan="6"></td></tr>';
echo '</table></body></html>';
exit;
