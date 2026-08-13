<?php
/**
 * Legacy redirect → employees/view.php
 */
require_once __DIR__ . '/config/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . app_url('employees/view.php' . ($q !== '' ? ('?' . $q) : '')));
exit;
