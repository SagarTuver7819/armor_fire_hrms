<?php
require_once __DIR__ . '/../_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$conn = getDBConnection();
$sheet = getContractorSheetById($id, $conn);
if (!$sheet) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations/index.php'));
    exit;
}

$up = $conn->prepare('UPDATE contractor_operation_sheets SET salary_generated = 1 WHERE id = ?');
$up->bind_param('i', $id);
$up->execute();
$up->close();

$chk = $conn->prepare('SELECT id FROM contractor_salary WHERE sheet_id = ? LIMIT 1');
$chk->bind_param('i', $id);
$chk->execute();
$have = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$have) {
    $ins = $conn->prepare('INSERT INTO contractor_salary (sheet_id, employee_id, operation, month_no, year_no, total_qty, total_amount) VALUES (?,?,?,?,?,?,?)');
    $ins->bind_param(
        'iisiidd',
        $id,
        $sheet['employee_id'],
        $sheet['operation'],
        $sheet['month_no'],
        $sheet['year_no'],
        $sheet['total_qty'],
        $sheet['total_amount']
    );
    $ins->execute();
    $ins->close();
} else {
    $ins = $conn->prepare('UPDATE contractor_salary SET total_qty=?, total_amount=? WHERE sheet_id=?');
    $ins->bind_param('ddi', $sheet['total_qty'], $sheet['total_amount'], $id);
    $ins->execute();
    $ins->close();
}

$conn->close();
header('Location: ' . app_url('contractor/operations/index.php?msg=generated'));
exit;
