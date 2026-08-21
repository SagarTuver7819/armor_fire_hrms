<?php
/**
 * Save monthly holiday set for one month/year
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
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$from = sprintf('%04d-%02d-01', $year, $month);
$to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);
$days = $_POST['days'] ?? [];

ensureMasterTables();
$conn = getDBConnection();

// Soft-remove existing Holiday-type rows for this month (keep Week-Off master rows)
$conn->query(
    "UPDATE holidays SET status = 0
     WHERE holiday_type = 'Holiday'
       AND holiday_date BETWEEN '{$conn->real_escape_string($from)}' AND '{$conn->real_escape_string($to)}'"
);

$ins = $conn->prepare(
    "INSERT INTO holidays (title, holiday_type, holiday_date, week_day, is_paid, remarks, status)
     VALUES (?, 'Holiday', ?, NULL, ?, '', 1)"
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
    $ins->bind_param('sss', $title, $date, $isPaid);
    $ins->execute();
}
$ins->close();
$conn->close();

header('Location: ' . app_url('masters/holidays/monthly.php?month=' . $month . '&year=' . $year . '&msg=saved'));
exit;
