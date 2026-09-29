<?php
/**
 * Recruitment — Interview candidate detail + status / follow-up
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row) {
    header('Location: ' . app_url('recruitment/interviews.php'));
    exit;
}

$labels = recruitmentStatusLabels();
$modes = recruitmentInterviewModes();
$sides = recruitmentAwaitedSides();
$canEdit = isAdmin() || isHR() || (function_exists('isStaffUser') && isStaffUser()) || canAccess('recruitment', 'edit');
$isDue = recruitmentIsFollowupDue($row);

$pageTitle = 'Interview · ' . $row['application_no'];
$useSidebar = true;
$sidebarMode = 'recruitment';
$sidebarActive = 'recruitment_interview';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];

require_once __DIR__ . '/../includes/header.php';

$toast = '';
$toastType = 'success';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $toast = 'Interview status updated.';
    } elseif ($_GET['msg'] === 'followup') {
        $toast = 'Follow-up call saved. Next reminder set.';
    } elseif ($_GET['msg'] === 'error') {
        $toast = (string) ($_GET['err'] ?? 'Update failed.');
        $toastType = 'error';
    }
}
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('recruitment/interviews.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Interview Candidates
        </a>
        <div class="toolbar-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if (($row['status'] ?? '') === 'selected'): ?>
                            <a href="<?php echo app_url('recruitment/offer_letter.php?id=' . (int) $row['id']); ?>" class="btn-primary" target="_blank">
                                <i class="fa-solid fa-file-signature"></i> Offer Letter
                            </a>
                            <?php
                            $pushDeptId = 0;
                            if (!empty($row['department_name']) && function_exists('getActiveMasterRows')) {
                                require_once __DIR__ . '/../includes/master_helper.php';
                                ensureMasterTables();
                                foreach (getActiveMasterRows('departments', 'department_name ASC') as $d) {
                                    if (strcasecmp(trim((string) ($d['department_name'] ?? '')), trim((string) $row['department_name'])) === 0) {
                                        $pushDeptId = (int) $d['id'];
                                        break;
                                    }
                                }
                            }
                            if ($pushDeptId > 0):
                            ?>
                            <a href="<?php echo app_url('employees/edit.php?department_id=' . $pushDeptId . '&from=recruitment&rec_id=' . (int) $row['id']); ?>" class="btn-secondary">
                                <i class="fa-solid fa-user-plus"></i> Push to HRMS Employee
                            </a>
                            <?php else: ?>
                            <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary" title="Select department then Add Employee">
                                <i class="fa-solid fa-user-plus"></i> Open Employees
                            </a>
                            <?php endif; ?>
            <?php endif; ?>
            <a href="<?php echo app_url('recruitment/view.php?id=' . (int) $row['id']); ?>" class="btn-secondary">
                <i class="fa-solid fa-eye"></i> Full Application
            </a>
        </div>
    </div>

    <?php if ($isDue): ?>
    <div class="rec-due-banner">
        <i class="fa-solid fa-bell"></i>
        Follow-up due since <?php echo htmlspecialchars(date('d M Y', strtotime((string) $row['next_followup_at']))); ?> — please call &amp; update status.
    </div>
    <?php endif; ?>

    <div class="form-page-card" style="max-width:1100px;">
        <div class="form-page-header" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start;">
            <div>
                <h1><?php echo htmlspecialchars((string) $row['full_name']); ?></h1>
                <p>
                    <?php echo htmlspecialchars((string) $row['application_no']); ?> ·
                    <?php echo htmlspecialchars((string) $row['position_name']); ?> ·
                    <?php echo htmlspecialchars((string) $row['department_name']); ?>
                    <?php if (!empty($row['age_years'])): ?> · Age <?php echo (int) $row['age_years']; ?><?php endif; ?>
                    <?php if (!empty($row['total_experience'])): ?> · Exp <?php echo htmlspecialchars((string) $row['total_experience']); ?><?php endif; ?>
                </p>
            </div>
            <span class="status-badge status-<?php echo htmlspecialchars((string) $row['status']); ?>">
                <?php echo htmlspecialchars($labels[$row['status']] ?? $row['status']); ?>
            </span>
        </div>

        <div class="rec-view-grid">
            <section>
                <h3>Contact &amp; ID</h3>
                <dl class="rec-dl">
                    <div><dt>Mobile</dt><dd><?php echo htmlspecialchars((string) $row['mobile']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo htmlspecialchars((string) $row['email']); ?></dd></div>
                    <div><dt>Aadhaar</dt><dd><?php echo htmlspecialchars((string) ($row['aadhaar_no'] ?: '—')); ?></dd></div>
                    <div><dt>PAN</dt><dd><?php echo htmlspecialchars((string) ($row['pan_no'] ?: '—')); ?></dd></div>
                    <div><dt>Bank</dt><dd><?php echo htmlspecialchars((string) ($row['bank_name'] ?: '—')); ?></dd></div>
                    <div><dt>A/C</dt><dd><?php echo htmlspecialchars((string) ($row['bank_account'] ?: '—')); ?></dd></div>
                    <div><dt>IFSC</dt><dd><?php echo htmlspecialchars((string) ($row['bank_ifsc'] ?: '—')); ?></dd></div>
                    <div><dt>Expected</dt><dd><?php echo $row['expected_salary'] !== null ? '₹ ' . number_format((float) $row['expected_salary'], 0) : '—'; ?></dd></div>
                </dl>
            </section>
            <section>
                <h3>Interview snapshot</h3>
                <dl class="rec-dl">
                    <div><dt>Mode</dt><dd><?php
                        $m = (string) ($row['interview_mode'] ?? '');
                        echo htmlspecialchars($modes[$m] ?? ($m ?: '—'));
                    ?></dd></div>
                    <div><dt>Date</dt><dd><?php echo !empty($row['interview_date']) ? htmlspecialchars(date('d M Y', strtotime($row['interview_date']))) : '—'; ?></dd></div>
                    <div><dt>Awaited with</dt><dd><?php
                        $aw = (string) ($row['awaited_with'] ?? '');
                        echo htmlspecialchars($sides[$aw] ?? ($aw ?: '—'));
                    ?></dd></div>
                    <div><dt>Next follow-up</dt><dd><?php echo !empty($row['next_followup_at']) ? htmlspecialchars(date('d M Y', strtotime($row['next_followup_at']))) : '—'; ?></dd></div>
                    <div><dt>Calls</dt><dd><?php echo (int) ($row['call_count'] ?? 0); ?></dd></div>
                    <div class="full"><dt>Not selected reason</dt><dd><?php echo nl2br(htmlspecialchars((string) ($row['not_selected_reason'] ?: '—'))); ?></dd></div>
                </dl>
            </section>
        </div>

        <?php if ($canEdit): ?>
        <section style="margin-top:22px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <h3>Update Interview Result</h3>
            <form method="POST" action="<?php echo app_url('recruitment/interview_save.php'); ?>" class="employee-form" id="recInterviewForm">
                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Interview Mode</label>
                        <select name="interview_mode" class="form-control">
                            <option value="">Select mode</option>
                            <?php foreach ($modes as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($row['interview_mode'] ?? '') === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Interview Date</label>
                        <input type="date" name="interview_date" class="form-control"
                               value="<?php echo htmlspecialchars((string) ($row['interview_date'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Result Status <em style="color:#d2232a;">*</em></label>
                        <select name="status" id="recResultStatus" class="form-control" required>
                            <?php foreach (['interview' => 'Interview', 'awaited' => 'Awaited', 'selected' => 'Selected', 'not_selected' => 'Not Selected'] as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($row['status'] ?? '') === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="recAwaitedWrap">
                        <label>Awaited With</label>
                        <select name="awaited_with" class="form-control">
                            <option value="">Select</option>
                            <?php foreach ($sides as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($row['awaited_with'] ?? '') === $k ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lab); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:#64748b;">Reminder auto-set after 6 days</small>
                    </div>
                    <div class="form-group" id="recNotSelWrap" style="grid-column:1/-1;">
                        <label>Reason for Not Selected</label>
                        <textarea name="not_selected_reason" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($row['not_selected_reason'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Interview Notes</label>
                        <textarea name="interview_notes" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($row['interview_notes'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>HR Remarks</label>
                        <textarea name="hr_remarks" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($row['hr_remarks'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
                </div>
            </form>
        </section>

        <section style="margin-top:22px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <h3>Follow-up Calls</h3>
            <p style="margin:0 0 12px;color:#64748b;font-size:13px;">
                First call pachi next reminder default 6 days — Awaited candidates mate review reminder.
            </p>
            <form method="POST" action="<?php echo app_url('recruitment/followup_save.php'); ?>" class="employee-form">
                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label>Call Date &amp; Time</label>
                        <input type="datetime-local" name="call_at" class="form-control"
                               value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Next Follow-up Date</label>
                        <input type="date" name="next_followup_at" class="form-control"
                               value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('+6 days'))); ?>">
                    </div>
                    <div class="form-group">
                        <label>Call Outcome</label>
                        <select name="outcome" class="form-control">
                            <option value="Connected">Connected</option>
                            <option value="Not Reachable">Not Reachable</option>
                            <option value="Callback Requested">Callback Requested</option>
                            <option value="No Answer">No Answer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Call Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="What was discussed…"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-phone"></i> Save Follow-up Call</button>
                </div>
            </form>

            <?php if (!empty($row['followups'])): ?>
            <div class="table-responsive" style="margin-top:14px;">
                <table class="data-table">
                    <thead>
                    <tr><th>#</th><th>Called At</th><th>Outcome</th><th>Next</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($row['followups'] as $fu): ?>
                        <tr>
                            <td>Call <?php echo (int) $fu['call_no']; ?></td>
                            <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime((string) $fu['call_at']))); ?></td>
                            <td><?php echo htmlspecialchars((string) ($fu['outcome'] ?: '—')); ?></td>
                            <td><?php echo !empty($fu['next_followup_at']) ? htmlspecialchars(date('d M Y', strtotime($fu['next_followup_at']))) : '—'; ?></td>
                            <td><?php echo nl2br(htmlspecialchars((string) ($fu['notes'] ?: '—'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="muted" style="margin-top:10px;">No follow-up calls logged yet.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>

<style>
.rec-view-grid{display:grid;grid-template-columns:1.1fr.9fr;gap:18px}
.rec-view-grid h3,.form-page-card h3{margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.rec-dl{margin:0;display:grid;grid-template-columns:1fr 1fr;gap:10px 14px}
.rec-dl .full{grid-column:1/-1}
.rec-dl dt{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase}
.rec-dl dd{margin:2px 0 0;font-size:14px;font-weight:600;color:#0f172a}
.rec-due-banner{background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:12px 14px;border-radius:12px;margin-bottom:14px;font-weight:700}
.status-badge{display:inline-flex;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:800}
.status-new{background:#dbeafe;color:#1d4ed8}
.status-interview{background:#fef3c7;color:#b45309}
.status-awaited{background:#ffedd5;color:#c2410c}
.status-selected{background:#dcfce7;color:#15803d}
.status-not_selected{background:#fee2e2;color:#b91c1c}
.muted{color:#94a3b8}
@media(max-width:860px){.rec-view-grid{grid-template-columns:1fr}.rec-dl{grid-template-columns:1fr}}
</style>
<script>
(function(){
  var sel=document.getElementById('recResultStatus');
  var aw=document.getElementById('recAwaitedWrap');
  var ns=document.getElementById('recNotSelWrap');
  function sync(){
    var v=sel?sel.value:'';
    if(aw) aw.style.display = (v==='awaited') ? '' : 'none';
    if(ns) ns.style.display = (v==='not_selected') ? '' : 'none';
  }
  if(sel){ sel.addEventListener('change', sync); sync(); }
})();
</script>

<?php
$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js'];
require_once __DIR__ . '/../includes/footer.php';
?>
<?php if ($toast !== ''): ?>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right' };
toastr[<?php echo json_encode($toastType); ?>](<?php echo json_encode($toast); ?>);
</script>
<?php endif; ?>
