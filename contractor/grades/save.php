<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/grades/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['grade_name'] ?? '');
if ($name === '') {
    die('Grade name is required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureContractorTables($conn);
if ($id > 0) {
    $stmt = $conn->prepare('UPDATE contractor_grades SET grade_name = ? WHERE id = ? AND status = 1');
    $stmt->bind_param('si', $name, $id);
} else {
    $stmt = $conn->prepare('INSERT INTO contractor_grades (grade_name, status) VALUES (?, 1)');
    $stmt->bind_param('s', $name);
}
$ok = $stmt->execute();
$error = $stmt->error;
$stmt->close();
$conn->close();
if (!$ok) {
    die('Save failed: ' . htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}
header('Location: ' . app_url('contractor/grades/index.php?msg=' . ($id > 0 ? 'updated' : 'added')));
exit;
