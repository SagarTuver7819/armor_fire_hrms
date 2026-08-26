<?php
/**
 * Upload / replace Leave Policy PDF (Admin)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/leave_policy.php';

requireLogin();
if (!isAdmin() && !isHR()) {
    header('Location: ' . app_url('masters/leaves/index.php'));
    exit;
}

$redirect = app_url('masters/leaves/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (empty($_FILES['policy_pdf']) || !is_array($_FILES['policy_pdf'])) {
    header('Location: ' . $redirect . '&msg=policy_error&err=' . rawurlencode('No file selected.'));
    exit;
}

$file = $_FILES['policy_pdf'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirect . '&msg=policy_error&err=' . rawurlencode('Upload failed.'));
    exit;
}

$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    header('Location: ' . $redirect . '&msg=policy_error&err=' . rawurlencode('Only PDF files are allowed.'));
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if ($mime !== 'application/pdf' && $mime !== 'application/octet-stream') {
    header('Location: ' . $redirect . '&msg=policy_error&err=' . rawurlencode('Invalid PDF file.'));
    exit;
}

leavePolicyEnsureDir();
$dest = leavePolicyAbsolutePath();
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: ' . $redirect . '&msg=policy_error&err=' . rawurlencode('Could not save PDF.'));
    exit;
}

header('Location: ' . $redirect . '&msg=policy_uploaded');
exit;
