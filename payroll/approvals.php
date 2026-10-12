<?php
/**
 * Payroll approvals — locked salary registers and their Maker → Checker → Accounts status
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/salary_register_helper.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();
requirePayrollRegisterAccess(0);

$rows = fetchPayrollApprovalQueue(60);
$labels = payrollApprovalStatusLabels();
$settings = getPayrollApprovalSettings();
$types = salaryRegisterTypes();

$countPending = 0;
$countMine = 0;
foreach ($rows as $r) {
    if (($r['approval_status'] ?? 'approved') !== 'approved') {
        $countPending++;
        if (payrollApprovalPendingActionFor($r) !== '') {
            $countMine++;
        }
    }
}

$pageTitle = 'Payroll Approvals';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'payroll_approvals';

require_once __DIR__ . '/../includes/header.php';

$fmtWhen = static function ($dt) {
    $dt = trim((string) $dt);
    if ($dt === '' || strpos($dt, '0000') === 0) {
        return '';
    }
    return formatDateDisplay(substr($dt, 0, 10)) . ' · ' . date('g:i A', strtotime($dt));
};
$stageCards = [
    ['Salary Maker', 'Payroll Manager', 'fa-user-pen', $settings['maker_user_id']],
    ['Checker', 'HR Head', 'fa-user-check', $settings['checker_user_id']],
    ['Final Approver', 'Accounts', 'fa-building-columns', $settings['accounts_user_id']],
];
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('payroll/register.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Salary Register
        </a>
        <?php if (isAdmin()): ?>
        <a href="<?php echo app_url('payroll/approval_settings.php'); ?>" class="btn-secondary">
            <i class="fa-solid fa-gear"></i> Approval Settings
        </a>
        <?php endif; ?>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Payroll Approvals</h1>
                <p>Maker → Checker → Accounts · <?php echo (int) $countPending; ?> pending<?php if ($countMine > 0): ?> · <strong style="color:#d2232a;"><?php echo (int) $countMine; ?> waiting for you</strong><?php endif; ?></p>
            </div>
        </div>

        <div class="pa-stage-row">
            <?php foreach ($stageCards as $i => [$title, $sub, $icon, $uid]):
                $name = payrollUserDisplayName($uid);
                ?>
                <div class="pa-stage<?php echo $name === '' ? ' is-empty' : ''; ?>">
                    <span class="pa-stage-step"><?php echo $i + 1; ?></span>
                    <span class="pa-stage-ico"><i class="fa-solid <?php echo $icon; ?>"></i></span>
                    <div class="pa-stage-txt">
                        <small><?php echo htmlspecialchars($title); ?> · <?php echo htmlspecialchars($sub); ?></small>
                        <strong><?php echo htmlspecialchars($name !== '' ? $name : 'Not set'); ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="leave-rpt-table-wrap">
            <table class="leave-rpt-table pa-table">
                <thead>
                    <tr>
                        <th class="sr">#</th>
                        <th class="txt">Register</th>
                        <th class="ctr">Employees</th>
                        <th class="num">Net (₹)</th>
                        <th class="txt">Locked By</th>
                        <th class="txt">Checked By</th>
                        <th class="txt">Approved By</th>
                        <th class="ctr">Status</th>
                        <th class="actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="empty-cell ctr">No locked salary registers yet.</td></tr>
                <?php endif; ?>
                <?php $sr = 1; foreach ($rows as $r):
                    $status = (string) ($r['approval_status'] ?? 'approved');
                    $myAction = payrollApprovalPendingActionFor($r);
                    $deptName = 'All departments';
                    if ((int) $r['department_id'] > 0) {
                        $d = getDepartmentById((int) $r['department_id']);
                        $deptName = $d ? (string) $d['department_name'] : 'Department';
                    }
                    $pillClass = $status === 'approved' ? 'is-approved' : ($myAction !== '' ? 'is-mine' : 'is-pending');
                    $pillIcon = $status === 'approved' ? 'fa-circle-check' : 'fa-hourglass-half';
                    $people = [
                        [$r['locked_by'], $r['locked_at']],
                        [$r['checked_by'], $r['checked_at']],
                        [$r['approved_by'], $r['approved_at']],
                    ];
                    ?>
                    <tr class="<?php echo $myAction !== '' ? 'is-highlight' : ''; ?>">
                        <td class="sr"><?php echo $sr++; ?></td>
                        <td class="txt">
                            <strong><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, (int) $r['month_no'], 1, (int) $r['year_no']))); ?></strong>
                            <div class="pa-sub"><?php echo htmlspecialchars($types[$r['register_type']] ?? $r['register_type']); ?> · <?php echo htmlspecialchars($deptName); ?></div>
                        </td>
                        <td class="ctr"><?php echo (int) $r['employee_count']; ?></td>
                        <td class="num"><?php echo number_format((float) $r['total_net'], 2); ?></td>
                        <?php foreach ($people as [$pid, $pwhen]):
                            $pname = payrollUserDisplayName($pid);
                            ?>
                            <td class="txt">
                                <?php if ($pname !== ''): ?>
                                    <strong class="pa-person"><?php echo htmlspecialchars($pname); ?></strong>
                                    <div class="pa-sub"><?php echo htmlspecialchars($fmtWhen($pwhen)); ?></div>
                                <?php else: ?>
                                    <span class="pa-sub">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="ctr">
                            <span class="pa-status <?php echo $pillClass; ?>">
                                <i class="fa-solid <?php echo $pillIcon; ?>"></i>
                                <?php echo htmlspecialchars($labels[$status] ?? $status); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a class="<?php echo $myAction !== '' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="<?php echo app_url(payrollLockRegisterUrl($r)); ?>">
                                <i class="fa-solid <?php echo $myAction !== '' ? 'fa-user-check' : 'fa-eye'; ?>"></i>
                                <?php echo $myAction !== '' ? 'Review' : 'Open'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
