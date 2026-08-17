<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureContractorTables($conn);
$out = contractorDtAjax(
    $conn,
    'contractor_products',
    'SELECT id, product_name, operation, process, unit, ot_text, rate, rejection_rate FROM contractor_products',
    ['product_name', 'operation', 'process', 'unit'],
    [0 => 'id', 1 => 'product_name', 2 => 'operation', 3 => 'process', 4 => 'unit', 5 => 'ot_text', 6 => 'rate', 7 => 'rejection_rate'],
    function ($row, $sr) {
        $name = (string) $row['product_name'];
        return [
            $sr,
            htmlspecialchars($name),
            htmlspecialchars((string) $row['operation']),
            htmlspecialchars((string) ($row['process'] ?: '-')),
            htmlspecialchars((string) ($row['unit'] ?: '-')),
            htmlspecialchars((string) ($row['ot_text'] ?: '-')),
            number_format((float) $row['rate'], 2),
            number_format((float) $row['rejection_rate'], 2),
            contractorActionBtns(
                app_url('contractor/products/edit.php?id=' . (int) $row['id']),
                app_url('contractor/products/delete.php?id=' . (int) $row['id']),
                $name
            ),
        ];
    }
);
$conn->close();
echo json_encode($out);
