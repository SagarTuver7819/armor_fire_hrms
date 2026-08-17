<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('contractor/products/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$ops = contractorOperations();
$operation = (string) ($_POST['operation'] ?? '');
$productName = trim($_POST['product_name'] ?? '');
$process = trim($_POST['process'] ?? '');
$unit = (($_POST['unit'] ?? 'PCS') === 'KG') ? 'KG' : 'PCS';
$rate = (float) ($_POST['rate'] ?? 0);
$otText = trim($_POST['ot_text'] ?? '');
$rej = (float) ($_POST['rejection_rate'] ?? 0);

if ($productName === '' || !isset($ops[$operation])) {
    die('Operation and Product Name are required. <a href="javascript:history.back()">Go Back</a>');
}

$conn = getDBConnection();
ensureContractorTables($conn);
if ($id > 0) {
    $stmt = $conn->prepare('UPDATE contractor_products SET operation=?, product_name=?, process=?, unit=?, rate=?, ot_text=?, rejection_rate=? WHERE id=? AND status=1');
    $stmt->bind_param('ssssdsdi', $operation, $productName, $process, $unit, $rate, $otText, $rej, $id);
} else {
    $stmt = $conn->prepare('INSERT INTO contractor_products (operation, product_name, process, unit, rate, ot_text, rejection_rate, status) VALUES (?,?,?,?,?,?,?,1)');
    $stmt->bind_param('ssssdsd', $operation, $productName, $process, $unit, $rate, $otText, $rej);
}
$ok = $stmt->execute();
$error = $stmt->error;
$stmt->close();
$conn->close();
if (!$ok) {
    die('Save failed: ' . htmlspecialchars($error) . ' <a href="javascript:history.back()">Go Back</a>');
}
header('Location: ' . app_url('contractor/products/index.php?msg=' . ($id > 0 ? 'updated' : 'added')));
exit;
