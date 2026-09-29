<?php
/**
 * Employee Voice — submit / edit form
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice()) {
    header('Location: ' . app_url('employee/dashboard.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
$emp = getEmployeeById($empId);
if (!$emp) {
    die('Employee profile not linked.');
}

$editId = (int) ($_GET['id'] ?? 0);
$ticket = null;
$detail = [];
$isEdit = false;

if ($editId > 0) {
    $ticket = evGetTicket($editId);
    if (!$ticket || (int) ($ticket['employee_id'] ?? 0) !== $empId || !evEmployeeCanModifyTicket($ticket)) {
        header('Location: ' . app_url('employee/voice/index.php?msg=edit_locked'));
        exit;
    }
    $isEdit = true;
    $type = (string) $ticket['module_type'];
    $detail = $ticket['detail'] ?? [];
} else {
    $type = strtoupper(trim((string) ($_GET['type'] ?? 'GRIEVANCE')));
}

$types = evModuleTypes();
if (!isset($types[$type])) {
    $type = 'GRIEVANCE';
}
$meta = $types[$type];
$categories = evCategories($type);

$flashError = (string) ($_SESSION['ev_flash_error'] ?? '');
unset($_SESSION['ev_flash_error']);

$pageTitle = $isEdit ? ('Edit · ' . $ticket['ticket_no']) : $meta['label'];
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice';

$v = static function ($arr, $key, $fallback = '') {
    if (!is_array($arr)) {
        return $fallback;
    }
    $val = $arr[$key] ?? $fallback;
    return $val === null ? $fallback : (string) $val;
};

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Employee Voice
        </a>
    </div>

    <?php if ($flashError !== ''): ?>
    <div class="alert alert-error" style="margin-bottom:14px;"><?php echo htmlspecialchars($flashError); ?></div>
    <?php endif; ?>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1 style="color:<?php echo htmlspecialchars($meta['color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                <?php echo $isEdit ? 'Edit ' : ''; ?><?php echo htmlspecialchars($meta['label']); ?>
                <?php if ($isEdit): ?>
                    <small style="font-size:14px;font-weight:700;color:#64748b;">· <?php echo htmlspecialchars((string) $ticket['ticket_no']); ?></small>
                <?php endif; ?>
            </h1>
            <p><?php echo $isEdit ? 'Update your submission. Edit is allowed only until HR progresses the ticket.' : 'Fill the form below. Ticket number will be generated after submit.'; ?></p>
        </div>

        <form method="POST" action="<?php echo app_url('employee/voice/save.php'); ?>" enctype="multipart/form-data" class="employee-form">
            <input type="hidden" name="module_type" value="<?php echo htmlspecialchars($type); ?>">
            <?php if ($isEdit): ?>
            <input type="hidden" name="ticket_id" value="<?php echo (int) $editId; ?>">
            <?php endif; ?>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Employee</label>
                    <input type="text" class="form-control" readonly
                           value="<?php echo htmlspecialchars(($emp['employee_code'] ?? '') . ' — ' . ($emp['employee_name'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" readonly
                           value="<?php echo htmlspecialchars((string) ($emp['department_name'] ?? '—')); ?>">
                </div>
                <div class="form-group">
                    <label>Plant / Location</label>
                    <input type="text" name="location_name" class="form-control" placeholder="Plant / Branch / Area"
                           value="<?php echo htmlspecialchars($v($ticket, 'location_name')); ?>">
                </div>
            </div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Category <span class="req">*</span></label>
                    <select name="category" class="form-control" required>
                        <option value="">Select category</option>
                        <?php
                        $selCat = $v($ticket, 'category');
                        foreach ($categories as $c):
                        ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $selCat === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Confidentiality</label>
                    <select name="confidentiality" class="form-control">
                        <?php
                        $selConf = $v($ticket, 'confidentiality', 'Normal');
                        foreach (evConfidentialityOptions() as $c):
                        ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $selConf === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Attachment<?php echo $isEdit ? ' (optional new)' : ''; ?></label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx">
                    <small class="form-help">JPG/PNG/PDF — max 5 MB</small>
                </div>
            </div>

            <div class="form-group">
                <label>Subject <span class="req">*</span></label>
                <input type="text" name="subject" class="form-control" maxlength="200" required placeholder="Short subject"
                       value="<?php echo htmlspecialchars($v($ticket, 'subject')); ?>">
            </div>
            <div class="form-group">
                <label>Description <span class="req">*</span></label>
                <textarea name="description" class="form-control" rows="5" required placeholder="Describe in detail"><?php echo htmlspecialchars($v($ticket, 'description')); ?></textarea>
            </div>

            <?php if ($type === 'GRIEVANCE'): ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#dc2626;">Complaint details</h3>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Complaint against</label>
                    <select name="complaint_against" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['Employee','Supervisor','Department','Facility','Other'] as $o): ?>
                            <option value="<?php echo $o; ?>" <?php echo $v($detail, 'complaint_against') === $o ? 'selected' : ''; ?>><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Incident date</label>
                    <input type="date" name="incident_date" class="form-control" value="<?php echo htmlspecialchars($v($detail, 'incident_date')); ?>">
                </div>
                <div class="form-group">
                    <label>Incident location</label>
                    <input type="text" name="incident_location" class="form-control" value="<?php echo htmlspecialchars($v($detail, 'incident_location')); ?>">
                </div>
                <div class="form-group">
                    <label>Preferred contact</label>
                    <select name="preferred_contact" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['Phone','Email','In person','No contact'] as $o): ?>
                            <option value="<?php echo $o; ?>" <?php echo $v($detail, 'preferred_contact') === $o ? 'selected' : ''; ?>><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="confidential_handling" value="1" <?php echo !empty($detail['confidential_handling']) ? 'checked' : ''; ?>> Confidential handling required</label>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="immediate_assistance" value="1" <?php echo !empty($detail['immediate_assistance']) ? 'checked' : ''; ?>> Immediate assistance required</label>
                </div>
            </div>
            <div class="form-group">
                <label>Requested resolution</label>
                <textarea name="requested_resolution" class="form-control" rows="3"><?php echo htmlspecialchars($v($detail, 'requested_resolution')); ?></textarea>
            </div>
            <?php elseif ($type === 'SUGGESTION'): ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#16a34a;">Suggestion details</h3>
            <div class="form-group">
                <label>Current problem / process</label>
                <textarea name="current_problem" class="form-control" rows="3"><?php echo htmlspecialchars($v($detail, 'current_problem')); ?></textarea>
            </div>
            <div class="form-group">
                <label>Proposed improvement</label>
                <textarea name="proposed_improvement" class="form-control" rows="3"><?php echo htmlspecialchars($v($detail, 'proposed_improvement')); ?></textarea>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Expected benefit</label>
                    <select name="expected_benefit" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['Cost','Quality','Productivity','Safety','Welfare'] as $o): ?>
                            <option value="<?php echo $o; ?>" <?php echo $v($detail, 'expected_benefit') === $o ? 'selected' : ''; ?>><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estimated cost saving</label>
                    <input type="text" name="estimated_saving" class="form-control" placeholder="Per month / year" value="<?php echo htmlspecialchars($v($detail, 'estimated_saving')); ?>">
                </div>
                <div class="form-group">
                    <label>Estimated implementation cost</label>
                    <input type="text" name="estimated_impl_cost" class="form-control" value="<?php echo htmlspecialchars($v($detail, 'estimated_impl_cost')); ?>">
                </div>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="help_implement" value="1" <?php echo !empty($detail['help_implement']) ? 'checked' : ''; ?>> Interested in helping implement</label>
            </div>
            <?php else: ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#2563eb;">Safety details</h3>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Hazard type</label>
                    <input type="text" name="hazard_type" class="form-control" placeholder="Or use category" value="<?php echo htmlspecialchars($v($detail, 'hazard_type')); ?>">
                </div>
                <div class="form-group">
                    <label>Exact location</label>
                    <input type="text" name="exact_location" class="form-control" value="<?php echo htmlspecialchars($v($detail, 'exact_location')); ?>">
                </div>
                <div class="form-group">
                    <label>Equipment / machine ref</label>
                    <input type="text" name="equipment_ref" class="form-control" value="<?php echo htmlspecialchars($v($detail, 'equipment_ref')); ?>">
                </div>
                <div class="form-group">
                    <label>Risk severity</label>
                    <select name="risk_severity" class="form-control">
                        <?php
                        $selSev = $v($detail, 'risk_severity', 'Medium');
                        foreach (['Low','Medium','High','Critical'] as $o):
                        ?>
                            <option value="<?php echo $o; ?>" <?php echo $selSev === $o ? 'selected' : ''; ?>><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="injury_near_miss" value="1" <?php echo !empty($detail['injury_near_miss']) ? 'checked' : ''; ?>> Injury / near miss occurred</label>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="immediate_danger" value="1" <?php echo !empty($detail['immediate_danger']) ? 'checked' : ''; ?>> Immediate danger present</label>
                </div>
            </div>
            <div class="form-group">
                <label>Immediate action already taken</label>
                <textarea name="immediate_action_taken" class="form-control" rows="3"><?php echo htmlspecialchars($v($detail, 'immediate_action_taken')); ?></textarea>
            </div>
            <?php endif; ?>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn-primary" style="background:<?php echo htmlspecialchars($meta['color']); ?>;">
                    <?php if ($isEdit): ?>
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    <?php else: ?>
                        <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                    <?php endif; ?>
                </button>
                <a href="<?php echo $isEdit ? app_url('employee/voice/view.php?id=' . $editId) : app_url('employee/voice/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
