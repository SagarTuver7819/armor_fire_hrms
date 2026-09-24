<?php
/**
 * Debug ingest (local) — appends NDJSON to debug-06893f.log
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Debug-Session-Id');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = ['message' => 'invalid', 'raw' => substr((string) $raw, 0, 500)];
}
if (empty($payload['sessionId'])) {
    $payload['sessionId'] = '06893f';
}
if (empty($payload['timestamp'])) {
    $payload['timestamp'] = (int) round(microtime(true) * 1000);
}
file_put_contents(__DIR__ . '/debug-06893f.log', json_encode($payload) . "\n", FILE_APPEND);
echo json_encode(['ok' => true]);
