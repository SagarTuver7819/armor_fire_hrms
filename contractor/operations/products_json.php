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
    $outProducts[] = [
        'id' => (int) $r['id'],
        'name' => trim((string) ($r['product_name'] ?? '')),
        'process' => (string) ($r['process'] ?? ''),
        'rate' => (float) $r['rate'],
        'ot_rate' => parseOtRate($r['ot_text'] ?? ''),
        'rejection_rate' => (float) $r['rejection_rate'],
    ];
}
echo json_encode([
    'products' => $outProducts,
    'grades' => [],
]);
