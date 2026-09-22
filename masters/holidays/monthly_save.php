<?php
/**
 * Save monthly holiday set for one month/year (+ optional department)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/master_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('masters/holidays/index.php'));
    exit;
}

$month = (int) ($_POST['month'] ?? date('n'));
$year = (int) ($_POST['year'] ?? date('Y'));
$scopeDeptId = (int) ($_POST['department_id'] ?? 0);
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$from = sprintf('%04d-%02d-01', $year, $month);
$to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);
$days = $_POST['days'] ?? [];

ensureMasterTables();
$conn = getDBConnection();

$fromEsc = $conn->real_escape_string($from);
$toEsc = $conn->real_escape_string($to);

// Soft-remove holidays overlapping this month (incl. From–To ranges)
$overlap = "holiday_type = 'Holiday'
           AND holiday_date IS NOT NULL
           AND holiday_date <= '{$toEsc}'
           AND COALESCE(NULLIF(holiday_to_date, '0000-00-00'), holiday_date) >= '{$fromEsc}'";
if ($scopeDeptId > 0) {
    $conn->query(
        "UPDATE holidays SET status = 0
         WHERE {$overlap}
           AND department_id = " . (int) $scopeDeptId
    );
} else {
    $conn->query(
        "UPDATE holidays SET status = 0
         WHERE {$overlap}
           AND (department_id IS NULL OR department_id = 0)"
    );
}

$ins = $conn->prepare(
    "INSERT INTO holidays (title, holiday_type, holiday_date, holiday_to_date, week_day, is_paid, department_id, remarks, status)
     VALUES (?, 'Holiday', ?, ?, NULL, ?, ?, '', 1)"
);

for ($d = 1; $d <= $monthDays; $d++) {
    $on = !empty($days[$d]['on']);
    if (!$on) {
        continue;
    }
    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $title = trim((string) ($days[$d]['title'] ?? ''));
    if ($title === '') {
        $title = 'Holiday ' . date('d M Y', strtotime($date));
    }
    $isPaid = (($days[$d]['is_paid'] ?? 'Yes') === 'No') ? 'No' : 'Yes';
    $deptBind = $scopeDeptId > 0 ? $scopeDeptId : null;
    if ($deptBind === null) {
        $zero = 0;
        $ins->bind_param('ssssi', $title, $date, $date, $isPaid, $zero);
        $ins->execute();
        $newId = (int) $conn->insert_id;
        if ($newId > 0) {
            $conn->query('UPDATE holidays SET department_id = NULL WHERE id = ' . $newId);
        }
    } else {
        $ins->bind_param('ssssi', $title, $date, $date, $isPaid, $deptBind);
        $ins->execute();
    }
}
$ins->close();
$conn->close();

$redir = app_url(
    'masters/holidays/monthly.php?month=' . $month . '&year=' . $year . '&msg=saved'
    . ($scopeDeptId > 0 ? '&department_id=' . $scopeDeptId : '')
);
header('Location: ' . $redir);
exit;
