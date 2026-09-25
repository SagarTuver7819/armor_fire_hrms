<?php
/**
 * View Policy + PDF preview
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();
ensurePolicyTables();
require_once __DIR__ . '/../includes/permission_helper.php';
requireAccess('policies', 'view');

$id = (int) ($_GET['id'] ?? 0);
$row = getPolicyById($id);
if (!$row) {
    header('Location: ' . app_url('policies/index.php?msg=error&err=' . rawurlencode('Policy not found.')));
    exit;
}

// Opening a Policy marks notification as read for this user
markPolicyRead($id, (int) ($_SESSION['user_id'] ?? 0));

$hasPdf = policyFileExists($row['pdf_file'] ?? '');
$pdfUrl = app_url('policies/pdf.php?id=' . $id);
$deptLabel = policyDepartmentsLabel($row);

$pageTitle = 'View Policy';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'policies';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('policies/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Policies
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('policies/edit.php?id=' . $id); ?>" class="btn-secondary">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
            <?php if ($hasPdf): ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">
                    <i class="fa-solid fa-up-right-from-square"></i> Open PDF
                </a>
                <a class="btn-secondary" href="<?php echo htmlspecialchars($pdfUrl . '&download=1'); ?>">
                    <i class="fa-solid fa-download"></i> Download
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo htmlspecialchars($row['title']); ?></h1>
                <p>
                    Policy Date: <strong><?php echo htmlspecialchars(formatDateDisplay($row['policy_date'])); ?></strong>
                    · Added On: <strong><?php echo htmlspecialchars(formatDateDisplay($row['added_date'])); ?></strong>
                    · Departments: <strong><?php echo htmlspecialchars($deptLabel); ?></strong>
                    <?php if (!empty($row['policy_no'])): ?>
                        · No: <strong><?php echo htmlspecialchars($row['policy_no']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($row['created_by_name'])): ?>
                        · By: <?php echo htmlspecialchars($row['created_by_name']); ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($row['remarks'])): ?>
                    <p class="form-hint" style="margin-top:6px;"><?php echo nl2br(htmlspecialchars($row['remarks'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$hasPdf): ?>
            <p class="form-hint">PDF file is missing. Edit this Policy and upload again.</p>
        <?php else: ?>
            <div class="leave-policy-frame-wrap">
                <iframe
                    class="leave-policy-frame"
                    title="Policy PDF"
                    src="<?php echo htmlspecialchars($pdfUrl); ?>#toolbar=1&navpanes=0"
                ></iframe>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.leave-policy-frame-wrap {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #f8fafc;
    min-height: 75vh;
}
.leave-policy-frame {
    width: 100%;
    height: 75vh;
    border: 0;
    display: block;
    background: #fff;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


