<?php
/**
 * Holiday Master — DataTables JSON (department join + optional dept filter)
 */
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../../includes/employee_helper.php';

header('Content-Type: application/json; charset=utf-8');

$scopeDeptId = (int) ($_POST['department_id'] ?? $_GET['department_id'] ?? 0);

$draw   = (int) ($_POST['draw'] ?? 1);
$start  = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 10);
if ($length <= 0 || $length > 100) {
    $length = 10;
}
$search = trim($_POST['search']['value'] ?? '');
$orderCol = (int) ($_POST['order'][0]['column'] ?? 2);
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$orderMap = [
    0 => 'h.id',
    1 => 'department_name',
    2 => 'h.title',
    3 => 'h.holiday_type',
    4 => 'h.holiday_date',
    5 => 'h.is_paid',
    6 => 'h.week_day',
];
$orderBy = $orderMap[$orderCol] ?? 'h.holiday_date';

$conn = getDBConnection();
ensureMasterTables($conn);

$from = "FROM holidays h
         LEFT JOIN departments d ON d.id = h.department_id";
$where = 'h.status = 1';
$types = '';
$params = [];

if ($scopeDeptId > 0) {
    $where .= ' AND (h.department_id IS NULL OR h.department_id = 0 OR h.department_id = ?)';
    $types .= 'i';
    $params[] = $scopeDeptId;
}

if ($search !== '') {
    $where .= ' AND (h.title LIKE ? OR h.holiday_type LIKE ? OR h.week_day LIKE ? OR d.department_name LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$totalSql = 'SELECT COUNT(*) AS c FROM holidays WHERE status = 1';
if ($scopeDeptId > 0) {
    $stT = $conn->prepare($totalSql . ' AND (department_id IS NULL OR department_id = 0 OR department_id = ?)');
    $stT->bind_param('i', $scopeDeptId);
    $stT->execute();
    $total = (int) ($stT->get_result()->fetch_assoc()['c'] ?? 0);
    $stT->close();
} else {
    $total = (int) ($conn->query($totalSql)->fetch_assoc()['c'] ?? 0);
}

$countSql = "SELECT COUNT(*) AS c {$from} WHERE {$where}";
if ($params) {
    $st = $conn->prepare($countSql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $filtered = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
} else {
    $filtered = (int) ($conn->query($countSql)->fetch_assoc()['c'] ?? 0);
}

$sql = "SELECT h.id, h.title, h.holiday_type, h.holiday_date, h.is_paid, h.week_day, h.department_id,
               CASE
                   WHEN h.department_id IS NULL OR h.department_id = 0 THEN 'All Departments'
                   ELSE COALESCE(d.department_name, '-')
               END AS department_name
        {$from}
        WHERE {$where}
        ORDER BY {$orderBy} {$orderDir}
        LIMIT ?, ?";
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
    $label = (string) $row['title'];
    $editQs = 'id=' . $id . ($scopeDeptId > 0 ? '&department_id=' . $scopeDeptId : '');
    $delQs = 'id=' . $id . ($scopeDeptId > 0 ? '&department_id=' . $scopeDeptId : '');
    $data[] = [
        $sr,
        htmlspecialchars((string) ($row['department_name'] ?? '-')),
        htmlspecialchars($label),
        htmlspecialchars((string) ($row['holiday_type'] ?? '-')),
        htmlspecialchars(formatDateDisplay($row['holiday_date'] ?? '') ?: '-'),
        htmlspecialchars((string) ($row['is_paid'] ?? '-')),
        htmlspecialchars((string) (($row['week_day'] ?? '') !== '' ? $row['week_day'] : '-')),
        '<div class="action-links" onclick="event.stopPropagation();">'
        . '<a href="' . htmlspecialchars(app_url('masters/holidays/edit.php?' . $editQs)) . '" class="action-btn edit" title="Edit"><i class="fa-solid fa-pen"></i></a>'
        . '<a href="' . htmlspecialchars(app_url('masters/holidays/delete.php?' . $delQs)) . '" class="action-btn delete btn-delete" data-name="' . htmlspecialchars($label) . '" title="Delete"><i class="fa-solid fa-trash"></i></a>'
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
