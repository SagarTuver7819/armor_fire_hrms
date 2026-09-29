<?php
/**
 * Recruitment — Interview candidate (full application + status / follow-up)
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
$statusKey = (string) ($row['status'] ?? 'new');

$v = static function ($value, $fallback = '—') {
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
};
$money = static function ($value) {
    if ($value === null || $value === '') {
        return '—';
    }
    return '₹ ' . number_format((float) $value, 0);
};

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
            <?php if ($statusKey === 'selected'): ?>
                <a href="<?php echo app_url('recruitment/offer_letter.php?id=' . (int) $row['id']); ?>" class="btn-primary" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-signature"></i> Offer Letter
                </a>
                <?php
                $pushDeptId = 0;
                if (!empty($row['department_name'])) {
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
                <a href="<?php echo app_url('employees/index.php'); ?>" class="btn-secondary">
                    <i class="fa-solid fa-user-plus"></i> Open Employees
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isDue): ?>
    <div class="rec-iv-alert">
        <i class="fa-solid fa-bell"></i>
        Follow-up due since <strong><?php echo htmlspecialchars(date('d M Y', strtotime((string) $row['next_followup_at']))); ?></strong> — please call &amp; update.
    </div>
    <?php endif; ?>

    <div class="rec-iv-page">
        <!-- Header -->
        <div class="rec-iv-hero">
            <div class="rec-iv-hero-main">
                <div class="rec-iv-avatar"><i class="fa-solid fa-user"></i></div>
                <div>
                    <h1><?php echo htmlspecialchars((string) $row['full_name']); ?></h1>
                    <p class="rec-iv-meta">
                        <span class="rec-iv-code"><?php echo htmlspecialchars((string) $row['application_no']); ?></span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars((string) $row['position_name']); ?></span>
                        <span>·</span>
                        <span><?php echo htmlspecialchars((string) $row['department_name']); ?></span>
                    </p>
                    <p class="rec-iv-sub">
                        <?php if (!empty($row['age_years'])): ?>Age <?php echo (int) $row['age_years']; ?><?php endif; ?>
                        <?php if (!empty($row['total_experience'])): ?>
                            <?php echo !empty($row['age_years']) ? ' · ' : ''; ?>
                            Exp <?php echo htmlspecialchars((string) $row['total_experience']); ?>
                        <?php endif; ?>
                        <?php if (!empty($row['created_at'])): ?>
                            · Applied <?php echo htmlspecialchars(date('d M Y', strtotime((string) $row['created_at']))); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <span class="status-badge status-<?php echo htmlspecialchars($statusKey); ?>">
                <?php echo htmlspecialchars($labels[$statusKey] ?? $statusKey); ?>
            </span>
        </div>

        <!-- Position -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-briefcase"></i><h2>Applied Position</h2></div>
            <div class="rec-iv-grid">
                <div class="rec-iv-item"><span>Department</span><strong><?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Position / Designation</span><strong><?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></strong></div>
            </div>
        </section>

        <!-- Personal -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-id-card"></i><h2>Personal Details</h2></div>
            <div class="rec-iv-grid">
                <div class="rec-iv-item"><span>Mobile</span><strong><?php echo htmlspecialchars($v($row['mobile'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Alternate Mobile</span><strong><?php echo htmlspecialchars($v($row['alt_mobile'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Email</span><strong><?php echo htmlspecialchars($v($row['email'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Date of Birth</span><strong><?php echo !empty($row['dob']) ? htmlspecialchars(date('d M Y', strtotime($row['dob']))) : '—'; ?></strong></div>
                <div class="rec-iv-item"><span>Age</span><strong><?php echo !empty($row['age_years']) ? ((int) $row['age_years'] . ' years') : '—'; ?></strong></div>
                <div class="rec-iv-item"><span>Gender</span><strong><?php echo htmlspecialchars($v($row['gender'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Marital Status</span><strong><?php echo htmlspecialchars($v($row['marital_status'] ?? '')); ?></strong></div>
                <div class="rec-iv-item rec-iv-span-2"><span>Address</span><strong><?php echo nl2br(htmlspecialchars($v($row['address'] ?? ''))); ?></strong></div>
                <div class="rec-iv-item"><span>City</span><strong><?php echo htmlspecialchars($v($row['city'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>State</span><strong><?php echo htmlspecialchars($v($row['state_name'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Pincode</span><strong><?php echo htmlspecialchars($v($row['pincode'] ?? '')); ?></strong></div>
            </div>
        </section>

        <!-- ID & Bank -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-building-columns"></i><h2>ID &amp; Bank Details</h2></div>
            <div class="rec-iv-grid">
                <div class="rec-iv-item"><span>Aadhaar No.</span><strong><?php echo htmlspecialchars($v($row['aadhaar_no'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>PAN No.</span><strong><?php echo htmlspecialchars($v($row['pan_no'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Bank Name</span><strong><?php echo htmlspecialchars($v($row['bank_name'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>Account No.</span><strong><?php echo htmlspecialchars($v($row['bank_account'] ?? '')); ?></strong></div>
                <div class="rec-iv-item"><span>IFSC</span><strong><?php echo htmlspecialchars($v($row['bank_ifsc'] ?? '')); ?></strong></div>
            </div>
        </section>

        <!-- Education -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-graduation-cap"></i><h2>Educational Details</h2></div>
            <?php if (empty($row['education'])): ?>
                <p class="rec-iv-empty">No education details submitted.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr><th>Degree</th><th>Institution</th><th>Specialization</th><th>Year</th><th>% / CGPA</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($row['education'] as $ed): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v($ed['degree'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['institution'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['specialization'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['year_of_passing'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ed['percentage'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Experience -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <h2>Experience Details</h2>
                <?php if (!empty($row['total_experience'])): ?>
                    <span class="rec-iv-chip"><?php echo htmlspecialchars((string) $row['total_experience']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (empty($row['experience'])): ?>
                <p class="rec-iv-empty">No experience details submitted (fresher / blank).</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th><th>Responsibilities</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($row['experience'] as $ex): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v($ex['company_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['designation'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['from_date'] ?? '')); ?></td>
                                <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars($v($ex['to_date'] ?? '')); ?></td>
                                <td><?php echo $money($ex['last_salary'] ?? null); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($v($ex['responsibilities'] ?? '', '—'))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Salary & Docs -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-indian-rupee-sign"></i><h2>Salary &amp; Documents</h2></div>
            <div class="rec-iv-grid">
                <div class="rec-iv-item"><span>Current / Last Salary</span><strong><?php echo $money($row['current_salary'] ?? null); ?></strong></div>
                <div class="rec-iv-item"><span>Expected Salary</span><strong><?php echo $money($row['expected_salary'] ?? null); ?></strong></div>
                <div class="rec-iv-item"><span>Notice Period</span><strong><?php echo htmlspecialchars($v($row['notice_period'] ?? '')); ?></strong></div>
            </div>
            <div class="rec-iv-docs">
                <?php
                $docs = [
                    'Bank Statement' => $row['bank_statement_file'] ?? '',
                    'Salary Slip' => $row['salary_slip_file'] ?? '',
                    'Resume / CV' => $row['resume_file'] ?? '',
                ];
                foreach ($docs as $label => $path):
                    $url = recruitmentPublicPath($path);
                ?>
                    <?php if ($url): ?>
                        <a class="rec-iv-doc" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($label); ?>
                        </a>
                    <?php else: ?>
                        <span class="rec-iv-doc is-empty"><i class="fa-regular fa-file"></i> <?php echo htmlspecialchars($label); ?> — not uploaded</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Interview snapshot -->
        <section class="rec-iv-card">
            <div class="rec-iv-card-head"><i class="fa-solid fa-clipboard-check"></i><h2>Interview Snapshot</h2></div>
            <div class="rec-iv-grid">
                <div class="rec-iv-item"><span>Mode</span><strong><?php
                    $m = (string) ($row['interview_mode'] ?? '');
                    echo htmlspecialchars($modes[$m] ?? $v($m));
                ?></strong></div>
                <div class="rec-iv-item"><span>Interview Date</span><strong><?php echo !empty($row['interview_date']) ? htmlspecialchars(date('d M Y', strtotime($row['interview_date']))) : '—'; ?></strong></div>
                <div class="rec-iv-item"><span>Awaited With</span><strong><?php
                    $aw = (string) ($row['awaited_with'] ?? '');
                    echo htmlspecialchars($sides[$aw] ?? $v($aw));
                ?></strong></div>
                <div class="rec-iv-item"><span>Next Follow-up</span><strong><?php echo !empty($row['next_followup_at']) ? htmlspecialchars(date('d M Y', strtotime($row['next_followup_at']))) : '—'; ?></strong></div>
                <div class="rec-iv-item"><span>Calls Logged</span><strong><?php echo (int) ($row['call_count'] ?? 0); ?></strong></div>
                <div class="rec-iv-item rec-iv-span-2"><span>Not Selected Reason</span><strong><?php echo nl2br(htmlspecialchars($v($row['not_selected_reason'] ?? ''))); ?></strong></div>
                <div class="rec-iv-item rec-iv-span-2"><span>Interview Notes</span><strong><?php echo nl2br(htmlspecialchars($v($row['interview_notes'] ?? ''))); ?></strong></div>
                <div class="rec-iv-item rec-iv-span-2"><span>HR Remarks</span><strong><?php echo nl2br(htmlspecialchars($v($row['hr_remarks'] ?? ''))); ?></strong></div>
            </div>
        </section>

        <?php if ($canEdit): ?>
        <!-- Update status -->
        <section class="rec-iv-card is-action">
            <div class="rec-iv-card-head"><i class="fa-solid fa-pen-to-square"></i><h2>Update Interview Result</h2></div>
            <form method="POST" action="<?php echo app_url('recruitment/interview_save.php'); ?>" class="employee-form">
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
                        <label>Result Status <em style="color:var(--brand);">*</em></label>
                        <select name="status" id="recResultStatus" class="form-control" required>
                            <?php foreach (['new' => 'New Application', 'interview' => 'Interview', 'awaited' => 'Awaited', 'selected' => 'Selected', 'not_selected' => 'Not Selected'] as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $statusKey === $k ? 'selected' : ''; ?>>
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
                        <small>Reminder auto-set after 6 days</small>
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

        <!-- Follow-ups -->
        <section class="rec-iv-card is-action">
            <div class="rec-iv-card-head"><i class="fa-solid fa-phone"></i><h2>Follow-up Calls</h2></div>
            <p class="rec-iv-hint">After each call, next reminder defaults to +6 days (Awaited review).</p>
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
            <div class="table-responsive" style="margin-top:16px;">
                <table class="data-table">
                    <thead>
                    <tr><th>#</th><th>Called At</th><th>Outcome</th><th>Next</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($row['followups'] as $fu): ?>
                        <tr>
                            <td>Call <?php echo (int) $fu['call_no']; ?></td>
                            <td><?php echo htmlspecialchars(date('d M Y H:i', strtotime((string) $fu['call_at']))); ?></td>
                            <td><?php echo htmlspecialchars($v($fu['outcome'] ?? '')); ?></td>
                            <td><?php echo !empty($fu['next_followup_at']) ? htmlspecialchars(date('d M Y', strtotime($fu['next_followup_at']))) : '—'; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($v($fu['notes'] ?? ''))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="rec-iv-empty" style="margin-top:12px;">No follow-up calls logged yet.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>

<style>
.rec-iv-page { max-width: 1100px; display: flex; flex-direction: column; gap: 14px; }
.rec-iv-alert {
    max-width: 1100px;
    background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;
    padding: 12px 14px; border-radius: 12px; font-weight: 700;
    display: flex; gap: 10px; align-items: center;
}
.rec-iv-hero {
    display: flex; justify-content: space-between; gap: 14px; flex-wrap: wrap; align-items: center;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px;
    box-shadow: 0 4px 16px rgba(15,23,42,.04);
}
.rec-iv-hero-main { display: flex; gap: 14px; align-items: center; min-width: 0; }
.rec-iv-avatar {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-soft, #fde8e9); color: var(--brand, #d2232a); font-size: 20px;
}
.rec-iv-hero h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; }
.rec-iv-meta, .rec-iv-sub { margin: 4px 0 0; color: #64748b; font-size: 13px; font-weight: 600; }
.rec-iv-code { color: var(--brand, #d2232a); font-weight: 800; }
.rec-iv-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px;
    box-shadow: 0 4px 16px rgba(15,23,42,.04);
}
.rec-iv-card.is-action { border-color: #fecaca; }
.rec-iv-card-head {
    display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
    padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;
}
.rec-iv-card-head i {
    width: 32px; height: 32px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-soft, #fde8e9); color: var(--brand, #d2232a); font-size: 13px;
}
.rec-iv-card-head h2 {
    margin: 0; font-size: 14px; font-weight: 800; color: #0f172a;
    text-transform: uppercase; letter-spacing: .04em;
}
.rec-iv-chip {
    margin-left: auto; background: #eff6ff; color: #1d4ed8;
    font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px;
}
.rec-iv-grid {
    display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px;
}
.rec-iv-item span {
    display: block; font-size: 11px; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px;
}
.rec-iv-item strong {
    display: block; font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.45;
    word-break: break-word;
}
.rec-iv-span-2 { grid-column: span 2; }
.rec-iv-empty, .rec-iv-hint { margin: 0; color: #94a3b8; font-size: 13px; font-weight: 600; }
.rec-iv-hint { margin-bottom: 12px; color: #64748b; }
.rec-iv-docs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.rec-iv-doc {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 12px; border-radius: 10px; border: 1px solid #fecaca;
    background: #fff5f5; color: #b91c1c; font-size: 13px; font-weight: 700; text-decoration: none;
}
.rec-iv-doc.is-empty { background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }
.status-badge { display:inline-flex;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-interview,.status-review { background:#fef3c7;color:#b45309; }
.status-awaited,.status-shortlisted { background:#ffedd5;color:#c2410c; }
.status-selected,.status-hired { background:#dcfce7;color:#15803d; }
.status-not_selected,.status-rejected { background:#fee2e2;color:#b91c1c; }
@media (max-width: 900px) {
    .rec-iv-grid { grid-template-columns: 1fr 1fr; }
    .rec-iv-span-2 { grid-column: span 2; }
}
@media (max-width: 640px) {
    .rec-iv-grid { grid-template-columns: 1fr; }
    .rec-iv-span-2 { grid-column: auto; }
}
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
