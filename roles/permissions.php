<?php
/**
 * Role Permissions Matrix — module × View/Add/Edit/Delete for a department scope (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureRoleTables();

$roleId = (int) ($_GET['role_id'] ?? 0);
$departmentId = (int) ($_GET['department_id'] ?? 0);
$roles = fetchRoles(true);
$role = $roleId > 0 ? getRoleById($roleId) : null;

if ($roleId > 0 && !$role) {
    header('Location: ' . app_url('roles/index.php?msg=error&err=' . rawurlencode('Role not found.')));
    exit;
}

$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$modules = getPermissionModules();
$actions = getPermissionActions();
$matrix = $roleId > 0 ? getRolePermissionMatrix($roleId, $departmentId) : [];

$pageTitle = 'Role Permissions';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'roles';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$extraJs = ['assets/js/role_permissions.js'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Role created — set permissions below.',
        'perms' => 'Permissions saved for this role & department.',
        'error' => (string) ($_GET['err'] ?? 'Something went wrong.'),
    ];
    $toast = $map[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'error') {
        $toastType = 'error';
    }
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('roles/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Roles
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0F766E;">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h1>Set Permissions</h1>
                    <p>Choose role &amp; department, then tick View / Add / Edit / Delete per module</p>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters" id="permFilterForm">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="permRoleId">Role <span class="req">*</span></label>
                    <select name="role_id" id="permRoleId" class="form-control" required onchange="this.form.submit()">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $roleId === (int) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="permDeptId">Department Scope</label>
                    <select name="department_id" id="permDeptId" class="form-control" onchange="this.form.submit()" <?php echo $roleId <= 0 ? 'disabled' : ''; ?>>
                        <option value="0" <?php echo $departmentId === 0 ? 'selected' : ''; ?>>All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $departmentId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Permissions saved for this scope only. Use “All Departments” for global access, or set per department.</small>
                </div>
            </div>
        </form>
    </div>

    <?php if ($roleId > 0 && $role): ?>
    <div class="form-page-card" style="margin-top:14px;">
        <form method="POST" action="<?php echo app_url('roles/permissions_save.php'); ?>" id="permMatrixForm">
            <input type="hidden" name="role_id" value="<?php echo $roleId; ?>">
            <input type="hidden" name="department_id" value="<?php echo $departmentId; ?>">

            <div class="flex-between" style="margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <div>
                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                    <span class="text-muted"> · </span>
                    <span class="text-muted">
                        <?php
                        if ($departmentId === 0) {
                            echo 'All Departments';
                        } else {
                            foreach ($departments as $d) {
                                if ((int) $d['id'] === $departmentId) {
                                    echo htmlspecialchars($d['department_name']);
                                    break;
                                }
                            }
                        }
                        ?>
                    </span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" class="btn-secondary btn-sm" id="permSelectAll">Select All</button>
                    <button type="button" class="btn-secondary btn-sm" id="permClearAll">Clear All</button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table" id="permMatrixTable">
                    <thead>
                        <tr>
                            <th style="min-width:180px;">Module</th>
                            <?php foreach ($actions as $actKey => $actLabel): ?>
                                <th class="text-center" style="width:90px;">
                                    <label style="cursor:pointer;display:inline-flex;flex-direction:column;align-items:center;gap:4px;">
                                        <input type="checkbox" class="perm-col-all" data-action="<?php echo htmlspecialchars($actKey); ?>" title="Toggle column">
                                        <span><?php echo htmlspecialchars($actLabel); ?></span>
                                    </label>
                                </th>
                            <?php endforeach; ?>
                            <th class="text-center" style="width:70px;">All</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $modKey => $mod): ?>
                            <?php $flags = $matrix[$modKey] ?? ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0]; ?>
                            <tr data-module="<?php echo htmlspecialchars($modKey); ?>">
                                <td>
                                    <i class="fa-solid <?php echo htmlspecialchars($mod['icon']); ?>" style="margin-right:6px;opacity:.75;"></i>
                                    <?php echo htmlspecialchars($mod['label']); ?>
                                </td>
                                <?php foreach ($actions as $actKey => $actLabel): ?>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               class="perm-check"
                                               name="perms[<?php echo htmlspecialchars($modKey); ?>][<?php echo htmlspecialchars($actKey); ?>]"
                                               value="1"
                                               data-action="<?php echo htmlspecialchars($actKey); ?>"
                                               <?php echo !empty($flags[$actKey]) ? 'checked' : ''; ?>>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center">
                                    <input type="checkbox" class="perm-row-all" title="Toggle row">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions sticky-actions" style="margin-top:16px;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Permissions
                </button>
                <a href="<?php echo app_url('roles/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="form-page-card" style="margin-top:14px;">
        <p class="text-muted" style="text-align:center;padding:24px;">Select a role to configure permissions.</p>
    </div>
    <?php endif; ?>
</main>

<?php if ($toast !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof toastr !== 'undefined') {
        toastr.options = { closeButton: true, progressBar: true, timeOut: 3500 };
        toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>('<?php echo addslashes($toast); ?>');
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
