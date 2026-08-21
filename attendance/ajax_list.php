<?php
/**
 * Attendance day-status ajax list
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$draw = (int) ($_POST['draw'] ?? 1);
$start = max(0, (int) ($_POST['start'] ?? 0));
$length = (int) ($_POST['length'] ?? 25);
if ($length <= 0 || $length > 100) {
    $length = 25;
}
$search = trim($_POST['search']['value'] ?? '');
$orderDir = (strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$orderMap = [
    1 => 'e.employee_name',
    2 => 'd.department_name',
    3 => 'a.attendance_date',
    4 => 'a.punch_in',
    5 => 'a.punch_out',
    6 => 'a.day_status',
    7 => 'a.working_minutes',
    8 => 'a.source',
];
$orderCol = (int) ($_POST['order'][0]['column'] ?? 3);
$orderBy = $orderMap[$orderCol] ?? 'a.attendance_date';

$conn = getDBConnection();
ensureAttendanceTables($conn);

$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (e.employee_code LIKE ? OR e.employee_name LIKE ? OR d.department_name LIKE ? OR a.day_status LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
}

$from = 'FROM attendance_day_status a
         INNER JOIN employees e ON e.id = a.employee_id
         LEFT JOIN departments d ON d.id = e.department_id';

$total = (int) $conn->query('SELECT COUNT(*) AS c FROM attendance_day_status')->fetch_assoc()['c'];

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

$sql = "SELECT a.attendance_date, a.punch_in, a.punch_out, a.day_status, a.working_minutes, a.source,
               e.employee_code, e.employee_name, d.department_name
        {$from} WHERE {$where}
        ORDER BY {$orderBy} {$orderDir}, a.id DESC
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
    $hours = round(((int) $row['working_minutes']) / 60, 2);
    $data[] = [
        $sr,
        htmlspecialchars(($row['employee_code'] ?: '-') . ' — ' . ($row['employee_name'] ?: '-')),
        htmlspecialchars($row['department_name'] ?: '-'),
        htmlspecialchars(date('d-m-Y', strtotime($row['attendance_date']))),
        htmlspecialchars($row['punch_in'] ? substr($row['punch_in'], 0, 5) : '-'),
        htmlspecialchars($row['punch_out'] ? substr($row['punch_out'], 0, 5) : '-'),
        htmlspecialchars($row['day_status']),
        $hours,
        htmlspecialchars($row['source']),
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
