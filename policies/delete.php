<?php
/**
 * Delete Policy (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();
require_once __DIR__ . '/../includes/permission_helper.php';
requireAccess('policies', 'delete');

$id = (int) ($_GET['id'] ?? 0);
$result = deletePolicy($id);

if (!$result['ok']) {
    header('Location: ' . app_url('policies/index.php?msg=error&err=' . rawurlencode($result['error'] ?? 'Delete failed.')));
    exit;
}

header('Location: ' . app_url('policies/index.php?msg=deleted'));
exit;


