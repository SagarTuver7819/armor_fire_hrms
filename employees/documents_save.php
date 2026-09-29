<?php
/**
 * Save / replace employee related document (employee self-service or HR/Admin edit)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/employee_documents_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$docType = trim((string) ($_POST['doc_type'] ?? ''));
$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);

$redirect = app_url('employees/view.php?id=' . max(0, $employeeId) . '&tab=documents');

if ($employeeId <= 0 || !isset(employeeRelatedDocumentTypes()[$docType])) {
    header('Location: ' . $redirect . '&msg=doc_invalid');
    exit;
}

$emp = getEmployeeById($employeeId);
if (!$emp) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$deptId = (int) ($emp['department_id'] ?? 0);
$isOwn = $sessionEmpId > 0 && $sessionEmpId === $employeeId;
$canStaffEdit = isAdmin() || isHR() || canAccess('employees', 'edit', $deptId);

if (!$isOwn && !$canStaffEdit) {
    header('Location: ' . $redirect . '&msg=doc_denied');
    exit;
}

if (isOfficeStaffRole() && !$isOwn) {
    header('Location: ' . $redirect . '&msg=doc_denied');
    exit;
}

try {
    ensureEmployeeRelatedDocumentsTables();
    if (!isset($_FILES['document_file'])) {
        header('Location: ' . $redirect . '&msg=doc_missing');
        exit;
    }
    $path = saveEmployeeRelatedDocumentFile($_FILES['document_file'], $employeeId, $docType);
    upsertEmployeeRelatedDocument($employeeId, $docType, $path, $userId > 0 ? $userId : null);

    // Notify Admin/HR when employee (or anyone) completes a document upload
    notifyStaffEmployeeDocumentUploaded($employeeId, $docType, $userId);

    header('Location: ' . $redirect . '&msg=doc_saved');
    exit;
} catch (Throwable $e) {
    $msg = rawurlencode($e->getMessage());
    header('Location: ' . $redirect . '&msg=doc_error&err=' . $msg);
    exit;
}
