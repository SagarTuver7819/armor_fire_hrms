<?php
/**
 * HR Dashboard — single workspace for HR day-to-day
 * Birthdays · Dept attendance · Leaves · Quick actions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/leave_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';

requireStaff();

if (!function_exists('isOfficeStaffRole')) {
    require_once __DIR__ . '/../includes/department_head_helper.php';
}
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
$todayMd = date('m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$year = (int) date('Y');
$month = (int) date('n');
$greetingHour = (int) date('G');
if ($greetingHour < 12) {
    $greeting = 'Good morning';
} elseif ($greetingHour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$userName = trim((string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'HR'));

// ── KPI counts ─────────────────────────────────────────
$kpi = [
    'active' => 0,
    'joiners' => 0,
    'exits' => 0,
    'pending_leave' => 0,
    'present_today' => 0,
    'absent_today' => 0,
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
        SUM(CASE WHEN day_status = 'Absent' THEN 1 ELSE 0 END) AS absent_c
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

// ── Birthdays: today + next 14 days ────────────────────
$birthdaysToday = [];
$birthdaysUpcoming = [];
$st = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_birth, e.designation, e.department_id,
            d.department_name, e.photo_file
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_birth IS NOT NULL
       AND e.date_of_birth != '0000-00-00'
       AND DATE_FORMAT(e.date_of_birth, '%m-%d') = ?
     ORDER BY e.employee_name ASC
     LIMIT 40"
);
$st->bind_param('s', $todayMd);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $birthdaysToday[] = $row;
}
$st->close();

$st = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_birth, e.designation, e.department_id,
            d.department_name, e.photo_file,
            DATE_FORMAT(e.date_of_birth, '%m-%d') AS md
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1
       AND e.date_of_birth IS NOT NULL
       AND e.date_of_birth != '0000-00-00'
       AND DATE_FORMAT(e.date_of_birth, '%m-%d') <> ?
     ORDER BY
        CASE
            WHEN DATE_FORMAT(e.date_of_birth, '%m-%d') >= ? THEN 0
            ELSE 1
        END,
        DATE_FORMAT(e.date_of_birth, '%m-%d') ASC
     LIMIT 80"
);
$st->bind_param('ss', $todayMd, $todayMd);
$st->execute();
$res = $st->get_result();
$cutoff = new DateTime('today');
$limit = (clone $cutoff)->modify('+14 days');
while ($row = $res->fetch_assoc()) {
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
    if ($candidate > $limit) {
        continue;
    }
    $row['next_on'] = $candidate->format('Y-m-d');
    $row['in_days'] = (int) $cutoff->diff($candidate)->days;
    $birthdaysUpcoming[] = $row;
    if (count($birthdaysUpcoming) >= 12) {
        break;
    }
}
$st->close();

// ── Work anniversaries: next 30 days from Join Date (1+ year) ──
$anniversaries = [];
$stAnnHr = $conn->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_joining, e.designation,
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
     ORDER BY t.next_on ASC, e.employee_name ASC
     LIMIT 20"
);
if ($stAnnHr) {
    while ($row = $stAnnHr->fetch_assoc()) {
        $row['years'] = (int) ($row['years'] ?? 0);
        $row['in_days'] = (int) ($row['in_days'] ?? 0);
        $anniversaries[] = $row;
    }
}

// ── Department-wise attendance today ───────────────────
$deptAttendance = [];
$sqlDeptAtt = "
    SELECT d.id, d.department_name, d.icon_class, d.icon_color, d.sort_order,
           COUNT(DISTINCT e.id) AS headcount,
           SUM(CASE WHEN ads.day_status IN ('Present','Half Day') THEN 1 ELSE 0 END) AS present_c,
           SUM(CASE WHEN ads.day_status = 'Absent' THEN 1 ELSE 0 END) AS absent_c,
           SUM(CASE WHEN ads.day_status = 'Week Off' THEN 1 ELSE 0 END) AS wo_c,
           SUM(CASE WHEN ads.day_status = 'Holiday' THEN 1 ELSE 0 END) AS hol_c,
           SUM(CASE WHEN ads.day_status = 'Leave' THEN 1 ELSE 0 END) AS leave_c
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id AND e.status = 1
    LEFT JOIN attendance_day_status ads
           ON ads.employee_id = e.id AND ads.attendance_date = ?
    WHERE d.status = 1
    GROUP BY d.id, d.department_name, d.icon_class, d.icon_color, d.sort_order
    HAVING headcount > 0
    ORDER BY d.sort_order ASC, d.department_name ASC
";
$st = $conn->prepare($sqlDeptAtt);
$st->bind_param('s', $today);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) {
    $hc = (int) $row['headcount'];
    $present = (int) $row['present_c'];
    $row['pct'] = $hc > 0 ? (int) round(($present / $hc) * 100) : 0;
    $deptAttendance[] = $row;
}
$st->close();

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
     LIMIT 10"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pendingLeaves[] = $row;
    }
}

// ── Upcoming holidays (30 days) ────────────────────────
$upcomingHolidays = [];
$to30 = date('Y-m-d', strtotime('+30 days'));
ensureMasterTables($conn);
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

// ── Recent joiners ─────────────────────────────────────
$recentJoiners = [];
$res = $conn->query(
    "SELECT e.id, e.employee_code, e.employee_name, e.date_of_joining, e.designation, d.department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 1 AND e.date_of_joining IS NOT NULL AND e.date_of_joining != '0000-00-00'
     ORDER BY e.date_of_joining DESC, e.id DESC
     LIMIT 8"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentJoiners[] = $row;
    }
}

$conn->close();

function hrInitials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'E';
    }
    $parts = preg_split('/\s+/', $name);
    $a = mb_substr($parts[0], 0, 1);
    $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return strtoupper($a . $b);
}

function hrAge($dob)
{
    if (empty($dob) || $dob === '0000-00-00') {
        return null;
    }
    try {
        $born = new DateTime($dob);
        $now = new DateTime('today');
        return (int) $born->diff($now)->y;
    } catch (Exception $e) {
        return null;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main hr-dash">
    <section class="hr-dash-hero">
        <div class="hr-dash-hero-copy">
            <p class="hr-dash-eyebrow"><i class="fa-solid fa-user-tie"></i> HR Workspace</p>
            <h1><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($userName); ?></h1>
            <p class="hr-dash-sub">
                <?php echo htmlspecialchars(date('l, d F Y')); ?> ·
                One place for birthdays, attendance, leaves &amp; people ops
            </p>
        </div>
        <div class="hr-dash-hero-actions">
            <a class="btn-primary" href="<?php echo app_url('dashboard.php#department-workspace'); ?>">
                <i class="fa-solid fa-user-plus"></i> Add Employee
            </a>
            <a class="btn-ghost" href="<?php echo app_url('attendance/manual.php'); ?>">
                <i class="fa-solid fa-pen-to-square"></i> Manual Attendance
            </a>
            <a class="btn-ghost" href="<?php echo app_url('leave/index.php'); ?>">
                <i class="fa-solid fa-scale-balanced"></i> Leave Desk
            </a>
            <a class="btn-ghost" href="<?php echo app_url('circulars/edit.php'); ?>">
                <i class="fa-solid fa-file-circle-plus"></i> Add Circular
            </a>
            <a class="btn-ghost" href="<?php echo app_url('policies/edit.php'); ?>">
                <i class="fa-solid fa-scroll"></i> Add Policy
            </a>
        </div>
    </section>

    <section class="hr-kpi-grid">
        <a class="hr-kpi" href="<?php echo app_url('employees/index.php'); ?>">
            <div class="hr-kpi-ico" style="--kpi:#2563eb"><i class="fa-solid fa-users"></i></div>
            <div>
                <span>Active Employees</span>
                <strong><?php echo number_format($kpi['active']); ?></strong>
            </div>
        </a>
        <a class="hr-kpi" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
            <div class="hr-kpi-ico" style="--kpi:#059669"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <span>Present Today</span>
                <strong><?php echo number_format($kpi['present_today']); ?></strong>
            </div>
        </a>
        <div class="hr-kpi">
            <div class="hr-kpi-ico" style="--kpi:#dc2626"><i class="fa-solid fa-user-xmark"></i></div>
            <div>
                <span>Absent Today</span>
                <strong><?php echo number_format($kpi['absent_today']); ?></strong>
            </div>
        </div>
        <a class="hr-kpi" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">
            <div class="hr-kpi-ico" style="--kpi:#d97706"><i class="fa-solid fa-clock"></i></div>
            <div>
                <span>Pending Leaves</span>
                <strong><?php echo number_format($kpi['pending_leave']); ?></strong>
            </div>
        </a>
        <div class="hr-kpi">
            <div class="hr-kpi-ico" style="--kpi:#0d9488"><i class="fa-solid fa-user-plus"></i></div>
            <div>
                <span>Joiners (Month)</span>
                <strong><?php echo number_format($kpi['joiners']); ?></strong>
            </div>
        </div>
        <a class="hr-kpi" href="<?php echo app_url('employees/exit_list.php'); ?>">
            <div class="hr-kpi-ico" style="--kpi:#7c3aed"><i class="fa-solid fa-door-open"></i></div>
            <div>
                <span>Exits (Month)</span>
                <strong><?php echo number_format($kpi['exits']); ?></strong>
            </div>
        </a>
    </section>

    <div class="hr-dash-layout">
        <div class="hr-dash-main-col">
            <!-- Birthdays -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-cake-candles"></i> Birthday Reminders</h2>
                        <p>Celebrate today · next 14 days</p>
                    </div>
                </div>

                <?php if ($birthdaysToday): ?>
                    <div class="hr-bday-today">
                        <div class="hr-bday-today-label"><i class="fa-solid fa-gift"></i> Today</div>
                        <div class="hr-bday-row">
                            <?php foreach ($birthdaysToday as $b): ?>
                                <?php
                                $photo = function_exists('employeeDocumentPublicUrl')
                                    ? employeeDocumentPublicUrl($b['photo_file'] ?? '')
                                    : '';
                                $age = hrAge($b['date_of_birth'] ?? '');
                                ?>
                                <a class="hr-bday-card is-today" href="<?php echo app_url('employees/view.php?id=' . (int) $b['id']); ?>">
                                    <div class="hr-bday-avatar">
                                        <?php if ($photo !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars(hrInitials($b['employee_name'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hr-bday-meta">
                                        <strong><?php echo htmlspecialchars($b['employee_name']); ?></strong>
                                        <span><?php echo htmlspecialchars(($b['department_name'] ?: '—') . ($age ? ' · turns ' . $age : '')); ?></span>
                                    </div>
                                    <span class="hr-bday-chip">Today</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hr-empty soft">No birthdays today.</div>
                <?php endif; ?>

                <?php if ($birthdaysUpcoming): ?>
                    <div class="hr-bday-upcoming">
                        <div class="hr-mini-label">Upcoming</div>
                        <div class="hr-bday-list">
                            <?php foreach ($birthdaysUpcoming as $b): ?>
                                <a class="hr-bday-line" href="<?php echo app_url('employees/view.php?id=' . (int) $b['id']); ?>">
                                    <div class="hr-bday-avatar sm">
                                        <span><?php echo htmlspecialchars(hrInitials($b['employee_name'])); ?></span>
                                    </div>
                                    <div class="hr-bday-meta">
                                        <strong><?php echo htmlspecialchars($b['employee_name']); ?></strong>
                                        <span><?php echo htmlspecialchars($b['department_name'] ?: '—'); ?></span>
                                    </div>
                                    <div class="hr-bday-when">
                                        <strong><?php echo htmlspecialchars(formatDateDisplay($b['next_on'] ?? '')); ?></strong>
                                        <span>in <?php echo (int) ($b['in_days'] ?? 0); ?> day<?php echo ((int) ($b['in_days'] ?? 0) === 1) ? '' : 's'; ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Dept attendance -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-chart-simple"></i> Department Attendance</h2>
                        <p>Today · <?php echo htmlspecialchars(formatDateDisplay($today)); ?></p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('attendance/report.php?show=1&month=' . $month . '&year=' . $year); ?>">
                        Full Report
                    </a>
                </div>

                <?php if (!$deptAttendance): ?>
                    <div class="hr-empty">No department headcount found.</div>
                <?php else: ?>
                    <div class="hr-dept-att-table-wrap">
                        <table class="hr-dept-att-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th class="num">Head</th>
                                    <th class="num">Present</th>
                                    <th class="num">Absent</th>
                                    <th class="num">Leave</th>
                                    <th class="num">WO / Hol</th>
                                    <th>Presence</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deptAttendance as $d): ?>
                                <?php
                                $color = $d['icon_color'] ?: '#F58220';
                                $icon = $d['icon_class'] ?: 'fa-building';
                                $pct = (int) $d['pct'];
                                ?>
                                <tr>
                                    <td>
                                        <a class="hr-dept-link" href="<?php echo app_url('department.php?id=' . (int) $d['id']); ?>">
                                            <span class="hr-dept-ico" style="background:<?php echo htmlspecialchars($color); ?>">
                                                <i class="fa-solid <?php echo htmlspecialchars($icon); ?>"></i>
                                            </span>
                                            <strong><?php echo htmlspecialchars($d['department_name']); ?></strong>
                                        </a>
                                    </td>
                                    <td class="num"><?php echo (int) $d['headcount']; ?></td>
                                    <td class="num ok"><?php echo (int) $d['present_c']; ?></td>
                                    <td class="num bad"><?php echo (int) $d['absent_c']; ?></td>
                                    <td class="num"><?php echo (int) $d['leave_c']; ?></td>
                                    <td class="num muted"><?php echo (int) $d['wo_c'] + (int) $d['hol_c']; ?></td>
                                    <td>
                                        <div class="hr-bar" title="<?php echo $pct; ?>%">
                                            <span style="width:<?php echo $pct; ?>%;background:<?php echo htmlspecialchars($color); ?>"></span>
                                        </div>
                                        <small><?php echo $pct; ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="hr-dash-side-col">
            <!-- Quick links -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-bolt"></i> Quick Actions</h2>
                        <p>Jump to daily HR work</p>
                    </div>
                </div>
                <div class="hr-quick-grid">
                    <a href="<?php echo app_url('employees/index.php'); ?>"><i class="fa-solid fa-users"></i> All Employees</a>
                    <a href="<?php echo app_url('dashboard.php#department-workspace'); ?>"><i class="fa-solid fa-user-plus"></i> Add Employee</a>
                    <a href="<?php echo app_url('attendance/import.php'); ?>"><i class="fa-solid fa-file-import"></i> Import Attendance</a>
                    <a href="<?php echo app_url('attendance/manual.php'); ?>"><i class="fa-solid fa-pen-to-square"></i> Manual Entry</a>
                    <a href="<?php echo app_url('leave/apply.php'); ?>"><i class="fa-solid fa-plus"></i> Apply Leave</a>
                    <a href="<?php echo app_url('circulars/index.php'); ?>"><i class="fa-solid fa-file-circle-plus"></i> Circulars</a>
                    <a href="<?php echo app_url('circulars/edit.php'); ?>"><i class="fa-solid fa-upload"></i> Add Circular</a>
                    <a href="<?php echo app_url('policies/index.php'); ?>"><i class="fa-solid fa-scroll"></i> Policies</a>
                    <a href="<?php echo app_url('policies/edit.php'); ?>"><i class="fa-solid fa-file-arrow-up"></i> Add Policy</a>
                    <a href="<?php echo app_url('payroll/register.php'); ?>"><i class="fa-solid fa-table"></i> Salary Register</a>
                    <a href="<?php echo app_url('masters/holidays/index.php'); ?>"><i class="fa-solid fa-calendar-days"></i> Holiday Master</a>
                    <a href="<?php echo app_url('dashboard.php'); ?>"><i class="fa-solid fa-building"></i> Dept Workspace</a>
                </div>
            </section>

            <!-- Pending leaves -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-inbox"></i> Leave Queue</h2>
                        <p><?php echo count($pendingLeaves); ?> pending</p>
                    </div>
                    <a class="btn-ghost" href="<?php echo app_url('leave/index.php?status=Pending'); ?>">View all</a>
                </div>
                <?php if (!$pendingLeaves): ?>
                    <div class="hr-empty soft">No pending leave requests.</div>
                <?php else: ?>
                    <ul class="hr-stack-list">
                        <?php foreach ($pendingLeaves as $lr): ?>
                            <li>
                                <a href="<?php echo app_url('employees/view.php?id=' . (int) $lr['employee_id'] . '&tab=history'); ?>">
                                    <strong><?php echo htmlspecialchars($lr['employee_name']); ?></strong>
                                    <span>
                                        <?php echo htmlspecialchars(($lr['code'] ? $lr['code'] . ' · ' : '') . ($lr['leave_type'] ?: 'Leave')); ?>
                                        · <?php echo htmlspecialchars(formatDateDisplay($lr['from_date'])); ?>
                                        <?php if ($lr['from_date'] !== $lr['to_date']): ?>
                                            → <?php echo htmlspecialchars(formatDateDisplay($lr['to_date'])); ?>
                                        <?php endif; ?>
                                    </span>
                                </a>
                                <em><?php echo number_format((float) $lr['days'], 1); ?>d</em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Holidays -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-umbrella-beach"></i> Upcoming Holidays</h2>
                        <p>Next 30 days</p>
                    </div>
                </div>
                <?php if (!$upcomingHolidays): ?>
                    <div class="hr-empty soft">No holidays in next 30 days.</div>
                <?php else: ?>
                    <ul class="hr-stack-list">
                        <?php foreach ($upcomingHolidays as $h): ?>
                            <li>
                                <div>
                                    <strong><?php echo htmlspecialchars($h['title']); ?></strong>
                                    <span>
                                        <?php echo htmlspecialchars(holidayFormatDateRangeDisplay($h['holiday_date'] ?? '', $h['holiday_to_date'] ?? '')); ?>
                                        · <?php echo !empty($h['department_id']) ? htmlspecialchars($h['department_name'] ?: 'Dept') : 'All Departments'; ?>
                                        <?php echo (($h['is_paid'] ?? 'Yes') === 'Yes') ? ' · Paid' : ' · Unpaid'; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Anniversaries -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-award"></i> Work Anniversaries</h2>
                        <p>Next 30 days</p>
                    </div>
                </div>
                <?php if (!$anniversaries): ?>
                    <div class="hr-empty soft">No upcoming work anniversaries.</div>
                <?php else: ?>
                    <ul class="hr-stack-list">
                        <?php foreach ($anniversaries as $a):
                            $when = ((int) ($a['in_days'] ?? 0) === 0)
                                ? 'Today'
                                : (((int) $a['in_days'] === 1) ? 'Tomorrow' : ('In ' . (int) $a['in_days'] . ' days'));
                            ?>
                            <li>
                                <a href="<?php echo app_url('employees/view.php?id=' . (int) $a['id']); ?>">
                                    <strong><?php echo htmlspecialchars($a['employee_name']); ?></strong>
                                    <span>
                                        <?php echo htmlspecialchars(formatDateDisplay($a['next_on'] ?? $a['date_of_joining'])); ?>
                                        · <?php echo (int) $a['years']; ?> yr<?php echo ((int) $a['years'] === 1) ? '' : 's'; ?>
                                        · <?php echo htmlspecialchars($when); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Recent joiners -->
            <section class="hr-panel">
                <div class="hr-panel-head">
                    <div>
                        <h2><i class="fa-solid fa-handshake"></i> Recent Joiners</h2>
                        <p>Latest additions</p>
                    </div>
                </div>
                <?php if (!$recentJoiners): ?>
                    <div class="hr-empty soft">No recent joiners.</div>
                <?php else: ?>
                    <ul class="hr-stack-list">
                        <?php foreach ($recentJoiners as $j): ?>
                            <li>
                                <a href="<?php echo app_url('employees/view.php?id=' . (int) $j['id']); ?>">
                                    <strong><?php echo htmlspecialchars($j['employee_name']); ?></strong>
                                    <span>
                                        <?php echo htmlspecialchars($j['employee_code'] ?: ''); ?>
                                        · <?php echo htmlspecialchars($j['department_name'] ?: '—'); ?>
                                        · <?php echo htmlspecialchars(formatDateDisplay($j['date_of_joining'])); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
