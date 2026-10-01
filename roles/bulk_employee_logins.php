<?php
/**
 * Bulk create Office Staff logins + always-visible Login ID / Password list (Admin)
 * Username = Employee Code · Password = name@123
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/department_head_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

requireAdmin();
ensureDepartmentHeadTables();
ensureRoleTables();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_bulk'])) {
    $result = bulkProvisionOfficeStaffLogins(true);
}

$credentials = fetchAllEmployeePortalCredentials();
$withLogin = 0;
$withoutLogin = 0;
foreach ($credentials as $c) {
    if (!empty($c['login_id'])) {
        $withLogin++;
    } else {
        $withoutLogin++;
    }
}

$pageTitle = 'Employee Logins';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'roles';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('roles/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Roles
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('roles/export_logins_excel.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-file-excel"></i> Download Excel
            </a>
            <a href="<?php echo app_url('roles/export_logins_pdf.php'); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i> Download PDF
            </a>
            <a href="<?php echo app_url('roles/assign.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-user-check"></i> Assign Role &amp; Login
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#0369A1;">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <h1>Employee Logins</h1>
                    <p>
                        All active employees · Login ID &amp; Password visible for Admin ·
                        Default: Username = <strong>Employee Code</strong> ·
                        Password = <strong>FirstName@123</strong>
                    </p>
                </div>
            </div>
        </div>

        <p class="text-muted" style="margin-bottom:14px;">
            Existing <strong>Department Head / Payroll Head / HR Head</strong> logins are skipped on bulk reset (not overwritten).
            Office Staff accounts are created or password reset.
            Use <strong>Employee Login</strong> on the login page (not Admin Login).
        </p>

        <form method="POST" style="margin-bottom:18px;">
            <input type="hidden" name="run_bulk" value="1">
            <button type="submit" class="btn-primary"
                    onclick="return confirm('Create / reset Office Staff logins for all active employees?');">
                <i class="fa-solid fa-bolt"></i> Create / Reset All Employee Logins
            </button>
        </form>

        <?php if (is_array($result)): ?>
            <div style="margin-bottom:16px;padding:14px;border-radius:10px;background:#F0FDF4;border:1px solid #BBF7D0;">
                <strong>Done.</strong>
                Created: <?php echo (int) $result['created']; ?> ·
                Updated: <?php echo (int) $result['updated']; ?> ·
                Skipped (other roles): <?php echo (int) $result['skipped']; ?>
                <?php if (!empty($result['errors'])): ?>
                    <div style="margin-top:8px;color:#B91C1C;">
                        <?php foreach (array_slice($result['errors'], 0, 15) as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            // Refresh list after bulk run
            $credentials = fetchAllEmployeePortalCredentials();
            $withLogin = 0;
            $withoutLogin = 0;
            foreach ($credentials as $c) {
                if (!empty($c['login_id'])) {
                    $withLogin++;
                } else {
                    $withoutLogin++;
                }
            }
            ?>
        <?php endif; ?>

        <div class="emp-login-summary" style="margin-top:4px;">
            <span><strong><?php echo count($credentials); ?></strong> active employees</span>
            <span class="ok"><i class="fa-solid fa-circle-check"></i> <?php echo (int) $withLogin; ?> with login</span>
            <?php if ($withoutLogin > 0): ?>
                <span class="warn"><i class="fa-solid fa-circle-exclamation"></i> <?php echo (int) $withoutLogin; ?> without login</span>
            <?php endif; ?>
            <a href="<?php echo app_url('roles/export_logins_excel.php'); ?>" class="btn-secondary" style="margin-left:auto;">
                <i class="fa-solid fa-file-excel"></i> Excel
            </a>
            <a href="<?php echo app_url('roles/export_logins_pdf.php'); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
        </div>

        <div class="table-wrap" style="margin-top:12px;">
            <table class="data-table emp-login-table" id="empLoginCredTable">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($credentials)): ?>
                    <tr>
                        <td colspan="9" class="text-muted" style="text-align:center;padding:24px;">
                            No active employees found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($credentials as $i => $row): ?>
                        <?php
                        $loginId = trim((string) ($row['login_id'] ?? ''));
                        $loginPass = (string) ($row['login_password'] ?? '');
                        $hasLogin = $loginId !== '';
                        $viewUrl = app_url('employees/view.php?id=' . (int) $row['employee_id'] . '&from=all');
                        $assignUrl = app_url('roles/assign.php?employee_id=' . (int) $row['employee_id']);
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><span class="code-badge"><?php echo htmlspecialchars((string) ($row['employee_code'] ?? '—')); ?></span></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($viewUrl); ?>">
                                    <strong><?php echo htmlspecialchars((string) ($row['employee_name'] ?? '—')); ?></strong>
                                </a>
                            </td>
                            <td>
                                <?php if ($hasLogin): ?>
                                    <code class="emp-login-id"><?php echo htmlspecialchars($loginId); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasLogin): ?>
                                    <code class="assign-pass-show"><?php echo htmlspecialchars($loginPass); ?></code>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) (($row['designation'] ?? '') !== '' ? $row['designation'] : '—')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['department_name'] ?? '—')); ?></td>
                            <td>
                                <?php if (!$hasLogin): ?>
                                    <span class="badge badge-muted">No login</span>
                                <?php elseif ((int) ($row['login_status'] ?? 0) === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <?php if ($hasLogin && !empty($row['portal_user_id'])): ?>
                                    <a href="<?php echo app_url('roles/assign.php?id=' . (int) $row['portal_user_id']); ?>"
                                       class="btn-sm btn-secondary" title="Edit login">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($assignUrl); ?>"
                                       class="btn-sm btn-primary" title="Create login">
                                        <i class="fa-solid fa-plus"></i>
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

<style>
.emp-login-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    align-items: center;
    font-size: 13px;
    font-weight: 650;
    color: #475569;
}
.emp-login-summary .ok { color: #15803d; }
.emp-login-summary .warn { color: #b45309; }
.emp-login-id {
    font-weight: 800;
    letter-spacing: 0.03em;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 2px 8px;
    border-radius: 6px;
}
.emp-login-table code.assign-pass-show {
    white-space: nowrap;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
