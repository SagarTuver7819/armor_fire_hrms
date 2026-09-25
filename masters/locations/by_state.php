<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/master_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$stateId = (int) ($_GET['state_id'] ?? 0);
$conn = getDBConnection();
ensureMasterTables($conn);
$rows = getAssignedLocationsByState($stateId, $conn);
$conn->close();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r['id'],
        'name' => (string) $r['name'],
    ];
}
echo json_encode($out);
