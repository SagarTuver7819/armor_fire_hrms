<?php
/**
 * Sync Armor Fire biometric punches
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'edit');
ensureBiometricTables();

@set_time_limit(600);
ini_set('max_execution_time', '600');
ini_set('memory_limit', '512M');

$machineId = (int) ($_GET['machine_id'] ?? $_POST['machine_id'] ?? 0);
$fromDate = trim((string) ($_GET['from_date'] ?? $_POST['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? $_POST['to_date'] ?? ''));
if ($fromDate === '') {
    $fromDate = date('Y-m-d', strtotime('-7 days'));
}
if ($toDate === '') {
    $toDate = date('Y-m-d');
}

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'sync') {
    try {
        if ($machineId > 0) {
            $result = biometricSyncMachine($machineId, $fromDate, $toDate);
        } else {
            $result = biometricSyncFromOcean($fromDate, $toDate, 0);
        }
    } catch (Throwable $e) {
        $result = [
            'ok' => false,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'message' => 'Sync failed: ' . $e->getMessage(),
        ];
    }
}

$machines = fetchBiometricMachines(false);
$stats = biometricDashboardStats();
$selectedName = 'All Armor Fire machines';
foreach ($machines as $m) {
    if ((int) $m['id'] === $machineId) {
        $selectedName = (string) $m['machine_name'];
        break;
    }
}

$pageTitle = 'Sync Machine Attendance';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_machines';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/machines.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Machines
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/machine_logs.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-fingerprint"></i> View Logs
            </a>
            <a href="<?php echo app_url('attendance/machine_form.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-plus"></i> Add Machine
            </a>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Sync Machine Attendance</h1>
            <p>Pull Armor Fire punches from linked machines via Ocean HRMS</p>
        </div>

        <div class="bio-stat-grid bio-stat-grid-3">
            <div class="bio-stat">
                <span class="bio-stat-label">Active Machines</span>
                <span class="bio-stat-value"><?php echo (int) $stats['machines_active']; ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Total Logs</span>
                <span class="bio-stat-value"><?php echo number_format((int) $stats['logs_total']); ?></span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Last Sync</span>
                <span class="bio-stat-value bio-stat-value-sm">
                    <?php echo $stats['last_sync_at'] ? htmlspecialchars(date('d-m H:i', strtotime($stats['last_sync_at']))) : '—'; ?>
                </span>
            </div>
        </div>

        <?php if (is_array($result)): ?>
            <div class="alert alert-<?php echo !empty($result['ok']) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars((string) ($result['message'] ?? '')); ?>
                <?php if (!empty($result['ok'])): ?>
                    <div class="bio-sync-summary">
                        <span><strong><?php echo (int) ($result['inserted'] ?? 0); ?></strong> inserted</span>
                        <span><strong><?php echo (int) ($result['updated'] ?? 0); ?></strong> updated</span>
                        <span><strong><?php echo (int) ($result['skipped'] ?? 0); ?></strong> skipped</span>
                    </div>
                    <div style="margin-top:8px;">
                        <a href="<?php echo app_url('attendance/machine_logs.php?from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate) . ($machineId ? '&machine_id=' . $machineId : '')); ?>" class="btn-secondary">
                            <i class="fa-solid fa-list"></i> Open synced logs
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="employee-form" id="bioSyncForm">
            <input type="hidden" name="action" value="sync">
            <div class="form-section">
                <h3><i class="fa-solid fa-rotate"></i> Sync Options</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group span-2">
                        <label>Machine</label>
                        <select name="machine_id" class="form-control">
                            <option value="0">All Armor Fire machines</option>
                            <?php foreach ($machines as $m): ?>
                                <option value="<?php echo (int) $m['id']; ?>"
                                    <?php echo $machineId === (int) $m['id'] ? 'selected' : ''; ?>
                                    <?php echo (int) $m['is_active'] !== 1 ? 'disabled' : ''; ?>>
                                    <?php
                                    echo htmlspecialchars($m['machine_name']);
                                    echo ' — ' . htmlspecialchars(biometricProviderLabel($m['provider_type']));
                                    if ((int) $m['is_active'] !== 1) {
                                        echo ' (inactive)';
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Selected: <?php echo htmlspecialchars($selectedName); ?></small>
                    </div>
                    <div class="form-group">
                        <label>From Date <span class="req">*</span></label>
                        <input type="date" name="from_date" class="form-control" required
                               value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>To Date <span class="req">*</span></label>
                        <input type="date" name="to_date" class="form-control" required
                               value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                </div>
                <div class="bio-sync-tips">
                    <strong>How sync works</strong>
                    <ol>
                        <li>Triggers machine pull on Ocean HRMS (for linked Ocean Machine ID)</li>
                        <li>Imports Armor Fire company punches into Machine Wise logs</li>
                        <li>Matches employees by code / biometric ID</li>
                    </ol>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary" id="bioSyncBtn">
                    <i class="fa-solid fa-rotate"></i> Start Sync
                </button>
                <a href="<?php echo app_url('attendance/machines.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<script>
(function () {
    var form = document.getElementById('bioSyncForm');
    var btn = document.getElementById('bioSyncBtn');
    if (!form || !btn) return;
    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing… please wait';
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
