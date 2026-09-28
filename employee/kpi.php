<?php
/**
 * Employee — fill today's hourly KPI (shift-wise)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';
require_once __DIR__ . '/../includes/department_head_helper.php';

requireLogin();

$empId = (int) ($_SESSION['employee_id'] ?? 0);
if ($empId <= 0 || !isEmployee()) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$emp = getEmployeeById($empId);
if (!$emp) {
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

$today = date('Y-m-d');
$date = isset($_GET['date']) ? substr((string) $_GET['date'], 0, 10) : $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
// Employees only fill for today
if ($date !== $today) {
    $date = $today;
}

$conn = getDBConnection();
ensureKpiTables($conn);
ensureMasterTables($conn);
$shifts = getActiveMasterRows('shifts', 'name ASC');
$sheet = kpiEnsureDraftSheet($empId, $date, $emp, $shifts, $conn);
$entries = $sheet ? kpiGetEntries((int) $sheet['id'], $conn) : [];
$conn->close();

$isSubmitted = $sheet && ($sheet['status'] ?? '') === 'submitted';
$built = kpiBuildHourlySlots($emp, $shifts);
$slots = $built['slots'];
// Prefer stored slot times from entries if present
if ($entries) {
    $slots = [];
    foreach ($entries as $e) {
        $slots[] = [
            'index' => (int) $e['slot_index'],
            'time_from' => $e['time_from'],
            'time_to' => $e['time_to'],
            'label' => $e['time_from'] . ' – ' . $e['time_to'],
            'activity' => (string) ($e['activity_text'] ?? ''),
        ];
    }
    usort($slots, static function ($a, $b) {
        return $a['index'] <=> $b['index'];
    });
} else {
    foreach ($slots as &$s) {
        $s['activity'] = '';
    }
    unset($s);
}

$lastIndex = $slots ? (int) $slots[count($slots) - 1]['index'] : 0;
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'ARMOR';
$dateDisp = function_exists('formatDateDisplay') ? formatDateDisplay($date) : date('d-m-Y', strtotime($date));
$shiftDisp = trim((string) ($sheet['shift_in'] ?? $built['shift_in']) . ' TO ' . (string) ($sheet['shift_out'] ?? $built['shift_out']));

$pageTitle = 'My KPI';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'my_kpi';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    $map = [
        'saved' => 'KPI draft saved.',
        'submitted' => 'KPI submitted successfully. Report will be available from tomorrow.',
        'error' => (string) ($_GET['err'] ?? 'Something went wrong.'),
    ];
    $toast = $map[$_GET['msg']] ?? '';
    if ($_GET['msg'] === 'error') {
        $toastType = 'error';
    }
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/dashboard.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo app_url('employee/kpi_history.php'); ?>" class="btn-secondary">
                <i class="fa-solid fa-clock-rotate-left"></i> Past KPI Reports
            </a>
        </div>
    </div>

    <div class="kpi-sheet-card">
        <div class="kpi-sheet-company"><?php echo htmlspecialchars(strtoupper($companyName)); ?></div>
        <div class="kpi-sheet-title">Key Performance Indicator (K.P.I.)</div>
        <div class="kpi-sheet-meta">
            <span>તારીખ / Date: <strong><?php echo htmlspecialchars($dateDisp); ?></strong></span>
            <?php if ($isSubmitted): ?>
                <span class="kpi-status is-done"><i class="fa-solid fa-circle-check"></i> Submitted</span>
            <?php else: ?>
                <span class="kpi-status is-draft"><i class="fa-solid fa-pen"></i> Fill &amp; Submit</span>
            <?php endif; ?>
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
                    <td><?php echo htmlspecialchars(trim((string) (($emp['department_name'] ?? '') . (($emp['designation'] ?? '') !== '' ? ' · ' . $emp['designation'] : '')))); ?></td>
                </tr>
                <tr>
                    <td>શિફ્ટ સમય / Shift Time</td>
                    <td><?php echo htmlspecialchars($shiftDisp); ?></td>
                </tr>
                <tr>
                    <td>રિપોર્ટિંગ મેનેજર / Reporting Manager</td>
                    <td><?php echo htmlspecialchars((string) (($emp['reporting_head'] ?? '') !== '' ? $emp['reporting_head'] : '—')); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="kpi-section-head">પ્રતિ કલાક K.P.I. શીટ / Hourly K.P.I. Sheet</div>

        <?php if ($isSubmitted): ?>
            <div class="alert alert-success" style="margin-bottom:12px;">
                Aaje no KPI submit thai gayo. Report format <strong>kal thi</strong> Past KPI Reports ma jovashe.
            </div>
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
                        <td class="num"><?php echo (int) $slot['index']; ?></td>
                        <td><?php echo htmlspecialchars($slot['label']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars(trim((string) ($slot['activity'] ?? '')) !== '' ? $slot['activity'] : '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($sheet['responsibility_ack'])): ?>
                <div class="kpi-ack-done">
                    <i class="fa-solid fa-square-check"></i>
                    All mara aaj na KPI Responsible for me — Confirmed
                    <?php if (!empty($sheet['submitted_at'])): ?>
                        · <?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($sheet['submitted_at']))); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" action="<?php echo app_url('employee/kpi_save.php'); ?>" class="kpi-fill-form" id="kpiFillForm">
                <input type="hidden" name="sheet_id" value="<?php echo (int) ($sheet['id'] ?? 0); ?>">
                <input type="hidden" name="kpi_date" value="<?php echo htmlspecialchars($date); ?>">

                <table class="kpi-hour-table">
                    <thead>
                        <tr>
                            <th style="width:70px;">ક્રમાંક</th>
                            <th style="width:160px;">સમય / Time</th>
                            <th>Activity / કામની વિગત</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($slots as $slot):
                        $idx = (int) $slot['index'];
                        $isLast = ($idx === $lastIndex);
                        ?>
                        <tr class="<?php echo $isLast ? 'is-last-hour' : ''; ?>">
                            <td class="num"><?php echo $idx; ?></td>
                            <td><?php echo htmlspecialchars($slot['label']); ?></td>
                            <td>
                                <textarea name="activity[<?php echo $idx; ?>]" class="form-control kpi-activity" rows="2"
                                          placeholder="<?php echo $isLast ? 'Last hour activity…' : 'Enter work done in this hour…'; ?>"><?php echo htmlspecialchars((string) ($slot['activity'] ?? '')); ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="kpi-submit-box">
                    <label class="kpi-ack-check">
                        <input type="checkbox" name="responsibility_ack" value="1" id="kpiAck" required>
                        <span>All mara aaj na KPI Responsible for me</span>
                    </label>
                    <p class="kpi-ack-hint">Last hour complete karta pehla aa checkbox tick kari ne Submit karo. Submit pachhi aaj no report <strong>next day</strong> thi Past Reports ma show thase.</p>
                    <div class="kpi-submit-actions">
                        <button type="submit" name="action" value="draft" class="btn-secondary" formnovalidate>
                            <i class="fa-solid fa-floppy-disk"></i> Save Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn-primary" id="kpiSubmitBtn">
                            <i class="fa-solid fa-paper-plane"></i> Submit KPI
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="kpi-sign-row">
            <div>
                <div class="kpi-sign-label">તૈયાર કરનાર / Prepared By</div>
                <div class="kpi-sign-val"><?php echo htmlspecialchars((string) ($emp['employee_name'] ?? '')); ?></div>
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

<?php
$extraJs = [
    'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
];
require_once __DIR__ . '/../includes/footer.php';
?>
<?php if ($toast !== ''): ?>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr[<?php echo json_encode($toastType); ?>](<?php echo json_encode($toast); ?>);
</script>
<?php endif; ?>
<script>
(function () {
    var form = document.getElementById('kpiFillForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var btn = e.submitter || document.activeElement;
        if (btn && btn.value === 'submit') {
            var ack = document.getElementById('kpiAck');
            if (ack && !ack.checked) {
                e.preventDefault();
                alert('Please tick: All mara aaj na KPI Responsible for me');
                ack.focus();
            }
        }
    });
})();
</script>
