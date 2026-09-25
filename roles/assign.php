<?php
/**
 * Assign Role + Employee Portal Login (Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireAdmin();
ensureRoleTables();
ensureDepartmentHeadTables();

$roleId = (int) ($_GET['role_id'] ?? 0);
$editId = (int) ($_GET['id'] ?? 0);
$roles = fetchRoles(true);
$officeStaffRoleId = getRoleIdByCode('OFFICE_STAFF');
if ($roleId <= 0 && $editId <= 0 && $officeStaffRoleId > 0) {
    $roleId = $officeStaffRoleId;
}
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$employees = fetchEmployeesForRoleAssign(0);
$assignments = fetchEmployeePortalAssignments(0); // full list for CRUD visibility
$editRow = $editId > 0 ? getEmployeePortalUserById($editId) : null;

$formError = '';
$formData = [];
if (!empty($_SESSION['role_assign_form']) && is_array($_SESSION['role_assign_form'])) {
    $formError = (string) ($_SESSION['role_assign_form']['error'] ?? '');
    $formData = is_array($_SESSION['role_assign_form']['data'] ?? null) ? $_SESSION['role_assign_form']['data'] : [];
    unset($_SESSION['role_assign_form']);
}

$selRole = array_key_exists('custom_role_id', $formData)
    ? (int) $formData['custom_role_id']
    : ($editRow ? (int) $editRow['custom_role_id'] : $roleId);
$selEmp = array_key_exists('employee_id', $formData)
    ? (int) $formData['employee_id']
    : ($editRow ? (int) $editRow['employee_id'] : 0);
$selDept = array_key_exists('filter_department_id', $formData)
    ? (int) $formData['filter_department_id']
    : 0;
if ($selDept <= 0 && $selEmp > 0) {
    foreach ($employees as $e) {
        if ((int) ($e['id'] ?? 0) === $selEmp) {
            $selDept = (int) ($e['department_id'] ?? 0);
            break;
        }
    }
}
if ($selDept <= 0 && $editRow) {
    $selDept = (int) ($editRow['emp_department_id'] ?? $editRow['department_id'] ?? 0);
}
$selUser = array_key_exists('username', $formData)
    ? (string) $formData['username']
    : ($editRow['username'] ?? '');
$selPass = array_key_exists('password', $formData)
    ? (string) $formData['password']
    : ($editRow['password'] ?? '');
$selStatus = array_key_exists('status', $formData)
    ? (int) $formData['status']
    : ($editRow ? (int) $editRow['status'] : 1);

$pageTitle = 'Assign Role & Login';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'roles';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$extraJs = ['assets/js/case_force.js', 'assets/js/role_assign.js'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'assigned' => 'Role & employee login saved.',
        'updated' => 'Existing login updated with new role.',
        'revoked' => 'Employee portal login removed.',
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
                <div class="master-list-icon" style="background:#0369A1;">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <h1><?php echo $editRow ? 'Edit Assignment' : 'Assign Role &amp; Login'; ?></h1>
                    <p>Link employee to a custom role and create separate Employee portal login</p>
                </div>
            </div>
        </div>

        <div class="alert" style="background:#F0F9FF;border:1px solid #BAE6FD;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13px;">
            <strong>Quick roles:</strong>
            Office Staff = normal employee login (self leave apply) ·
            Department Head = after setting head in <a href="<?php echo app_url('roles/department_heads.php'); ?>">Department Heads</a> ·
            Payroll Head = leave approve all depts ·
            HR Head = HR + leave view/approve
        </div>

        <?php if ($formError !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;"><?php echo htmlspecialchars($formError); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo app_url('roles/assign_save.php'); ?>" class="employee-form" autocomplete="off" id="roleAssignForm">
            <input type="hidden" name="user_id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label for="assignRoleId">Custom Role <span class="req">*</span></label>
                    <select name="custom_role_id" id="assignRoleId" class="form-control" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $selRole === (int) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="assignDeptId">Department <span class="req">*</span></label>
                    <select name="filter_department_id" id="assignDeptId" class="form-control" <?php echo $editRow ? 'disabled' : 'required'; ?>>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $selDept === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editRow && $selDept > 0): ?>
                        <input type="hidden" name="filter_department_id" value="<?php echo (int) $selDept; ?>">
                    <?php endif; ?>
                    <small class="form-hint">First select department, then pick employee for head / role</small>
                </div>
                <div class="form-group">
                    <label for="assignEmpId">Employee <span class="req">*</span></label>
                    <select name="employee_id" id="assignEmpId" class="form-control" required <?php echo $editRow ? 'disabled' : ''; ?>>
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $e): ?>
                            <?php
                            $label = trim(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? ''));
                            if (!empty($e['department_name'])) {
                                $label .= ' (' . $e['department_name'] . ')';
                            }
                            if (!empty($e['portal_user_id']) && (int) $e['portal_user_id'] !== (int) ($editRow['id'] ?? 0)) {
                                $label .= ' · already has login';
                            }
                            $eDept = (int) ($e['department_id'] ?? 0);
                            ?>
                            <option value="<?php echo (int) $e['id']; ?>"
                                    data-code="<?php echo htmlspecialchars($e['employee_code'] ?? ''); ?>"
                                    data-dept="<?php echo $eDept; ?>"
                                    <?php echo $selEmp === (int) $e['id'] ? 'selected' : ''; ?>
                                    <?php echo ($selDept > 0 && $eDept !== $selDept) ? 'hidden disabled' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editRow): ?>
                        <input type="hidden" name="employee_id" value="<?php echo (int) $editRow['employee_id']; ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="assignUsername">Login Username <span class="req">*</span></label>
                    <input type="text" name="username" id="assignUsername" class="form-control" required maxlength="50"
                           value="<?php echo htmlspecialchars($selUser); ?>" autocomplete="off"
                           placeholder="Default: employee code">
                </div>
                <div class="form-group">
                    <label for="assignPassword">Password <?php echo $editRow ? '' : '<span class="req" id="assignPassReq">*</span>'; ?></label>
                    <input type="text" name="password" id="assignPassword" class="form-control" maxlength="100"
                           <?php echo $editRow ? '' : 'required'; ?>
                           value="<?php echo htmlspecialchars($selPass); ?>"
                           placeholder="<?php echo $editRow ? 'Current password shown · edit to change' : 'Set password (or leave blank if employee already has login)'; ?>"
                           autocomplete="off"
                           spellcheck="false">
                    <small class="form-hint" id="assignPassHint">
                        <?php echo $editRow
                            ? 'Password is visible for Admin · change and Save to update'
                            : 'If employee already has login, password is optional — role will be updated'; ?>
                    </small>
                </div>
                <div class="form-group">
                    <label for="assignStatus">Login Status</label>
                    <select name="status" id="assignStatus" class="form-control">
                        <option value="1" <?php echo $selStatus === 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $selStatus === 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Assignment
                </button>
                <a href="<?php echo app_url('roles/assign.php' . ($roleId > 0 ? ('?role_id=' . $roleId) : '')); ?>" class="btn-secondary">Clear</a>
            </div>
        </form>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <div class="form-page-header" style="margin-bottom:10px;">
            <h2 style="font-size:1.1rem;margin:0;">Assigned Employee Logins</h2>
            <p class="text-muted" style="margin:4px 0 0;">
                All portal logins · Edit to change role · Revoke to remove login
                <?php if ($selRole > 0): ?>
                    · Filter tip: list shows all roles (<?php echo count($assignments); ?> total)
                <?php endif; ?>
            </p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="9" class="text-muted" style="text-align:center;padding:24px;">
                            No employee logins assigned yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $i => $a): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($a['employee_name'] ?? $a['full_name'] ?? '—'); ?></strong></td>
                            <td><?php echo htmlspecialchars($a['employee_code'] ?? '—'); ?></td>
                            <td><code><?php echo htmlspecialchars($a['username']); ?></code></td>
                            <td><code class="assign-pass-show"><?php echo htmlspecialchars((string) ($a['password'] ?? '')); ?></code></td>
                            <td><?php echo htmlspecialchars($a['role_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($a['department_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ((int) $a['status'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="<?php echo app_url('roles/assign.php?id=' . (int) $a['id']); ?>"
                                   class="btn-sm btn-secondary" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="<?php echo app_url('roles/assign_revoke.php?id=' . (int) $a['id']); ?>"
                                   class="btn-sm btn-danger"
                                   onclick="return confirm('Remove this employee portal login?');" title="Revoke">
                                    <i class="fa-solid fa-user-xmark"></i>
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
