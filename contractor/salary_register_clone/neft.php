<?php
require_once __DIR__ . '/../_bootstrap.php';

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
header('Location: ' . app_url('contractor/salary_register_clone/index.php?' . http_build_query([
    'month' => $month,
    'year' => $year,
]) . '#cncNeftSheet'));
exit;
