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
    0 => 'em.id',
    1 => 'e.employee_code',
    2 => 'e.employee_name',
    3 => 'em.designation_type',
    4 => 'em.designation',
    5 => 'd.department_name',
    6 => 'em.sub_department',
    7 => 'em.process',
    8 => 'em.date_of_joining',
];
$orderCol = (int) ($_POST['order'][0]['column'] ?? 0);
$orderBy = $orderMap[$orderCol] ?? 'em.id';

$conn = getDBConnection();
ensureContractorTables($conn);

$where = 'em.status = 1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (e.employee_code LIKE ? OR e.employee_name LIKE ? OR em.designation LIKE ? OR d.department_name LIKE ? OR em.sub_department LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'sssss';
    $params = [$like, $like, $like, $like, $like];
}

$from = 'FROM contractor_employment em
         INNER JOIN employees e ON e.id = em.employee_id AND e.pay_type = \'Jobwork\'
         LEFT JOIN departments d ON d.id = em.department_id';

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM contractor_employment em WHERE em.status = 1')->fetch_assoc()['c'];

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

$sql = "SELECT em.id, e.employee_code, e.employee_name, em.designation_type, em.designation,
               d.department_name, em.sub_department, em.process, em.date_of_joining
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
    $date = $row['date_of_joining'] ? formatDateDisplay($row['date_of_joining']) : '-';
    $data[] = [
        $sr,
        htmlspecialchars((string) $row['employee_code']),
        htmlspecialchars((string) $row['employee_name']),
        htmlspecialchars((string) ($row['designation_type'] ?: '-')),
        htmlspecialchars((string) ($row['designation'] ?: '-')),
        htmlspecialchars((string) ($row['department_name'] ?: '-')),
        htmlspecialchars((string) ($row['sub_department'] ?: '-')),
        htmlspecialchars((string) ($row['process'] ?: '-')),
        $date,
        contractorActionBtns(
            app_url('contractor/employment/edit.php?id=' . (int) $row['id']),
            app_url('contractor/employment/delete.php?id=' . (int) $row['id']),
            $row['employee_code'] . ' ' . $row['employee_name']
        ),
    ];
}
$st->close();
$conn->close();
echo json_encode(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
