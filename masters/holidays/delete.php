<?php
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$returnDeptId = (int) ($_GET['department_id'] ?? 0);
if ($id > 0) {
    softDeleteMasterRow($master['table'], $id);
}

$redir = app_url('masters/holidays/index.php');
if ($returnDeptId > 0) {
    $redir .= '?department_id=' . $returnDeptId . '&msg=deleted';
} else {
    $redir .= '?msg=deleted';
}
header('Location: ' . $redir);
exit;
