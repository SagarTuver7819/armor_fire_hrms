<?php
/**
 * Link / unlink machine punches → local employee
 * Affects ONLY machine_attendance_logs + biometric_employee_map
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('attendance/machine_logs.php'));
    exit;
}

$action = (string) ($_POST['action'] ?? 'link');
$redirect = trim((string) ($_POST['redirect'] ?? ''));
if ($redirect === '' || strpos($redirect, 'attendance/') === false) {
    $redirect = app_url('attendance/machine_logs.php');
} elseif (strpos($redirect, 'http') !== 0) {
    $redirect = app_url(ltrim($redirect, '/'));
}

if ($action === 'auto_link') {
    $_SESSION['bio_flash'] = autoLinkMachineLogs();
} elseif ($action === 'unlink') {
    $_SESSION['bio_flash'] = unlinkMachineLogEmployee(
        (int) ($_POST['log_id'] ?? 0),
        !empty($_POST['clear_map'])
    );
} else {
    $_SESSION['bio_flash'] = linkMachineLogsToEmployee(
        (int) ($_POST['employee_id'] ?? 0),
        trim((string) ($_POST['biometric_code'] ?? '')),
        (int) ($_POST['log_id'] ?? 0),
        !isset($_POST['apply_same_code']) || !empty($_POST['apply_same_code'])
    );
}

header('Location: ' . $redirect);
exit;
