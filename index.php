<?php
/**
 * Login Page - Professional Animated HRMS UI
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';

$companyName = getCompanyName();
$companyLogo = getLoginLogo();
$hasCustomLogo = isCustomLogo($companyLogo);

if (isLoggedIn()) {
    require_once __DIR__ . '/config/app.php';
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $loginAs  = trim($_POST['login_as'] ?? 'admin');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare(
            "SELECT id, username, password, full_name, role, department_id, status
             FROM users WHERE username = ? AND status = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$user || $user['password'] !== $password) {
            $error = 'Invalid username or password.';
        } elseif ($user['role'] !== $loginAs) {
            $error = 'You selected "' . strtoupper($loginAs) . '" but this account is "' . strtoupper($user['role']) . '".';
        } else {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            header('Location: dashboard.php');
            exit;
        }
    }
}

$selectedRole = isset($_POST['login_as']) ? $_POST['login_as'] : 'admin';
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
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="lp">

<div class="lp-stage">
    <!-- ========== LEFT BRAND PANEL ========== -->
    <section class="lp-hero">
        <div class="lp-hero-bg" aria-hidden="true">
            <div class="lp-mesh"></div>
            <div class="lp-ring lp-ring-a"></div>
            <div class="lp-ring lp-ring-b"></div>
            <div class="lp-ring lp-ring-c"></div>
            <span class="lp-dot d1"></span>
            <span class="lp-dot d2"></span>
            <span class="lp-dot d3"></span>
            <span class="lp-dot d4"></span>
            <span class="lp-dot d5"></span>
        </div>

        <div class="lp-hero-inner">
            <header class="lp-brand reveal" style="--d:.05s">
                <?php if ($hasCustomLogo): ?>
                    <img src="<?php echo htmlspecialchars($companyLogo); ?>"
                         alt="<?php echo htmlspecialchars($companyName); ?>"
                         class="lp-brand-logo">
                <?php else: ?>
                    <div class="lp-brand-mark"><i class="fa-solid fa-fire-flame-curved"></i></div>
                <?php endif; ?>
                <div>
                    <div class="lp-brand-name">
                        <?php
                        $name = htmlspecialchars($companyName);
                        // Style ARMOR / FIRE like logo if name matches
                        if (stripos($companyName, 'armor') !== false && stripos($companyName, 'fire') !== false) {
                            echo 'ARMOR<span class="fire">FIRE</span>';
                        } else {
                            echo $name;
                        }
                        ?>
                    </div>
                    <div class="lp-brand-tag">Shield of Quality · HRMS</div>
                </div>
            </header>

            <div class="lp-hero-copy">
                <p class="lp-eyebrow reveal" style="--d:.15s">
                    <span class="lp-pulse"></span> Enterprise HR Workspace
                </p>
                <h1 class="lp-title reveal" style="--d:.25s">
                    People. Process.<br>
                    <em>Performance.</em>
                </h1>
                <p class="lp-desc reveal" style="--d:.35s">
                    A secure, department-wise HRMS built for Armor Fire — manage workforce, roles and operations from one professional dashboard.
                </p>
            </div>

            <div class="lp-stats reveal" style="--d:.45s">
                <div class="lp-stat">
                    <div class="lp-stat-icon"><i class="fa-solid fa-building"></i></div>
                    <div>
                        <strong data-count="34">0</strong>
                        <span>Departments</span>
                    </div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <div>
                        <strong>2</strong>
                        <span>Access Roles</span>
                    </div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-icon"><i class="fa-solid fa-lock"></i></div>
                    <div>
                        <strong>100%</strong>
                        <span>Secure Login</span>
                    </div>
                </div>
            </div>

            <!-- Fills middle empty space -->
            <div class="lp-feature-board reveal" style="--d:.52s">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-id-badge"></i></div>
                    <div>
                        <strong>Employee Master</strong>
                        <p>Department-wise workforce profiles &amp; records</p>
                    </div>
                </div>
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <div>
                        <strong>Attendance Hub</strong>
                        <p>Daily presence, shifts and leave tracking</p>
                    </div>
                </div>
                <div class="lp-feature-card">
                    <div class="lp-feature-icon"><i class="fa-solid fa-chart-column"></i></div>
                    <div>
                        <strong>HR Analytics</strong>
                        <p>Live reports for admin decision making</p>
                    </div>
                </div>
                <div class="lp-feature-card highlight">
                    <div class="lp-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <strong>Shield of Quality</strong>
                        <p>Secure role-based access for every user</p>
                    </div>
                </div>
            </div>

            <div class="lp-modules reveal" style="--d:.6s">
                <div class="lp-module">
                    <i class="fa-solid fa-users"></i>
                    <span>Employees</span>
                </div>
                <div class="lp-module">
                    <i class="fa-solid fa-clock"></i>
                    <span>Attendance</span>
                </div>
                <div class="lp-module">
                    <i class="fa-solid fa-sitemap"></i>
                    <span>Departments</span>
                </div>
                <div class="lp-module">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Reports</span>
                </div>
            </div>

            <footer class="lp-hero-foot reveal" style="--d:.68s">
                <i class="fa-solid fa-shield-halved"></i>
                Encrypted session · Admin &amp; Employee access
            </footer>
        </div>
    </section>

    <!-- ========== RIGHT LOGIN PANEL ========== -->
    <section class="lp-auth">
        <div class="lp-auth-card reveal-right">
            <div class="lp-auth-top">
                <div class="lp-auth-logo-mobile">
                    <?php if ($hasCustomLogo): ?>
                        <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="">
                    <?php else: ?>
                        <div class="lp-brand-mark sm"><i class="fa-solid fa-fire-flame-curved"></i></div>
                    <?php endif; ?>
                </div>
                <h2>Sign in</h2>
                <p>Access your HRMS workspace securely</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="lp-alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" class="lp-form" autocomplete="off">
                <div class="lp-field">
                    <label>Continue as</label>
                    <div class="lp-roles" role="radiogroup" aria-label="Login role">
                        <label class="lp-role">
                            <input type="radio" name="login_as" value="admin" <?php echo $selectedRole === 'admin' ? 'checked' : ''; ?>>
                            <span class="lp-role-ui">
                                <i class="fa-solid fa-user-shield"></i>
                                <b>Admin</b>
                                <small>Full control</small>
                            </span>
                        </label>
                        <label class="lp-role">
                            <input type="radio" name="login_as" value="employee" <?php echo $selectedRole === 'employee' ? 'checked' : ''; ?>>
                            <span class="lp-role-ui">
                                <i class="fa-solid fa-user"></i>
                                <b>Employee</b>
                                <small>Self service</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="lp-field">
                    <label for="username">Username</label>
                    <div class="lp-input">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required
                               value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                    </div>
                </div>

                <div class="lp-field">
                    <label for="password">Password</label>
                    <div class="lp-input">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="lp-eye" id="togglePass" aria-label="Show password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="lp-submit" id="btnLogin">
                    <span class="lp-submit-text">
                        Sign In to HRMS
                        <i class="fa-solid fa-arrow-right"></i>
                    </span>
                    <span class="lp-submit-load" hidden>
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        Authenticating...
                    </span>
                </button>
            </form>

            <div class="lp-demo">
                <div class="lp-demo-title">Demo credentials</div>
                <div class="lp-demo-grid">
                    <button type="button" class="lp-demo-btn" data-user="admin" data-pass="password123" data-role="admin">
                        <span>Admin</span>
                        <code>admin</code>
                    </button>
                    <button type="button" class="lp-demo-btn" data-user="emp001" data-pass="password123" data-role="employee">
                        <span>Employee</span>
                        <code>emp001</code>
                    </button>
                </div>
            </div>
        </div>

        <p class="lp-copy">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName); ?> HRMS · Ocean Infotech</p>
    </section>
</div>

<script src="assets/js/login.js"></script>
</body>
</html>
