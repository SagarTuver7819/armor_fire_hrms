<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
header('Content-Type: application/json; charset=utf-8');

$draw = (int) ($_POST['draw'] ?? 1);
$start = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$orderMap = [
    0 => 'e.id',
    1 => 'e.employee_code',
    2 => 'e.employee_name',
    3 => 'd.department_name',
    4 => 'e.designation',
    5 => 'e.mobile_number',
];
$orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
$orderBy = $orderMap[$orderCol] ?? 'e.employee_code';

$conn = getDBConnection();
ensureEmployeesTable($conn);

$where = contractorJobworkWhere();
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (e.employee_code LIKE ? OR e.employee_name LIKE ? OR d.department_name LIKE ? OR e.designation LIKE ? OR e.mobile_number LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'sssss';
    $params = [$like, $like, $like, $like, $like];
}

$from = 'FROM employees e LEFT JOIN departments d ON d.id = e.department_id';
$total = (int) $conn->query("SELECT COUNT(*) AS c FROM employees e WHERE " . contractorJobworkWhere())->fetch_assoc()['c'];

$countSql = "SELECT COUNT(*) AS c {$from} WHERE {$where}";
if ($params) {
    $st = $conn->prepare($countSql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $filtered = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
} else {
    $filtered = (int) $conn->query($countSql)->fetch_assoc()['c'];
}

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.designation, e.mobile_number, e.department_id,
               d.department_name
        {$from} WHERE {$where} ORDER BY {$orderBy} {$orderDir} LIMIT ?, ?";
$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $start;
$params2[] = $length;
$st = $conn->prepare($sql);
$st->bind_param($types2, ...$params2);
$st->execute();
$res = $st->get_result();
$data = [];
$sr = $start;
while ($row = $res->fetch_assoc()) {
    $sr++;
    $id = (int) $row['id'];
    $deptId = (int) $row['department_id'];
    $name = (string) $row['employee_name'];
    $editUrl = app_url('employees/edit.php?id=' . $id . '&from=contractor');
    $delUrl = app_url('employees/delete.php?id=' . $id . '&department_id=' . $deptId . '&from=contractor');
    $viewUrl = app_url('employees/view.php?id=' . $id);
    $actions = '<a class="btn-icon" href="' . htmlspecialchars($viewUrl) . '" title="View"><i class="fa-solid fa-eye"></i></a>'
        . ' <a class="btn-icon" href="' . htmlspecialchars($editUrl) . '" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . ' <a class="btn-icon btn-delete" href="' . htmlspecialchars($delUrl) . '" data-name="' . htmlspecialchars($name) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>';
    $data[] = [
        $sr,
        htmlspecialchars((string) $row['employee_code']),
        htmlspecialchars($name),
        htmlspecialchars((string) ($row['department_name'] ?: '-')),
        htmlspecialchars((string) ($row['designation'] ?: '-')),
        htmlspecialchars((string) ($row['mobile_number'] ?: '-')),
        $actions,
    ];
}
$st->close();
$conn->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
