<?php
require_once __DIR__ . '/../_bootstrap.php';
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
    0 => 's.id',
    1 => 's.operation',
    2 => 'e.employee_name',
    3 => 's.month_no',
    4 => 's.year_no',
    5 => 's.total_qty',
    6 => 's.total_amount',
];
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderBy = $orderMap[$orderCol] ?? 's.id';
$months = contractorMonths();

$conn = getDBConnection();
ensureContractorTables($conn);

$where = 's.status = 1';
$types = '';
$params = [];
$filterOp = trim((string) ($_POST['filter_operation'] ?? ''));
$filterEmp = (int) ($_POST['filter_employee'] ?? 0);
$filterMonth = (int) ($_POST['filter_month'] ?? 0);
$filterYear = (int) ($_POST['filter_year'] ?? 0);
if ($filterOp !== '') {
    $where .= ' AND s.operation = ?';
    $types .= 's';
    $params[] = $filterOp;
}
if ($filterEmp > 0) {
    $where .= ' AND s.employee_id = ?';
    $types .= 'i';
    $params[] = $filterEmp;
}
if ($filterMonth > 0) {
    $where .= ' AND s.month_no = ?';
    $types .= 'i';
    $params[] = $filterMonth;
}
if ($filterYear > 0) {
    $where .= ' AND s.year_no = ?';
    $types .= 'i';
    $params[] = $filterYear;
}
if ($search !== '') {
    $where .= ' AND (s.operation LIKE ? OR e.employee_name LIKE ? OR e.employee_code LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$from = 'FROM contractor_operation_sheets s
         INNER JOIN employees e ON e.id = s.employee_id';
$total = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_operation_sheets s WHERE s.status = 1')->fetch_assoc()['c'];

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

$sql = "SELECT s.id, s.operation, s.month_no, s.year_no, s.total_qty, s.total_amount, s.salary_generated,
               e.employee_code, e.employee_name
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
    $name = htmlspecialchars((string) $row['employee_code'] . ' - ' . $row['employee_name']);
    $sid = (int) $row['id'];
    $genLabel = ((int) $row['salary_generated'] === 1) ? 'Generated' : 'Generate Salary';
    $actions = '<a class="btn-icon" href="' . htmlspecialchars(app_url('contractor/operations/edit.php?id=' . $sid)) . '" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . ' <a class="btn-icon" href="' . htmlspecialchars(app_url('contractor/operations/generate.php?id=' . $sid)) . '" title="' . $genLabel . '"><i class="fa-solid fa-indian-rupee-sign"></i></a>'
        . ' <a class="btn-icon btn-delete" href="' . htmlspecialchars(app_url('contractor/operations/delete.php?id=' . $sid)) . '" data-name="' . htmlspecialchars($row['employee_code'] . ' ' . $row['operation']) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>';
    $data[] = [
        $sr,
        htmlspecialchars((string) $row['operation']),
        $name,
        htmlspecialchars($months[(int) $row['month_no']] ?? (string) $row['month_no']),
        (int) $row['year_no'],
        number_format((float) $row['total_qty'], 2),
        number_format((float) $row['total_amount'], 2),
        $actions,
    ];
}
$st->close();
$conn->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
