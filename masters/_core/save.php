<?php
/**
 * Shared Master Save handler
 * Requires $masterKey set before include.
 */

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $masterListUrl);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$data = collectMasterPostData($master, $_POST);
$error = validateMasterData($master, $data);

if ($error !== '') {
    die(htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}

$result = saveMasterRow($master, $data, $id);

if (!$result['ok']) {
    die('Save failed: ' . htmlspecialchars($result['error']) . ' <a href="javascript:history.back()">Go Back</a>');
}

$msg = $id > 0 ? 'updated' : 'added';
header('Location: ' . $masterListUrl . '?msg=' . $msg);
exit;
