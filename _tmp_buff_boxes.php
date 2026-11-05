<?php
require __DIR__ . '/includes/contractor_helper.php';
$c = getDBConnection();
ensureContractorTables($c);
$days = [
    '1' => ['q' => 10, 'r' => 4, 'ap' => 5, 'ot' => 0],
    '2' => ['q' => 2, 'r' => 1, 'ap' => 0, 'ot' => 0],
];
$calc = calculateContractorRow('BUFF', 2, 0, 3, $days, 1.5);
echo json_encode($calc) . "\n";
$c->close();
