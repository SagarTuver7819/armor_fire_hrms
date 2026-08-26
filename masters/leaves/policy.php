<?php
/**
 * Leave Policy — show PDF as-is + Print (same format)
 */

$masterKey = 'leaves';
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../../includes/leave_policy.php';

$pageTitle = 'Leave Policy';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'leaves';

$hasPdf = leavePolicyExists();
$pdfUrl = app_url('masters/leaves/policy_pdf.php');
$backUrl = app_url('masters/leaves/index.php');

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Leave Master
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($hasPdf): ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">
                    <i class="fa-solid fa-up-right-from-square"></i> Open PDF
                </a>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(app_url('masters/leaves/policy_pdf.php?download=1')); ?>">
                    <i class="fa-solid fa-download"></i> Download
                </a>
                <button type="button" class="btn-primary" id="btnLeavePolicyPrint">
                    <i class="fa-solid fa-print"></i> Print
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Leave Policy</h1>
                <p>Original PDF · same format for view &amp; print · Effective from 1st May 2026</p>
            </div>
        </div>

        <?php if (!$hasPdf): ?>
            <p class="form-hint">No Leave Policy PDF uploaded yet. Use <strong>Upload Leave Policy</strong> on Leave Master.</p>
        <?php else: ?>
            <div class="leave-policy-frame-wrap">
                <iframe
                    id="leavePolicyFrame"
                    class="leave-policy-frame"
                    title="Leave Policy PDF"
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
@media print {
    .top-header,
    .app-sidebar,
    .sidebar-fab,
    .page-toolbar,
    .form-page-header,
    .page-footer {
        display: none !important;
    }
    .app-shell, .app-content, .dashboard-main, .form-page-card {
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: 0 !important;
    }
    .leave-policy-frame-wrap,
    .leave-policy-frame {
        height: 100vh !important;
        min-height: 100vh !important;
        border: 0 !important;
        border-radius: 0 !important;
    }
}
</style>
<script>
(function () {
    var btn = document.getElementById('btnLeavePolicyPrint');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var w = window.open(<?php echo json_encode($pdfUrl); ?>, '_blank');
        if (!w) {
            alert('Please allow pop-ups to print the Leave Policy PDF.');
            return;
        }
        setTimeout(function () {
            try { w.focus(); w.print(); } catch (e) {}
        }, 1200);
    });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
