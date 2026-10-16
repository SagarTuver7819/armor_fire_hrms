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
            $roleCode = strtoupper((string) ($_SESSION['role_code'] ?? ''));
            if ($roleCode === 'DEPT_HEAD') {
                $hd = $_SESSION['headed_department_ids'][0] ?? 0;
                header('Location: ' . app_url($hd > 0 ? ('employees/index.php?department_id=' . (int) $hd) : 'hr/dashboard.php'));
            } elseif ($userRole === 'employee' || $roleCode === 'OFFICE_STAFF') {
                $_SESSION['show_company_popup'] = 1;
                header('Location: ' . app_url('employee/dashboard.php'));
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

<div class="bg-scene" aria-hidden="true">
    <div class="bg-orb one"></div>
    <div class="bg-orb two"></div>
    <div class="bg-orb three"></div>

    <i class="bg-icon slate gear-xl fa-solid fa-users"></i>
    <i class="bg-icon gear-md fa-solid fa-id-badge"></i>
    <i class="bg-icon slate gear-sm fa-solid fa-briefcase"></i>
    <i class="bg-icon flame-a fa-solid fa-fire"></i>
    <i class="bg-icon flame-b fa-solid fa-fire"></i>
    <i class="bg-icon slate drop-a fa-solid fa-calendar-check"></i>
    <i class="bg-icon slate drop-b fa-solid fa-chart-line"></i>
    <i class="bg-icon shield fa-solid fa-shield-halved"></i>
    <i class="bg-icon hydrant fa-solid fa-user-tie"></i>
    <i class="bg-icon extinguisher fa-solid fa-building"></i>
    <i class="bg-icon slate nut fa-solid fa-indian-rupee-sign"></i>

    <div class="hr-line" aria-hidden="true">
        <div class="hr-desk">
            <span class="hr-desk-lamp"></span>
            <svg class="hr-desk-svg" viewBox="0 0 150 110" fill="none">
                <rect x="8" y="48" width="118" height="14" rx="3" fill="#475569"/>
                <rect x="18" y="62" width="10" height="40" rx="2" fill="#64748b"/>
                <rect x="106" y="62" width="10" height="40" rx="2" fill="#64748b"/>
                <rect x="28" y="18" width="72" height="48" rx="6" fill="#1e293b"/>
                <rect x="34" y="24" width="60" height="34" rx="3" fill="#0f172a"/>
                <rect class="hr-screen" x="38" y="28" width="52" height="26" rx="2" fill="#22c55e" opacity="0.55"/>
                <rect x="52" y="66" width="28" height="4" rx="1" fill="#94a3b8"/>
                <circle cx="128" cy="40" r="10" fill="#d2232a" opacity="0.9"/>
                <text x="128" y="44" text-anchor="middle" font-size="9" font-weight="800" fill="#fff" font-family="Outfit,sans-serif">HR</text>
            </svg>
            <span class="hr-paper p1"></span>
            <span class="hr-paper p2"></span>
        </div>

        <div class="hr-belt"></div>
        <div class="hr-lane">
            <span class="hr-item" style="--d:-0s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="6" y="2" width="28" height="36" rx="3" fill="#fff" stroke="#cbd5e1"/><circle cx="20" cy="14" r="6" fill="#fecaca"/><rect x="11" y="24" width="18" height="3" rx="1" fill="#e2e8f0"/><rect x="13" y="30" width="14" height="3" rx="1" fill="#e2e8f0"/></svg>
                </span>
                <b class="hr-item-label">CV</b>
            </span>
            <span class="hr-item" style="--d:-3s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="8" y="4" width="24" height="32" rx="3" fill="#fff" stroke="#94a3b8"/><circle cx="20" cy="14" r="5" fill="#64748b"/><rect x="12" y="22" width="16" height="8" rx="1" fill="#fee2e2"/></svg>
                </span>
                <b class="hr-item-label">ID</b>
            </span>
            <span class="hr-item hot" style="--d:-6s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="7" y="5" width="26" height="30" rx="4" fill="#d2232a"/><circle cx="20" cy="15" r="5" fill="#fecaca"/><rect x="12" y="23" width="16" height="3" rx="1" fill="#fff" opacity=".85"/><rect x="14" y="29" width="12" height="3" rx="1" fill="#fff" opacity=".55"/></svg>
                </span>
                <b class="hr-item-label">JOIN</b>
            </span>
            <span class="hr-item" style="--d:-9s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="10" y="4" width="20" height="32" rx="3" fill="#1e293b"/><circle cx="20" cy="16" r="7" fill="none" stroke="#22c55e" stroke-width="2"/><circle cx="20" cy="16" r="2" fill="#22c55e"/><rect x="14" y="28" width="12" height="3" rx="1" fill="#94a3b8"/></svg>
                </span>
                <b class="hr-item-label">PUNCH</b>
            </span>
            <span class="hr-item" style="--d:-12s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="8" y="4" width="24" height="32" rx="3" fill="#fff" stroke="#f59e0b"/><path d="M14 14h12M14 20h10M14 26h8" stroke="#fbbf24" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <b class="hr-item-label">LEAVE</b>
            </span>
            <span class="hr-item hot" style="--d:-15s">
                <span class="hr-item-ico">
                    <svg viewBox="0 0 40 40"><rect x="6" y="6" width="28" height="28" rx="3" fill="#16a34a"/><text x="20" y="24" text-anchor="middle" font-size="14" font-weight="800" fill="#fff" font-family="Outfit,sans-serif">₹</text><rect x="12" y="28" width="16" height="3" rx="1" fill="#bbf7d0"/></svg>
                </span>
                <b class="hr-item-label">PAY</b>
            </span>
        </div>

        <div class="hr-gate">
            <svg viewBox="0 0 70 100" width="70" height="100">
                <path d="M8 100 V22 Q8 12 18 12 H52 Q62 12 62 22 V100" fill="none" stroke="#475569" stroke-width="5" stroke-linecap="round"/>
                <rect x="16" y="0" width="38" height="16" rx="8" fill="#d2232a"/>
                <text x="35" y="11.5" text-anchor="middle" font-size="10" font-weight="800" fill="#fff" font-family="Outfit,sans-serif">HR</text>
                <circle class="hr-lamp" cx="58" cy="6" r="3.5" fill="#22c55e"/>
            </svg>
            <span class="hr-beam"></span>
        </div>

        <div class="hr-done">
            <span class="hr-done-card">
                <i></i><i></i><i></i>
                <b class="hr-item-label ok">PAID</b>
            </span>
            <span class="hr-done-box">
                <strong>NEFT</strong>
                <small>DONE</small>
            </span>
        </div>
    </div>
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
