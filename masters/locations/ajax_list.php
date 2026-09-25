<?php
$masterKey = 'locations';
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
    0 => 'l.id',
    1 => 's.name',
    2 => 'l.name',
    3 => 'l.sort_order',
];
$orderBy = $orderMap[$orderCol] ?? 's.name';

$conn = getDBConnection();
ensureMasterTables($conn);

$from = 'FROM assigned_locations l
         LEFT JOIN assigned_states s ON s.id = l.state_id';
$where = 'l.status = 1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (l.name LIKE ? OR s.name LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'ss';
    $params = [$like, $like];
}

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM assigned_locations WHERE status = 1')->fetch_assoc()['c'];
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

$sql = "SELECT l.id, l.name, l.sort_order, s.name AS state_name
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
        htmlspecialchars((string) ($row['state_name'] ?: '-')),
        htmlspecialchars($label),
        (int) ($row['sort_order'] ?? 0),
        '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('masters/locations/edit.php?id=' . $id)) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('masters/locations/delete.php?id=' . $id)) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars($label) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
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
