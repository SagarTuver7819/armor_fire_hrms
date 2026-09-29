<?php
/**
 * Recruitment — Interview candidate (full-width profile + live criteria marking)
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
$positionName = (string) ($row['position_name'] ?? '');

$criteria = getRecruitmentCriteriaForPosition($positionName);
$marksMap = getRecruitmentApplicationMarks($id);
foreach ($marksMap as $m) {
    $found = false;
    foreach ($criteria as $c) {
        if (strcasecmp((string) $c['criteria_label'], (string) $m['criteria_label']) === 0) {
            $found = true;
            break;
        }
    }
    if (!$found && !empty($m['criteria_label'])) {
        $criteria[] = [
            'id' => (int) ($m['criteria_id'] ?? 0),
            'criteria_label' => $m['criteria_label'],
            'position_name' => $positionName,
        ];
    }
}

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
$show = static function ($label, $value) use ($v) {
    echo '<div class="view-row"><span>' . htmlspecialchars($label) . '</span><strong>' . $value . '</strong></div>';
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
        $toast = 'Follow-up call saved.';
    } elseif ($_GET['msg'] === 'criteria') {
        $toast = 'Interview criteria saved.';
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
            <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $row['id']); ?>" class="btn-secondary" target="_blank" rel="noopener">
                <i class="fa-solid fa-file-pdf"></i> Print / PDF
            </a>
            <?php if ($statusKey === 'selected'): ?>
                <a href="<?php echo app_url('recruitment/offer_letter.php?id=' . (int) $row['id']); ?>" class="btn-primary" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-signature"></i> Offer Letter
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isDue): ?>
    <div class="rec-iv-alert">
        <i class="fa-solid fa-bell"></i>
        Follow-up due since <strong><?php echo htmlspecialchars(formatDateDisplay((string) $row['next_followup_at'])); ?></strong>
    </div>
    <?php endif; ?>

    <!-- Profile hero (employee-style) -->
    <section class="emp-id-card rec-iv-id">
        <div class="emp-id-top">
            <div class="emp-id-identity">
                <div class="emp-avatar emp-avatar--profile" aria-hidden="true">
                    <span><?php
                        $parts = preg_split('/\s+/', trim((string) $row['full_name']));
                        $ini = strtoupper(substr($parts[0] ?? 'C', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
                        echo htmlspecialchars($ini);
                    ?></span>
                </div>
                <div class="emp-id-copy">
                    <div class="emp-id-tags">
                        <span class="status-badge status-<?php echo htmlspecialchars($statusKey); ?>">
                            <?php echo htmlspecialchars($labels[$statusKey] ?? $statusKey); ?>
                        </span>
                    </div>
                    <div class="emp-id-title-row">
                        <strong class="emp-id-code"><?php echo htmlspecialchars((string) $row['application_no']); ?></strong>
                        <h1><?php echo htmlspecialchars((string) $row['full_name']); ?></h1>
                    </div>
                    <p class="emp-id-role">
                        <span><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></span>
                        <span class="sep">|</span>
                        <span><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></span>
                    </p>
                </div>
            </div>
            <div class="emp-id-actions">
                <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $row['id']); ?>" target="_blank" class="btn-ghost">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </a>
                <?php if ($canEdit): ?>
                <a href="#recCriteria" class="btn-primary">
                    <i class="fa-solid fa-list-check"></i> Interview Marking
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="emp-id-stats">
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-cake-candles"></i></div>
                <div><span>Age</span><strong><?php echo !empty($row['age_years']) ? ((int) $row['age_years'] . ' years') : '—'; ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div><span>Experience</span><strong><?php echo htmlspecialchars($v($row['total_experience'] ?? '')); ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div><span>Expected</span><strong><?php echo $money($row['expected_salary'] ?? null); ?></strong></div>
            </div>
            <div class="emp-stat-item">
                <div class="emp-stat-icon"><i class="fa-solid fa-calendar"></i></div>
                <div><span>Applied</span><strong><?php echo !empty($row['created_at']) ? htmlspecialchars(formatDateDisplay($row['created_at'])) : '—'; ?></strong></div>
            </div>
        </div>
    </section>

    <!-- Full-width section cards -->
    <div class="view-grid rec-iv-grid-full">
        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <div><h3>Applied Position</h3><p>Department &amp; role applied for</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Department', htmlspecialchars($v($row['department_name'] ?? '')));
                $show('Position / Designation', htmlspecialchars($v($row['position_name'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-phone"></i>
                <div><h3>Contact</h3><p>Mobile &amp; email</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Mobile', htmlspecialchars($v($row['mobile'] ?? '')));
                $show('Alternate Mobile', htmlspecialchars($v($row['alt_mobile'] ?? '')));
                $show('Email', htmlspecialchars($v($row['email'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-id-card"></i>
                <div><h3>Personal Details</h3><p>Identity &amp; address</p></div>
            </div>
            <div class="view-body view-body-3">
                <?php
                $show('Date of Birth', !empty($row['dob']) ? htmlspecialchars(formatDateDisplay($row['dob'])) : '—');
                $show('Age', !empty($row['age_years']) ? ((int) $row['age_years'] . ' years') : '—');
                $show('Gender', htmlspecialchars($v($row['gender'] ?? '')));
                $show('Marital Status', htmlspecialchars($v($row['marital_status'] ?? '')));
                $show('City', htmlspecialchars($v($row['city'] ?? '')));
                $show('State', htmlspecialchars($v($row['state_name'] ?? '')));
                $show('Pincode', htmlspecialchars($v($row['pincode'] ?? '')));
                echo '<div class="view-row view-row-full"><span>Address</span><strong>' . nl2br(htmlspecialchars($v($row['address'] ?? ''))) . '</strong></div>';
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-building-columns"></i>
                <div><h3>ID &amp; Bank</h3><p>Aadhaar · PAN · Bank</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Aadhaar No.', htmlspecialchars($v($row['aadhaar_no'] ?? '')));
                $show('PAN No.', htmlspecialchars($v($row['pan_no'] ?? '')));
                $show('Bank Name', htmlspecialchars($v($row['bank_name'] ?? '')));
                $show('Account No.', htmlspecialchars($v($row['bank_account'] ?? '')));
                $show('IFSC', htmlspecialchars($v($row['bank_ifsc'] ?? '')));
                ?>
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-indian-rupee-sign"></i>
                <div><h3>Salary &amp; Documents</h3><p>Expectation &amp; uploads</p></div>
            </div>
            <div class="view-body">
                <?php
                $show('Current / Last Salary', $money($row['current_salary'] ?? null));
                $show('Expected Salary', $money($row['expected_salary'] ?? null));
                $show('Notice Period', htmlspecialchars($v($row['notice_period'] ?? '')));
                ?>
                <div class="rec-iv-docs">
                    <?php
                    $docs = [
                        'Bank Statement' => $row['bank_statement_file'] ?? '',
                        'Salary Slip' => $row['salary_slip_file'] ?? '',
                        'Resume' => $row['resume_file'] ?? '',
                    ];
                    foreach ($docs as $lab => $path):
                        $url = recruitmentPublicPath($path);
                    ?>
                        <?php if ($url): ?>
                            <a class="rec-iv-doc" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-paperclip"></i> <?php echo htmlspecialchars($lab); ?>
                            </a>
                        <?php else: ?>
                            <span class="rec-iv-doc is-empty"><?php echo htmlspecialchars($lab); ?> — N/A</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-graduation-cap"></i>
                <div><h3>Educational Details</h3><p>Qualifications submitted</p></div>
            </div>
            <div class="view-body">
                <?php if (empty($row['education'])): ?>
                    <p class="rec-iv-empty">No education details.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Degree</th><th>Institution</th><th>Specialization</th><th>Year</th><th>% / CGPA</th></tr></thead>
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
            </div>
        </div>

        <div class="view-card view-card-wide">
            <div class="view-card-head">
                <i class="fa-solid fa-briefcase"></i>
                <div><h3>Experience Details</h3><p><?php echo htmlspecialchars($v($row['total_experience'] ?? '—')); ?></p></div>
            </div>
            <div class="view-body">
                <?php if (empty($row['experience'])): ?>
                    <p class="rec-iv-empty">No experience details.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($row['experience'] as $ex): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($v($ex['company_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['designation'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($v($ex['from_date'] ?? '')); ?></td>
                                <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars($v($ex['to_date'] ?? '')); ?></td>
                                <td><?php echo $money($ex['last_salary'] ?? null); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($v($ex['responsibilities'] ?? ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEdit): ?>
        <!-- Live interview matrix -->
        <div class="view-card view-card-wide" id="recCriteria">
            <div class="view-card-head">
                <i class="fa-solid fa-list-check"></i>
                <div>
                    <h3>Interview Running · HR Matrix</h3>
                    <p>Fill Yes/No · Score · Notes while interview is going on — included in PDF</p>
                </div>
            </div>
            <div class="view-body">
                <form method="POST" action="<?php echo app_url('recruitment/criteria_save.php'); ?>" class="employee-form">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="position_name" value="<?php echo htmlspecialchars($positionName); ?>">

                    <div class="rec-matrix">
                        <?php foreach ($criteria as $idx => $c):
                            $label = (string) $c['criteria_label'];
                            $key = strtolower(trim($label));
                            $type = (string) ($c['answer_type'] ?? 'yesno');
                            $mark = $marksMap[$key] ?? [];
                            $cid = (int) ($c['id'] ?? 0);
                            $isPos = trim((string) ($c['position_name'] ?? '')) !== '';
                            $answer = trim((string) ($mark['answer_value'] ?? ''));
                            $scoreVal = $mark['score_value'] ?? '';
                            if ($type === 'score' && $scoreVal === '' && $answer !== '' && is_numeric($answer)) {
                                $scoreVal = $answer;
                            }
                            $filled = ($type === 'yesno' && ($answer === 'Yes' || $answer === 'No'))
                                || ($type === 'score' && $scoreVal !== '' && $scoreVal !== null)
                                || ($type === 'text' && $answer !== '');
                        ?>
                        <div class="rec-matrix-row <?php echo $filled ? 'is-filled' : ''; ?>">
                            <div class="rec-matrix-q">
                                <span class="rec-matrix-no"><?php echo (int) $idx + 1; ?></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($label); ?></strong>
                                    <small><?php echo $isPos ? 'Position' : 'HR Round'; ?> · <?php echo htmlspecialchars(strtoupper($type)); ?></small>
                                </div>
                            </div>
                            <div class="rec-matrix-a">
                                <input type="hidden" name="mark[<?php echo $idx; ?>][label]" value="<?php echo htmlspecialchars($label); ?>">
                                <input type="hidden" name="mark[<?php echo $idx; ?>][criteria_id]" value="<?php echo $cid; ?>">
                                <input type="hidden" name="mark[<?php echo $idx; ?>][type]" value="<?php echo htmlspecialchars($type); ?>">
                                <?php if ($type === 'yesno'): ?>
                                    <select name="mark[<?php echo $idx; ?>][answer]" class="form-control">
                                        <option value="">—</option>
                                        <option value="Yes" <?php echo $answer === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="No" <?php echo $answer === 'No' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                <?php elseif ($type === 'score'): ?>
                                    <div class="rec-score-wrap">
                                        <input type="number" name="mark[<?php echo $idx; ?>][score]" class="form-control" min="0" max="10" step="1"
                                               value="<?php echo $scoreVal !== '' && $scoreVal !== null ? (int) $scoreVal : ''; ?>" placeholder="0–10">
                                        <span>/ 10</span>
                                    </div>
                                <?php else: ?>
                                    <textarea name="mark[<?php echo $idx; ?>][answer]" class="form-control" rows="2" placeholder="Write answer…"><?php echo htmlspecialchars($answer); ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-grid form-grid-3" style="margin-top:16px;">
                        <div class="form-group">
                            <label>Add Extra Question</label>
                            <input type="text" name="new_criteria" class="form-control" placeholder="Custom criteria / question">
                        </div>
                        <div class="form-group">
                            <label>Answer Type</label>
                            <select name="new_type" class="form-control">
                                <option value="text">Text</option>
                                <option value="yesno">Yes / No</option>
                                <option value="score">Score / 10</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Save For</label>
                            <select name="new_scope" class="form-control">
                                <option value="position">This Position (<?php echo htmlspecialchars($positionName ?: 'current'); ?>)</option>
                                <option value="global">All Positions (HR Matrix)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-check-double"></i> Save Interview Matrix</button>
                        <a href="<?php echo app_url('recruitment/pdf.php?id=' . (int) $row['id']); ?>" class="btn-secondary" target="_blank">
                            <i class="fa-solid fa-file-pdf"></i> Generate PDF
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Status update -->
        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-pen-to-square"></i>
                <div><h3>Update Interview Result</h3><p>Selected · Not Selected · Awaited</p></div>
            </div>
            <div class="view-body">
                <form method="POST" action="<?php echo app_url('recruitment/interview_save.php'); ?>" class="employee-form">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label>Interview Mode</label>
                            <select name="interview_mode" class="form-control">
                                <option value="">Select mode</option>
                                <?php foreach ($modes as $k => $lab): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($row['interview_mode'] ?? '') === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Interview Date</label>
                            <input type="date" name="interview_date" class="form-control" value="<?php echo htmlspecialchars((string) ($row['interview_date'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Result Status *</label>
                            <select name="status" id="recResultStatus" class="form-control" required>
                                <?php foreach (['new' => 'New Application', 'interview' => 'Interview', 'awaited' => 'Awaited', 'selected' => 'Selected', 'not_selected' => 'Not Selected'] as $k => $lab): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $statusKey === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="recAwaitedWrap">
                            <label>Awaited With</label>
                            <select name="awaited_with" class="form-control">
                                <option value="">Select</option>
                                <?php foreach ($sides as $k => $lab): ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>" <?php echo ($row['awaited_with'] ?? '') === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                                <?php endforeach; ?>
                            </select>
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
            </div>
        </div>

        <div class="view-card">
            <div class="view-card-head">
                <i class="fa-solid fa-phone-volume"></i>
                <div><h3>Follow-up Calls</h3><p>After call · +6 days reminder</p></div>
            </div>
            <div class="view-body">
                <form method="POST" action="<?php echo app_url('recruitment/followup_save.php'); ?>" class="employee-form">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <div class="form-grid form-grid-2">
                        <div class="form-group">
                            <label>Call Date &amp; Time</label>
                            <input type="datetime-local" name="call_at" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Next Follow-up</label>
                            <input type="date" name="next_followup_at" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('+6 days'))); ?>">
                        </div>
                        <div class="form-group">
                            <label>Outcome</label>
                            <select name="outcome" class="form-control">
                                <option value="Connected">Connected</option>
                                <option value="Not Reachable">Not Reachable</option>
                                <option value="Callback Requested">Callback Requested</option>
                                <option value="No Answer">No Answer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-phone"></i> Save Call</button>
                    </div>
                </form>
                <?php if (!empty($row['followups'])): ?>
                <div class="table-responsive" style="margin-top:12px;">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>When</th><th>Outcome</th><th>Next</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($row['followups'] as $fu): ?>
                            <tr>
                                <td><?php echo (int) $fu['call_no']; ?></td>
                                <td><?php echo htmlspecialchars(formatDateTimeDisplay((string) $fu['call_at'])); ?></td>
                                <td><?php echo htmlspecialchars($v($fu['outcome'] ?? '')); ?></td>
                                <td><?php echo !empty($fu['next_followup_at']) ? htmlspecialchars(formatDateDisplay($fu['next_followup_at'])) : '—'; ?></td>
                                <td><?php echo nl2br(htmlspecialchars($v($fu['notes'] ?? ''))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.rec-iv-alert {
    background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;
    padding: 12px 14px; border-radius: 12px; font-weight: 700; margin-bottom: 14px;
}
.rec-iv-id { margin-bottom: 16px; }
.rec-iv-grid-full { width: 100%; max-width: none; }
.view-body { padding: 12px 16px 16px; }
.view-body-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; }
.view-row { display: flex; flex-direction: column; gap: 2px; padding: 6px 0; border-bottom: 1px dashed #f1f5f9; }
.view-row span { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .03em; }
.view-row strong { font-size: 14px; font-weight: 700; color: #0f172a; line-height: 1.4; word-break: break-word; }
.view-row-full { grid-column: 1 / -1; }
.rec-iv-docs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.rec-iv-doc {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 9px;
    border: 1px solid #fecaca; background: #fff5f5; color: #b91c1c; font-size: 12px; font-weight: 700; text-decoration: none;
}
.rec-iv-doc.is-empty { background: #f8fafc; border-color: #e2e8f0; color: #94a3b8; }
.rec-iv-empty { margin: 0; color: #94a3b8; font-weight: 600; }
.rec-matrix { display: flex; flex-direction: column; gap: 10px; }
.rec-matrix-row {
    display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(180px, 0.8fr);
    gap: 12px; align-items: start;
    padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
}
.rec-matrix-row.is-filled { border-color: #fecaca; background: #fffafa; }
.rec-matrix-q { display: flex; gap: 10px; align-items: flex-start; }
.rec-matrix-no {
    width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
    background: #fee2e2; color: #b91c1c; font-size: 12px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
}
.rec-matrix-q strong { display: block; font-size: 13px; color: #0f172a; line-height: 1.35; }
.rec-matrix-q small { color: #94a3b8; font-weight: 600; font-size: 11px; }
.rec-matrix-a .form-control { width: 100%; }
.rec-score-wrap { display: flex; align-items: center; gap: 8px; }
.rec-score-wrap input { max-width: 100px; }
.rec-score-wrap span { font-weight: 700; color: #64748b; }
.form-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 900px) {
    .view-body-3, .rec-matrix-row, .form-grid-3 { grid-template-columns: 1fr; }
}
.status-badge { display:inline-flex;padding:5px 11px;border-radius:999px;font-size:11px;font-weight:800; }
.status-new { background:#dbeafe;color:#1d4ed8; }
.status-interview { background:#fef3c7;color:#b45309; }
.status-awaited { background:#ffedd5;color:#c2410c; }
.status-selected { background:#dcfce7;color:#15803d; }
.status-not_selected { background:#fee2e2;color:#b91c1c; }
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
