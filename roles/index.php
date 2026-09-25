<?php
/**
 * Custom Roles — list (Admin only)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureRoleTables();

$rows = fetchRoles(false);

$pageTitle = 'Roles & Access';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'roles';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Role created.',
        'updated' => 'Role updated.',
        'deleted' => 'Role deleted.',
        'perms' => 'Permissions saved.',
        'assigned' => 'Role assigned with employee login.',
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
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('roles/department_heads.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-user-tie"></i> Department Heads
            </a>
            <a href="<?php echo app_url('roles/bulk_employee_logins.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-users-gear"></i> Bulk Employee Logins
            </a>
            <a href="<?php echo app_url('roles/assign.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-user-check"></i> Assign Role &amp; Login
            </a>
            <a href="<?php echo app_url('users/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-user-gear"></i> Staff Users
            </a>
            <a href="<?php echo app_url('roles/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add Role
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0F766E;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div>
                    <h1>Roles &amp; Access</h1>
                    <p>Custom roles · Department Heads · Leave: Payroll Head approve · Office Staff self-apply</p>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Role</th>
                        <th>Code</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="text-muted" style="text-align:center;padding:28px;">
                            No custom roles yet. Click <strong>Add Role</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                                <?php if (!empty($r['description'])): ?>
                                    <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['code'] ?? '—'); ?></td>
                            <td><?php echo (int) ($r['user_count'] ?? 0); ?></td>
                            <td>
                                <?php if ((int) $r['status'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="<?php echo app_url('roles/permissions.php?role_id=' . (int) $r['id']); ?>"
                                   class="btn-sm btn-secondary" title="Set permissions">
                                    <i class="fa-solid fa-key"></i> Permissions
                                </a>
                                <a href="<?php echo app_url('roles/assign.php?role_id=' . (int) $r['id']); ?>"
                                   class="btn-sm btn-secondary" title="Assign to employee">
                                    <i class="fa-solid fa-user-check"></i> Assign
                                </a>
                                <a href="<?php echo app_url('roles/edit.php?id=' . (int) $r['id']); ?>"
                                   class="btn-sm btn-secondary" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="<?php echo app_url('roles/delete.php?id=' . (int) $r['id']); ?>"
                                   class="btn-sm btn-danger"
                                   onclick="return confirm('Delete this role?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
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
