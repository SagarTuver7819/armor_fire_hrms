<?php
/**
 * Update employee training status (Admin / HR)
 * POST: employee_id, training_type, status, start_date?, end_date?, remarks?
 * Returns JSON if ajax=1, else redirects back
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/induction_helper.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$isAjax = !empty($_POST['ajax']) || !empty($_GET['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

if (!isAdmin() && !isHR()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only Admin / HR can update training status']);
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$trainingType = strtolower(trim((string) ($_POST['training_type'] ?? 'general')));
$status = strtolower(trim((string) ($_POST['status'] ?? 'pending')));

$emp = $employeeId > 0 ? getEmployeeById($employeeId) : null;
if (!$emp) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Employee not found']);
    exit;
}

$deptId = (int) ($emp['department_id'] ?? 0);
if (!canAccess('employees', 'edit', $deptId) && !isAdmin() && !isHR()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    exit;
}

$result = saveEmployeeTrainingStatus($employeeId, $trainingType, $status, [
    'start_date' => $_POST['start_date'] ?? '',
    'end_date' => $_POST['end_date'] ?? '',
    'remarks' => $_POST['remarks'] ?? '',
    'updated_by' => (int) ($_SESSION['user_id'] ?? 0),
]);

if (!$result['ok']) {
    http_response_code(400);
}

$labels = employeeTrainingStatusLabels();
$st = $result['status'] ?? $status;
echo json_encode([
    'ok' => !empty($result['ok']),
    'error' => $result['error'] ?? null,
    'status' => $st,
    'status_label' => $labels[$st] ?? $st,
    'badge_style' => employeeTrainingStatusBadgeStyle($st),
]);
