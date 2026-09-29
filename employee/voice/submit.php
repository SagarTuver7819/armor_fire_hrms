<?php
/**
 * Employee Voice — submit form
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

$type = strtoupper(trim((string) ($_GET['type'] ?? 'GRIEVANCE')));
$types = evModuleTypes();
if (!isset($types[$type])) {
    $type = 'GRIEVANCE';
}
$meta = $types[$type];
$categories = evCategories($type);

$pageTitle = $meta['label'];
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'employee_voice';

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Employee Voice
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1 style="color:<?php echo htmlspecialchars($meta['color']); ?>;">
                <i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i>
                <?php echo htmlspecialchars($meta['label']); ?>
            </h1>
            <p>Fill the form below. Ticket number will be generated after submit.</p>
        </div>

        <form method="POST" action="<?php echo app_url('employee/voice/save.php'); ?>" enctype="multipart/form-data" class="employee-form">
            <input type="hidden" name="module_type" value="<?php echo htmlspecialchars($type); ?>">

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
                    <input type="text" name="location_name" class="form-control" placeholder="Plant / Branch / Area">
                </div>
            </div>

            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Category <span class="req">*</span></label>
                    <select name="category" class="form-control" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Confidentiality</label>
                    <select name="confidentiality" class="form-control">
                        <?php foreach (evConfidentialityOptions() as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Attachment</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx">
                    <small class="form-help">JPG/PNG/PDF — max 5 MB</small>
                </div>
            </div>

            <div class="form-group">
                <label>Subject <span class="req">*</span></label>
                <input type="text" name="subject" class="form-control" maxlength="200" required placeholder="Short subject">
            </div>
            <div class="form-group">
                <label>Description <span class="req">*</span></label>
                <textarea name="description" class="form-control" rows="5" required placeholder="Describe in detail"></textarea>
            </div>

            <?php if ($type === 'GRIEVANCE'): ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#dc2626;">Complaint details</h3>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Complaint against</label>
                    <select name="complaint_against" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['Employee','Supervisor','Department','Facility','Other'] as $o): ?>
                            <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Incident date</label>
                    <input type="date" name="incident_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Incident location</label>
                    <input type="text" name="incident_location" class="form-control">
                </div>
                <div class="form-group">
                    <label>Preferred contact</label>
                    <select name="preferred_contact" class="form-control">
                        <option value="">Select</option>
                        <option value="Phone">Phone</option>
                        <option value="Email">Email</option>
                        <option value="In person">In person</option>
                        <option value="No contact">No contact</option>
                    </select>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="confidential_handling" value="1"> Confidential handling required</label>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="immediate_assistance" value="1"> Immediate assistance required</label>
                </div>
            </div>
            <div class="form-group">
                <label>Requested resolution</label>
                <textarea name="requested_resolution" class="form-control" rows="3"></textarea>
            </div>
            <?php elseif ($type === 'SUGGESTION'): ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#16a34a;">Suggestion details</h3>
            <div class="form-group">
                <label>Current problem / process</label>
                <textarea name="current_problem" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Proposed improvement</label>
                <textarea name="proposed_improvement" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Expected benefit</label>
                    <select name="expected_benefit" class="form-control">
                        <option value="">Select</option>
                        <?php foreach (['Cost','Quality','Productivity','Safety','Welfare'] as $o): ?>
                            <option value="<?php echo $o; ?>"><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estimated cost saving</label>
                    <input type="text" name="estimated_saving" class="form-control" placeholder="Per month / year">
                </div>
                <div class="form-group">
                    <label>Estimated implementation cost</label>
                    <input type="text" name="estimated_impl_cost" class="form-control">
                </div>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="help_implement" value="1"> Interested in helping implement</label>
            </div>
            <?php else: ?>
            <h3 style="margin:18px 0 10px;font-size:15px;color:#2563eb;">Safety details</h3>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Hazard type</label>
                    <input type="text" name="hazard_type" class="form-control" placeholder="Or use category">
                </div>
                <div class="form-group">
                    <label>Exact location</label>
                    <input type="text" name="exact_location" class="form-control">
                </div>
                <div class="form-group">
                    <label>Equipment / machine ref</label>
                    <input type="text" name="equipment_ref" class="form-control">
                </div>
                <div class="form-group">
                    <label>Risk severity</label>
                    <select name="risk_severity" class="form-control">
                        <?php foreach (['Low','Medium','High','Critical'] as $o): ?>
                            <option value="<?php echo $o; ?>" <?php echo $o === 'Medium' ? 'selected' : ''; ?>><?php echo $o; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="injury_near_miss" value="1"> Injury / near miss occurred</label>
                </div>
                <div class="form-group checkbox-group" style="padding-top:28px;">
                    <label><input type="checkbox" name="immediate_danger" value="1"> Immediate danger present</label>
                </div>
            </div>
            <div class="form-group">
                <label>Immediate action already taken</label>
                <textarea name="immediate_action_taken" class="form-control" rows="3"></textarea>
            </div>
            <?php endif; ?>

            <div class="form-actions" style="margin-top:18px;">
                <button type="submit" class="btn-primary" style="background:<?php echo htmlspecialchars($meta['color']); ?>;">
                    <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                </button>
                <a href="<?php echo app_url('employee/voice/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
