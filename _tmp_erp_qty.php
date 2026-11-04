<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/contractor_helper.php';
$map = fetchErpKraDateWiseQtys('CNC', 2026, 11);
echo 'parts=' . count($map) . PHP_EOL;
foreach ($map as $pn => $days) {
    echo $pn . ' => ' . json_encode($days) . PHP_EOL;
}
