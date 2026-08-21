<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$operation = (string) ($_GET['operation'] ?? '');
$conn = getDBConnection();
ensureContractorTables($conn);
$products = getContractorProductsByOperation($operation, $conn);
$conn->close();

$outProducts = [];
foreach ($products as $r) {
    // Reference Operations list shows Contract Process label (process), not product name prefix
    $process = trim(html_entity_decode((string) ($r['process'] ?? '')));
    $name = trim(html_entity_decode((string) ($r['product_name'] ?? '')));
    $label = $process !== '' && $process !== '-' ? $process : $name;
    $outProducts[] = [
        'id' => (int) $r['id'],
        'name' => $label,
        'product_name' => $name,
        'process' => $process,
        'rate' => (float) $r['rate'],
        'ot_rate' => parseOtRate($r['ot_text'] ?? ''),
        'rejection_rate' => (float) $r['rejection_rate'],
    ];
}
echo json_encode([
    'products' => $outProducts,
    'grades' => [],
]);
