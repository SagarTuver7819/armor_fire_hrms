<?php
/**
 * Payroll approval settings — pick Maker / Checker / Accounts users (Admin only)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();
if (!isAdmin()) {
    header('Location: ' . app_url('dashboard.php?msg=denied'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    savePayrollApprovalSettings($_POST);
    header('Location: ' . app_url('payroll/approval_settings.php?msg=saved'));
    exit;
}

$settings = getPayrollApprovalSettings();
$users = fetchPayrollApprovalCandidateUsers();

$pageTitle = 'Payroll Approval Settings';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'payroll_approvals';

require_once __DIR__ . '/../includes/header.php';

$userLabel = static function (array $u) {
    $name = trim((string) ($u['employee_name'] ?: $u['full_name'] ?: $u['username']));
    $parts = [];
    if (!empty($u['employee_code'])) {
        $parts[] = $u['employee_code'];
    }
    $parts[] = $name;
    $extra = array_filter([(string) ($u['designation'] ?? ''), (string) ($u['department_name'] ?? '')]);
    $label = implode(' — ', $parts);
    if ($extra) {
        $label .= ' (' . implode(', ', $extra) . ')';
    }
    if ($u['role'] !== 'employee') {
        $label .= ' · ' . strtoupper((string) $u['role']);
    }
    return $label;
};

$stages = [
    'maker_user_id' => ['Salary Maker', 'Payroll Manager · makes the salary and locks the register', 'fa-user-pen'],
    'checker_user_id' => ['Checker', 'HR Head · checks the locked salary and approves or sends back', 'fa-user-check'],
    'accounts_user_id' => ['Final Approver (Accounts)', 'Accounts · marks Done → NEFT sheet becomes final', 'fa-building-columns'],
];
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('payroll/approvals.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Payroll Approvals
        </a>
    </div>

    <?php if (($_GET['msg'] ?? '') === 'saved'): ?>
        <div class="form-page-card" style="margin-bottom:12px;border-left:4px solid #16a085;">
            <p style="margin:0;"><i class="fa-solid fa-circle-check" style="color:#16a085;"></i> Payroll approval settings saved.</p>
        </div>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Payroll Approval Settings</h1>
                <p>Maker → Checker → Accounts. Leave a step empty to skip it.</p>
            </div>
        </div>

        <form method="POST" class="employee-form">
            <div class="pa-settings-list">
                <?php $step = 1; foreach ($stages as $key => [$label, $hint, $icon]): ?>
                <div class="pa-settings-row">
                    <div class="pa-settings-meta">
                        <span class="pa-stage-ico"><i class="fa-solid <?php echo $icon; ?>"></i></span>
                        <div>
                            <strong><span class="pa-settings-step"><?php echo $step++; ?></span> <?php echo htmlspecialchars($label); ?></strong>
                            <small><?php echo htmlspecialchars($hint); ?></small>
                        </div>
                    </div>
                    <div class="pa-settings-field">
                        <select name="<?php echo $key; ?>" class="form-control">
                            <option value="0">— Not set —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int) $u['id']; ?>" <?php echo (int) $settings[$key] === (int) $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($userLabel($u)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="form-hint" style="margin-top:10px;">
                The selected users can open the Salary Register and NEFT Sheet even without payroll rights in their role.
                The Maker still needs payroll rights (e.g. the Payroll Head role) to generate salary.
            </p>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
