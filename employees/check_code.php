<?php
/**
 * Check if employee_code already exists (JSON).
 * Used by Add/Edit form on blur — does not change any data.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$excludeId = (int) ($_GET['exclude_id'] ?? $_POST['exclude_id'] ?? 0);

if ($code === '') {
    echo json_encode(['ok' => true, 'exists' => false]);
    exit;
}

$conn = getDBConnection();
ensureEmployeesTable($conn);
$row = findEmployeeByCode($conn, $code, $excludeId);
$conn->close();

if (!$row) {
    echo json_encode(['ok' => true, 'exists' => false, 'code' => $code]);
    exit;
}

$active = ((int) ($row['status'] ?? 1) === 1);
echo json_encode([
    'ok'            => true,
    'exists'        => true,
    'code'          => $code,
    'id'            => (int) $row['id'],
    'employee_name' => (string) ($row['employee_name'] ?? ''),
    'status'        => (int) ($row['status'] ?? 1),
    'active'        => $active,
]);
