<?php
/**
 * Muster report — day grid P / A / WO / H / HD
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
$monthDays = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$from = sprintf('%04d-%02d-01', $year, $month);
$to = sprintf('%04d-%02d-%02d', $year, $month, $monthDays);

$conn = getDBConnection();
ensureAttendanceTables($conn);

$where = "e.status = 1 AND e.pay_type IN ('Salary','Jobwork')";
$types = '';
$params = [];
if ($deptId > 0) {
    $where .= ' AND e.department_id = ?';
    $types .= 'i';
    $params[] = $deptId;
}
if ($employeeId > 0) {
    $where .= ' AND e.id = ?';
    $types .= 'i';
    $params[] = $employeeId;
}
$sql = "SELECT e.id, e.employee_code, e.employee_name, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE {$where}
        ORDER BY d.department_name ASC, e.employee_code ASC";
if ($params) {
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $emps = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} else {
    $emps = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$statusMap = [];
$st = $conn->prepare(
    "SELECT employee_id, attendance_date, day_status
     FROM attendance_day_status
     WHERE attendance_date BETWEEN ? AND ?"
);
$st->bind_param('ss', $from, $to);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) {
    $statusMap[(int) $r['employee_id']][$r['attendance_date']] = $r['day_status'];
}
$st->close();
$conn->close();

$codeMap = [
    'Present' => 'P',
    'Absent' => 'A',
    'Week Off' => 'WO',
    'Holiday' => 'H',
    'Leave' => 'L',
    'Half Day' => 'HD',
];

$pageTitle = 'Muster Report';
$useSidebar = true;
$sidebarMode = 'attendance';
$sidebarActive = 'attendance_report';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo htmlspecialchars(app_url('attendance/report.php?' . http_build_query([
            'show' => 1,
            'department_id' => $deptId,
            'month' => $month,
            'year' => $year,
        ]))); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Report
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Muster Report</h1>
            <p><?php echo htmlspecialchars(date('F Y', mktime(0, 0, 0, $month, 1, $year))); ?> · P=Present, A=Absent, WO=Week Off, H=Holiday, HD=Half Day, L=Leave</p>
        </div>
        <div class="table-wrap" style="overflow:auto;">
            <table class="data-table" style="min-width:100%;font-size:12px;">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <?php for ($d = 1; $d <= $monthDays; $d++): ?>
                            <th><?php echo $d; ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emps as $emp): ?>
                        <?php $eid = (int) $emp['id']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                            <td><?php echo htmlspecialchars($emp['employee_name']); ?></td>
                            <?php for ($d = 1; $d <= $monthDays; $d++): ?>
                                <?php
                                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                $stt = $statusMap[$eid][$date] ?? '';
                                $code = $codeMap[$stt] ?? '';
                                ?>
                                <td style="text-align:center;"><?php echo htmlspecialchars($code); ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
