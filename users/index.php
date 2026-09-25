<?php
/**
 * Staff Users — list (Admin only)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureRoleTables();

$rows = fetchStaffUsers();

$pageTitle = 'Staff Users';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'staff_users';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Staff user created.',
        'updated' => 'Staff user updated.',
        'deleted' => 'Staff user deleted.',
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
        <div class="toolbar-actions">
            <a href="<?php echo app_url('users/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add Staff User
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#1D4ED8;">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h1>Staff Users</h1>
                    <p>Assign custom roles to HR users · Admin always has full access</p>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Login As</th>
                        <th>Custom Role</th>
                        <th>Home Dept</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-muted" style="text-align:center;padding:28px;">No staff users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $u): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo strtoupper(htmlspecialchars($u['role'])); ?></td>
                            <td>
                                <?php if (($u['role'] ?? '') === 'admin'): ?>
                                    <span class="text-muted">Full access</span>
                                <?php elseif (!empty($u['role_name'])): ?>
                                    <?php echo htmlspecialchars($u['role_name']); ?>
                                <?php else: ?>
                                    <span class="badge badge-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['department_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ((int) $u['status'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="<?php echo app_url('users/edit.php?id=' . (int) $u['id']); ?>"
                                   class="btn-sm btn-secondary" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <?php if ((int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                <a href="<?php echo app_url('users/delete.php?id=' . (int) $u['id']); ?>"
                                   class="btn-sm btn-danger"
                                   onclick="return confirm('Delete this user?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                                <?php endif; ?>
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
