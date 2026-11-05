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
$asOf = trim((string)($_GET['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = date('Y-m-d');
}
$salaryExpr = 'NULL AS decided_salary';
$colRes = $conn->query("SHOW COLUMNS FROM employees LIKE 'decided_salary'");
if ($colRes && $colRes->num_rows > 0) {
    $salaryExpr = 'decided_salary';
}
if ($colRes) {
    $colRes->free();
}
$weekExpr = "'' AS week_off_day";
$weekBindDate = false;
$weekCol = $conn->query("SHOW COLUMNS FROM employees LIKE 'week_off_day'");
$hasWeekCol = $weekCol && $weekCol->num_rows > 0;
if ($weekCol) {
    $weekCol->free();
}
$hist = $conn->query("SHOW TABLES LIKE 'employee_week_off_history'");
$hasHist = $hist && $hist->num_rows > 0;
if ($hist) {
    $hist->free();
}
if ($hasWeekCol && $hasHist) {
    $weekExpr = "COALESCE((
        SELECT h.week_off_day FROM employee_week_off_history h
        WHERE h.employee_id = employees.id AND h.effective_from <= ?
        ORDER BY h.effective_from DESC, h.id DESC
        LIMIT 1
    ), week_off_day) AS week_off_day";
    $weekBindDate = true;
} elseif ($hasWeekCol) {
    $weekExpr = 'week_off_day';
}
$stmt = $conn->prepare(
    "SELECT employee_code, employee_name, {$salaryExpr}, {$weekExpr}
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
if ($weekBindDate) {
    $stmt->bind_param('ss', $asOf, $dept);
} else {
    $stmt->bind_param('s', $dept);
}
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
        $salary = $row['decided_salary'] ?? '';
        if ($salary === null) {
            $salary = '';
        }
        $salary = trim((string)$salary);
        $weekOff = trim((string)($row['week_off_day'] ?? ''));
        if ($name === '') {
            continue;
        }
        $rowKey = strtolower($code !== '' ? $code : $name);
        if (isset($seen[$rowKey])) {
            continue;
        }
        $seen[$rowKey] = true;
        $employees[] = ['name' => $name, 'code' => $code, 'salary' => $salary, 'week_off' => $weekOff];
    }
}
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'employees' => $employees]);
