<?php
/**
 * CNC Clone NEFT download.
 * Copies NFPS_FMT SALARY FOUNDARY AND OTHERS.xlsx and fills CNC nets into that sheet.
 * Header row and column styles stay as they are in the template.
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
$export = cncCloneNfpsExport($neftRows, $month, $year);
if (!$export['rows']) {
    header('Location: ' . app_url('contractor/salary_register_clone/index.php?' . http_build_query(['month' => $month, 'year' => $year]) . '#cncNeftSheet'));
    exit;
}

$template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'NFPS_FMT SALARY FOUNDARY AND OTHERS.xlsx';
if (!is_file($template)) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'NFPS template file was not found.';
    exit;
}

$xlsx = cncCloneFillNfpsTemplate($template, $export['rows']);
if ($xlsx === '') {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'NFPS template could not be filled.';
    exit;
}

$filename = 'NFPS_FMT SALARY CNC.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsx));
header('Pragma: no-cache');
header('Expires: 0');
echo $xlsx;
exit;
