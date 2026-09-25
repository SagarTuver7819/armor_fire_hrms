<?php
/**
 * Delete Circular (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/circular_helper.php';

requireStaff();

$id = (int) ($_GET['id'] ?? 0);
$result = deleteCircular($id);

if (!$result['ok']) {
    header('Location: ' . app_url('circulars/index.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Delete failed.')));
    exit;
}

header('Location: ' . app_url('circulars/index.php?msg=deleted'));
exit;
