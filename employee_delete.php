<?php
/**
 * Legacy redirect → employees/delete.php
 */
require_once __DIR__ . '/config/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . app_url('employees/delete.php' . ($q !== '' ? ('?' . $q) : '')));
exit;
