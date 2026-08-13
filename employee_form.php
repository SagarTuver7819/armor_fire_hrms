<?php
/**
 * Legacy redirect → employees/edit.php
 */
require_once __DIR__ . '/config/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . app_url('employees/edit.php' . ($q !== '' ? ('?' . $q) : '')));
exit;
