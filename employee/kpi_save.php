<?php
/**
 * Save / submit employee KPI sheet (draft / hour / final)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

requireLogin();

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !isEmployee() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$sheetId = (int) ($_POST['sheet_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'draft')));
$ack = !empty($_POST['responsibility_ack']);
$slotIndex = (int) ($_POST['slot_index'] ?? 0);
$activities = $_POST['activity'] ?? [];
if (!is_array($activities)) {
    $activities = [];
}

$conn = getDBConnection();
ensureKpiTables($conn);
$sheet = kpiGetSheetById($sheetId, $conn);

if (!$sheet || (int) $sheet['employee_id'] !== $empId) {
    $conn->close();
    header('Location: ' . app_url('employee/kpi.php?msg=error&err=' . urlencode('Invalid KPI sheet.')));
    exit;
}

if (($sheet['kpi_date'] ?? '') !== date('Y-m-d')) {
    $conn->close();
    header('Location: ' . app_url('employee/kpi.php?msg=error&err=' . urlencode('Only today\'s KPI can be filled.')));
    exit;
}

$byIndex = [];
foreach ($activities as $idx => $text) {
    $byIndex[(int) $idx] = $text;
}

if ($action === 'hour') {
    $text = $byIndex[$slotIndex] ?? '';
    $res = kpiSubmitHour($sheetId, $slotIndex, $text, $conn);
    $conn->close();
    if (!$res['ok']) {
        header('Location: ' . app_url('employee/kpi.php?msg=error&err=' . urlencode($res['error'] ?? 'Hour submit failed')));
        exit;
    }
    header('Location: ' . app_url('employee/kpi.php?msg=hour#slot-' . $slotIndex));
    exit;
}

$save = kpiSaveDraft($sheetId, $byIndex, $conn);
if (!$save['ok']) {
    $conn->close();
    header('Location: ' . app_url('employee/kpi.php?msg=error&err=' . urlencode($save['error'] ?? 'Save failed')));
    exit;
}

if ($action === 'submit') {
    $sub = kpiSubmitSheet($sheetId, $ack, $conn);
    $conn->close();
    if (!$sub['ok']) {
        header('Location: ' . app_url('employee/kpi.php?msg=error&err=' . urlencode($sub['error'] ?? 'Submit failed')));
        exit;
    }
    header('Location: ' . app_url('employee/kpi.php?msg=submitted'));
    exit;
}

$conn->close();
header('Location: ' . app_url('employee/kpi.php?msg=saved'));
exit;
