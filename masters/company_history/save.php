<?php
/**
 * Save Company History master
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('masters/company_history/index.php'));
    exit;
}

$title = trim((string) ($_POST['title'] ?? 'Company History'));
$company = trim((string) ($_POST['company'] ?? ''));
$heading = trim((string) ($_POST['heading'] ?? 'Company History'));
$paraRaw = (string) ($_POST['paragraphs'] ?? '');
$paragraphs = preg_split("/\n\s*\n/", $paraRaw);
$paragraphs = array_values(array_filter(array_map('trim', $paragraphs), static function ($p) {
    return $p !== '';
}));

$body = [
    'company' => $company,
    'heading' => $heading,
    'paragraphs' => $paragraphs,
    'products_2021' => companyContentLinesToArray($_POST['products_2021'] ?? ''),
    'products_today' => companyContentLinesToArray($_POST['products_today'] ?? ''),
];

$res = saveCompanyContent('history', $title, $body, (int) ($_SESSION['user_id'] ?? 0));
if (!$res['ok']) {
    header('Location: ' . app_url('masters/company_history/index.php?msg=error&err=' . urlencode($res['error'] ?? 'Save failed')));
    exit;
}
header('Location: ' . app_url('masters/company_history/index.php?msg=saved'));
exit;
