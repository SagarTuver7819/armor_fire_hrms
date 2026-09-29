<?php
/**
 * AJAX — submit one KPI hour from global reminder popup
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

requireLogin();

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !function_exists('isEmployee') || !isEmployee() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not allowed']);
    exit;
}

$sheetId = (int) ($_POST['sheet_id'] ?? 0);
$slotIndex = (int) ($_POST['slot_index'] ?? 0);
$activity = trim((string) ($_POST['activity'] ?? ''));

$conn = getDBConnection();
ensureKpiTables($conn);
$sheet = kpiGetSheetById($sheetId, $conn);

if (!$sheet || (int) $sheet['employee_id'] !== $empId) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Invalid KPI sheet.']);
    exit;
}

if (($sheet['kpi_date'] ?? '') !== date('Y-m-d')) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Only today\'s KPI can be filled.']);
    exit;
}

$res = kpiSubmitHour($sheetId, $slotIndex, $activity, $conn);
$conn->close();

echo json_encode($res, JSON_UNESCAPED_UNICODE);
