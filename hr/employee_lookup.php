<?php
/**
 * HR employee quick lookup — search by name/code + view card JSON
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = strtolower(trim((string) ($_GET['action'] ?? 'search')));

if ($action === 'view') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid employee.']);
        exit;
    }
    $emp = getEmployeeById($id);
    if (!$emp || (int) ($emp['status'] ?? 0) !== 1) {
        echo json_encode(['ok' => false, 'error' => 'Employee not found.']);
        exit;
    }
    $photo = '';
    if (function_exists('employeeDocumentPublicUrl')) {
        $photo = employeeDocumentPublicUrl($emp['photo_file'] ?? '');
    }
    echo json_encode([
        'ok' => true,
        'employee' => [
            'id' => (int) $emp['id'],
            'employee_code' => (string) ($emp['employee_code'] ?? ''),
            'employee_name' => (string) ($emp['employee_name'] ?? ''),
            'photo_url' => $photo,
            'designation' => (string) ($emp['designation'] ?? ''),
            'department' => (string) ($emp['department_name'] ?? ''),
            'office_mobile' => (string) ($emp['office_mobile'] ?? ''),
            'office_email' => (string) ($emp['office_email'] ?? ''),
            'desk_no' => (string) ($emp['desk_no'] ?? ''),
        ],
    ]);
    exit;
}

// Default: search
$q = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 80);
} else {
    $q = substr($q, 0, 80);
}

if ($q === '') {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$conn = getDBConnection();
ensureEmployeesTable($conn);

$like = '%' . $q . '%';
$sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.photo_file,
               d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE e.status = 1
          AND (
              e.employee_code LIKE ?
              OR e.employee_name LIKE ?
              OR REPLACE(e.employee_code, ' ', '') LIKE REPLACE(?, ' ', '')
          )
        ORDER BY
            CASE
                WHEN UPPER(e.employee_code) = UPPER(?) THEN 0
                WHEN UPPER(e.employee_code) LIKE UPPER(CONCAT(?, '%')) THEN 1
                WHEN UPPER(e.employee_name) LIKE UPPER(CONCAT(?, '%')) THEN 2
                ELSE 3
            END,
            e.employee_name ASC
        LIMIT 12";
$st = $conn->prepare($sql);
$st->bind_param('ssssss', $like, $like, $like, $q, $q, $q);
$st->execute();
$res = $st->get_result();
$results = [];
while ($row = $res->fetch_assoc()) {
    $photo = '';
    if (function_exists('employeeDocumentPublicUrl')) {
        $photo = employeeDocumentPublicUrl($row['photo_file'] ?? '');
    }
    $results[] = [
        'id' => (int) $row['id'],
        'employee_code' => (string) ($row['employee_code'] ?? ''),
        'employee_name' => (string) ($row['employee_name'] ?? ''),
        'designation' => (string) ($row['designation'] ?? ''),
        'department' => (string) ($row['department_name'] ?? ''),
        'photo_url' => $photo,
    ];
}
$st->close();
$conn->close();

echo json_encode(['ok' => true, 'results' => $results]);
exit;
