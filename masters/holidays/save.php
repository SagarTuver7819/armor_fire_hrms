<?php
/**
 * Holiday save — redirect back to department scope when applicable
 */
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $masterListUrl);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$returnDeptId = (int) ($_POST['return_department_id'] ?? 0);
$data = collectMasterPostData($master, $_POST);

$error = validateMasterData($master, $data);
if ($error !== '') {
    die(htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}

// 0 / empty = All Departments
$deptId = (int) ($data['department_id'] ?? 0);
$data['department_id'] = $deptId;

$result = saveMasterRow($master, $data, $id);
if (!$result['ok']) {
    die('Save failed: ' . htmlspecialchars($result['error']) . ' <a href="javascript:history.back()">Go Back</a>');
}

$savedId = (int) $result['id'];
if ($savedId > 0 && $deptId === 0) {
    $conn = getDBConnection();
    $up = $conn->prepare('UPDATE holidays SET department_id = NULL WHERE id = ?');
    $up->bind_param('i', $savedId);
    $up->execute();
    $up->close();
    $conn->close();
}

$msg = $id > 0 ? 'updated' : 'added';
$redir = app_url('masters/holidays/index.php');
if ($returnDeptId > 0) {
    $redir .= '?department_id=' . $returnDeptId . '&msg=' . $msg;
} else {
    $redir .= '?msg=' . $msg;
}
header('Location: ' . $redir);
exit;
