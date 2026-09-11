<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$operation = (string) ($_GET['operation'] ?? '');
$conn = getDBConnection();
ensureContractorTables($conn);
$products = getContractorProductsByOperation($operation, $conn);
$grades = contractorOperationShowsGrade($operation) ? getContractorGrades($conn) : [];
$conn->close();

$outProducts = [];
$seenLabels = [];
foreach ($products as $r) {
    // Reference Operations list shows Contract Process label (process), not product name prefix
    $process = trim(html_entity_decode((string) ($r['process'] ?? '')));
    $name = trim(html_entity_decode((string) ($r['product_name'] ?? '')));
    $label = $process !== '' && $process !== '-' ? $process : $name;
    if ($label === '') {
        continue;
    }
    // Same Contract Process label twice (e.g. Foundry castings) → keep one
    $key = function_exists('mb_strtolower')
        ? mb_strtolower($label, 'UTF-8')
        : strtolower($label);
    if (isset($seenLabels[$key])) {
        continue;
    }
    $seenLabels[$key] = true;
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

$outGrades = [];
foreach ($grades as $g) {
    $outGrades[] = [
        'id' => (int) ($g['id'] ?? 0),
        'name' => (string) ($g['name'] ?? ''),
    ];
}

echo json_encode([
    'products' => $outProducts,
    'grades' => $outGrades,
    'show_grade' => contractorOperationShowsGrade($operation),
]);
