<?php
/**
 * Active employees linked to an ERP Production department.
 * Called by Armor ERP attendance sheets. No login page; key required.
 */
header('Content-Type: application/json; charset=utf-8');

$key = (string)($_GET['key'] ?? '');
if (!hash_equals('ArmorAttLink_9f3c7a2e6b814d05', $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$dept = trim((string)($_GET['department'] ?? ''));
if ($dept === '') {
    echo json_encode(['ok' => true, 'employees' => []]);
    exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare(
    "SELECT employee_code, employee_name
     FROM employees
     WHERE (status = 1 OR LOWER(CAST(status AS CHAR)) IN ('1', 'active'))
       AND LOWER(TRIM(production_department)) = LOWER(?)
     ORDER BY employee_name ASC"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    $conn->close();
    exit;
}
$stmt->bind_param('s', $dept);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
$employees = [];
$seen = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $name = trim((string)($row['employee_name'] ?? ''));
        $code = trim((string)($row['employee_code'] ?? ''));
        if ($name === '') {
            continue;
        }
        $rowKey = strtolower($code !== '' ? $code : $name);
        if (isset($seen[$rowKey])) {
            continue;
        }
        $seen[$rowKey] = true;
        $employees[] = ['name' => $name, 'code' => $code];
    }
}
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'employees' => $employees]);
