<?php
/**
 * App Sidebar — Dashboard / Masters / Departments (submenu → boxes)
 *
 * Enable before header:
 *   $useSidebar = true;
 *   $sidebarMode = 'workspace' | 'masters' | 'employees';
 *   $sidebarDeptId = 5;          // optional current department
 *   $sidebarActive = 'join_employee' | 'modules' | 'hub' | master key | 'all_employees';
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
if (!function_exists('canAccess')) {
    require_once __DIR__ . '/permission_helper.php';
}
if (!function_exists('refreshHeadedDepartmentsSession')) {
    require_once __DIR__ . '/department_head_helper.php';
}
if (empty($_SESSION['role_code']) && !empty($_SESSION['custom_role_id'])) {
    refreshHeadedDepartmentsSession();
}

$sidebarMode = $sidebarMode ?? 'workspace';
$sidebarDeptId = isset($sidebarDeptId) ? (int) $sidebarDeptId : 0;
$sidebarActive = $sidebarActive ?? '';

$canEmp = canAccess('employees', 'view');
$canCirc = canAccess('circulars', 'view');
$canPol = canAccess('policies', 'view');
$canAtt = canAccess('attendance', 'view');
$canLeave = canAccess('leave', 'view');
$canPay = canAccess('payroll', 'view');
$canMasters = canAccess('masters', 'view');
$canContractor = canAccess('contractor', 'view');
$canDepts = canAccess('departments', 'view');
$canRecruitment = isAdmin() || isHR() || (function_exists('isStaffUser') && isStaffUser()) || canAccess('recruitment', 'view');
$allowedDeptIds = allowedDepartmentsFor('departments', 'view');
$allowedEmpDepts = allowedDepartmentsFor('employees', 'view');

$isOfficeStaffNav = function_exists('isOfficeStaffRole') && isOfficeStaffRole();
$isDeptHeadNav = function_exists('isDeptHeadRole') && isDeptHeadRole();
$sessionEmpIdNav = (int) ($_SESSION['employee_id'] ?? 0);
$headedDeptsNav = array_map('intval', $_SESSION['headed_department_ids'] ?? []);
$primaryHeadDept = $headedDeptsNav[0] ?? 0;

// Office Staff: never show company-wide HR menus
if ($isOfficeStaffNav) {
    $canEmp = false;
    $canAtt = false;
    $canRecruitment = false;
    $canPay = false;
    $canMasters = false;
    $canContractor = false;
    $canDepts = false;
    $allowedDeptIds = [];
}

$sidebarDepartments = [];
$connSb = getDBConnection();
$resSb = $connSb->query(
    "SELECT id, department_name, icon_class, icon_color
     FROM departments WHERE status = 1
     ORDER BY sort_order ASC, department_name ASC"
);
if ($resSb) {
    while ($r = $resSb->fetch_assoc()) {
        $did = (int) $r['id'];
        if ($allowedDeptIds !== null && $allowedDeptIds !== [] && !in_array($did, $allowedDeptIds, true)) {
            continue;
        }
        if ($allowedDeptIds === []) {
            continue;
        }
        $sidebarDepartments[] = $r;
    }
}
$connSb->close();

$sidebarDept = null;
if ($sidebarDeptId > 0) {
    foreach ($sidebarDepartments as $d) {
        if ((int) $d['id'] === $sidebarDeptId) {
            $sidebarDept = $d;
            break;
        }
    }
}

if (!function_exists('getMastersConfig')) {
    require_once __DIR__ . '/masters_config.php';
}
$mastersNav = getMastersConfig();

$openDepartments = ($sidebarDeptId > 0 || $sidebarMode === 'department' || $sidebarMode === 'employees' || $sidebarActive === 'modules');
$openMasters = ($sidebarMode === 'masters' || $sidebarActive === 'hub' || isset($mastersNav[$sidebarActive]));
$regType = (string) ($_GET['type'] ?? '');
$openContractor = ($sidebarMode === 'contractor' || strpos((string) $sidebarActive, 'contractor') === 0 || $regType === 'jobwork_govt' || $regType === 'jobwork_actual' || $regType === 'contractor_main');
$openAttendance = ($sidebarMode === 'attendance' || strpos((string) $sidebarActive, 'attendance') === 0);
$openRecruitment = ($sidebarMode === 'recruitment' || strpos((string) $sidebarActive, 'recruitment') === 0);
$openTraining = ($sidebarMode === 'training' || strpos((string) $sidebarActive, 'training') === 0 || in_array((string) $sidebarActive, ['training_induction', 'training_sales'], true));
$openEmployeesMenu = in_array((string) $sidebarActive, ['all_employees', 'visiting_card', 'exit_employee', 'join_employee'], true)
    || $sidebarMode === 'employees';
$openLeaveMenu = in_array((string) $sidebarActive, [
    'leave_request', 'leave_encashment', 'coff_history', 'coff_report', 'dl_report', 'lwp_report', 'leave_balance',
], true) || strpos((string) $sidebarActive, 'leave') === 0;
$openPayrollMenu = in_array((string) $sidebarActive, [
    'salary_register', 'joining_exit_report', 'dept_cost_summary', 'neft_sheet',
], true) || strpos((string) $sidebarActive, 'payroll') === 0;
$openAdminMenu = in_array((string) $sidebarActive, ['roles', 'staff_users', 'settings', 'dept_heads'], true);

if (!function_exists('getDashboardLogo')) {
    require_once __DIR__ . '/settings.php';
}
$sidebarLogoPath = function_exists('getLoginLogo') ? getLoginLogo() : getDashboardLogo();
if (function_exists('getDashboardLogo')) {
    $dashLogo = getDashboardLogo();
    // Prefer login logo (full brand mark); fall back to dashboard logo
    if (function_exists('isCustomLogo') && isCustomLogo($sidebarLogoPath) === false && isCustomLogo($dashLogo)) {
        $sidebarLogoPath = $dashLogo;
    }
}
$sidebarLogoSrc = $sidebarLogoPath;
if ($sidebarLogoSrc && strpos($sidebarLogoSrc, 'http') !== 0 && strpos($sidebarLogoSrc, '/') !== 0) {
    $sidebarLogoSrc = app_url($sidebarLogoSrc);
}
$sidebarCompanyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';
?>

<aside class="app-sidebar" id="appSidebar" aria-label="Sidebar navigation">
    <div class="sidebar-top">
        <a href="<?php echo app_url('dashboard.php'); ?>" class="sidebar-logo-link" title="<?php echo htmlspecialchars($sidebarCompanyName); ?>">
            <img src="<?php echo htmlspecialchars($sidebarLogoSrc); ?>"
                 alt="<?php echo htmlspecialchars($sidebarCompanyName); ?>"
                 class="sidebar-logo-img"
                 onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Hide / Show sidebar" aria-label="Toggle sidebar">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>

    <div class="sidebar-search-wrap">
        <label class="sidebar-search" for="sidebarMenuSearch">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search"
                   id="sidebarMenuSearch"
                   class="sidebar-search-input"
                   placeholder="Search menu…"
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" class="sidebar-search-clear" id="sidebarMenuSearchClear" title="Clear" aria-label="Clear search" hidden>
                <i class="fa-solid fa-xmark"></i>
            </button>
        </label>
        <div class="sidebar-search-empty" id="sidebarSearchEmpty" hidden>No menu items found</div>
    </div>

    <div class="sidebar-scroll" id="sidebarScroll">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>
            <nav class="sidebar-nav">
                <?php if ($isOfficeStaffNav): ?>
                <a href="<?php echo app_url('employee/dashboard.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'emp_home' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <?php if ($sessionEmpIdNav > 0): ?>
                <a href="<?php echo app_url('employees/view.php?id=' . $sessionEmpIdNav); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'my_profile' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>My Profile</span>
                </a>
                <?php endif; ?>
                <?php if ($canCirc): ?>
                <a href="<?php echo app_url('circulars/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'circulars' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    <span>Circulars</span>
                </a>
                <?php endif; ?>
                <?php if ($canPol): ?>
                <a href="<?php echo app_url('policies/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'policies' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-scroll"></i>
                    <span>Policies</span>
                </a>
                <?php endif; ?>
                <?php if ($canLeave): ?>
                <a href="<?php echo app_url('leave/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'leave_request' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-plane-departure"></i>
                    <span>My Leave</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo app_url('employee/attendance.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'my_attendance' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>My Attendance</span>
                </a>
                <a href="<?php echo app_url('employee/kpi.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'my_kpi' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>My KPI</span>
                </a>
                <a href="<?php echo app_url('employee/salary_slips.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'my_salary_slip' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>My Salary Slip</span>
                </a>
                <a href="<?php echo app_url('employee/change_password.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'change_password' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-key"></i>
                    <span>Change Password</span>
                </a>
                <?php else: ?>
                <a href="<?php echo app_url('dashboard.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <?php if ($sessionEmpIdNav > 0 && function_exists('isEmployee') && isEmployee()): ?>
                <a href="<?php echo app_url('employee/salary_slips.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'my_salary_slip' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>My Salary Slip</span>
                </a>
                <a href="<?php echo app_url('employee/change_password.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'change_password' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-key"></i>
                    <span>Change Password</span>
                </a>
                <?php endif; ?>
                <?php if (function_exists('isStaffUser') && isStaffUser()): ?>
                <a href="<?php echo app_url('hr/dashboard.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'hr_dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>HR Dashboard</span>
                </a>
                <a href="<?php echo app_url('hr/kpi.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'hr_kpi' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>KPI Reports</span>
                </a>
                <?php elseif (!$isDeptHeadNav && (canAccess('leave', 'edit', 0) || canAccess('payroll', 'view', 0))): ?>
                <a href="<?php echo app_url('hr/dashboard.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'hr_dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>Workspace</span>
                </a>
                <a href="<?php echo app_url('hr/kpi.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'hr_kpi' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>KPI Reports</span>
                </a>
                <?php endif; ?>
                <?php if ($canCirc): ?>
                <a href="<?php echo app_url('circulars/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'circulars' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-circle-plus"></i>
                    <span>Circulars</span>
                </a>
                <?php endif; ?>
                <?php if ($canPol): ?>
                <a href="<?php echo app_url('policies/index.php'); ?>"
                   class="sidebar-link <?php echo $sidebarActive === 'policies' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-scroll"></i>
                    <span>Policies</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <?php if (!$isOfficeStaffNav): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Modules</div>

            <?php if ($canRecruitment): ?>
            <div class="sidebar-accordion <?php echo $openRecruitment ? 'is-open' : ''; ?>" data-accordion="recruitment" data-default-open="<?php echo $openRecruitment ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openRecruitment ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openRecruitment ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-user-plus"></i><span>Recruitment</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('recruitment/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'recruitment' ? 'active' : ''; ?>"><i class="fa-solid fa-inbox"></i><span>Applications</span></a>
                    <a href="<?php echo app_url('recruitment/interviews.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'recruitment_interview' ? 'active' : ''; ?>"><i class="fa-solid fa-clipboard-user"></i><span>Interview Candidates</span></a>
                    <a href="<?php echo app_url('recruitment/qr.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'recruitment_qr' ? 'active' : ''; ?>"><i class="fa-solid fa-qrcode"></i><span>Apply QR Code</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canEmp): ?>
            <div class="sidebar-accordion <?php echo $openTraining ? 'is-open' : ''; ?>" data-accordion="training" data-default-open="<?php echo $openTraining ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openTraining ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openTraining ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-chalkboard-user"></i><span>Training</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('training/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'training' ? 'active' : ''; ?>"><i class="fa-solid fa-list"></i><span>Training List</span></a>
                    <a href="<?php echo app_url('training/index.php?type=general'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'training_induction' ? 'active' : ''; ?>"><i class="fa-solid fa-clipboard-list"></i><span>General Induction</span></a>
                    <a href="<?php echo app_url('training/index.php?type=sales'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'training_sales' ? 'active' : ''; ?>"><i class="fa-solid fa-handshake"></i><span>Sales Training</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canEmp): ?>
            <div class="sidebar-accordion <?php echo $openEmployeesMenu ? 'is-open' : ''; ?>" data-accordion="employees_menu" data-default-open="<?php echo $openEmployeesMenu ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openEmployeesMenu ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openEmployeesMenu ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-users"></i><span>Employees</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <?php if ($isDeptHeadNav && $primaryHeadDept > 0): ?>
                    <a href="<?php echo app_url('employees/index.php?department_id=' . $primaryHeadDept); ?>" class="sidebar-link sidebar-sublink <?php echo ($sidebarActive === 'join_employee' || $sidebarActive === 'all_employees') ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i><span>Department Employees</span></a>
                    <?php else: ?>
                    <a href="<?php echo app_url('employees/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'all_employees' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i><span>All Employees</span></a>
                    <a href="<?php echo app_url('employees/visiting_card.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'visiting_card' ? 'active' : ''; ?>"><i class="fa-solid fa-id-card"></i><span>Visiting Card</span></a>
                    <a href="<?php echo app_url('employees/exit_list.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'exit_employee' ? 'active' : ''; ?>"><i class="fa-solid fa-user-xmark"></i><span>Exit Employees</span></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canLeave): ?>
            <div class="sidebar-accordion <?php echo $openLeaveMenu ? 'is-open' : ''; ?>" data-accordion="leave_menu" data-default-open="<?php echo $openLeaveMenu ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openLeaveMenu ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openLeaveMenu ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-plane-departure"></i><span>Leave</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('leave/index.php' . ($isDeptHeadNav && $primaryHeadDept > 0 ? ('?department_id=' . $primaryHeadDept) : '')); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'leave_request' ? 'active' : ''; ?>"><i class="fa-solid fa-list-check"></i><span>Leave Requests</span></a>
                    <a href="<?php echo app_url('leave/encashment.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'leave_encashment' ? 'active' : ''; ?>"><i class="fa-solid fa-money-bill-wave"></i><span>Leave Encashment</span></a>
                    <a href="<?php echo app_url('leave/coff_history.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'coff_history' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>C-Off History</span></a>
                    <a href="<?php echo app_url('leave/coff_report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'coff_report' ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice"></i><span>C-Off Report</span></a>
                    <a href="<?php echo app_url('leave/dl_report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'dl_report' ? 'active' : ''; ?>"><i class="fa-solid fa-briefcase"></i><span>Duty Leave Report</span></a>
                    <a href="<?php echo app_url('leave/lwp_report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'lwp_report' ? 'active' : ''; ?>"><i class="fa-solid fa-user-slash"></i><span>LWP Report</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canAtt): ?>
            <div class="sidebar-accordion <?php echo $openAttendance ? 'is-open' : ''; ?>" data-accordion="attendance" data-default-open="<?php echo $openAttendance ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openAttendance ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openAttendance ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('attendance/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_list' ? 'active' : ''; ?>"><i class="fa-solid fa-list"></i><span>Attendance List</span></a>
                    <a href="<?php echo app_url('attendance/manual.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_manual' ? 'active' : ''; ?>"><i class="fa-solid fa-pen-to-square"></i><span>Manual Entry</span></a>
                    <a href="<?php echo app_url('attendance/import.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_import' ? 'active' : ''; ?>"><i class="fa-solid fa-file-import"></i><span>Import</span></a>
                    <a href="<?php echo app_url('attendance/report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_report' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple"></i><span>Report</span></a>
                    <a href="<?php echo app_url('attendance/late_report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_late' ? 'active' : ''; ?>"><i class="fa-solid fa-user-clock"></i><span>Late / Early</span></a>
                    <a href="<?php echo app_url('attendance/history.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_history' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>Import History</span></a>
                    <a href="<?php echo app_url('attendance/machines.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_machines' ? 'active' : ''; ?>"><i class="fa-solid fa-server"></i><span>Biometric Machines</span></a>
                    <a href="<?php echo app_url('attendance/machine_logs.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_machine_logs' ? 'active' : ''; ?>"><i class="fa-solid fa-fingerprint"></i><span>Machine Logs</span></a>
                    <a href="<?php echo app_url('attendance/machine_report.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'attendance_machine_report' ? 'active' : ''; ?>"><i class="fa-solid fa-table"></i><span>Machine Report</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canPay): ?>
            <div class="sidebar-accordion <?php echo $openPayrollMenu ? 'is-open' : ''; ?>" data-accordion="payroll_menu" data-default-open="<?php echo $openPayrollMenu ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openPayrollMenu ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openPayrollMenu ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-wallet"></i><span>Payroll</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('payroll/register.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'salary_register' ? 'active' : ''; ?>"><i class="fa-solid fa-table"></i><span>Salary Register</span></a>
                    <a href="<?php echo app_url('payroll/joining_exit.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'joining_exit_report' ? 'active' : ''; ?>"><i class="fa-solid fa-user-plus"></i><span>Joining / Exit</span></a>
                    <a href="<?php echo app_url('payroll/cost_summary.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'dept_cost_summary' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie"></i><span>Dept Cost Summary</span></a>
                    <a href="<?php echo app_url('payroll/neft.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'neft_sheet' ? 'active' : ''; ?>"><i class="fa-solid fa-building-columns"></i><span>NEFT Sheet</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canContractor): ?>
            <div class="sidebar-accordion <?php echo $openContractor ? 'is-open' : ''; ?>" data-accordion="contractor" data-default-open="<?php echo $openContractor ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openContractor ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openContractor ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-helmet-safety"></i><span>Contractor</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="<?php echo app_url('contractor/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_hub' ? 'active' : ''; ?>"><i class="fa-solid fa-border-all"></i><span>Hub</span></a>
                    <a href="<?php echo app_url('contractor/employees/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_employees' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i><span>Employees</span></a>
                    <a href="<?php echo app_url('contractor/employment/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_employment' ? 'active' : ''; ?>"><i class="fa-solid fa-file-contract"></i><span>Employment Details</span></a>
                    <a href="<?php echo app_url('contractor/products/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_products' ? 'active' : ''; ?>"><i class="fa-solid fa-box"></i><span>Product Master</span></a>
                    <a href="<?php echo app_url('contractor/grades/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_grades' ? 'active' : ''; ?>"><i class="fa-solid fa-layer-group"></i><span>Grade Master</span></a>
                    <a href="<?php echo app_url('contractor/operations/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'contractor_operations' ? 'active' : ''; ?>"><i class="fa-solid fa-gears"></i><span>Operations Rate</span></a>
                    <a href="<?php echo app_url('payroll/register.php?type=jobwork_govt'); ?>" class="sidebar-link sidebar-sublink <?php echo in_array($regType, ['jobwork_govt', 'jobwork_actual', 'contractor_main'], true) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar"></i><span>Jobwork Salary</span></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canDepts): ?>
            <div class="sidebar-accordion <?php echo $openDepartments ? 'is-open' : ''; ?>" data-accordion="departments" data-default-open="<?php echo $openDepartments ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openDepartments ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openDepartments ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-building"></i><span>Departments</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu sidebar-submenu-scroll">
                    <a href="<?php echo app_url('dashboard.php#department-workspace'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'dashboard' ? 'active' : ''; ?>"><i class="fa-solid fa-border-all"></i><span>All Department Boxes</span></a>
                    <?php foreach ($sidebarDepartments as $d): ?>
                    <a href="<?php echo app_url('department.php?id=' . (int) $d['id']); ?>" class="sidebar-link sidebar-sublink <?php echo ((int) $d['id'] === $sidebarDeptId && $sidebarActive === 'modules') ? 'active' : ''; ?>" title="<?php echo htmlspecialchars($d['department_name']); ?>">
                        <span class="sidebar-dot" style="background: <?php echo htmlspecialchars($d['icon_color']); ?>;"></span>
                        <span><?php echo htmlspecialchars($d['department_name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canMasters): ?>
            <div class="sidebar-accordion <?php echo $openMasters ? 'is-open' : ''; ?>" data-accordion="masters" data-default-open="<?php echo $openMasters ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openMasters ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openMasters ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-database"></i><span>Masters</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu sidebar-submenu-scroll">
                    <a href="<?php echo app_url('masters/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'hub' ? 'active' : ''; ?>"><i class="fa-solid fa-cubes"></i><span>Masters Hub</span></a>
                    <a href="<?php echo app_url('masters/company_history/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'company_history' ? 'active' : ''; ?>"><i class="fa-solid fa-book-open"></i><span>Company History</span></a>
                    <a href="<?php echo app_url('masters/company_vision/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'company_vision' ? 'active' : ''; ?>"><i class="fa-solid fa-bullseye"></i><span>Vision / Mission</span></a>
                    <?php foreach ($mastersNav as $m): ?>
                    <a href="<?php echo app_url('masters/' . $m['folder'] . '/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === $m['key'] ? 'active' : ''; ?>">
                        <i class="fa-solid <?php echo htmlspecialchars($m['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($m['title']); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($sidebarDept && $canDepts): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Current Department</div>
                <div class="sidebar-dept-card">
                    <span class="sidebar-dept-icon" style="background: <?php echo htmlspecialchars($sidebarDept['icon_color']); ?>;">
                        <i class="fa-solid <?php echo htmlspecialchars($sidebarDept['icon_class']); ?>"></i>
                    </span>
                    <div>
                        <strong><?php echo htmlspecialchars($sidebarDept['department_name']); ?></strong>
                        <small>Module boxes</small>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <a href="<?php echo app_url('department.php?id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'modules' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-puzzle-piece"></i>
                        <span>Module Boxes</span>
                    </a>
                    <a href="<?php echo app_url('employees/index.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'join_employee' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Join Employee</span>
                    </a>
                    <a href="<?php echo app_url('employees/visiting_card.php?department_id=' . $sidebarDeptId . '&show=1'); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'visiting_card' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Visiting Card</span>
                    </a>
                    <a href="<?php echo app_url('employees/exit_list.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'exit_employee' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-xmark"></i>
                        <span>Exit Employee List</span>
                    </a>
                    <a href="<?php echo app_url('attendance/manual.php?department_id=' . $sidebarDeptId . '&show=1'); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'attendance_manual' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Manual Attendance</span>
                    </a>
                    <a href="<?php echo app_url('attendance/report.php?show=1&department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'attendance_report' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-check"></i>
                        <span>Attendance Report</span>
                    </a>
                    <a href="<?php echo app_url('payroll/diary.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'diary' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Salary Structure</span>
                    </a>
                    <a href="<?php echo app_url('payroll/jobwork.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'jobwork' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-gears"></i>
                        <span>Jobwork Entry</span>
                    </a>
                    <a href="<?php echo app_url('payroll/generate.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'generate' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-indian-rupee-sign"></i>
                        <span>Generate Salary</span>
                    </a>
                    <a href="<?php echo app_url('payroll/register.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'salary_register' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-table"></i>
                        <span>Salary Register</span>
                    </a>
                    <a href="<?php echo app_url('payroll/joining_exit.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'joining_exit_report' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-right-left"></i>
                        <span>Joining / Exit Report</span>
                    </a>
                    <a href="<?php echo app_url('payroll/cost_summary.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'dept_cost_summary' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dept Cost Summary</span>
                    </a>
                    <a href="<?php echo app_url('payroll/neft.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'neft_sheet' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-building-columns"></i>
                        <span>NEFT Sheet</span>
                    </a>
                    <a href="<?php echo app_url('leave/index.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'leave_request' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Leave Request</span>
                    </a>
                    <a href="<?php echo app_url('leave/balance.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'leave_balance' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-scale-balanced"></i>
                        <span>Leave Balance</span>
                    </a>
                    <a href="<?php echo app_url('leave/coff_history.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'coff_history' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>C-Off History</span>
                    </a>
                    <a href="<?php echo app_url('leave/coff_report.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'coff_report' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>C-Off Report</span>
                    </a>
                    <a href="<?php echo app_url('leave/dl_report.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'dl_report' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>Duty Leave Report</span>
                    </a>
                    <a href="<?php echo app_url('leave/lwp_report.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'lwp_report' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-xmark"></i>
                        <span>LWP Report</span>
                    </a>
                    <a href="<?php echo app_url('masters/holidays/index.php?department_id=' . $sidebarDeptId); ?>"
                       class="sidebar-link <?php echo $sidebarActive === 'holidays' ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Holiday Master</span>
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <?php if (!$isOfficeStaffNav && ((function_exists('isAdmin') && isAdmin()) || (function_exists('canManageDepartmentHeads') && canManageDepartmentHeads()))): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Admin</div>
            <div class="sidebar-accordion <?php echo $openAdminMenu ? 'is-open' : ''; ?>" data-accordion="admin_menu" data-default-open="<?php echo $openAdminMenu ? '1' : '0'; ?>">
                <button type="button" class="sidebar-acc-btn <?php echo $openAdminMenu ? 'is-active' : ''; ?>" aria-expanded="<?php echo $openAdminMenu ? 'true' : 'false'; ?>">
                    <span class="sidebar-acc-left"><i class="fa-solid fa-gears"></i><span>System Setup</span></span>
                    <i class="fa-solid fa-chevron-down sidebar-acc-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <a href="<?php echo app_url('roles/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'roles' ? 'active' : ''; ?>"><i class="fa-solid fa-user-shield"></i><span>Roles &amp; Access</span></a>
                    <a href="<?php echo app_url('users/index.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'staff_users' ? 'active' : ''; ?>"><i class="fa-solid fa-user-gear"></i><span>Staff Users</span></a>
                    <a href="<?php echo app_url('company_settings.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'settings' ? 'active' : ''; ?>"><i class="fa-solid fa-building"></i><span>Company Settings</span></a>
                    <?php elseif (function_exists('canManageDepartmentHeads') && canManageDepartmentHeads()): ?>
                    <a href="<?php echo app_url('roles/department_heads.php'); ?>" class="sidebar-link sidebar-sublink <?php echo $sidebarActive === 'dept_heads' ? 'active' : ''; ?>"><i class="fa-solid fa-user-tie"></i><span>Department Heads</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="sidebar-footer-mini">
        <?php
        $footerHome = (function_exists('isOfficeStaffRole') && isOfficeStaffRole())
            ? app_url('employee/dashboard.php')
            : app_url('dashboard.php');
        ?>
        <a href="<?php echo $footerHome; ?>" class="sidebar-link">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
    </div>
</aside>

<button type="button" class="sidebar-fab" id="sidebarFab" title="Show sidebar" aria-label="Show sidebar">
    <i class="fa-solid fa-bars"></i>
</button>
