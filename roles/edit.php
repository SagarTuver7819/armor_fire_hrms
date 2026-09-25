<?php
/**
 * Add / Edit Custom Role (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureRoleTables();

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRoleById($id) : null;
if ($id > 0 && !$row) {
    header('Location: ' . app_url('roles/index.php?msg=error&err=' . rawurlencode('Role not found.')));
    exit;
}

$formError = '';
$formData = [];
if (!empty($_SESSION['role_form']) && is_array($_SESSION['role_form'])) {
    $formError = (string) ($_SESSION['role_form']['error'] ?? '');
    $formData = is_array($_SESSION['role_form']['data'] ?? null) ? $_SESSION['role_form']['data'] : [];
    unset($_SESSION['role_form']);
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

$statusVal = array_key_exists('status', $formData)
    ? (int) $formData['status']
    : ($row ? (int) $row['status'] : 1);

$pageTitle = $row ? 'Edit Role' : 'Add Role';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'roles';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('roles/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Roles
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0F766E;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <h1><?php echo $row ? 'Edit Role' : 'Add Role'; ?></h1>
                    <p>Define a custom role, then set module permissions</p>
                </div>
            </div>
        </div>

        <?php if ($formError !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($formError); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo app_url('roles/save.php'); ?>" class="employee-form">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="roleName">Role Name <span class="req">*</span></label>
                    <input type="text" name="name" id="roleName" class="form-control" required maxlength="100"
                           value="<?php echo htmlspecialchars($val('name')); ?>" placeholder="e.g. HR Executive">
                </div>
                <div class="form-group">
                    <label for="roleCode">Code</label>
                    <input type="text" name="code" id="roleCode" class="form-control" maxlength="50"
                           value="<?php echo htmlspecialchars($val('code')); ?>" placeholder="e.g. HR_EXEC">
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label for="roleDesc">Description</label>
                    <textarea name="description" id="roleDesc" class="form-control" rows="2"
                              placeholder="Optional notes"><?php echo htmlspecialchars($val('description')); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="roleStatus">Status</label>
                    <select name="status" id="roleStatus" class="form-control">
                        <option value="1" <?php echo $statusVal === 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $statusVal === 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Role
                </button>
                <?php if ($id > 0): ?>
                    <a href="<?php echo app_url('roles/permissions.php?role_id=' . $id); ?>" class="btn-secondary">
                        <i class="fa-solid fa-key"></i> Set Permissions
                    </a>
                <?php endif; ?>
                <a href="<?php echo app_url('roles/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
