<?php
/**
 * Contract Process rate remark history — list + save (AJAX JSON).
 */
require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureContractorTables($conn);

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $productId = (int) ($_GET['product_id'] ?? 0);
    $rows = contractorProductRateHistoryList($conn, $productId, 30);
    $conn->close();
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    $conn->close();
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = $_POST;
$raw = file_get_contents('php://input');
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = array_merge($payload, $decoded);
    }
}

$result = contractorSaveProductRateHistory($conn, [
    'product_id' => (int) ($payload['product_id'] ?? 0),
    'sheet_id' => (int) ($payload['sheet_id'] ?? 0),
    'operation' => (string) ($payload['operation'] ?? ''),
    'month_no' => (int) ($payload['month_no'] ?? 0),
    'year_no' => (int) ($payload['year_no'] ?? 0),
    'old_rate' => (float) ($payload['old_rate'] ?? 0),
    'new_rate' => (float) ($payload['new_rate'] ?? 0),
    'remark' => (string) ($payload['remark'] ?? ''),
    'update_master' => true,
]);

$rows = [];
if (!empty($result['ok'])) {
    $rows = contractorProductRateHistoryList($conn, (int) ($payload['product_id'] ?? 0), 30);
}
$conn->close();

if (empty($result['ok'])) {
    http_response_code(400);
}
echo json_encode([
    'ok' => !empty($result['ok']),
    'id' => (int) ($result['id'] ?? 0),
    'message' => (string) ($result['message'] ?? ''),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
