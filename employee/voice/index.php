<?php
/**
 * Employee Voice — hub (3 cards)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice()) {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
$emp = getEmployeeById($empId);
ensureEmployeeVoiceTables();
$types = evModuleTypes();

$pageTitle = 'Employee Voice';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <a href="<?php echo app_url('employee/voice/my.php'); ?>" class="btn-secondary">
            <i class="fa-solid fa-inbox"></i> My Submissions
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><i class="fa-solid fa-comments" style="color:#d2232a;"></i> Employee Voice</h1>
            <p>Submit a grievance, share a suggestion, or report a safety concern. You can only view your own tickets.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:8px;">
            <?php foreach ($types as $t): ?>
            <a href="<?php echo app_url('employee/voice/submit.php?type=' . urlencode($t['key'])); ?>"
               style="display:block;text-decoration:none;border:1px solid #e5e7eb;border-radius:16px;padding:22px 18px;background:<?php echo htmlspecialchars($t['bg']); ?>;transition:transform .15s;">
                <div style="width:52px;height:52px;border-radius:14px;background:<?php echo htmlspecialchars($t['color']); ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px;">
                    <i class="fa-solid <?php echo htmlspecialchars($t['icon']); ?>"></i>
                </div>
                <div style="font-weight:800;color:#111;font-size:16px;margin-bottom:6px;"><?php echo htmlspecialchars($t['label']); ?></div>
                <div style="font-size:13px;color:#64748b;line-height:1.4;">
                    <?php
                    if ($t['key'] === 'GRIEVANCE') {
                        echo 'Workplace complaints, relations, facilities &amp; discipline';
                    } elseif ($t['key'] === 'SUGGESTION') {
                        echo 'Cost saving, productivity, quality &amp; improvement ideas';
                    } else {
                        echo 'Unsafe machines, PPE, fire hazards, near misses';
                    }
                    ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
