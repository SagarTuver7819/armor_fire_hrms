<?php
require_once __DIR__ . '/../_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM contractor_grades WHERE id = ? AND status = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if (!$row) {
        header('Location: ' . app_url('contractor/grades/index.php'));
        exit;
    }
}

$pageTitle = $row ? 'Edit Grade' : 'Add Grade';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_grades';
require_once __DIR__ . '/../../includes/header.php';
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/grades/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Grade Master</a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $row ? 'Edit Grade' : 'Add Grade'; ?></h1>
            <p>Grade Master</p>
        </div>
        <form method="POST" action="<?php echo app_url('contractor/grades/save.php'); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <div class="form-section">
                <h3><i class="fa-solid fa-layer-group"></i> Details</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group span-2">
                        <label>Grade Name <span class="req">*</span></label>
                        <input type="text" name="grade_name" class="form-control" required
                               value="<?php echo htmlspecialchars((string) ($row['grade_name'] ?? '')); ?>">
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="<?php echo app_url('contractor/grades/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
