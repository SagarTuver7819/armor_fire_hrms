<?php
require_once __DIR__ . '/../_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT * FROM contractor_products WHERE id = ? AND status = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    if (!$row) {
        header('Location: ' . app_url('contractor/products/index.php'));
        exit;
    }
}

$ops = contractorOperations();
$pageTitle = $row ? 'Edit Product' : 'Add Product';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_products';
require_once __DIR__ . '/../../includes/header.php';
$currentOp = (string) ($row['operation'] ?? '');
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/products/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Product Master</a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $row ? 'Edit Product' : 'Add Product'; ?></h1>
            <p>Rate, OT rate and Rejection rate drive Operations Rate List amounts</p>
        </div>
        <form method="POST" action="<?php echo app_url('contractor/products/save.php'); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <div class="form-section">
                <h3><i class="fa-solid fa-box"></i> Details</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Operation <span class="req">*</span></label>
                        <select name="operation" class="form-control" required>
                            <option value="">Select Operation</option>
                            <?php foreach ($ops as $op): ?>
                                <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $currentOp === $op ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($op); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product Name <span class="req">*</span></label>
                        <input type="text" name="product_name" class="form-control" required
                               value="<?php echo htmlspecialchars((string) ($row['product_name'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Process</label>
                        <input type="text" name="process" class="form-control"
                               value="<?php echo htmlspecialchars((string) ($row['process'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit" class="form-control">
                            <?php $unit = (string) ($row['unit'] ?? 'PCS'); ?>
                            <option value="PCS" <?php echo $unit === 'PCS' ? 'selected' : ''; ?>>PCS</option>
                            <option value="KG" <?php echo $unit === 'KG' ? 'selected' : ''; ?>>KG</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rate</label>
                        <input type="number" step="0.01" name="rate" class="form-control"
                               value="<?php echo htmlspecialchars((string) ($row['rate'] ?? '0.00')); ?>">
                    </div>
                    <div class="form-group">
                        <label>OT Rate / OT Text</label>
                        <input type="text" name="ot_text" class="form-control" placeholder="Enter OT Rate"
                               value="<?php echo htmlspecialchars((string) ($row['ot_text'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Rejection Rate</label>
                        <input type="number" step="0.01" name="rejection_rate" class="form-control"
                               value="<?php echo htmlspecialchars((string) ($row['rejection_rate'] ?? '0.00')); ?>">
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="<?php echo app_url('contractor/products/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
