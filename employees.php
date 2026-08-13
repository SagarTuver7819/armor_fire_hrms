<?php
/**
 * Legacy redirect → employees/index.php
 */
require_once __DIR__ . '/config/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . app_url('employees/index.php' . ($q !== '' ? ('?' . $q) : '')));
exit;
