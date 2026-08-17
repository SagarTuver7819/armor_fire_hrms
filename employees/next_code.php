<?php
/**
 * Next employee code for Salary / Jobwork
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$payType = (($_GET['pay_type'] ?? 'Salary') === 'Jobwork') ? 'Jobwork' : 'Salary';
$conn = getDBConnection();
ensureEmployeesTable($conn);
$code = generateEmployeeCode($conn, $payType);
$conn->close();

echo json_encode(['ok' => true, 'code' => $code, 'pay_type' => $payType]);
