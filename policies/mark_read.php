<?php
/**
 * Mark Policy notification(s) as read
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$id = (int) ($_GET['id'] ?? 0);
$go = trim((string) ($_GET['go'] ?? ''));

if ($all) {
    markAllPoliciesRead($userId);
    header('Location: ' . app_url('policies/index.php'));
    exit;
}

if ($id > 0) {
    markPolicyRead($id, $userId);
    if ($go === 'view') {
        header('Location: ' . app_url('policies/view.php?id=' . $id));
        exit;
    }
}

header('Location: ' . app_url('policies/index.php'));
exit;


