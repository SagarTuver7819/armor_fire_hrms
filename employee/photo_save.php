<?php
/**
 * Employee self-service: update own profile photo only
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$emp = getEmployeeById($empId);
if (!$emp) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$redirect = app_url('employees/view.php?id=' . $empId);

try {
    ensureEmployeesTable();
    $oldPhoto = (string) ($emp['photo_file'] ?? '');
    $newPhoto = applyEmployeeDocumentUpload('photo_file', $empId, 'photo', $oldPhoto);
    if ($newPhoto === $oldPhoto) {
        header('Location: ' . $redirect . '&msg=photo_missing');
        exit;
    }
    $conn = getDBConnection();
    $st = $conn->prepare('UPDATE employees SET photo_file = ? WHERE id = ? LIMIT 1');
    $st->bind_param('si', $newPhoto, $empId);
    $st->execute();
    $st->close();
    $conn->close();
    header('Location: ' . $redirect . '&msg=photo_updated');
    exit;
} catch (Throwable $e) {
    header('Location: ' . $redirect . '&msg=photo_error');
    exit;
}
