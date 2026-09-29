<?php
/**
 * AJAX — due KPI hour slots for logged-in employee (global reminder)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

requireLogin();

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !function_exists('isEmployee') || !isEmployee()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not an employee session']);
    exit;
}

$conn = getDBConnection();
ensureMasterTables($conn);
$payload = kpiGetDueReminderPayload($empId, $conn);
$conn->close();

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
