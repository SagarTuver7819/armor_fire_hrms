<?php
/**
 * Admin - Company Logo Setup
 * Login Logo + Dashboard Logo + Document Header / Footer details
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
    'company_name'    => 'Armor Fire',
    'login_logo'      => null,
    'dashboard_logo'  => null,
    'header_details'  => '',
    'footer_details'  => '',
];

$res = $conn->query(
    "SELECT company_name, login_logo, dashboard_logo, company_logo, header_details, footer_details
     FROM company_settings WHERE id = 1 LIMIT 1"
);
if ($res && $row = $res->fetch_assoc()) {
    $current['company_name']   = $row['company_name'];
    $current['login_logo']     = !empty($row['login_logo']) ? $row['login_logo'] : $row['company_logo'];
    $current['dashboard_logo'] = !empty($row['dashboard_logo']) ? $row['dashboard_logo'] : $row['company_logo'];
    $current['header_details'] = (string) ($row['header_details'] ?? '');
    $current['footer_details'] = (string) ($row['footer_details'] ?? '');
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
    $headerDetails = trim($_POST['header_details'] ?? '');
    $footerDetails = trim($_POST['footer_details'] ?? '');
    $removeLogin = isset($_POST['remove_login_logo']);
    $removeDash  = isset($_POST['remove_dashboard_logo']);

    if ($companyName === '') {
        $message = 'Company name is required.';
        $messageType = 'error';
        $current['header_details'] = $headerDetails;
        $current['footer_details'] = $footerDetails;
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
            $current['header_details'] = $headerDetails;
            $current['footer_details'] = $footerDetails;
        } else {
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
                     company_logo = ?,
                     header_details = ?,
                     footer_details = ?
                 WHERE id = 1"
            );
            $legacy = $dashLogo;
            $stmt->bind_param(
                'ssssss',
                $companyName,
                $loginLogo,
                $dashLogo,
                $legacy,
                $headerDetails,
                $footerDetails
            );
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
            $current['header_details'] = $headerDetails;
            $current['footer_details'] = $footerDetails;
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $message = 'Company settings saved successfully.';
    $messageType = 'success';
    $res = $conn->query(
        "SELECT company_name, login_logo, dashboard_logo, company_logo, header_details, footer_details
         FROM company_settings WHERE id = 1 LIMIT 1"
    );
    if ($res && $row = $res->fetch_assoc()) {
        $current['company_name']   = $row['company_name'];
        $current['login_logo']     = !empty($row['login_logo']) ? $row['login_logo'] : $row['company_logo'];
        $current['dashboard_logo'] = !empty($row['dashboard_logo']) ? $row['dashboard_logo'] : $row['company_logo'];
        $current['header_details'] = (string) ($row['header_details'] ?? '');
        $current['footer_details'] = (string) ($row['footer_details'] ?? '');
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
                    <p>Login / Dashboard logos + document header &amp; footer for all PDF prints.</p>
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

                <div class="doc-brand-block">
                    <div class="logo-split-title" style="margin-bottom:14px;">
                        <i class="fa-solid fa-file-lines"></i>
                        <div>
                            <strong>Document Header &amp; Footer</strong>
                            <span>Used on Offer Letter, Application PDF and other print documents</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="header_details">Header Details</label>
                        <textarea id="header_details" name="header_details" class="form-control" rows="5"
                                  placeholder="e.g.&#10;Plot / Address line&#10;City, State, PIN&#10;Phone · Email · GSTIN"><?php echo htmlspecialchars($current['header_details'] ?? ''); ?></textarea>
                        <small class="form-help">Appears under company name + logo at the top of every document PDF.</small>
                    </div>

                    <div class="form-group">
                        <label for="footer_details">Footer Details</label>
                        <textarea id="footer_details" name="footer_details" class="form-control" rows="4"
                                  placeholder="e.g.&#10;Registered Office: …&#10;CIN / GST / Website"><?php echo htmlspecialchars($current['footer_details'] ?? ''); ?></textarea>
                        <small class="form-help">Appears at the bottom of every document PDF.</small>
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

            <div class="doc-preview-sample">
                <p class="preview-label">Document header</p>
                <div class="doc-preview-box">
                    <strong><?php echo htmlspecialchars($current['company_name'] ?? 'Armor Fire'); ?></strong>
                    <?php if (trim((string) ($current['header_details'] ?? '')) !== ''): ?>
                        <div class="doc-preview-text"><?php echo nl2br(htmlspecialchars($current['header_details'])); ?></div>
                    <?php else: ?>
                        <div class="doc-preview-text muted">Header details not set yet.</div>
                    <?php endif; ?>
                </div>
                <p class="preview-label" style="margin-top:12px;">Document footer</p>
                <div class="doc-preview-box">
                    <?php if (trim((string) ($current['footer_details'] ?? '')) !== ''): ?>
                        <div class="doc-preview-text"><?php echo nl2br(htmlspecialchars($current['footer_details'])); ?></div>
                    <?php else: ?>
                        <div class="doc-preview-text muted">Footer details not set yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <p class="form-help">Logo watermark + this header/footer apply on PDF print pages.</p>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
