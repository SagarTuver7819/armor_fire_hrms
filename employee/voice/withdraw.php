<?php
/**
 * Employee Voice — withdraw own ticket
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('employee/voice/index.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
$id = (int) ($_POST['id'] ?? 0);
$res = evWithdrawTicket($id, $empId, (int) ($_SESSION['user_id'] ?? 0));

if (!empty($res['ok'])) {
    header('Location: ' . app_url('employee/voice/index.php?msg=withdrawn'));
    exit;
}

header('Location: ' . app_url('employee/voice/view.php?id=' . $id . '&msg=' . rawurlencode($res['error'] ?? 'Withdraw failed')));
exit;
