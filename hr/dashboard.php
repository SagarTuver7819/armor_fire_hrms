<?php
/**
 * HR Dashboard — employee-portal style structure
 * Greeting · KPI cards · Monthly join/left · Panels · Queues · Matrix
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireStaff();

if (function_exists('isOfficeStaffRole') && isOfficeStaffRole()) {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$pageTitle = 'HR Dashboard';
$useSidebar = true;
$sidebarActive = 'hr_dashboard';

ensureEmployeesTable();
ensureMasterTables();
ensureLeaveTables();

$conn = getDBConnection();
ensureAttendanceTables($conn);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthLabel = date('M Y');
$year = (int) date('Y');
$month = (int) date('n');
$panelLimit = 10;
$expand = strtolower(trim((string) ($_GET['expand'] ?? '')));

$hour = (int) date('G');
if ($hour < 12) {
    $greet = 'Good Morning';
} elseif ($hour < 17) {
    $greet = 'Good Afternoon';
} else {
    $greet = 'Good Evening';
}
$userName = trim((string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'HR'));
$roleLabel = function_exists('getUserRoleLabel') ? getUserRoleLabel() : 'HR';

// Company logo for greeting (Admin / HR dashboard brand mark)
if (!function_exists('getLoginLogo')) {
    require_once __DIR__ . '/../includes/settings.php';
}
$hrBrandLogo = function_exists('getLoginLogo') ? getLoginLogo() : '';
if ($hrBrandLogo === '' || (function_exists('isCustomLogo') && !isCustomLogo($hrBrandLogo))) {
    $hrBrandLogo = function_exists('getDashboardLogo') ? getDashboardLogo() : '';
}
$hrBrandLogoSrc = $hrBrandLogo;
if ($hrBrandLogoSrc && strpos($hrBrandLogoSrc, 'http') !== 0 && strpos($hrBrandLogoSrc, '/') !== 0) {
    $hrBrandLogoSrc = app_url($hrBrandLogoSrc);
}
if ($hrBrandLogoSrc === '') {
    $hrBrandLogoSrc = app_url('assets/images/logo-placeholder.svg');
}

// ── KPI counts ─────────────────────────────────────────
$kpi = [
    'active' => 0,
    'joiners' => 0,
    'exits' => 0,
    'pending_leave' => 0,
    'present_today' => 0,
    'absent_today' => 0,
    'leave_today' => 0,
    'kpi_submitted_today' => 0,
    'voice_open' => 0,
];

$r = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE status = 1");
$kpi['active'] = $r ? (int) $r->fetch_assoc()['c'] : 0;

$st = $conn->prepare(
    "SELECT COUNT(*) AS c FROM employees
     WHERE status = 1 AND date_of_joining BETWEEN ? AND ?"
);
$st->bind_param('ss', $monthStart, $monthEnd);
$st->execute();
$kpi['joiners'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$st = $conn->prepare(
    "SELECT COUNT(*) AS c FROM employees
     WHERE date_of_exit BETWEEN ? AND ?"
);
$st->bind_param('ss', $monthStart, $monthEnd);
$st->execute();
$kpi['exits'] = (int) ($st->get_result()->fetch_assoc()['c'] ?? 0);
$st->close();

$r = $conn->query("SELECT COUNT(*) AS c FROM leave_requests WHERE status = 'Pending'");
$kpi['pending_leave'] = $r ? (int) $r->fetch_assoc()['c'] : 0;

$st = $conn->prepare(
    "SELECT
        SUM(CASE WHEN day_status IN ('Present','Half Day') THEN 1 ELSE 0 END) AS present_c,
        SUM(CASE WHEN day_status = 'Absent' THEN 1 ELSE 0 END) AS absent_c,
        SUM(CASE WHEN day_status = 'Leave' THEN 1 ELSE 0 END) AS leave_c
     FROM attendance_day_status ads
     INNER JOIN employees e ON e.id = ads.employee_id AND e.status = 1
     WHERE ads.attendance_date = ?"
);
$st->bind_param('s', $today);
$st->execute();
$attToday = $st->get_result()->fetch_assoc() ?: [];
$st->close();
$kpi['present_today'] = (int) ($attToday['present_c'] ?? 0);
$kpi['absent_today'] = (int) ($attToday['absent_c'] ?? 0);
$kpi['leave_today'] = (int) ($attToday['leave_c'] ?? 0);

require_once __DIR__ . '/../includes/kpi_helper.php';
ensureKpiTables($conn);
$kpi['kpi_submitted_today'] = kpiCountSubmittedOnDate($today, $conn);

require_once __DIR__ . '/../includes/employee_voice_helper.php';
ensureEmployeeVoiceTables($conn);
$rEv = $conn->query(
    "SELECT COUNT(*) AS c FROM ev_ticket
     WHERE is_deleted = 0 AND status NOT IN ('Closed','Rejected','Withdrawn','Verified','Resolved')"
);
$kpi['voice_open'] = $rEv ? (int) $rEv->fetch_assoc()['c'] : 0;

$presencePct = $kpi['active'] > 0
    ? (int) round(($kpi['present_today'] / $kpi['active']) * 100)
    : 0;

// ── Monthly joiners / leavers ──────────────────────────
$monthlyJoinEmployees = [];
$monthlyLeftEmployees = [];
$stJoin = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, d.department_name, e.date_of_joining
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_joining IS NOT NULL
       AND e.date_of_joining != ''
       AND e.date_of_joining != '0000-00-00'
       AND e.date_of_joining BETWEEN ? AND ?
     ORDER BY e.date_of_joining ASC, e.employee_name ASC
     LIMIT 60"
);
$stJoin->bind_param('ss', $monthStart, $monthEnd);
$stJoin->execute();
$resJoin = $stJoin->get_result();
while ($row = $resJoin->fetch_assoc()) {
    $monthlyJoinEmployees[] = $row;
}
$stJoin->close();

$stLeft = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, d.department_name, e.date_of_exit
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.date_of_exit IS NOT NULL
       AND e.date_of_exit != ''
       AND e.date_of_exit != '0000-00-00'
       AND e.date_of_exit BETWEEN ? AND ?
     ORDER BY e.date_of_exit ASC, e.employee_name ASC
     LIMIT 60"
);
$stLeft->bind_param('ss', $monthStart, $monthEnd);
$stLeft->execute();
$resLeft = $stLeft->get_result();
while ($row = $resLeft->fetch_assoc()) {
    $monthlyLeftEmployees[] = $row;
}
$stLeft->close();

// ── Policies / Circulars ───────────────────────────────
$recentPolicies = [];
$recentPoliciesTotal = 0;
$recentCirculars = [];
$recentCircularsTotal = 0;
require_once __DIR__ . '/../includes/policy_helper.php';
require_once __DIR__ . '/../includes/circular_helper.php';
ensurePolicyTables();
ensureCircularTables();
$allPol = fetchPolicies(0, 0);
$recentPoliciesTotal = count($allPol);
$recentPolicies = array_slice($allPol, 0, $expand === 'policies' ? 50 : $panelLimit);
$allCirc = fetchCirculars(0, 0);
$recentCircularsTotal = count($allCirc);
$recentCirculars = array_slice($allCirc, 0, $expand === 'circulars' ? 50 : $panelLimit);

// ── Work anniversaries (next 30 days) ───────────────────
$workAnniversaries = [];
$workAnniversariesTotal = 0;
$stAnn = $conn->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_joining, e.designation, e.photo_file,
            d.department_name,
            t.next_on,
            DATEDIFF(t.next_on, CURDATE()) AS in_days,
            TIMESTAMPDIFF(YEAR, e.date_of_joining, t.next_on) AS years
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     INNER JOIN (
         SELECT id,
                DATE_ADD(
                    date_of_joining,
                    INTERVAL (
                        TIMESTAMPDIFF(YEAR, date_of_joining, CURDATE())
                        + IF(
                            DATE_ADD(
                                date_of_joining,
                                INTERVAL TIMESTAMPDIFF(YEAR, date_of_joining, CURDATE()) YEAR
                            ) < CURDATE(),
                            1,
                            0
                        )
                    ) YEAR
                ) AS next_on
         FROM employees
         WHERE status = 1
           AND date_of_joining IS NOT NULL
           AND date_of_joining != ''
           AND date_of_joining != '0000-00-00'
           AND date_of_joining < CURDATE()
     ) t ON t.id = e.id
     WHERE e.status = 1
       AND t.next_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND TIMESTAMPDIFF(YEAR, e.date_of_joining, t.next_on) >= 1
     ORDER BY t.next_on ASC, e.employee_name ASC"
);
$annCandidates = [];
if ($stAnn) {
    while ($row = $stAnn->fetch_assoc()) {
        $row['in_days'] = (int) ($row['in_days'] ?? 0);
        $row['years'] = (int) ($row['years'] ?? 0);
        $annCandidates[] = $row;
    }
}
$workAnniversariesTotal = count($annCandidates);
$workAnniversaries = array_slice($annCandidates, 0, $expand === 'anniv' ? 50 : $panelLimit);

// ── Birthdays (next 45 days) ───────────────────────────
$upcomingBirthdays = [];
$upcomingBirthdaysTotal = 0;
$stBday = $conn->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_birth, e.designation, e.photo_file,
            d.department_name,
            DATE_FORMAT(e.date_of_birth, '%m-%d') AS md
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_birth IS NOT NULL
       AND e.date_of_birth != ''
       AND e.date_of_birth != '0000-00-00'
     ORDER BY e.employee_name ASC
     LIMIT 500"
);
$bdayCandidates = [];
$cutoff = new DateTime('today');
$bdayLimitDt = (clone $cutoff)->modify('+45 days');
if ($stBday) {
    while ($row = $stBday->fetch_assoc()) {
        $md = (string) ($row['md'] ?? '');
        if (!preg_match('/^\d{2}-\d{2}$/', $md)) {
            continue;
        }
        $y = (int) $cutoff->format('Y');
        $candidate = DateTime::createFromFormat('Y-m-d', $y . '-' . $md);
        if (!$candidate) {
            continue;
        }
        if ($candidate < $cutoff) {
            $candidate->modify('+1 year');
        }
        if ($candidate > $bdayLimitDt) {
            continue;
        }
        $row['next_on'] = $candidate->format('Y-m-d');
        $row['in_days'] = (int) $cutoff->diff($candidate)->days;
        $bdayCandidates[] = $row;
    }
}
usort($bdayCandidates, static function ($a, $b) {
    return [$a['in_days'], $a['employee_name']] <=> [$b['in_days'], $b['employee_name']];
});
$upcomingBirthdaysTotal = count($bdayCandidates);
$upcomingBirthdays = array_slice($bdayCandidates, 0, $expand === 'bday' ? 50 : $panelLimit);

// ── Pending leaves ─────────────────────────────────────
$pendingLeaves = [];
$res = $conn->query(
    "SELECT lr.id, lr.from_date, lr.to_date, lr.days, lr.leave_half, lr.reason, lr.created_at,
            e.employee_name, e.employee_code, e.id AS employee_id,
            d.department_name, lt.code, lt.leave_type
     FROM leave_requests lr
     INNER JOIN employees e ON e.id = lr.employee_id
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
     WHERE lr.status = 'Pending'
     ORDER BY lr.created_at DESC
     LIMIT 12"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pendingLeaves[] = $row;
    }
}

// ── Upcoming holidays ──────────────────────────────────
$upcomingHolidays = [];
$to30 = date('Y-m-d', strtotime('+30 days'));
$st = $conn->prepare(
    "SELECT h.id, h.title, h.holiday_date, h.holiday_to_date, h.is_paid, h.department_id, d.department_name
     FROM holidays h
     LEFT JOIN departments d ON d.id = h.department_id
     WHERE h.status = 1 AND h.holiday_type = 'Holiday'
       AND h.holiday_date IS NOT NULL
       AND h.holiday_date <= ?
       AND COALESCE(NULLIF(h.holiday_to_date, '0000-00-00'), h.holiday_date) >= ?
     ORDER BY h.holiday_date ASC
     LIMIT 12"
);
$st->bind_param('ss', $to30, $today);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $upcomingHolidays[] = $row;
}
$st->close();

// ── Voice recent open tickets ──────────────────────────
$voiceTickets = [];
$resVoice = $conn->query(
    "SELECT t.id, t.ticket_no, t.module_type, t.subject, t.status, t.priority, t.submitted_at,
            e.employee_name, e.employee_code
     FROM ev_ticket t
     LEFT JOIN employees e ON e.id = t.employee_id
     WHERE t.is_deleted = 0
       AND t.status NOT IN ('Closed','Rejected','Withdrawn','Verified','Resolved')
     ORDER BY
        CASE WHEN t.priority = 'Critical' THEN 0 WHEN t.priority = 'High' THEN 1 ELSE 2 END,
        t.submitted_at DESC
     LIMIT 10"
);
if ($resVoice) {
    while ($row = $resVoice->fetch_assoc()) {
        $voiceTickets[] = $row;
    }
}

$conn->close();

$employeeMatrix = fetchEmployeeMatrixHeads();
$canManageHeads = canManageDepartmentHeads();
$evTypes = evModuleTypes();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="form-page-card" style="margin-bottom:14px;">
        <div class="form-page-header" style="margin-bottom:0;">
            <div class="master-list-title" style="width:100%;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div style="display:flex;align-items:center;gap:14px;min-width:220px;flex:1;">
                    <div class="emp-avatar emp-dash-avatar hr-dash-brand-logo" aria-hidden="true">
                        <img src="<?php echo htmlspecialchars($hrBrandLogoSrc); ?>"
                             alt="<?php echo htmlspecialchars(function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire'); ?>"
                             onerror="this.src='<?php echo app_url('assets/images/logo-placeholder.svg'); ?>'">
                    </div>
                    <div>
                        <h1 class="emp-dash-greet">
                            <?php echo htmlspecialchars($greet); ?>,
                            <span class="emp-dash-greet-name"><?php echo htmlspecialchars($userName); ?></span>
                        </h1>
                        <p>
                            HR Workspace
                            · <strong class="emp-dash-desig"><?php echo htmlspecialchars($roleLabel); ?></strong>
                            · <?php echo htmlspecialchars(date('l, d F Y')); ?>
                        </p>
                    </div>
                </div>
                <div class="emp-dash-att-strip">
                    <a class="emp-dash-att-chip is-ok" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-user-check"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Present Today</div>
                            <div class="emp-dash-att-value"><?php echo number_format($kpi['present_today']); ?> <span class="emp-dash-att-sep">·</span> <?php echo $presencePct; ?>%</div>
                        </div>
                    </a>
                    <a class="emp-dash-att-chip is-late" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-user-xmark"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Absent / Leave</div>
                            <div class="emp-dash-att-value"><?php echo number_format($kpi['absent_today']); ?> Abs <span class="emp-dash-att-sep">·</span> <?php echo number_format($kpi['leave_today']); ?> Lv</div>
                        </div>
                    </a>
                    <a class="emp-dash-att-chip is-punch" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-inbox"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Leave Queue</div>
                            <div class="emp-dash-att-value"><?php echo number_format($kpi['pending_leave']); ?> pending</div>
                        </div>
                    </a>
                    <a class="emp-dash-att-chip is-shift" href="<?php echo app_url('employee_voice/index.php'); ?>">
                        <div class="emp-dash-att-ico"><i class="fa-solid fa-comments"></i></div>
                        <div>
                            <div class="emp-dash-att-label">Employee Voice</div>
                            <div class="emp-dash-att-value"><?php echo number_format($kpi['voice_open']); ?> open</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="hr-emp-quickbar">
        <a class="btn-primary" href="<?php echo app_url('dashboard.php#department-workspace'); ?>"><i class="fa-solid fa-user-plus"></i> Add Employee</a>
        <a class="btn-ghost" href="<?php echo app_url('attendance/manual.php'); ?>"><i class="fa-solid fa-pen-to-square"></i> Manual Attendance</a>
        <a class="btn-ghost" href="<?php echo app_url('leave/index.php'); ?>"><i class="fa-solid fa-scale-balanced"></i> Leave Desk</a>
        <a class="btn-ghost" href="<?php echo app_url('hr/kpi.php'); ?>"><i class="fa-solid fa-clipboard-list"></i> KPI Reports</a>
        <a class="btn-ghost" href="<?php echo app_url('circulars/edit.php'); ?>"><i class="fa-solid fa-file-circle-plus"></i> Add Circular</a>
        <a class="btn-ghost" href="<?php echo app_url('policies/edit.php'); ?>"><i class="fa-solid fa-scroll"></i> Add Policy</a>
        <a class="btn-ghost" href="<?php echo app_url('employee_voice/index.php'); ?>"><i class="fa-solid fa-comments"></i> Employee Voice</a>
    </div>

    <div class="dash-hero-stats emp-dash-kpi emp-dash-kpi-2">
        <div class="form-page-card emp-leave-bal-card is-approved">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">People Snapshot</div>
                    <div class="emp-leave-bal-sub">Active headcount · this month movement</div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('employees/index.php'); ?>" title="All Employees">
                    <i class="fa-solid fa-users"></i>
                </a>
            </div>
            <div class="emp-leave-bal-grid has-dl">
                <a class="emp-leave-chip is-pl" href="<?php echo app_url('employees/index.php'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Active</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['active']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-sl" href="<?php echo app_url('employees/index.php'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Join</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['joiners']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-coff" href="<?php echo app_url('employees/exit_list.php'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Exit</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['exits']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-dl" href="<?php echo app_url('hr/kpi.php'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>KPI</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['kpi_submitted_today']); ?></strong>
                    </span>
                </a>
            </div>
        </div>

        <div class="form-page-card emp-leave-bal-card">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Today Ops</div>
                    <div class="emp-leave-bal-sub"><?php echo htmlspecialchars(formatDateDisplay($today)); ?> · attendance &amp; queues
                        <?php if ($kpi['pending_leave'] > 0): ?>
                            <span class="emp-leave-pending-pill"><?php echo (int) $kpi['pending_leave']; ?> leave pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>" title="Attendance">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
            <div class="emp-leave-bal-grid has-dl">
                <a class="emp-leave-chip is-pl" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Present</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['present_today']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-sl" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Absent</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['absent_today']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-coff" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Leave Q</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['pending_leave']); ?></strong>
                    </span>
                </a>
                <a class="emp-leave-chip is-dl" href="<?php echo app_url('employee_voice/index.php'); ?>">
                    <span class="emp-leave-chip-line">
                        <b>Voice</b><span class="emp-leave-chip-dash">-</span><strong><?php echo number_format($kpi['voice_open']); ?></strong>
                    </span>
                </a>
            </div>
        </div>
    </div>

    <div class="dash-hero-stats emp-dash-kpi emp-dash-kpi-2">
        <div class="form-page-card emp-leave-bal-card is-join">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Monthly Join Employee</div>
                    <div class="emp-leave-bal-sub"><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyJoinEmployees); ?> joined</div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('employees/index.php'); ?>" title="Employees">
                    <i class="fa-solid fa-user-plus"></i>
                </a>
            </div>
            <div class="emp-move-list">
                <?php if (!$monthlyJoinEmployees): ?>
                    <div class="emp-move-empty">No employees joined this month.</div>
                <?php else: ?>
                    <div class="emp-move-head">
                        <span>Employee Code</span>
                        <span>Name</span>
                        <span>Date of Joining</span>
                        <span>Department</span>
                    </div>
                    <?php foreach ($monthlyJoinEmployees as $je):
                        $joinDateRaw = trim((string) ($je['date_of_joining'] ?? ''));
                        $joinDateDisp = ($joinDateRaw !== '' && $joinDateRaw !== '0000-00-00')
                            ? formatDateDisplay($joinDateRaw) : '—';
                    ?>
                        <a class="emp-move-chip is-join" href="<?php echo app_url('employees/view.php?id=' . (int) $je['id']); ?>">
                            <b class="emp-move-code"><?php echo htmlspecialchars((string) ($je['employee_code'] ?? '—')); ?></b>
                            <span class="emp-move-name"><?php echo htmlspecialchars((string) ($je['employee_name'] ?? '—')); ?></span>
                            <small class="emp-move-date"><?php echo htmlspecialchars($joinDateDisp); ?></small>
                            <em class="emp-move-dept"><?php echo htmlspecialchars(trim((string) ($je['department_name'] ?? '')) !== '' ? $je['department_name'] : '—'); ?></em>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-page-card emp-leave-bal-card is-left">
            <div class="emp-leave-bal-top">
                <div>
                    <div class="emp-leave-bal-title">Monthly Left Employee</div>
                    <div class="emp-leave-bal-sub"><?php echo htmlspecialchars($monthLabel); ?> · <?php echo count($monthlyLeftEmployees); ?> left</div>
                </div>
                <a class="emp-leave-bal-link" href="<?php echo app_url('employees/exit_list.php'); ?>" title="Exit list">
                    <i class="fa-solid fa-user-minus"></i>
                </a>
            </div>
            <div class="emp-move-list">
                <?php if (!$monthlyLeftEmployees): ?>
                    <div class="emp-move-empty">No employees left this month.</div>
                <?php else: ?>
                    <div class="emp-move-head">
                        <span>Employee Code</span>
                        <span>Name</span>
                        <span>Date of Left</span>
                        <span>Department</span>
                    </div>
                    <?php foreach ($monthlyLeftEmployees as $le):
                        $leftDateRaw = trim((string) ($le['date_of_exit'] ?? ''));
                        $leftDateDisp = ($leftDateRaw !== '' && $leftDateRaw !== '0000-00-00')
                            ? formatDateDisplay($leftDateRaw) : '—';
                    ?>
                        <a class="emp-move-chip is-left" href="<?php echo app_url('employees/view.php?id=' . (int) $le['id']); ?>">
                            <b class="emp-move-code"><?php echo htmlspecialchars((string) ($le['employee_code'] ?? '—')); ?></b>
                            <span class="emp-move-name"><?php echo htmlspecialchars((string) ($le['employee_name'] ?? '—')); ?></span>
                            <small class="emp-move-date"><?php echo htmlspecialchars($leftDateDisp); ?></small>
                            <em class="emp-move-dept"><?php echo htmlspecialchars(trim((string) ($le['department_name'] ?? '')) !== '' ? $le['department_name'] : '—'); ?></em>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="emp-dash-panels">
        <section class="form-page-card emp-dash-panel is-policies">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-scroll" style="color:#7C3AED;"></i> Recent Policies</h3>
                    <p><?php echo (int) $recentPoliciesTotal; ?> total</p>
                </div>
                <a class="btn-ghost emp-dash-more" href="<?php echo app_url('policies/index.php'); ?>">View all</a>
            </div>
            <div class="emp-dash-panel-body">
                <?php if (!$recentPolicies): ?>
                    <div class="emp-dash-empty">No policies yet.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($recentPolicies as $p): ?>
                            <li>
                                <a class="emp-dash-row" href="<?php echo app_url('policies/view.php?id=' . (int) $p['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($p['policy_date'] ?? ($p['added_date'] ?? ''))); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars((string) ($p['title'] ?? 'Policy')); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-circulars">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-file-circle-plus" style="color:#1D4ED8;"></i> Recent Circulars</h3>
                    <p><?php echo (int) $recentCircularsTotal; ?> total</p>
                </div>
                <a class="btn-ghost emp-dash-more" href="<?php echo app_url('circulars/index.php'); ?>">View all</a>
            </div>
            <div class="emp-dash-panel-body">
                <?php if (!$recentCirculars): ?>
                    <div class="emp-dash-empty">No circulars yet.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($recentCirculars as $c): ?>
                            <li>
                                <a class="emp-dash-row" href="<?php echo app_url('circulars/view.php?id=' . (int) $c['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($c['circular_date'] ?? ($c['added_date'] ?? ''))); ?></em>
                                    <strong class="emp-dash-detail"><?php echo htmlspecialchars((string) ($c['title'] ?? 'Circular')); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-anniv">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-award" style="color:#B45309;"></i> Work Anniversaries</h3>
                    <p>Join date · next 30 days · <?php echo (int) $workAnniversariesTotal; ?></p>
                </div>
                <?php if ($workAnniversariesTotal > $panelLimit && $expand !== 'anniv'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('hr/dashboard.php?expand=anniv#panel-anniv'); ?>">More</a>
                <?php elseif ($expand === 'anniv'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('hr/dashboard.php#panel-anniv'); ?>">Less</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-anniv">
                <?php if (!$workAnniversaries): ?>
                    <div class="emp-dash-empty">No upcoming work anniversaries.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($workAnniversaries as $a):
                            $when = ((int) ($a['in_days'] ?? 0) === 0)
                                ? 'Today'
                                : (((int) $a['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $a['in_days'] . ' days'));
                            $dn = trim((string) ($a['department_name'] ?? ''));
                            ?>
                            <li>
                                <a class="emp-dash-row emp-dash-pan" href="<?php echo app_url('employees/view.php?id=' . (int) $a['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($a['next_on'] ?? '')); ?></em>
                                    <div class="emp-dash-pan-body">
                                        <strong class="emp-dash-name"><?php echo htmlspecialchars((string) ($a['employee_name'] ?? '—')); ?></strong>
                                        <span class="emp-dash-meta">
                                            <?php echo htmlspecialchars((int) ($a['years'] ?? 0) . ' yr · ' . $when); ?>
                                            <?php if ($dn !== ''): ?> · <?php echo htmlspecialchars($dn); ?><?php endif; ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-bday">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-cake-candles" style="color:#DB2777;"></i> Upcoming Birthdays</h3>
                    <p>Next 45 days · <?php echo (int) $upcomingBirthdaysTotal; ?></p>
                </div>
                <?php if ($upcomingBirthdaysTotal > $panelLimit && $expand !== 'bday'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('hr/dashboard.php?expand=bday#panel-bday'); ?>">More</a>
                <?php elseif ($expand === 'bday'): ?>
                    <a class="btn-ghost emp-dash-more" href="<?php echo app_url('hr/dashboard.php#panel-bday'); ?>">Less</a>
                <?php endif; ?>
            </div>
            <div class="emp-dash-panel-body" id="panel-bday">
                <?php if (!$upcomingBirthdays): ?>
                    <div class="emp-dash-empty">No upcoming birthdays.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($upcomingBirthdays as $b):
                            $when = ((int) ($b['in_days'] ?? 0) === 0)
                                ? 'Today'
                                : (((int) $b['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $b['in_days'] . ' days'));
                            $dn = trim((string) ($b['department_name'] ?? ''));
                            ?>
                            <li>
                                <a class="emp-dash-row emp-dash-pan" href="<?php echo app_url('employees/view.php?id=' . (int) $b['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($b['next_on'] ?? '')); ?></em>
                                    <div class="emp-dash-pan-body">
                                        <strong class="emp-dash-name"><?php echo htmlspecialchars((string) ($b['employee_name'] ?? '—')); ?></strong>
                                        <span class="emp-dash-meta">
                                            <?php echo htmlspecialchars($when); ?>
                                            <?php if ($dn !== ''): ?> · <?php echo htmlspecialchars($dn); ?><?php endif; ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="emp-dash-panels hr-ops-panels">
        <section class="form-page-card emp-dash-panel is-leave-q">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-inbox" style="color:#d97706;"></i> Leave Queue</h3>
                    <p><?php echo count($pendingLeaves); ?> pending</p>
                </div>
                <a class="btn-ghost emp-dash-more" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">View all</a>
            </div>
            <div class="emp-dash-panel-body">
                <?php if (!$pendingLeaves): ?>
                    <div class="emp-dash-empty">No pending leave requests.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($pendingLeaves as $lr): ?>
                            <li>
                                <a class="emp-dash-row emp-dash-pan" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($lr['from_date'] ?? '')); ?></em>
                                    <div class="emp-dash-pan-body">
                                        <strong class="emp-dash-name"><?php echo htmlspecialchars((string) ($lr['employee_name'] ?? '—')); ?></strong>
                                        <span class="emp-dash-meta">
                                            <?php echo htmlspecialchars(trim(($lr['code'] ? $lr['code'] . ' · ' : '') . ($lr['leave_type'] ?: 'Leave'))); ?>
                                            · <?php echo number_format((float) $lr['days'], 1); ?>d
                                            <?php if (!empty($lr['department_name'])): ?> · <?php echo htmlspecialchars($lr['department_name']); ?><?php endif; ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-voice">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-comments" style="color:#d2232a;"></i> Employee Voice</h3>
                    <p><?php echo (int) $kpi['voice_open']; ?> open tickets</p>
                </div>
                <a class="btn-ghost emp-dash-more" href="<?php echo app_url('employee_voice/index.php'); ?>">View all</a>
            </div>
            <div class="emp-dash-panel-body">
                <?php if (!$voiceTickets): ?>
                    <div class="emp-dash-empty">No open voice tickets.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($voiceTickets as $vt):
                            $meta = $evTypes[$vt['module_type']] ?? null;
                            ?>
                            <li>
                                <a class="emp-dash-row emp-dash-pan" href="<?php echo app_url('employee_voice/view.php?id=' . (int) $vt['id']); ?>">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars((string) ($meta['short'] ?? $vt['module_type'])); ?></em>
                                    <div class="emp-dash-pan-body">
                                        <strong class="emp-dash-name"><?php echo htmlspecialchars((string) ($vt['ticket_no'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($vt['subject'] ?? '')); ?></strong>
                                        <span class="emp-dash-meta">
                                            <?php echo htmlspecialchars((string) ($vt['employee_name'] ?: '—')); ?>
                                            · <?php echo htmlspecialchars((string) ($vt['status'] ?? '')); ?>
                                            · <?php echo htmlspecialchars((string) ($vt['priority'] ?? '')); ?>
                                        </span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="form-page-card emp-dash-panel is-holiday">
            <div class="emp-dash-panel-head">
                <div>
                    <h3><i class="fa-solid fa-umbrella-beach" style="color:#0d9488;"></i> Upcoming Holidays</h3>
                    <p>Next 30 days</p>
                </div>
                <a class="btn-ghost emp-dash-more" href="<?php echo app_url('masters/holidays/index.php'); ?>">Manage</a>
            </div>
            <div class="emp-dash-panel-body">
                <?php if (!$upcomingHolidays): ?>
                    <div class="emp-dash-empty">No holidays in next 30 days.</div>
                <?php else: ?>
                    <ul class="emp-dash-list">
                        <?php foreach ($upcomingHolidays as $h): ?>
                            <li>
                                <div class="emp-dash-row emp-dash-pan">
                                    <em class="emp-dash-date"><?php echo htmlspecialchars(formatDateDisplay($h['holiday_date'] ?? '')); ?></em>
                                    <div class="emp-dash-pan-body">
                                        <strong class="emp-dash-name"><?php echo htmlspecialchars((string) ($h['title'] ?? 'Holiday')); ?></strong>
                                        <span class="emp-dash-meta">
                                            <?php echo htmlspecialchars(function_exists('holidayFormatDateRangeDisplay') ? holidayFormatDateRangeDisplay($h['holiday_date'] ?? '', $h['holiday_to_date'] ?? '') : formatDateDisplay($h['holiday_date'] ?? '')); ?>
                                            · <?php echo !empty($h['department_id']) ? htmlspecialchars($h['department_name'] ?: 'Dept') : 'All Departments'; ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="form-page-card emp-matrix-card" id="employee-matrix" style="margin-top:14px;">
        <div class="emp-matrix-head">
            <div>
                <h2><i class="fa-solid fa-sitemap"></i> Employee Matrix</h2>
                <p>Department / HR / Payroll Heads · Desk · Contact</p>
            </div>
            <?php if ($canManageHeads): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn-secondary" href="<?php echo app_url('roles/assign.php'); ?>">
                        <i class="fa-solid fa-user-check"></i> Assign Head Login
                    </a>
                    <a class="btn-primary" href="<?php echo app_url('roles/department_heads.php'); ?>">
                        <i class="fa-solid fa-user-tie"></i> Set Department Heads
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-wrap emp-matrix-wrap">
            <table class="data-table emp-matrix-table">
                <thead>
                    <tr>
                        <th>Sr</th>
                        <th>Emp Desk No</th>
                        <th>Emp Code</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Official Mobile</th>
                        <th>Official Email</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$employeeMatrix): ?>
                    <tr>
                        <td colspan="8" class="emp-matrix-empty">
                            No heads set yet.
                            <?php if ($canManageHeads): ?>
                                Set department-wise heads from
                                <a href="<?php echo app_url('roles/department_heads.php'); ?>">Department Heads</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employeeMatrix as $i => $row):
                        $headCode = strtoupper(trim((string) ($row['head_role_code'] ?? 'DEPT_HEAD')));
                        $rowTone = 'is-dept';
                        if ($headCode === 'HR_HEAD') {
                            $rowTone = 'is-hr';
                        } elseif ($headCode === 'PAYROLL_HEAD') {
                            $rowTone = 'is-pay';
                        }
                        $desk = trim((string) ($row['desk_no'] ?? ''));
                        $code = trim((string) ($row['employee_code'] ?? ''));
                        $name = trim((string) ($row['employee_name'] ?? ''));
                        $desig = trim((string) ($row['designation'] ?? ''));
                        $dept = trim((string) ($row['department_name'] ?? ''));
                        $mobile = trim((string) ($row['office_mobile'] ?? ''));
                        $mail = trim((string) ($row['office_email'] ?? ''));
                        ?>
                        <tr class="emp-matrix-row <?php echo $rowTone; ?>">
                            <td><span class="emp-matrix-sr"><?php echo $i + 1; ?></span></td>
                            <td><?php if ($desk !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-desk"><?php echo htmlspecialchars($desk); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($code !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-code"><?php echo htmlspecialchars($code); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><span class="emp-matrix-name"><?php echo htmlspecialchars($name !== '' ? $name : '—'); ?></span></td>
                            <td><?php if ($desig !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-desig"><?php echo htmlspecialchars($desig); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($dept !== ''): ?>
                                <span class="emp-matrix-chip emp-matrix-dept"><?php echo htmlspecialchars($dept); ?></span>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td><?php if ($mobile !== ''): ?>
                                <a class="emp-matrix-chip emp-matrix-mobile" href="tel:<?php echo htmlspecialchars($mobile); ?>">
                                    <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($mobile); ?>
                                </a>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                            <td class="emp-matrix-mail"><?php if ($mail !== ''): ?>
                                <a class="emp-matrix-chip emp-matrix-email" href="mailto:<?php echo htmlspecialchars($mail); ?>">
                                    <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($mail); ?>
                                </a>
                            <?php else: ?><span class="emp-matrix-na">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
