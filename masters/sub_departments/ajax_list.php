<?php
$masterKey = 'sub_departments';
require_once __DIR__ . '/../_core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$draw   = (int) ($_POST['draw'] ?? 1);
$start  = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 1);
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$orderMap = [
    0 => 's.id',
    1 => 'd.department_name',
    2 => 's.name',
];
$orderBy = $orderMap[$orderCol] ?? 'd.department_name';

$conn = getDBConnection();
ensureMasterTables($conn);

$from = 'FROM sub_departments s
         LEFT JOIN departments d ON d.id = s.department_id';
$where = 's.status = 1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (s.name LIKE ? OR d.department_name LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'ss';
    $params = [$like, $like];
}

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM sub_departments WHERE status = 1')->fetch_assoc()['c'];
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

$sql = "SELECT s.id, s.name, d.department_name
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
    $label = (string) $row['name'];
    $data[] = [
        $sr,
        htmlspecialchars((string) ($row['department_name'] ?: '-')),
        htmlspecialchars($label),
        '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('masters/sub_departments/edit.php?id=' . $id)) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('masters/sub_departments/delete.php?id=' . $id)) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars($label) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
        . '</div>',
    ];
}
$st->close();
$conn->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $data,
]);
