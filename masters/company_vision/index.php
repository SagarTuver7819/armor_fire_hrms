<?php
/**
 * Master — Vision, Mission & Core Values (View)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'view');
ensureCompanyContentTables();

$row = getCompanyContent('vision');
$body = is_array($row['body'] ?? null) ? $row['body'] : companyContentDefaultVision();
$title = (string) ($row['title'] ?? 'Vision, Mission & Core Values');
$canEdit = canAccess('masters', 'edit');

$pageTitle = 'Vision Mission Values';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'company_vision';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'saved' => 'Vision, Mission & Core Values saved successfully.',
        'error' => (string) ($_GET['err'] ?? 'Save failed.'),
    ];
    $toast = $map[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'error') {
        $toastType = 'error';
    }
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('masters/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Masters
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($canEdit): ?>
            <a href="<?php echo app_url('masters/company_vision/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-pen-to-square"></i> Edit Vision / Mission / Values
            </a>
            <?php endif; ?>
            <a href="<?php echo app_url('masters/company_history/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-book-open"></i> Company History
            </a>
        </div>
    </div>

    <div class="co-master-view is-vision">
        <div class="co-master-view-head">
            <div class="co-master-view-ico"><i class="fa-solid fa-bullseye"></i></div>
            <div>
                <p class="co-master-view-eyebrow">ARMOR FIRE</p>
                <h1><?php echo htmlspecialchars((string) ($body['heading'] ?? $title)); ?></h1>
                <p>View · Employee login popup right section</p>
            </div>
        </div>

        <div class="co-master-view-body">
            <?php if (!empty($body['leadership'])): ?>
                <div class="co-master-lead"><?php echo nl2br(htmlspecialchars((string) $body['leadership'])); ?></div>
            <?php endif; ?>

            <div class="co-about-callout is-vision-box">
                <h4 style="margin:0 0 6px;font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;">Vision</h4>
                <p><?php echo nl2br(htmlspecialchars((string) ($body['vision'] ?? ''))); ?></p>
            </div>

            <?php if (!empty($body['mission'])): ?>
                <h3>Mission</h3>
                <ul class="co-about-mission">
                    <?php foreach ($body['mission'] as $item): ?>
                        <li><i class="fa-solid fa-check"></i> <span><?php echo htmlspecialchars((string) $item); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($body['core_values'])): ?>
                <h3>Core Values</h3>
                <div class="co-master-values">
                    <?php foreach ($body['core_values'] as $item): ?>
                        <span><?php echo htmlspecialchars((string) $item); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
<?php if ($toast !== ''): ?>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr[<?php echo json_encode($toastType); ?>](<?php echo json_encode($toast); ?>);
</script>
<?php endif; ?>
