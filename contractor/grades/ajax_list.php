<?php
require_once __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureContractorTables($conn);
$out = contractorDtAjax(
    $conn,
    'contractor_grades',
    'SELECT id, grade_name FROM contractor_grades',
    ['grade_name'],
    [0 => 'id', 1 => 'grade_name'],
    function ($row, $sr) {
        $name = (string) $row['grade_name'];
        return [
            $sr,
            htmlspecialchars($name),
            contractorActionBtns(
                app_url('contractor/grades/edit.php?id=' . (int) $row['id']),
                app_url('contractor/grades/delete.php?id=' . (int) $row['id']),
                $name
            ),
        ];
    }
);
$conn->close();
echo json_encode($out);
