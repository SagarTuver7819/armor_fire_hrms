<?php
/**
 * Mark circular notification(s) as read
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/circular_helper.php';

requireStaff();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$id = (int) ($_GET['id'] ?? 0);
$go = trim((string) ($_GET['go'] ?? ''));

if ($all) {
    markAllCircularsRead($userId);
    header('Location: ' . app_url('circulars/index.php'));
    exit;
}

if ($id > 0) {
    markCircularRead($id, $userId);
    if ($go === 'view') {
        header('Location: ' . app_url('circulars/view.php?id=' . $id));
        exit;
    }
}

header('Location: ' . app_url('circulars/index.php'));
exit;
