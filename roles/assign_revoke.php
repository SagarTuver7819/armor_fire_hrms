<?php
/**
 * Revoke Employee Portal Login (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$result = revokeEmployeePortalLogin($id);
if (!$result['ok']) {
    header('Location: ' . app_url('roles/assign.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Revoke failed.')));
    exit;
}
header('Location: ' . app_url('roles/assign.php?msg=revoked'));
exit;
