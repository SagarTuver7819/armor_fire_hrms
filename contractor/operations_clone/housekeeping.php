<?php
/**
 * Buff House keeping Expense — current amount, history, and save.
 */
require_once __DIR__ . '/../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureContractorTables($conn);

$month = (int) ($_GET['month'] ?? $_POST['month'] ?? 0);
$year = (int) ($_GET['year'] ?? $_POST['year'] ?? 0);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'amount' => fetchBuffHousekeepingAmount($year, $month, $conn),
        'rows' => buffHousekeepingHistoryList($conn, $month, $year),
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
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

$result = saveBuffHousekeepingAmount(
    $conn,
    (int) ($payload['month'] ?? $month),
    (int) ($payload['year'] ?? $year),
    (float) ($payload['amount'] ?? 0),
    (string) ($payload['remark'] ?? '')
);
$rows = [];
if (!empty($result['ok'])) {
    $rows = buffHousekeepingHistoryList($conn, (int) ($payload['month'] ?? $month), (int) ($payload['year'] ?? $year));
}
$conn->close();
if (empty($result['ok'])) {
    http_response_code(400);
}
echo json_encode([
    'ok' => !empty($result['ok']),
    'amount' => (float) ($result['amount'] ?? 0),
    'message' => (string) ($result['message'] ?? ''),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
