<?php
/**
 * KPI Report — paper format (employee own next-day / HR anytime)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

requireLogin();

$sheetId = (int) ($_GET['id'] ?? 0);
$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$asStaff = function_exists('isStaffUser') && isStaffUser();

$conn = getDBConnection();
ensureKpiTables($conn);
$sheet = $sheetId > 0 ? kpiGetSheetById($sheetId, $conn) : null;
if (!$sheet) {
    $conn->close();
    header('Location: ' . app_url($asStaff ? 'hr/kpi.php' : 'employee/kpi_history.php'));
    exit;
}

$isOwn = $sessionEmpId > 0 && (int) $sheet['employee_id'] === $sessionEmpId;
if (!$asStaff && !$isOwn) {
    $conn->close();
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

if (!$asStaff && !kpiCanViewReport($sheet, false)) {
    $conn->close();
    header('Location: ' . app_url('employee/kpi_history.php?msg=wait'));
    exit;
}

$emp = getEmployeeById((int) $sheet['employee_id']);
$entries = kpiGetEntries($sheetId, $conn);
$conn->close();

$slots = [];
foreach ($entries as $e) {
    $slots[] = $e;
}
usort($slots, static function ($a, $b) {
    return ((int) $a['slot_index']) <=> ((int) $b['slot_index']);
});

$companyName = function_exists('getCompanyName') ? getCompanyName() : 'ARMOR';
$dateDisp = formatDateDisplay($sheet['kpi_date'] ?? '');
$shiftDisp = trim(($sheet['shift_in'] ?? '') . ' TO ' . ($sheet['shift_out'] ?? ''));
$backUrl = $asStaff
    ? app_url('hr/kpi.php?date=' . urlencode((string) $sheet['kpi_date']))
    : app_url('employee/kpi_history.php');

$pageTitle = 'KPI Report';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = $asStaff ? 'hr_kpi' : 'my_kpi';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between no-print">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <button type="button" class="btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print
        </button>
    </div>

    <div class="kpi-sheet-card kpi-report-print">
        <div class="kpi-sheet-company"><?php echo htmlspecialchars(strtoupper($companyName)); ?></div>
        <div class="kpi-sheet-title">Key Performance Indicator (K.P.I.)</div>
        <div class="kpi-sheet-meta">
            <span>તારીખ / Date: <strong><?php echo htmlspecialchars($dateDisp); ?></strong></span>
            <span class="kpi-status is-done"><i class="fa-solid fa-circle-check"></i> Submitted</span>
        </div>

        <table class="kpi-info-table">
            <thead>
                <tr>
                    <th style="width:38%;">વિગતો / Details</th>
                    <th>માહિતી / Information</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>કર્મચારીનું નામ / Employee Name</td>
                    <td><strong><?php echo htmlspecialchars((string) ($emp['employee_name'] ?? '')); ?></strong></td>
                </tr>
                <tr>
                    <td>કર્મચારી કોડ / Employee Code</td>
                    <td><?php echo htmlspecialchars((string) ($emp['employee_code'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td>વિભાગ / Department</td>
                    <td><?php echo htmlspecialchars(trim((string) (($emp['department_name'] ?? '') . (!empty($emp['designation']) ? ' · ' . $emp['designation'] : '')))); ?></td>
                </tr>
                <tr>
                    <td>શિફ્ટ સમય / Shift Time</td>
                    <td><?php echo htmlspecialchars($shiftDisp !== ' TO ' ? $shiftDisp : '—'); ?></td>
                </tr>
                <tr>
                    <td>રિપોર્ટિંગ મેનેજર / Reporting Manager</td>
                    <td><?php echo htmlspecialchars((string) (($emp['reporting_head'] ?? '') !== '' ? $emp['reporting_head'] : '—')); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="kpi-section-head">પ્રતિ કલાક K.P.I. શીટ / Hourly K.P.I. Sheet</div>

        <table class="kpi-hour-table">
            <thead>
                <tr>
                    <th style="width:70px;">ક્રમાંક</th>
                    <th style="width:160px;">સમય / Time</th>
                    <th>Activity / કામની વિગત</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($slots as $slot): ?>
                <tr>
                    <td class="num"><?php echo (int) $slot['slot_index']; ?></td>
                    <td><?php echo htmlspecialchars($slot['time_from'] . ' – ' . $slot['time_to']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars(trim((string) ($slot['activity_text'] ?? '')) !== '' ? $slot['activity_text'] : '—')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($sheet['responsibility_ack'])): ?>
            <div class="kpi-ack-done">
                <i class="fa-solid fa-square-check"></i>
                Based on my best knowledge, the above information is correct, and I confirm its accuracy. — Confirmed
            </div>
        <?php endif; ?>

        <div class="kpi-sign-row">
            <div>
                <div class="kpi-sign-label">તૈયાર કરનાર / Prepared By</div>
                <div class="kpi-sign-val"><?php echo htmlspecialchars((string) ($sheet['prepared_by_name'] ?: ($emp['employee_name'] ?? ''))); ?></div>
            </div>
            <div>
                <div class="kpi-sign-label">ચકાસનાર / Checked By</div>
                <div class="kpi-sign-val">—</div>
            </div>
            <div>
                <div class="kpi-sign-label">મંજૂર કરનાર / Approved By</div>
                <div class="kpi-sign-val">—</div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
