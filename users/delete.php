<?php
/**
 * Delete Staff User (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$result = deleteStaffUser($id);
if (!$result['ok']) {
    header('Location: ' . app_url('users/index.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Delete failed.')));
    exit;
}
header('Location: ' . app_url('users/index.php?msg=deleted'));
exit;
