<?php
/**
 * Armor Fire — Biometric Machines list
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'view');
ensureBiometricTables();
// Lock one-time seed (so delete never comes back, even after db_sync)
$__bioLock = getDBConnection();
seedArmorBiometricMachinesOnce($__bioLock);
$__bioLock->close();

$toast = '';
$toastType = 'success';
if (!empty($_SESSION['bio_flash'])) {
    $toast = (string) ($_SESSION['bio_flash']['message'] ?? '');
    $toastType = !empty($_SESSION['bio_flash']['ok']) ? 'success' : 'error';
    unset($_SESSION['bio_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAccess('attendance', 'edit');
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'toggle') {
        $r = toggleBiometricMachine((int) ($_POST['machine_id'] ?? 0));
        $_SESSION['bio_flash'] = $r;
        header('Location: ' . app_url('attendance/machines.php'));
        exit;
    }
    if ($action === 'delete') {
        $mid = (int) ($_POST['machine_id'] ?? 0);
        $mRow = getBiometricMachineById($mid);
        $r = deleteBiometricMachine($mid);
        if (!empty($r['ok']) && $mRow) {
            $r['message'] = 'Machine deleted: ' . (string) $mRow['machine_name'];
        }
        $_SESSION['bio_flash'] = $r;
        header('Location: ' . app_url('attendance/machines.php'));
        exit;
    }
}

$machines = fetchBiometricMachines(false);
$stats = biometricDashboardStats();
$logCounts = biometricLogCountByMachine();
$canEdit = canAccess('attendance', 'edit');

$pageTitle = 'Biometric Machines';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_machines';
$extraCss = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
];
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('attendance/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Attendance
        </a>
        <div class="toolbar-actions">
            <a href="<?php echo app_url('attendance/machine_logs.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-fingerprint"></i> Machine Wise Logs
            </a>
            <a href="<?php echo app_url('attendance/machine_sync.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-rotate"></i> Sync Attendance
            </a>
            <?php if ($canEdit): ?>
            <a href="<?php echo app_url('attendance/machine_form.php'); ?>" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Add Machine
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Biometric Machines — Armor Fire</h1>
            <p>Link machines · Sync punches · View machine-wise attendance</p>
        </div>

        <div class="bio-stat-grid">
            <div class="bio-stat">
                <span class="bio-stat-label">Machines</span>
                <span class="bio-stat-value"><?php echo (int) $stats['machines_total']; ?></span>
                <span class="bio-stat-sub"><?php echo (int) $stats['machines_active']; ?> active</span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Synced Punches</span>
                <span class="bio-stat-value"><?php echo number_format((int) $stats['logs_total']); ?></span>
                <span class="bio-stat-sub"><?php echo number_format((int) $stats['logs_matched']); ?> employee matched</span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Today</span>
                <span class="bio-stat-value"><?php echo number_format((int) $stats['logs_today']); ?></span>
                <span class="bio-stat-sub">punches synced today</span>
            </div>
            <div class="bio-stat">
                <span class="bio-stat-label">Last Sync</span>
                <span class="bio-stat-value bio-stat-value-sm">
                    <?php echo $stats['last_sync_at'] ? htmlspecialchars(date('d-m-Y H:i', strtotime($stats['last_sync_at']))) : '—'; ?>
                </span>
                <span class="bio-stat-sub">from Ocean / devices</span>
            </div>
        </div>

        <div class="table-wrap bio-table-wrap">
            <table class="data-table bio-machines-table" style="width:100%;">
                <thead>
                    <tr>
                        <th width="48">Sr</th>
                        <th>Machine</th>
                        <th>Provider</th>
                        <th>Connection</th>
                        <th>Interval</th>
                        <th>Logs</th>
                        <th>Last Sync</th>
                        <th>Status</th>
                        <th width="220">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$machines): ?>
                    <tr>
                        <td colspan="9" class="bio-empty">
                            No machines linked yet.
                            <?php if ($canEdit): ?>
                                <a href="<?php echo app_url('attendance/machine_form.php'); ?>">Add first machine</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($machines as $i => $m): ?>
                        <?php
                        $mid = (int) $m['id'];
                        $active = (int) $m['is_active'] === 1;
                        $prov = (string) $m['provider_type'];
                        $logsN = (int) ($logCounts[$mid] ?? 0);
                        $ipShow = trim((string) ($m['ip_address'] ?? ''));
                        if ($ipShow === '127.0.0.1') {
                            $ipShow = '';
                        }
                        $mNameJs = htmlspecialchars((string) $m['machine_name'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="<?php echo $active ? '' : 'bio-row-off'; ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <div class="bio-machine-name"><?php echo htmlspecialchars($m['machine_name']); ?></div>
                                <div class="bio-machine-code"><?php echo htmlspecialchars($m['machine_code']); ?></div>
                                <?php if (!empty($m['remarks'])): ?>
                                    <div class="bio-machine-note"><?php echo htmlspecialchars($m['remarks']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="bio-provider bio-provider-<?php echo htmlspecialchars($prov); ?>">
                                    <?php echo htmlspecialchars(biometricProviderLabel($prov)); ?>
                                </span>
                            </td>
                            <td class="bio-conn">
                                <div class="bio-url" title="<?php echo htmlspecialchars($m['api_url']); ?>">
                                    <?php echo htmlspecialchars($m['api_url']); ?>
                                </div>
                                <?php if ($ipShow !== ''): ?>
                                    <div class="bio-meta"><i class="fa-solid fa-network-wired"></i>
                                        <?php echo htmlspecialchars($ipShow); ?><?php echo $m['port'] !== '' && $m['port'] !== null ? ':' . htmlspecialchars((string) $m['port']) : ''; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($m['api_username'])): ?>
                                    <div class="bio-meta"><i class="fa-solid fa-user"></i>
                                        <?php echo htmlspecialchars($m['api_username']); ?>
                                        <?php if (!empty($m['api_password'])): ?>
                                            <span class="bio-pwd-ok">· password set</span>
                                        <?php else: ?>
                                            <span class="bio-pwd-miss">· password missing</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($m['ocean_machine_id'])): ?>
                                    <div class="bio-meta"><i class="fa-solid fa-link"></i> Ocean #<?php echo (int) $m['ocean_machine_id']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $m['sync_interval_minutes']; ?> min</td>
                            <td>
                                <a href="<?php echo app_url('attendance/machine_logs.php?machine_id=' . $mid); ?>">
                                    <?php echo number_format($logsN); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($m['last_sync_at'])): ?>
                                    <div><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($m['last_sync_at']))); ?></div>
                                    <div class="bio-meta">
                                        <?php echo htmlspecialchars((string) $m['last_sync_status']); ?>
                                        · <?php echo (int) $m['last_sync_count']; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="bio-meta">Never synced</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo $active ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fa-solid fa-circle"></i>
                                    <?php echo $active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="bio-actions">
                                    <a class="btn-secondary bio-btn" href="<?php echo app_url('attendance/machine_sync.php?machine_id=' . $mid); ?>" title="Sync">
                                        <i class="fa-solid fa-rotate"></i>
                                    </a>
                                    <a class="btn-secondary bio-btn" href="<?php echo app_url('attendance/machine_logs.php?machine_id=' . $mid); ?>" title="Logs">
                                        <i class="fa-solid fa-list"></i>
                                    </a>
                                    <?php if ($canEdit): ?>
                                    <a class="btn-secondary bio-btn" href="<?php echo app_url('attendance/machine_form.php?id=' . $mid); ?>" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <form method="post" class="bio-inline-form" onsubmit="return confirm('Toggle status for <?php echo $mNameJs; ?>?');">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="machine_id" value="<?php echo $mid; ?>">
                                        <button type="submit" class="btn-secondary bio-btn" title="<?php echo $active ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fa-solid fa-<?php echo $active ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="bio-inline-form js-bio-delete-form"
                                          data-name="<?php echo $mNameJs; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="machine_id" value="<?php echo $mid; ?>">
                                        <button type="button" class="btn-secondary bio-btn bio-btn-danger js-bio-delete-btn" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    document.querySelectorAll('.js-bio-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var form = btn.closest('form');
            if (!form) return;
            var name = form.getAttribute('data-name') || 'this machine';
            var message = 'Delete machine "' + name + '"? This cannot be undone. Punch logs will be kept.';

            if (window.ConfirmDelete && typeof window.ConfirmDelete.ask === 'function') {
                window.ConfirmDelete.ask(message, function () {
                    form.submit();
                }, { title: 'Delete Machine?', yesLabel: 'Yes, Delete' });
                return;
            }

            if (window.confirm(message)) {
                form.submit();
            }
        });
    });

    <?php if ($toast !== ''): ?>
    if (typeof toastr !== 'undefined') {
        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 4000 };
        toastr.<?php echo $toastType === 'error' ? 'error' : 'success'; ?>(<?php echo json_encode($toast); ?>);
    } else {
        alert(<?php echo json_encode($toast); ?>);
    }
    <?php endif; ?>
})();
</script>
