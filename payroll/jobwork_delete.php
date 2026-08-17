<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

requireLogin();
$id = (int) ($_GET['id'] ?? 0);
$deptId = (int) ($_GET['department_id'] ?? 0);
if ($id > 0) {
    $conn = getDBConnection();
    ensurePayrollTables($conn);
    $stmt = $conn->prepare("DELETE FROM jobwork_entries WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
header('Location: ' . app_url('payroll/jobwork.php?department_id=' . $deptId . '&msg=deleted'));
exit;
