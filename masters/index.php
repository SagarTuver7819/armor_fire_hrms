<?php
/**
 * Masters Hub - grid of all 10 masters
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/master_helper.php';

ensureMasterTables();
$masters = getMastersConfig();

$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = 'hub';

$pageTitle = 'Masters';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="dashboard-title-block">
        <h1>MASTERS</h1>
        <p>MASTER DATA MANAGEMENT</p>
    </div>

    <section class="module-section">
        <div class="section-heading">
            <i class="fa-solid fa-database"></i>
            <span>ALL MASTERS</span>
        </div>

        <div class="module-grid masters-hub-grid">
            <?php foreach ($masters as $m): ?>
                <?php
                $count = countMasterRows($m['table']);
                $url = app_url('masters/' . $m['folder'] . '/index.php');
                ?>
                <a href="<?php echo htmlspecialchars($url); ?>" class="module-card master-hub-card" title="<?php echo htmlspecialchars($m['title']); ?>">
                    <div class="module-icon" style="background-color: <?php echo htmlspecialchars($m['color']); ?>;">
                        <i class="fa-solid <?php echo htmlspecialchars($m['icon']); ?>"></i>
                    </div>
                    <div class="module-label"><?php echo htmlspecialchars($m['title']); ?></div>
                    <div class="module-meta"><?php echo (int) $count; ?> Records</div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
