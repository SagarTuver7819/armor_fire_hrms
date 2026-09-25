<?php
/**
 * Bulk create Office Staff logins for all active employees (Admin)
 * Username = Employee Code · Password = name@123
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireAdmin();
ensureDepartmentHeadTables();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_bulk'])) {
    $result = bulkProvisionOfficeStaffLogins(true);
}

$pageTitle = 'Bulk Employee Logins';
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
                <div class="master-list-icon" style="background:#0369A1;">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <h1>Bulk Employee Logins</h1>
                    <p>
                        All active employees · Role <strong>Office Staff</strong> ·
                        Username = <strong>Employee Code</strong> ·
                        Password = <strong><?php echo htmlspecialchars(employeePortalDefaultPassword()); ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <p class="text-muted" style="margin-bottom:14px;">
            Existing <strong>Department Head / Payroll Head / HR Head</strong> logins are skipped (not overwritten).
            Office Staff accounts are created or password reset.
        </p>

        <form method="POST">
            <input type="hidden" name="run_bulk" value="1">
            <button type="submit" class="btn-primary"
                    onclick="return confirm('Create / reset Office Staff logins for all active employees?');">
                <i class="fa-solid fa-bolt"></i> Create / Reset All Employee Logins
            </button>
        </form>

        <?php if (is_array($result)): ?>
            <div style="margin-top:18px;padding:14px;border-radius:10px;background:#F0FDF4;border:1px solid #BBF7D0;">
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

            <?php if (!empty($result['samples'])): ?>
            <div class="table-wrap" style="margin-top:14px;">
                <h3 style="font-size:1rem;margin-bottom:8px;">Sample logins (test)</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Login as</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['samples'] as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><?php echo htmlspecialchars($s['department']); ?></td>
                                <td><code><?php echo htmlspecialchars($s['username']); ?></code></td>
                                <td><code><?php echo htmlspecialchars($s['password']); ?></code></td>
                                <td>Employee</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
