<?php
/**
 * Master — Company History (Edit)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'edit');
ensureCompanyContentTables();

$row = getCompanyContent('history');
$body = is_array($row['body'] ?? null) ? $row['body'] : companyContentDefaultHistory();
$title = (string) ($row['title'] ?? 'Company History');

$pageTitle = 'Edit Company History';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'company_history';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('masters/company_history/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to View
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Edit Company History</h1>
            <p>Employee login popup — left section content</p>
        </div>

        <form method="POST" action="<?php echo app_url('masters/company_history/save.php'); ?>" class="employee-form">
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo htmlspecialchars($title); ?>">
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company" class="form-control" required
                           value="<?php echo htmlspecialchars((string) ($body['company'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label>Heading</label>
                    <input type="text" name="heading" class="form-control"
                           value="<?php echo htmlspecialchars((string) ($body['heading'] ?? 'Company History')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>History Paragraphs <small>(separate paragraphs with a blank line)</small></label>
                <textarea name="paragraphs" class="form-control" rows="14" required><?php
                    echo htmlspecialchars(implode("\n\n", $body['paragraphs'] ?? []));
                ?></textarea>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>2021 Expansion Products <small>(one per line)</small></label>
                    <textarea name="products_2021" class="form-control" rows="7"><?php
                        echo htmlspecialchars(implode("\n", $body['products_2021'] ?? []));
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label>Today Products <small>(one per line)</small></label>
                    <textarea name="products_today" class="form-control" rows="7"><?php
                        echo htmlspecialchars(implode("\n", $body['products_today'] ?? []));
                    ?></textarea>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <a href="<?php echo app_url('masters/company_history/index.php'); ?>" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Company History
                </button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
