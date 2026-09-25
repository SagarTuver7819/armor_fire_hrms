<?php
/**
 * Add / Edit Staff User (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureRoleTables();

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getStaffUserById($id) : null;
if ($id > 0 && !$row) {
    header('Location: ' . app_url('users/index.php?msg=error&err=' . rawurlencode('User not found.')));
    exit;
}

$roles = fetchRoles(true);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');

$formError = '';
$formData = [];
if (!empty($_SESSION['staff_user_form']) && is_array($_SESSION['staff_user_form'])) {
    $formError = (string) ($_SESSION['staff_user_form']['error'] ?? '');
    $formData = is_array($_SESSION['staff_user_form']['data'] ?? null) ? $_SESSION['staff_user_form']['data'] : [];
    unset($_SESSION['staff_user_form']);
}

$val = static function ($key, $fallback = '') use ($formData, $row) {
    if (array_key_exists($key, $formData)) {
        return (string) $formData[$key];
    }
    if ($row && array_key_exists($key, $row) && $row[$key] !== null) {
        return (string) $row[$key];
    }
    return (string) $fallback;
};

$loginRole = array_key_exists('role', $formData)
    ? (string) $formData['role']
    : ($row['role'] ?? 'hr');
$statusVal = array_key_exists('status', $formData)
    ? (int) $formData['status']
    : ($row ? (int) $row['status'] : 1);
$customRoleId = array_key_exists('custom_role_id', $formData)
    ? (int) $formData['custom_role_id']
    : (int) ($row['custom_role_id'] ?? 0);
$deptId = array_key_exists('department_id', $formData)
    ? (int) $formData['department_id']
    : (int) ($row['department_id'] ?? 0);

$pageTitle = $row ? 'Edit Staff User' : 'Add Staff User';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'staff_users';
$extraJs = ['assets/js/case_force.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('users/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Staff Users
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#1D4ED8;">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h1><?php echo $row ? 'Edit Staff User' : 'Add Staff User'; ?></h1>
                    <p>Login portal role + optional custom access role for HR</p>
                </div>
            </div>
        </div>

        <?php if ($formError !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($formError); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo app_url('users/save.php'); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="fullName">Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" id="fullName" class="form-control" required maxlength="100"
                           value="<?php echo htmlspecialchars($val('full_name')); ?>">
                </div>
                <div class="form-group">
                    <label for="username">Username <span class="req">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" required maxlength="50"
                           value="<?php echo htmlspecialchars($val('username')); ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">Password <?php echo $id > 0 ? '' : '<span class="req">*</span>'; ?></label>
                    <input type="password" name="password" id="password" class="form-control" maxlength="100"
                           <?php echo $id > 0 ? '' : 'required'; ?>
                           placeholder="<?php echo $id > 0 ? 'Leave blank to keep current' : ''; ?>"
                           autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="loginRole">Login As <span class="req">*</span></label>
                    <select name="role" id="loginRole" class="form-control">
                        <option value="hr" <?php echo $loginRole === 'hr' ? 'selected' : ''; ?>>HR</option>
                        <option value="admin" <?php echo $loginRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group" id="customRoleWrap">
                    <label for="customRoleId">Custom Role (Access)</label>
                    <select name="custom_role_id" id="customRoleId" class="form-control">
                        <option value="0">— Not assigned —</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $customRoleId === (int) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Required for HR to access modules. Admin always has full access.</small>
                </div>
                <div class="form-group">
                    <label for="homeDept">Home Department</label>
                    <select name="department_id" id="homeDept" class="form-control">
                        <option value="0">— None —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="userStatus">Status</label>
                    <select name="status" id="userStatus" class="form-control">
                        <option value="1" <?php echo $statusVal === 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $statusVal === 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save User
                </button>
                <a href="<?php echo app_url('users/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    var roleSel = document.getElementById('loginRole');
    var wrap = document.getElementById('customRoleWrap');
    function sync() {
        if (!roleSel || !wrap) return;
        wrap.style.opacity = roleSel.value === 'admin' ? '0.45' : '1';
        wrap.querySelector('select').disabled = roleSel.value === 'admin';
    }
    if (roleSel) {
        roleSel.addEventListener('change', sync);
        sync();
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
