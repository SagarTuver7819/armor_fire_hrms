<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/master_helper.php';
require_once __DIR__ . '/../../includes/employee_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
$conn = getDBConnection();
ensureContractorTables($conn);
ensureMasterTables($conn);

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM contractor_employment WHERE id = ? AND status = 1 LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $conn->close();
        header('Location: ' . app_url('contractor/employment/index.php'));
        exit;
    }
}

$employees = getActiveContractorEmployees($conn);
$conn->close();

$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');
$designations = getActiveMasterRows('designations', 'sort_order ASC, name ASC');
$shifts = getActiveMasterRows('shifts', 'name ASC');

$pageTitle = $row ? 'Edit Employment Details' : 'Add Employment Details';
$useSidebar = true;
$sidebarMode = 'contractor';
$sidebarActive = 'contractor_employment';
$extraJs = ['assets/js/subdept_cascade.js'];
require_once __DIR__ . '/../../includes/header.php';

function emVal($row, $key, $default = '')
{
    if (!$row || !isset($row[$key]) || $row[$key] === null) {
        return $default;
    }
    return $row[$key];
}
?>
<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('contractor/employment/index.php'); ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
    <div class="form-page-card">
        <div class="form-page-header">
            <h1><?php echo $row ? 'Edit' : 'Add'; ?> Contractor Employment Details</h1>
            <p>Job posting for a contractor employee</p>
        </div>
        <form method="POST" action="<?php echo app_url('contractor/employment/save.php'); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <div class="form-section">
                <h3><i class="fa-solid fa-briefcase"></i> Employment</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Select Employee <span class="req">*</span></label>
                        <select name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php $eid = (int) emVal($row, 'employee_id', 0); ?>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int) $emp['id']; ?>" <?php echo $eid === (int) $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(contractorEmployeeOptionLabel($emp)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$employees): ?>
                            <small class="form-hint">No Jobwork employees yet. Add them in Join Employee with Pay Type = Jobwork.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Designation Type <span class="req">*</span></label>
                        <?php $dt = emVal($row, 'designation_type', 'worker'); ?>
                        <select name="designation_type" class="form-control" required>
                            <option value="worker" <?php echo $dt === 'worker' ? 'selected' : ''; ?>>worker</option>
                            <option value="staff" <?php echo $dt === 'staff' ? 'selected' : ''; ?>>staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Designation <span class="req">*</span></label>
                        <select name="designation" class="form-control" required>
                            <option value="">Select Designation</option>
                            <?php $curDes = (string) emVal($row, 'designation'); ?>
                            <?php foreach ($designations as $des): ?>
                                <?php $dn = (string) ($des['name'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($dn); ?>" <?php echo $curDes === $dn ? 'selected' : ''; ?>><?php echo htmlspecialchars($dn); ?></option>
                            <?php endforeach; ?>
                            <?php if ($curDes !== '' && !in_array($curDes, array_column($designations, 'name'), true)): ?>
                                <option value="<?php echo htmlspecialchars($curDes); ?>" selected><?php echo htmlspecialchars($curDes); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Department <span class="req">*</span></label>
                        <select name="department_id" class="form-control" required>
                            <option value="">Select Department</option>
                            <?php $did = (int) emVal($row, 'department_id', 0); ?>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int) $dept['id']; ?>" <?php echo $did === (int) $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Sub Department</label>
                        <select name="sub_department_id" class="form-control"
                                data-selected="<?php echo (int) emVal($row, 'sub_department_id', 0); ?>">
                            <option value="">Select Sub Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Process</label>
                        <input type="text" name="process" class="form-control"
                               value="<?php echo htmlspecialchars(emVal($row, 'process')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Joining <span class="req">*</span></label>
                        <input type="date" name="date_of_joining" class="form-control" required
                               value="<?php echo htmlspecialchars(emVal($row, 'date_of_joining')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Confirmation Date</label>
                        <input type="date" name="confirmation_date" class="form-control"
                               value="<?php echo htmlspecialchars(emVal($row, 'confirmation_date')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Employee PF Number</label>
                        <input type="text" name="employee_pf_no" class="form-control"
                               value="<?php echo htmlspecialchars(emVal($row, 'employee_pf_no')); ?>">
                    </div>
                    <div class="form-group">
                        <label>UAN No</label>
                        <input type="text" name="uan_no" class="form-control"
                               value="<?php echo htmlspecialchars(emVal($row, 'uan_no')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Payment Mode <span class="req">*</span></label>
                        <?php $pm = emVal($row, 'payment_mode', 'Bank'); ?>
                        <select name="payment_mode" class="form-control" required>
                            <?php foreach (contractorPaymentModes() as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $pm === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employment Type <span class="req">*</span></label>
                        <?php $et = emVal($row, 'employment_type', 'Contract'); ?>
                        <select name="employment_type" class="form-control" required>
                            <?php foreach (contractorEmploymentTypes() as $k => $lab): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $et === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Select Shift <span class="req">*</span></label>
                        <select name="shift_id" class="form-control" required>
                            <option value="">Select Shift</option>
                            <?php $sid = (int) emVal($row, 'shift_id', 0); ?>
                            <?php foreach ($shifts as $sh): ?>
                                <option value="<?php echo (int) $sh['id']; ?>" <?php echo $sid === (int) $sh['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($sh['name'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Outdoor Attendance <span class="req">*</span></label>
                        <?php $oa = emVal($row, 'outdoor_attendance', 'No'); ?>
                        <select name="outdoor_attendance" class="form-control" required>
                            <option value="No" <?php echo $oa === 'No' ? 'selected' : ''; ?>>No</option>
                            <option value="Yes" <?php echo $oa === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="<?php echo app_url('contractor/employment/index.php'); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>
<script>
    window.SUBDEPT_URL = <?php echo json_encode(app_url('masters/sub_departments/by_department.php')); ?>;
    window.SUBDEPT_SELECTED = <?php echo json_encode((string) emVal($row, 'sub_department_id', '0')); ?>;
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
