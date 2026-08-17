<?php
/**
 * employees/delete.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$fromAll = isset($_GET['all']) ? 1 : 0;
$fromContractor = (($_GET['from'] ?? '') === 'contractor');

if ($id > 0) {
    $conn = getDBConnection();
    ensureEmployeesTable($conn);
    $stmt = $conn->prepare("UPDATE employees SET status = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

if ($fromContractor) {
    header('Location: ' . app_url('contractor/employees/index.php?msg=deleted'));
} elseif ($fromAll || $deptId <= 0) {
    header('Location: ' . app_url('employees/index.php?msg=deleted'));
} else {
    header('Location: ' . app_url('employees/index.php?department_id=' . $deptId . '&msg=deleted'));
}
exit;
