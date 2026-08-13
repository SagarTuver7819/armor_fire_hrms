<?php
/**
 * Legacy redirect → employees/pdf.php
 */
require_once __DIR__ . '/config/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . app_url('employees/pdf.php' . ($q !== '' ? ('?' . $q) : '')));
exit;
