<?php
require_once __DIR__ . '/../_bootstrap.php';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare('UPDATE contractor_employment SET status = 0 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
header('Location: ' . app_url('contractor/employment/index.php?msg=deleted'));
exit;
