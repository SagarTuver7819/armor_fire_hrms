<?php
/**
 * Login Page — Admin & Employee portals
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/department_head_helper.php';

$companyName = getCompanyName();
$companyLogo = getLoginLogo();
$hasCustomLogo = isCustomLogo($companyLogo);

if (isLoggedIn()) {
    require_once __DIR__ . '/includes/department_head_helper.php';
    if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
        refreshHeadedDepartmentsSession();
    }
    if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
        header('Location: ' . app_url('employee/dashboard.php'));
    } elseif (isHR()) {
        header('Location: ' . app_url('hr/dashboard.php'));
    } elseif (isEmployee()) {
        header('Location: ' . app_url('employee/dashboard.php'));
    } else {
        // Admin / staff → HR Dashboard
        header('Location: ' . app_url('hr/dashboard.php'));
    }
    exit;
}

$error = '';
// UI has 2 choices; Admin covers staff (admin + hr accounts)
$uiRoles = ['admin', 'employee'];
$dbRoles = ['admin', 'hr', 'employee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    // Match DB usernames case-insensitively (do not force capitals in the form UI)
    if (function_exists('mb_strtoupper')) {
        $username = mb_strtoupper($usernameInput, 'UTF-8');
    } else {
        $username = strtoupper($usernameInput);
    }
    $loginAs = strtolower(trim($_POST['login_as'] ?? 'admin'));
    if (!in_array($loginAs, $uiRoles, true)) {
        $loginAs = 'admin';
    }

    if ($usernameInput === '' || $password === '') {
        $error = 'Please enter username and password.';
        $username = $usernameInput;
    } else {
        ensureRoleTables();
        $conn = getDBConnection();
        $stmt = $conn->prepare(
            "SELECT id, username, password, full_name, role, department_id, custom_role_id, employee_id, status
             FROM users WHERE username = ? AND status = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $roleName = '';
        if ($user && !empty($user['custom_role_id'])) {
            $rs = $conn->prepare('SELECT name FROM roles WHERE id = ? AND status = 1 LIMIT 1');
            $rid = (int) $user['custom_role_id'];
            $rs->bind_param('i', $rid);
            $rs->execute();
            $rr = $rs->get_result()->fetch_assoc();
            $rs->close();
            $roleName = (string) ($rr['name'] ?? '');
        }
        $conn->close();

        $userRole = strtolower((string) ($user['role'] ?? ''));
        $roleOk = false;
        if ($loginAs === 'admin') {
            // Admin portal: Admin + HR staff accounts
            $roleOk = in_array($userRole, ['admin', 'hr'], true);
        } else {
            $roleOk = ($userRole === 'employee');
        }

        if (!$user || $user['password'] !== $password) {
            $error = 'Invalid username or password.';
        } elseif (!in_array($userRole, $dbRoles, true)) {
            $error = 'This account cannot sign in here.';
        } elseif (!$roleOk) {
            if ($loginAs === 'admin') {
                $error = 'Use Employee Login for this account.';
            } else {
                $error = 'Use Admin Login for this account.';
            }
        } elseif ($userRole === 'employee' && (empty($user['custom_role_id']) || $roleName === '')) {
            $error = 'No active role assigned. Contact Admin.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['employee_id'] = !empty($user['employee_id']) ? (int) $user['employee_id'] : 0;
            $_SESSION['custom_role_id'] = !empty($user['custom_role_id']) ? (int) $user['custom_role_id'] : 0;
            $_SESSION['custom_role_name'] = $roleName;
            unset($_SESSION['permissions'], $_SESSION['permissions_role_id']);
            if ($_SESSION['custom_role_id'] > 0) {
                loadUserPermissionsIntoSession($_SESSION['custom_role_id'], true);
            } else {
                $_SESSION['permissions'] = [];
                $_SESSION['permissions_role_id'] = 0;
            }
            refreshHeadedDepartmentsSession();
            $roleCode = strtoupper((string) ($_SESSION['role_code'] ?? ''));
            if ($roleCode === 'OFFICE_STAFF') {
                header('Location: ' . app_url('employee/dashboard.php'));
            } elseif ($roleCode === 'DEPT_HEAD') {
                $hd = $_SESSION['headed_department_ids'][0] ?? 0;
                header('Location: ' . app_url($hd > 0 ? ('employees/index.php?department_id=' . (int) $hd) : 'hr/dashboard.php'));
            } else {
                // Admin / HR / staff → HR Dashboard
                header('Location: ' . app_url('hr/dashboard.php'));
            }
            exit;
        }
        // Keep what the user typed in the form (no capital force on login UI)
        $username = $usernameInput;
    }
}

$selectedRole = isset($_POST['login_as']) && in_array($_POST['login_as'], $uiRoles, true)
    ? $_POST['login_as']
    : 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($companyName); ?> HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/assets/css/login.css'); ?>">
</head>
<body class="login-page">

<div class="login-bg" aria-hidden="true">
    <div class="login-bg-glow login-bg-glow-a"></div>
    <div class="login-bg-glow login-bg-glow-b"></div>
    <div class="login-bg-grid"></div>
</div>

<div class="login-center">
    <div class="login-card anim-in">
        <span class="login-hub-badge">Command Hub</span>

        <header class="login-brand">
            <p class="login-since">Since 2010</p>

            <?php if ($hasCustomLogo): ?>
                <div class="login-logo-wrap anim-logo">
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>?v=<?php echo @filemtime(__DIR__ . '/' . $companyLogo) ?: time(); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?>"
                         class="login-logo login-logo-custom">
                </div>
            <?php else: ?>
                <div class="login-logo-wrap anim-logo">
                    <div class="login-logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#d2232a" d="M60 8 L108 112 H88 L78 88 H42 L32 112 H12 Z"/>
                            <path fill="#111111" d="M60 28 L74 64 H46 Z"/>
                            <path fill="#d2232a" d="M60 42c-1.2 5-5 9-5 15a5 5 0 0 0 10 0c0-6-3.8-10-5-15z"/>
                            <path fill="#f87171" d="M60 52c-.7 2.8-2.6 4.8-2.6 8a2.6 2.6 0 0 0 5.2 0c0-3.2-1.9-5.2-2.6-8z"/>
                        </svg>
                    </div>
                </div>
                <div class="login-wordmark anim-wordmark">
                    <span class="wm-armor">ARMOR</span>
                    <span class="wm-fire">FIRE</span>
                    <span class="wm-tag">Shield of Quality</span>
                </div>
            <?php endif; ?>

            <h1 class="login-portal-title">Human Resource Management</h1>
            <p class="login-tagline">Engineered for Safety · Precision ERP Node</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="login-alert anim-shake" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" class="login-form" autocomplete="off">
            <div class="login-field">
                <label>Login as</label>
                <div class="login-roles login-roles-2" role="radiogroup" aria-label="Login role">
                    <label class="login-role">
                        <input type="radio" name="login_as" value="admin" <?php echo $selectedRole === 'admin' ? 'checked' : ''; ?>>
                        <span class="login-role-box admin">
                            <span class="login-role-icon"><i class="fa-solid fa-user-shield"></i></span>
                            <b>Admin Login</b>
                            <small>Admin &amp; HR staff</small>
                        </span>
                    </label>
                    <label class="login-role">
                        <input type="radio" name="login_as" value="employee" <?php echo $selectedRole === 'employee' ? 'checked' : ''; ?>>
                        <span class="login-role-box employee">
                            <span class="login-role-icon"><i class="fa-solid fa-id-badge"></i></span>
                            <b>Employee Login</b>
                            <small>Staff portal access</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="login-field">
                <label for="username">Credential ID</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" id="username" name="username" placeholder="Credential ID" required
                           autocomplete="username"
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
            </div>

            <div class="login-field">
                <label for="password">Encryption Key</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Encryption Key" required
                           autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="login-btn" id="btnLogin">
                <span class="login-btn-text">
                    Validate &amp; Initiate Session
                    <i class="fa-solid fa-bolt"></i>
                </span>
                <span class="login-btn-load" hidden>
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    Authenticating...
                </span>
            </button>
        </form>

        <footer class="login-card-foot">
            <span class="foot-label">Designed &amp; Developed by</span>
            <span class="foot-brand">Ocean Infotech</span>
        </footer>
    </div>

    <p class="login-copy anim-in" style="--delay:.3s">
        © <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?> · Secure Admin &amp; Employee portals
    </p>
</div>

<script src="assets/js/login.js"></script>
</body>
</html>
