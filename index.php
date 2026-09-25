<?php
/**
 * Login Page — Admin, HR & Employee portals
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
        header('Location: ' . app_url('dashboard.php'));
    }
    exit;
}

$error = '';
$allowedRoles = ['admin', 'hr', 'employee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    // Username always UPPERCASE (same as Employee Login / Staff Users save)
    if (function_exists('mb_strtoupper')) {
        $username = mb_strtoupper($username, 'UTF-8');
    } else {
        $username = strtoupper($username);
    }
    $loginAs  = strtolower(trim($_POST['login_as'] ?? 'admin'));

    if (!in_array($loginAs, $allowedRoles, true)) {
        $loginAs = 'admin';
    }

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
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
        $user   = $result->fetch_assoc();
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

        if (!$user || $user['password'] !== $password) {
            $error = 'Invalid username or password.';
        } elseif (!in_array($user['role'], $allowedRoles, true)) {
            $error = 'This account cannot sign in here.';
        } elseif ($user['role'] !== $loginAs) {
            $error = 'You selected "' . strtoupper($loginAs) . '" but this account is "' . strtoupper($user['role']) . '".';
        } elseif ($user['role'] === 'employee' && (empty($user['custom_role_id']) || $roleName === '')) {
            $error = 'No active role assigned. Contact Admin.';
        } else {
            $_SESSION['user_id']        = $user['id'];
            $_SESSION['username']       = $user['username'];
            $_SESSION['full_name']      = $user['full_name'];
            $_SESSION['role']           = $user['role'];
            $_SESSION['department_id']  = $user['department_id'];
            $_SESSION['employee_id']    = !empty($user['employee_id']) ? (int) $user['employee_id'] : 0;
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
            } elseif ($user['role'] === 'hr' || $user['role'] === 'employee') {
                header('Location: ' . app_url('hr/dashboard.php'));
            } else {
                header('Location: ' . app_url('dashboard.php'));
            }
            exit;
        }
    }
}

$selectedRole = isset($_POST['login_as']) && in_array($_POST['login_as'], $allowedRoles, true)
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
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-page">

<div class="login-bg" aria-hidden="true">
    <div class="login-bg-gradient"></div>
    <div class="login-bg-grid"></div>
    <div class="login-orb login-orb-1"></div>
    <div class="login-orb login-orb-2"></div>
    <div class="login-orb login-orb-3"></div>
    <div class="login-ring login-ring-1"></div>
    <div class="login-ring login-ring-2"></div>
    <div class="login-flame"></div>
    <div class="login-particles">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
</div>

<div class="login-center">
    <div class="login-card anim-in">
        <div class="login-card-glow"></div>
        <div class="login-card-shine"></div>

        <header class="login-brand">
            <?php if ($hasCustomLogo): ?>
                <div class="login-logo-frame">
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?>"
                         class="login-logo">
                </div>
            <?php else: ?>
                <div class="login-logo-mark">
                    <i class="fa-solid fa-fire-flame-curved"></i>
                </div>
            <?php endif; ?>
            <h1 class="login-company">
                <?php
                if (stripos($companyName, 'armor') !== false && stripos($companyName, 'fire') !== false) {
                    echo 'ARMOR<span class="fire">FIRE</span>';
                } else {
                    echo htmlspecialchars($companyName);
                }
                ?>
            </h1>
            <p class="login-tagline">Shield of Quality · HRMS Portal</p>
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
                <div class="login-roles" role="radiogroup" aria-label="Login role">
                    <label class="login-role">
                        <input type="radio" name="login_as" value="admin" <?php echo $selectedRole === 'admin' ? 'checked' : ''; ?>>
                        <span class="login-role-box admin">
                            <span class="login-role-icon"><i class="fa-solid fa-user-shield"></i></span>
                            <b>Admin Login</b>
                            <small>System &amp; full control</small>
                        </span>
                    </label>
                    <label class="login-role">
                        <input type="radio" name="login_as" value="hr" <?php echo $selectedRole === 'hr' ? 'checked' : ''; ?>>
                        <span class="login-role-box hr">
                            <span class="login-role-icon"><i class="fa-solid fa-users-gear"></i></span>
                            <b>HR Login</b>
                            <small>People &amp; workforce</small>
                        </span>
                    </label>
                    <label class="login-role">
                        <input type="radio" name="login_as" value="employee" <?php echo $selectedRole === 'employee' ? 'checked' : ''; ?>>
                        <span class="login-role-box employee">
                            <span class="login-role-icon"><i class="fa-solid fa-id-badge"></i></span>
                            <b>Employee Login</b>
                            <small>Role-based access</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="login-field">
                <label for="username">Username</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
            </div>

            <div class="login-field">
                <label for="password">Password</label>
                <div class="login-input-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="login-eye" id="togglePass" aria-label="Show password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-btn" id="btnLogin">
                <span class="login-btn-text">
                    Sign In to HRMS
                    <i class="fa-solid fa-arrow-right"></i>
                </span>
                <span class="login-btn-load" hidden>
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    Authenticating...
                </span>
            </button>
        </form>

        <div class="login-demo">
            <p class="login-demo-label">Quick demo access</p>
            <div class="login-demo-row">
                <button type="button" class="login-demo-chip" data-user="admin" data-pass="password123" data-role="admin">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Admin</span>
                    <code>admin</code>
                </button>
                <button type="button" class="login-demo-chip" data-user="hr" data-pass="password123" data-role="hr">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>HR</span>
                    <code>hr</code>
                </button>
            </div>
        </div>

        <footer class="login-card-foot">
            <i class="fa-solid fa-shield-halved"></i>
            Admin · HR · Employee secure portals
        </footer>
    </div>

    <p class="login-copy anim-in" style="--delay:.35s">
        © <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?> HRMS · Ocean Infotech
    </p>
</div>

<script src="assets/js/case_force.js"></script>
<script src="assets/js/login.js"></script>
</body>
</html>
