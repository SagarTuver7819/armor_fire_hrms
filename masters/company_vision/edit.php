<?php
/**
 * Master — Vision, Mission & Core Values (Edit)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/company_content_helper.php';

requireAccess('masters', 'edit');
ensureCompanyContentTables();

$row = getCompanyContent('vision');
$body = is_array($row['body'] ?? null) ? $row['body'] : companyContentDefaultVision();
$title = (string) ($row['title'] ?? 'Vision, Mission & Core Values');

$pageTitle = 'Edit Vision Mission Values';
$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'company_vision';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('masters/company_vision/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to View
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Edit Vision, Mission &amp; Core Values</h1>
            <p>Employee login popup — right section content</p>
        </div>

        <form method="POST" action="<?php echo app_url('masters/company_vision/save.php'); ?>" class="employee-form">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" class="form-control" required
                       value="<?php echo htmlspecialchars($title); ?>">
            </div>

            <div class="form-group">
                <label>Leadership / Intro</label>
                <textarea name="leadership" class="form-control" rows="4" required><?php
                    echo htmlspecialchars((string) ($body['leadership'] ?? ''));
                ?></textarea>
            </div>

            <div class="form-group">
                <label>Vision</label>
                <textarea name="vision" class="form-control" rows="3" required><?php
                    echo htmlspecialchars((string) ($body['vision'] ?? ''));
                ?></textarea>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Mission <small>(one point per line)</small></label>
                    <textarea name="mission" class="form-control" rows="8" required><?php
                        echo htmlspecialchars(implode("\n", $body['mission'] ?? []));
                    ?></textarea>
                </div>
                <div class="form-group">
                    <label>Core Values <small>(one per line)</small></label>
                    <textarea name="core_values" class="form-control" rows="8" required><?php
                        echo htmlspecialchars(implode("\n", $body['core_values'] ?? []));
                    ?></textarea>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <a href="<?php echo app_url('masters/company_vision/index.php'); ?>" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Vision / Mission / Values
                </button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
