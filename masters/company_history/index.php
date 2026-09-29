<?php
/**
 * Master — Company History (View)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'view');
ensureCompanyContentTables();

$row = getCompanyContent('history');
$body = is_array($row['body'] ?? null) ? $row['body'] : companyContentDefaultHistory();
$title = (string) ($row['title'] ?? 'Company History');
$canEdit = canAccess('masters', 'edit');

$pageTitle = 'Company History';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'company_history';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'saved' => 'Company History saved successfully.',
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
            <a href="<?php echo app_url('masters/company_history/edit.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-pen-to-square"></i> Edit History
            </a>
            <?php endif; ?>
            <a href="<?php echo app_url('masters/company_vision/index.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-bullseye"></i> Vision / Mission / Values
            </a>
        </div>
    </div>

    <div class="co-master-view is-history">
        <div class="co-master-view-head">
            <div class="co-master-view-ico"><i class="fa-solid fa-book-open"></i></div>
            <div>
                <p class="co-master-view-eyebrow"><?php echo htmlspecialchars((string) ($body['company'] ?? 'ARMOR FIRE')); ?></p>
                <h1><?php echo htmlspecialchars((string) ($body['heading'] ?? $title)); ?></h1>
                <p>View · Employee login popup left section</p>
            </div>
        </div>

        <div class="co-master-view-body">
            <?php foreach (($body['paragraphs'] ?? []) as $p): ?>
                <p><?php echo nl2br(htmlspecialchars((string) $p)); ?></p>
            <?php endforeach; ?>

            <?php if (!empty($body['products_2021'])): ?>
                <h3>2021 Expansion Products</h3>
                <div class="co-about-chips is-blue">
                    <?php foreach ($body['products_2021'] as $item): ?>
                        <span><?php echo htmlspecialchars((string) $item); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($body['products_today'])): ?>
                <h3>Products Today</h3>
                <div class="co-about-chips is-red">
                    <?php foreach ($body['products_today'] as $item): ?>
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
