<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$operation = (string) ($_GET['operation'] ?? '');
$keepIds = [];
if (!empty($_GET['keep_ids'])) {
    foreach (explode(',', (string) $_GET['keep_ids']) as $kid) {
        $kid = (int) trim($kid);
        if ($kid > 0) {
            $keepIds[$kid] = true;
        }
    }
}

$conn = getDBConnection();
ensureContractorTables($conn);
$products = getContractorProductsByOperation($operation, $conn);

// Also pull keep_ids even if operation label drifted / duplicate was filtered
if ($keepIds) {
    $have = [];
    foreach ($products as $r) {
        $have[(int) $r['id']] = true;
    }
    $missing = array_keys(array_diff_key($keepIds, $have));
    if ($missing) {
        $in = implode(',', array_map('intval', $missing));
        $extra = $conn->query("SELECT * FROM contractor_products WHERE status = 1 AND id IN ($in)");
        if ($extra) {
            while ($row = $extra->fetch_assoc()) {
                $products[] = $row;
            }
        }
    }
}

$grades = contractorOperationShowsGrade($operation) ? getContractorGrades($conn) : [];
$conn->close();

$outProducts = [];
$seenLabels = [];
foreach ($products as $r) {
    $id = (int) $r['id'];
    $process = trim(html_entity_decode((string) ($r['process'] ?? '')));
    $name = trim(html_entity_decode((string) ($r['product_name'] ?? '')));
    $label = $process !== '' && $process !== '-' ? $process : $name;
    if ($label === '') {
        continue;
    }
    $key = function_exists('mb_strtolower')
        ? mb_strtolower($label, 'UTF-8')
        : strtolower($label);

    // Duplicate Contract Process labels: keep ALL ids (append #id for clarity).
    // Previously we dropped duplicates → edit of CNC/BUFF/etc. lost selected product_id.
    if (isset($seenLabels[$key])) {
        $label = $label . ' #' . $id;
    } else {
        $seenLabels[$key] = $id;
    }

    $outProducts[] = [
        'id' => $id,
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
