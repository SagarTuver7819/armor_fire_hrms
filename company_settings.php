<?php
/**
 * Admin - Company Logo Setup
 * Separate options: Login Logo + Dashboard Logo
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';

requireLogin();

if (!isAdmin()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$conn = getDBConnection();
ensureCompanySettingsSchema($conn);

$current = [
    'company_name'   => 'Armor Fire',
    'login_logo'     => null,
    'dashboard_logo' => null,
];

$res = $conn->query(
    "SELECT company_name, login_logo, dashboard_logo, company_logo
     FROM company_settings WHERE id = 1 LIMIT 1"
);
if ($res && $row = $res->fetch_assoc()) {
    $current['company_name']   = $row['company_name'];
    $current['login_logo']     = !empty($row['login_logo']) ? $row['login_logo'] : $row['company_logo'];
    $current['dashboard_logo'] = !empty($row['dashboard_logo']) ? $row['dashboard_logo'] : $row['company_logo'];
}

$message = '';
$messageType = '';

/**
 * Handle one logo upload field
 * $protectPath = other logo path that must not be deleted if shared
 */
function handleLogoUpload($fileKey, $currentPath, $prefix, &$errorMsg, $protectPath = null)
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    $file = $_FILES[$fileKey];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Logo upload failed. Please try again.';
        return $currentPath;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxSize = 2 * 1024 * 1024;

    if (!in_array($ext, $allowed, true)) {
        $errorMsg = 'Only JPG, PNG, GIF, WEBP, SVG files allowed.';
        return $currentPath;
    }

    if ($file['size'] > $maxSize) {
        $errorMsg = 'Logo size must be under 2 MB.';
        return $currentPath;
    }

    $uploadDir = __DIR__ . '/assets/uploads/logo';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newName = $prefix . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
    $dest = $uploadDir . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errorMsg = 'Could not save uploaded logo.';
        return $currentPath;
    }

    // Delete old file only if not still used by the other logo
    if (
        !empty($currentPath)
        && strpos($currentPath, 'assets/uploads/logo/') === 0
        && $currentPath !== $protectPath
    ) {
        $oldFull = __DIR__ . '/' . $currentPath;
        if (is_file($oldFull) && realpath($oldFull) !== realpath($dest)) {
            @unlink($oldFull);
        }
    }

    return 'assets/uploads/logo/' . $newName;
}

function removeUploadedLogo($path)
{
    if (!empty($path) && strpos($path, 'assets/uploads/logo/') === 0) {
        $full = __DIR__ . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $removeLogin = isset($_POST['remove_login_logo']);
    $removeDash  = isset($_POST['remove_dashboard_logo']);

    if ($companyName === '') {
        $message = 'Company name is required.';
        $messageType = 'error';
    } else {
        $loginLogo = $current['login_logo'];
        $dashLogo  = $current['dashboard_logo'];
        $oldLogin  = $loginLogo;
        $oldDash   = $dashLogo;
        $uploadError = '';

        if ($removeLogin) {
            $loginLogo = null;
        }
        if ($removeDash) {
            $dashLogo = null;
        }

        $loginLogo = handleLogoUpload('login_logo', $loginLogo, 'login_logo', $uploadError, $dashLogo ?: $oldDash);
        if ($uploadError === '') {
            $dashLogo = handleLogoUpload('dashboard_logo', $dashLogo, 'dashboard_logo', $uploadError, $loginLogo ?: $oldLogin);
        }

        if ($uploadError !== '') {
            $message = $uploadError;
            $messageType = 'error';
        } else {
            // Cleanup unused old files
            foreach ([$oldLogin, $oldDash] as $oldPath) {
                if (
                    !empty($oldPath)
                    && $oldPath !== $loginLogo
                    && $oldPath !== $dashLogo
                    && strpos($oldPath, 'assets/uploads/logo/') === 0
                ) {
                    removeUploadedLogo($oldPath);
                }
            }

            $stmt = $conn->prepare(
                "UPDATE company_settings
                 SET company_name = ?,
                     login_logo = ?,
                     dashboard_logo = ?,
                     company_logo = ?
                 WHERE id = 1"
            );
            // Keep company_logo synced to dashboard for older code
            $legacy = $dashLogo;
            $stmt->bind_param('ssss', $companyName, $loginLogo, $dashLogo, $legacy);
            $ok = $stmt->execute();
            $stmt->close();
            $conn->close();

            if ($ok) {
                header('Location: company_settings.php?saved=1');
                exit;
            }

            $message = 'Failed to save settings.';
            $messageType = 'error';
            $conn = getDBConnection();
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $message = 'Company settings saved successfully.';
    $messageType = 'success';
    $res = $conn->query(
        "SELECT company_name, login_logo, dashboard_logo, company_logo
         FROM company_settings WHERE id = 1 LIMIT 1"
    );
    if ($res && $row = $res->fetch_assoc()) {
        $current['company_name']   = $row['company_name'];
        $current['login_logo']     = !empty($row['login_logo']) ? $row['login_logo'] : $row['company_logo'];
        $current['dashboard_logo'] = !empty($row['dashboard_logo']) ? $row['dashboard_logo'] : $row['company_logo'];
    }
}

$conn->close();

$pageTitle = 'Company Settings';

$useSidebar = true;
$sidebarMode = 'employees';
$sidebarActive = 'settings';

require_once __DIR__ . '/includes/header.php';

$defaultLogo = 'assets/images/logo-placeholder.svg';
$previewLogin = (!empty($current['login_logo']) && file_exists(__DIR__ . '/' . $current['login_logo']))
    ? $current['login_logo'] : $defaultLogo;
$previewDash = (!empty($current['dashboard_logo']) && file_exists(__DIR__ . '/' . $current['dashboard_logo']))
    ? $current['dashboard_logo'] : $defaultLogo;
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="settings-layout settings-layout-wide">
        <div class="settings-card">
            <div class="settings-card-header">
                <i class="fa-solid fa-image"></i>
                <div>
                    <h2>Company Logo Setup</h2>
                    <p>Login page logo and Dashboard logo — set separately.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="settings-form">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control"
                           value="<?php echo htmlspecialchars($current['company_name'] ?? 'Armor Fire'); ?>" required>
                </div>

                <div class="logo-split-grid">
                    <!-- LOGIN LOGO -->
                    <div class="logo-split-box">
                        <div class="logo-split-title">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <div>
                                <strong>Login Page Logo</strong>
                                <span>Shown on login / sign-in screen</span>
                            </div>
                        </div>

                        <div class="logo-mini-preview">
                            <img src="<?php echo htmlspecialchars($previewLogin); ?>?v=<?php echo time(); ?>" alt="Login Logo">
                        </div>

                        <div class="form-group">
                            <label for="login_logo">Upload Login Logo</label>
                            <input type="file" id="login_logo" name="login_logo" class="form-control"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
                            <small class="form-help">JPG, PNG, GIF, WEBP, SVG — Max 2 MB</small>
                        </div>

                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="remove_login_logo" value="1">
                                Remove login logo (use default)
                            </label>
                        </div>
                    </div>

                    <!-- DASHBOARD LOGO -->
                    <div class="logo-split-box">
                        <div class="logo-split-title">
                            <i class="fa-solid fa-gauge-high"></i>
                            <div>
                                <strong>Dashboard Logo</strong>
                                <span>Shown in top header after login</span>
                            </div>
                        </div>

                        <div class="logo-mini-preview">
                            <img src="<?php echo htmlspecialchars($previewDash); ?>?v=<?php echo time(); ?>" alt="Dashboard Logo">
                        </div>

                        <div class="form-group">
                            <label for="dashboard_logo">Upload Dashboard Logo</label>
                            <input type="file" id="dashboard_logo" name="dashboard_logo" class="form-control"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
                            <small class="form-help">JPG, PNG, GIF, WEBP, SVG — Max 2 MB</small>
                        </div>

                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="remove_dashboard_logo" value="1">
                                Remove dashboard logo (use default)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Save Settings
                    </button>
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="settings-card preview-card">
            <h3>Quick Preview</h3>

            <div class="dual-preview">
                <div>
                    <p class="preview-label">Login Logo</p>
                    <div class="logo-preview-box">
                        <img src="<?php echo htmlspecialchars($previewLogin); ?>?v=<?php echo time(); ?>"
                             alt="Login Logo" class="logo-preview-img">
                    </div>
                </div>
                <div>
                    <p class="preview-label">Dashboard Logo</p>
                    <div class="logo-preview-box">
                        <img src="<?php echo htmlspecialchars($previewDash); ?>?v=<?php echo time(); ?>"
                             alt="Dashboard Logo" class="logo-preview-img">
                    </div>
                </div>
            </div>

            <p class="preview-name"><?php echo htmlspecialchars($current['company_name'] ?? 'Armor Fire'); ?></p>
            <p class="form-help">Both logos can be different images.</p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
