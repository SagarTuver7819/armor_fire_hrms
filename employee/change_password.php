<?php
/**
 * Employee self-service: set new password (no old-password verify)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();

if (!isEmployee()) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . app_url('index.php'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($newPass === '' || $confirm === '') {
        $error = 'Enter new password and confirm password.';
    } elseif ($newPass !== $confirm) {
        $error = 'New password and confirm password do not match.';
    } elseif (strlen($newPass) < 4) {
        $error = 'Password must be at least 4 characters.';
    } elseif (strlen($newPass) > 100) {
        $error = 'Password is too long (max 100 characters).';
    } else {
        $conn = getDBConnection();
        $st = $conn->prepare(
            "UPDATE users SET password = ?
             WHERE id = ? AND role = 'employee'
             LIMIT 1"
        );
        $st->bind_param('si', $newPass, $userId);
        $ok = $st->execute() && $st->affected_rows >= 0;
        $st->close();
        // Confirm row exists
        $chk = $conn->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'employee' LIMIT 1"
        );
        $chk->bind_param('i', $userId);
        $chk->execute();
        $exists = (bool) $chk->get_result()->fetch_assoc();
        $chk->close();
        $conn->close();

        if ($ok && $exists) {
            header('Location: ' . app_url('employee/change_password.php?msg=updated'));
            exit;
        }
        $error = 'Could not update password. Please try again.';
    }
}

$pageTitle = 'Change Password';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'change_password';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];

require_once __DIR__ . '/../includes/header.php';

$toastMsg = '';
$toastType = 'success';
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $toastMsg = 'Password updated successfully.';
}
if ($error !== '') {
    $toastMsg = $error;
    $toastType = 'error';
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
    </div>

    <div class="form-page-card" style="max-width:520px;">
        <div class="form-page-header">
            <div>
                <h1><i class="fa-solid fa-key" style="color:#F58220;"></i> Change Password</h1>
                <p>Set a new login password · No old password needed</p>
            </div>
        </div>

        <form method="post" action="<?php echo app_url('employee/change_password.php'); ?>" autocomplete="off" class="employee-form">
            <div class="form-grid" style="grid-template-columns:1fr;">
                <div class="form-group">
                    <label for="new_password">New Password <span class="req">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="form-control"
                           required minlength="4" maxlength="100"
                           placeholder="Enter new password"
                           autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           required minlength="4" maxlength="100"
                           placeholder="Re-enter new password"
                           autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions" style="margin-top:8px;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Password
                </button>
                <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php if ($toastMsg !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof toastr !== 'undefined') {
        toastr.options = { closeButton: true, progressBar: true, timeOut: 3500 };
        toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>('<?php echo addslashes($toastMsg); ?>');
    } else {
        alert(<?php echo json_encode($toastMsg); ?>);
    }
});
</script>
<?php endif; ?>

<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
