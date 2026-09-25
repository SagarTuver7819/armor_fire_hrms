<?php
/**
 * Add / Edit biometric machine (Armor Fire)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/biometric_helper.php';

requireLogin();
requireAccess('attendance', 'edit');
ensureBiometricTables();

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getBiometricMachineById($id) : null;
if ($id > 0 && !$row) {
    $_SESSION['bio_flash'] = ['ok' => false, 'message' => 'Machine not found.'];
    header('Location: ' . app_url('attendance/machines.php'));
    exit;
}

$error = '';
$form = $row ?: [
    'machine_name' => '',
    'machine_code' => '',
    'provider_type' => 'old_crm',
    'api_url' => '',
    'auth_type' => 'basic',
    'api_username' => '',
    'api_password' => '',
    'corporate_id' => '',
    'ip_address' => '',
    'port' => '',
    'ocean_machine_id' => '',
    'sync_interval_minutes' => 60,
    'is_active' => 1,
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'machine_name' => $_POST['machine_name'] ?? '',
        'machine_code' => $_POST['machine_code'] ?? '',
        'provider_type' => $_POST['provider_type'] ?? 'old_crm',
        'api_url' => $_POST['api_url'] ?? '',
        'auth_type' => $_POST['auth_type'] ?? 'basic',
        'api_username' => $_POST['api_username'] ?? '',
        'api_password' => $_POST['api_password'] ?? '',
        'keep_password' => empty($_POST['api_password']),
        'corporate_id' => $_POST['corporate_id'] ?? '',
        'ip_address' => $_POST['ip_address'] ?? '',
        'port' => $_POST['port'] ?? '',
        'ocean_machine_id' => $_POST['ocean_machine_id'] ?? 0,
        'sync_interval_minutes' => $_POST['sync_interval_minutes'] ?? 60,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'remarks' => $_POST['remarks'] ?? '',
    ];
    $form = array_merge($form, $payload);
    $result = saveBiometricMachine($payload, $id);
    if (!empty($result['ok'])) {
        $_SESSION['bio_flash'] = $result;
        header('Location: ' . app_url('attendance/machines.php'));
        exit;
    }
    $error = (string) ($result['message'] ?? 'Save failed.');
}

$providers = biometricProviderOptions();
$isEdit = $id > 0;

$pageTitle = $isEdit ? 'Edit Machine' : 'Add Machine';
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
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $isEdit ? 'Edit Biometric Machine' : 'Add Biometric Machine'; ?></h1>
            <p>Armor Fire · API connection for punch sync</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" class="employee-form" autocomplete="off">
            <div class="form-section">
                <h3><i class="fa-solid fa-server"></i> Machine Details</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Machine Name <span class="req">*</span></label>
                        <input type="text" name="machine_name" class="form-control" required
                               value="<?php echo htmlspecialchars((string) $form['machine_name']); ?>"
                               placeholder="e.g. EASyBio Gate">
                    </div>
                    <div class="form-group">
                        <label>Machine Code</label>
                        <input type="text" name="machine_code" class="form-control force-upper"
                               value="<?php echo htmlspecialchars((string) $form['machine_code']); ?>"
                               placeholder="Auto from name if blank">
                        <small>Unique code · leave blank to auto-generate</small>
                    </div>
                    <div class="form-group">
                        <label>Provider Type <span class="req">*</span></label>
                        <select name="provider_type" class="form-control" required>
                            <?php foreach ($providers as $k => $label): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"
                                    <?php echo ((string) $form['provider_type'] === $k) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sync Interval (minutes)</label>
                        <input type="number" name="sync_interval_minutes" class="form-control" min="1" max="1440"
                               value="<?php echo (int) ($form['sync_interval_minutes'] ?: 60); ?>">
                    </div>
                    <div class="form-group span-2">
                        <label>API URL <span class="req">*</span></label>
                        <input type="text" name="api_url" class="form-control" required
                               value="<?php echo htmlspecialchars((string) $form['api_url']); ?>"
                               placeholder="https://api.example.com/...">
                    </div>
                    <div class="form-group">
                        <label>Auth Type</label>
                        <select name="auth_type" class="form-control">
                            <?php
                            $auth = (string) ($form['auth_type'] ?: 'basic');
                            foreach (['basic' => 'Basic Auth', 'bearer' => 'Bearer Token', 'none' => 'None'] as $ak => $al):
                            ?>
                                <option value="<?php echo $ak; ?>" <?php echo $auth === $ak ? 'selected' : ''; ?>>
                                    <?php echo $al; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ocean Machine ID</label>
                        <input type="number" name="ocean_machine_id" class="form-control" min="0"
                               value="<?php echo htmlspecialchars((string) ($form['ocean_machine_id'] ?? '')); ?>"
                               placeholder="Optional link to Ocean HRMS machine">
                        <small>Used when triggering sync on Ocean (Armor company)</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-key"></i> API Credentials</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Username / User ID</label>
                        <input type="text" name="api_username" class="form-control"
                               value="<?php echo htmlspecialchars((string) $form['api_username']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Password <?php echo $isEdit ? '<small>(leave blank to keep)</small>' : ''; ?></label>
                        <input type="password" name="api_password" class="form-control" value="" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label>Corporate ID</label>
                        <input type="text" name="corporate_id" class="form-control"
                               value="<?php echo htmlspecialchars((string) $form['corporate_id']); ?>"
                               placeholder="For eTimeOffice / EASyBio">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-network-wired"></i> Device Network</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" name="ip_address" class="form-control"
                               value="<?php echo htmlspecialchars((string) $form['ip_address']); ?>"
                               placeholder="192.168.1.199">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="text" name="port" class="form-control"
                               value="<?php echo htmlspecialchars((string) $form['port']); ?>"
                               placeholder="5005">
                    </div>
                    <div class="form-group span-2">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control"
                               value="<?php echo htmlspecialchars((string) $form['remarks']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="bio-check-label">
                            <input type="checkbox" name="is_active" value="1"
                                <?php echo !empty($form['is_active']) ? 'checked' : ''; ?>>
                            Active (include in sync)
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-check"></i>
                    <?php echo $isEdit ? 'Update Machine' : 'Save Machine'; ?>
                </button>
                <a href="<?php echo app_url('attendance/machines.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
