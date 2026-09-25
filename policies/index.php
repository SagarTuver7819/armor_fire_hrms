<?php
/**
 * Policies — list (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();
ensurePolicyTables();
require_once __DIR__ . '/../includes/permission_helper.php';
requireAccess('policies', 'view');

$year = (int) ($_GET['year'] ?? 0);
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$rows = fetchPolicies($year, $deptFilter);
$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$canEditPolicy = canAccess('policies', 'edit');
$canDeletePolicy = canAccess('policies', 'delete');
// Employee portal roles (except HR Head): view/PDF only — never show edit/delete
if (isEmployee()) {
    $roleCode = strtoupper((string) ($_SESSION['role_code'] ?? ''));
    if ($roleCode !== 'HR_HEAD') {
        $canEditPolicy = false;
        $canDeletePolicy = false;
    }
}
$showPolicyActions = $canEditPolicy || $canDeletePolicy;

$pageTitle = 'Policies';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'policies';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'added' => 'Policy added successfully. Notification sent to bell.',
        'updated' => 'Policy updated successfully.',
        'deleted' => 'Policy deleted.',
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
        <a href="<?php echo app_url('hr/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to HR Dashboard
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if (canAccess('policies', 'add')): ?>
            <a href="<?php echo app_url('policies/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add Policy
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#7C3AED;">
                    <i class="fa-solid fa-scroll"></i>
                </div>
                <div>
                    <h1>Company Policies</h1>
                    <p>Department-wise PDF Policies · Bell notifications for unread</p>
                </div>
            </div>
        </div>

        <form method="GET" class="employee-form register-filters">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" class="form-control" onchange="this.form.submit()">
                        <option value="0">All years</option>
                        <?php for ($y = (int) date('Y') + 1; $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All / Any</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptFilter === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="form-page-card" style="margin-top:14px;">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Title</th>
                        <th>Departments</th>
                        <th>Policy No</th>
                        <th>Policy Date</th>
                        <th>Added On</th>
                        <th>Uploaded By</th>
                        <th>PDF</th>
                        <?php if ($showPolicyActions): ?>
                        <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="<?php echo $showPolicyActions ? 9 : 8; ?>" class="empty-cell">No Policies yet.<?php if (canAccess('policies', 'add')): ?> Click <strong>Add Policy</strong> to upload a scanned PDF.<?php endif; ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                            <?php if (!empty($r['remarks'])): ?>
                                <div class="sr-code"><?php echo htmlspecialchars(strlen($r['remarks']) > 80 ? substr($r['remarks'], 0, 77) . '…' : $r['remarks']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="circ-dept-tag <?php echo !empty($r['apply_all_departments']) ? 'is-all' : ''; ?>">
                                <?php echo htmlspecialchars(policyDepartmentsLabel($r)); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($r['policy_no'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['policy_date'])); ?></td>
                        <td><?php echo htmlspecialchars(formatDateDisplay($r['added_date'])); ?></td>
                        <td><?php echo htmlspecialchars($r['created_by_name'] ?: '—'); ?></td>
                        <td>
                            <?php if (policyFileExists($r['pdf_file'] ?? '')): ?>
                                <a class="action-btn edit" title="View PDF"
                                   href="<?php echo app_url('policies/view.php?id=' . (int) $r['id']); ?>">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </a>
                            <?php else: ?>
                                <span class="sr-code">Missing</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($showPolicyActions): ?>
                        <td>
                            <?php if ($canEditPolicy): ?>
                            <a class="action-btn edit" title="Edit"
                               href="<?php echo app_url('policies/edit.php?id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDeletePolicy): ?>
                            <a class="action-btn delete btn-delete" title="Delete"
                               href="<?php echo app_url('policies/delete.php?id=' . (int) $r['id']); ?>"
                               data-name="<?php echo htmlspecialchars($r['title']); ?>">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php if ($toast): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>('<?php echo addslashes($toast); ?>');
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


