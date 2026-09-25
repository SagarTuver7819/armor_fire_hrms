<?php
/**
 * Department Heads — one employee head per department (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireDepartmentHeadsManager();
ensureDepartmentHeadTables();


$rows = fetchDepartmentHeadsList();
$deptHeadRoleId = getRoleIdByCode('DEPT_HEAD');

$pageTitle = 'Department Heads';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = isAdmin() ? 'roles' : 'dept_heads';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'saved' => 'Department head saved.',
        'cleared' => 'Department head cleared.',
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
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('roles/assign.php' . ($deptHeadRoleId ? ('?role_id=' . $deptHeadRoleId) : '')); ?>" class="btn-secondary">
                <i class="fa-solid fa-user-check"></i> Assign Dept Head Login
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#B45309;">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <h1>Department Heads</h1>
                    <p>One head per department · Shown in Employee Matrix · Only <strong>Admin</strong> / <strong>HR Head</strong> can set</p>
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Department</th>
                        <th>Head Employee</th>
                        <th>Portal Login</th>
                        <th style="min-width:280px;">Set / Change Head</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px;">No departments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                        $deptId = (int) $r['department_id'];
                        $emps = fetchEmployeesByDepartment($deptId);
                        $curEmp = (int) ($r['employee_id'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['department_name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($curEmp > 0): ?>
                                    <strong><?php echo htmlspecialchars($r['employee_name'] ?? ''); ?></strong>
                                    <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['portal_username'])): ?>
                                    <code><?php echo htmlspecialchars($r['portal_username']); ?></code>
                                    <?php if (!empty($r['role_name'])): ?>
                                        <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['role_name']); ?></div>
                                    <?php endif; ?>
                                <?php elseif ($curEmp > 0): ?>
                                    <a href="<?php echo app_url('roles/assign.php?role_id=' . (int) $deptHeadRoleId); ?>" class="text-muted" style="font-size:12px;">
                                        Create login →
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo app_url('roles/department_heads_save.php'); ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                    <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
                                    <select name="employee_id" class="form-control" style="min-width:180px;flex:1;" required>
                                        <option value="">Select employee</option>
                                        <?php foreach ($emps as $e): ?>
                                            <option value="<?php echo (int) $e['id']; ?>" <?php echo $curEmp === (int) $e['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-sm btn-primary" title="Save">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                    <?php if ($curEmp > 0): ?>
                                        <a href="<?php echo app_url('roles/department_heads_clear.php?department_id=' . $deptId); ?>"
                                           class="btn-sm btn-danger"
                                           onclick="return confirm('Clear department head?');" title="Clear">
                                            <i class="fa-solid fa-xmark"></i>
                                        </a>
                                    <?php endif; ?>
                                </form>
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
