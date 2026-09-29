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
            'slot_status' => (string) ($e['slot_status'] ?? 'open'),
            'slot_submitted_at' => (string) ($e['slot_submitted_at'] ?? ''),
        ];
    }
    usort($slots, static function ($a, $b) {
        return $a['index'] <=> $b['index'];
    });
} else {
    foreach ($slots as &$s) {
        $s['activity'] = '';
        $s['slot_status'] = 'open';
        $s['slot_submitted_at'] = '';
    }
    unset($s);
}

$lastIndex = $slots ? (int) $slots[count($slots) - 1]['index'] : 0;
$openHours = 0;
foreach ($slots as $s) {
    if (($s['slot_status'] ?? 'open') !== 'submitted') {
        $openHours++;
    }
}
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'ARMOR';
$dateDisp = function_exists('formatDateDisplay') ? formatDateDisplay($date) : formatDateDisplay($date);
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
        'hour' => 'Hour activity submitted.',
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
                    Based on my best knowledge, the above information is correct, and I confirm its accuracy. — Confirmed
                    <?php if (!empty($sheet['submitted_at'])): ?>
                        · <?php echo htmlspecialchars(formatDateTimeDisplay($sheet['submitted_at'])); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" action="<?php echo app_url('employee/kpi_save.php'); ?>" class="kpi-fill-form" id="kpiFillForm">
                <input type="hidden" name="sheet_id" value="<?php echo (int) ($sheet['id'] ?? 0); ?>">
                <input type="hidden" name="kpi_date" value="<?php echo htmlspecialchars($date); ?>">
                <input type="hidden" name="slot_index" id="kpiSlotIndex" value="">

                <table class="kpi-hour-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">ક્રમાંક</th>
                            <th style="width:130px;">સમય / Time</th>
                            <th>Activity / કામની વિગત</th>
                            <th style="width:110px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($slots as $slot):
                        $idx = (int) $slot['index'];
                        $isLast = ($idx === $lastIndex);
                        $hourDone = (($slot['slot_status'] ?? 'open') === 'submitted');
                        ?>
                        <tr class="<?php echo $isLast ? 'is-last-hour' : ''; ?><?php echo $hourDone ? ' is-hour-done' : ''; ?>"
                            data-slot="<?php echo $idx; ?>"
                            data-from="<?php echo htmlspecialchars($slot['time_from']); ?>"
                            data-to="<?php echo htmlspecialchars($slot['time_to']); ?>"
                            data-status="<?php echo $hourDone ? 'submitted' : 'open'; ?>">
                            <td class="num"><?php echo $idx; ?></td>
                            <td><?php echo htmlspecialchars($slot['label']); ?></td>
                            <td>
                                <?php if ($hourDone): ?>
                                    <div class="kpi-activity-locked">
                                        <?php echo nl2br(htmlspecialchars((string) ($slot['activity'] ?? '—'))); ?>
                                    </div>
                                <?php else: ?>
                                    <textarea name="activity[<?php echo $idx; ?>]" id="kpiAct<?php echo $idx; ?>"
                                              class="form-control kpi-activity" rows="2"
                                              placeholder="<?php echo $isLast ? 'Last hour activity…' : 'Enter work done in this hour…'; ?>"><?php echo htmlspecialchars((string) ($slot['activity'] ?? '')); ?></textarea>
                                <?php endif; ?>
                            </td>
                            <td class="kpi-hour-action">
                                <?php if ($hourDone): ?>
                                    <span class="kpi-hour-done-pill"><i class="fa-solid fa-check"></i> Done</span>
                                <?php else: ?>
                                    <button type="submit" name="action" value="hour" class="btn-primary kpi-hour-submit"
                                            data-slot="<?php echo $idx; ?>" formnovalidate>
                                        <i class="fa-solid fa-paper-plane"></i> Submit
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="kpi-submit-box">
                    <label class="kpi-ack-check">
                        <input type="checkbox" name="responsibility_ack" value="1" id="kpiAck" <?php echo $openHours > 0 ? '' : 'required'; ?>>
                        <span>Based on my best knowledge, the above information is correct, and I confirm its accuracy.</span>
                    </label>
                    <p class="kpi-ack-hint">
                        Darak hour pachhi <strong>Submit</strong> dabavo. Badha hours Submit thay pachhi aa confirmation tick kari ne final <strong>Submit KPI</strong> karo.
                        Final submit pachhi report <strong>next day</strong> thi Past Reports ma show thase.
                        <?php if ($openHours > 0): ?>
                            <br>Pending hours: <strong><?php echo (int) $openHours; ?></strong>
                        <?php endif; ?>
                    </p>
                    <div class="kpi-submit-actions">
                        <button type="submit" name="action" value="draft" class="btn-secondary" formnovalidate>
                            <i class="fa-solid fa-floppy-disk"></i> Save Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn-primary" id="kpiSubmitBtn"
                            <?php echo $openHours > 0 ? 'disabled title="First submit all hours"' : ''; ?>>
                            <i class="fa-solid fa-flag-checkered"></i> Submit KPI (Final)
                        </button>
                    </div>
                </div>
            </form>

            <div class="kpi-hour-modal" id="kpiHourModal" hidden>
                <div class="kpi-hour-modal-backdrop" data-kpi-close></div>
                <div class="kpi-hour-modal-box" role="dialog" aria-modal="true">
                    <div class="kpi-hour-modal-icon"><i class="fa-solid fa-clock"></i></div>
                    <h3 id="kpiHourModalTitle">Hour complete</h3>
                    <p id="kpiHourModalMsg">Aa hour nu activity fill kari Submit karo.</p>
                    <textarea id="kpiHourModalText" class="form-control" rows="3" placeholder="Enter work done in this hour…"></textarea>
                    <div class="kpi-hour-modal-actions">
                        <button type="button" class="btn-secondary" data-kpi-close>Later</button>
                        <button type="button" class="btn-primary" id="kpiHourModalSubmit">
                            <i class="fa-solid fa-paper-plane"></i> Submit Hour
                        </button>
                    </div>
                </div>
            </div>
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

    var slotInput = document.getElementById('kpiSlotIndex');
    var modal = document.getElementById('kpiHourModal');
    var modalText = document.getElementById('kpiHourModalText');
    var modalTitle = document.getElementById('kpiHourModalTitle');
    var modalMsg = document.getElementById('kpiHourModalMsg');
    var activePopupSlot = 0;
    var reminded = {};

    function parseHm(hm) {
        var p = String(hm || '').split(':');
        var h = parseInt(p[0], 10) || 0;
        var m = parseInt(p[1], 10) || 0;
        return h * 60 + m;
    }

    function nowMinutes() {
        var d = new Date();
        return d.getHours() * 60 + d.getMinutes();
    }

    function openModal(slot, from, to) {
        if (!modal) return;
        activePopupSlot = slot;
        if (modalTitle) modalTitle.textContent = 'Hour ' + from + ' – ' + to + ' complete';
        if (modalMsg) modalMsg.textContent = 'Aa hour nu activity fill kari Submit karo.';
        var ta = document.getElementById('kpiAct' + slot);
        if (modalText) modalText.value = ta ? ta.value : '';
        modal.hidden = false;
        document.body.classList.add('kpi-hour-modal-open');
        if (modalText) modalText.focus();
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('kpi-hour-modal-open');
        activePopupSlot = 0;
    }

    function checkDueHours() {
        var now = nowMinutes();
        var rows = form.querySelectorAll('tr[data-slot][data-status="open"]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var slot = parseInt(row.getAttribute('data-slot'), 10);
            var from = row.getAttribute('data-from') || '';
            var to = row.getAttribute('data-to') || '';
            var end = parseHm(to);
            var start = parseHm(from);
            // After hour ends (or within last 5 min of hour), remind once
            var due = now >= end || (now >= start && now >= end - 5);
            if (!due || reminded[slot]) continue;
            reminded[slot] = true;
            openModal(slot, from, to);
            break;
        }
    }

    form.addEventListener('click', function (e) {
        var btn = e.target.closest('.kpi-hour-submit');
        if (!btn) return;
        var slot = parseInt(btn.getAttribute('data-slot'), 10);
        if (slotInput) slotInput.value = String(slot);
        var ta = document.getElementById('kpiAct' + slot);
        if (ta && !String(ta.value || '').trim()) {
            e.preventDefault();
            alert('Please enter activity for this hour before Submit.');
            ta.focus();
        }
    });

    form.addEventListener('submit', function (e) {
        var btn = e.submitter || document.activeElement;
        var action = btn && btn.value ? btn.value : '';
        if (action === 'submit') {
            var ack = document.getElementById('kpiAck');
            if (ack && !ack.checked) {
                e.preventDefault();
                alert('Please confirm: Based on my best knowledge, the above information is correct, and I confirm its accuracy.');
                ack.focus();
            }
        }
    });

    if (modal) {
        modal.querySelectorAll('[data-kpi-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        var submitBtn = document.getElementById('kpiHourModalSubmit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                if (!activePopupSlot) return;
                var text = modalText ? String(modalText.value || '').trim() : '';
                if (!text) {
                    alert('Please enter activity for this hour before Submit.');
                    if (modalText) modalText.focus();
                    return;
                }
                var ta = document.getElementById('kpiAct' + activePopupSlot);
                if (ta) ta.value = text;
                if (slotInput) slotInput.value = String(activePopupSlot);
                // Create synthetic submit via hidden button
                var hourBtn = form.querySelector('.kpi-hour-submit[data-slot="' + activePopupSlot + '"]');
                if (hourBtn) {
                    closeModal();
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(hourBtn);
                    } else {
                        hourBtn.click();
                    }
                }
            });
        }
    }

    checkDueHours();
    setInterval(checkDueHours, 30000);
})();
</script>
