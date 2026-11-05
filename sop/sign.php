<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sop_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$signature = (string) ($_POST['contractor_signature'] ?? '');
$token = trim((string) ($_POST['token'] ?? ''));
$conn = getDBConnection();

if ($token !== '') {
    $row = cncSopGetByToken($conn, $token);
    if (!$row) {
        $conn->close();
        echo json_encode(['ok' => false, 'error' => 'Contractor Agreement not found.']);
        exit;
    }
    $ok = cncSopSaveContractorSign($conn, (int) $row['id'], $signature);
    $conn->close();
    echo json_encode(['ok' => (bool) $ok]);
    exit;
}

require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
requireLogin();
$id = (int) ($_POST['id'] ?? 0);
$deptId = (int) ($_POST['department_id'] ?? 0);
requireAccess('departments', 'edit', $deptId);
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
$row = cncSopGet($conn, $id);
if (!$department || !cncSopIsCncDepartment($department) || !$row || (int) $row['department_id'] !== $deptId) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Contractor Agreement not found.']);
    exit;
}
$ok = cncSopSaveContractorSign($conn, $id, $signature);
$conn->close();
echo json_encode(['ok' => (bool) $ok]);
exit;
