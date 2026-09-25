<?php
/**
 * Add / Edit Policy (HR / Admin)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/policy_helper.php';

requireStaff();
ensurePolicyTables();
require_once __DIR__ . '/../includes/permission_helper.php';

$id = (int) ($_GET['id'] ?? 0);
requireAccess('policies', $id > 0 ? 'edit' : 'add');
$row = $id > 0 ? getPolicyById($id) : null;
if ($id > 0 && !$row) {
    header('Location: ' . app_url('policies/index.php?msg=error&err=' . rawurlencode('Policy not found.')));
    exit;
}

$departments = getActiveMasterRows('departments', 'sort_order ASC, department_name ASC');

$formError = '';
$formData = [];
if (!empty($_SESSION['policy_form']) && is_array($_SESSION['policy_form'])) {
    $formError = (string) ($_SESSION['policy_form']['error'] ?? '');
    $formData = is_array($_SESSION['policy_form']['data'] ?? null)
        ? $_SESSION['policy_form']['data']
        : [];
    unset($_SESSION['policy_form']);
}

$val = static function ($key, $fallback = '') use ($formData, $row) {
    if (array_key_exists($key, $formData)) {
        return (string) $formData[$key];
    }
    if ($row && array_key_exists($key, $row) && $row[$key] !== null) {
        return (string) $row[$key];
    }
    return (string) $fallback;
};

if (array_key_exists('apply_all_departments', $formData)) {
    $applyAll = (int) $formData['apply_all_departments'] === 1;
} elseif ($row) {
    $applyAll = !empty($row['apply_all_departments']);
} else {
    $applyAll = true; // default All Departments
}

if (array_key_exists('department_ids', $formData) && is_array($formData['department_ids'])) {
    $selectedDeptIds = array_map('intval', $formData['department_ids']);
} elseif ($row) {
    $selectedDeptIds = array_map('intval', $row['department_ids'] ?? []);
} else {
    $selectedDeptIds = [];
}
$selectedDeptLookup = array_fill_keys($selectedDeptIds, true);

$pageTitle = $row ? 'Edit Policy' : 'Add Policy';
$useSidebar = true;
$sidebarMode = 'workspace';
$sidebarActive = 'policies';
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css'];
$extraJs = ['assets/js/policy_form.js'];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('policies/index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Policies
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background:#7C3AED;">
                    <i class="fa-solid fa-scroll"></i>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Scan hard-copy → PDF · Choose departments (All or multi-select) · Dates</p>
                </div>
            </div>
        </div>

        <?php if ($formError !== ''): ?>
            <div class="form-alert form-alert-error" role="alert" style="margin:0 0 16px;padding:12px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-weight:600;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($formError); ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?php echo app_url('policies/save.php'); ?>"
              class="policy-form"
              enctype="multipart/form-data"
              autocomplete="off"
              id="policyForm">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="form-section">
                <div class="form-grid form-grid-3">
                    <div class="form-group span-2">
                        <label for="policyTitle">Title <span class="req">*</span></label>
                        <input type="text" name="policy_title" id="policyTitle" class="form-control" required maxlength="255"
                               placeholder="e.g. Leave Policy Update / Office Timing"
                               value="<?php echo htmlspecialchars($val('title')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="policyNo">Policy No</label>
                        <input type="text" name="policy_no" id="policyNo" class="form-control" maxlength="100"
                               placeholder="Optional ref no."
                               value="<?php echo htmlspecialchars($val('policy_no')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="policyDate">Policy Date <span class="req">*</span> <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="policy_date" id="policyDate" class="form-control js-date" required
                               placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue($val('policy_date', date('Y-m-d')))); ?>">
                        <small class="form-hint">Date printed / written on the policy</small>
                    </div>
                    <div class="form-group">
                        <label for="addedDate">Added On <span class="req">*</span> <small>(DD-MM-YYYY)</small></label>
                        <input type="text" name="added_date" id="addedDate" class="form-control js-date" required
                               placeholder="DD-MM-YYYY"
                               value="<?php echo htmlspecialchars(dateInputValue($val('added_date', date('Y-m-d')))); ?>">
                        <small class="form-hint">Date this Policy was entered in HRMS</small>
                    </div>
                    <div class="form-group">
                        <label for="policyPdf">
                            Scan PDF (Hard Copy)
                            <?php if (!$row): ?><span class="req">*</span><?php endif; ?>
                        </label>
                        <input type="file" name="policy_pdf" id="policyPdf" class="form-control"
                               accept="application/pdf,.pdf"
                               <?php echo $row ? '' : 'required'; ?>>
                        <small class="form-hint">
                            PDF only · keep under 10 MB
                            <?php if ($row && !empty($row['original_filename'])): ?>
                                · Current: <?php echo htmlspecialchars($row['original_filename']); ?>
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group full circ-dept-field">
                        <div class="circ-dept-field-head">
                            <label for="circDeptSelect">Departments <span class="req">*</span></label>
                            <div class="circ-dept-actions">
                                <button type="button" class="circ-dept-action" id="circSelectAllBtn">
                                    <i class="fa-solid fa-check-double"></i> All Departments
                                </button>
                                <button type="button" class="circ-dept-action is-muted" id="circClearDeptBtn">
                                    <i class="fa-solid fa-xmark"></i> Clear
                                </button>
                            </div>
                        </div>
                        <div class="circ-dept-select-wrap">
                            <span class="circ-dept-ico" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                            <select name="department_ids[]"
                                    id="circDeptSelect"
                                    class="form-control no-select2 js-circ-dept-select"
                                    multiple="multiple"
                                    data-placeholder="Search &amp; select departments…">
                                <option value="all" <?php echo $applyAll ? 'selected' : ''; ?>>All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <?php $did = (int) $d['id']; ?>
                                    <option value="<?php echo $did; ?>"
                                        <?php echo !$applyAll && isset($selectedDeptLookup[$did]) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="form-hint">
                            Use <button type="button" class="circ-dept-inline-link" id="circSelectAllLink">All Departments</button>
                            for everyone, or search and multi-select specific departments below.
                        </small>
                    </div>

                    <div class="form-group full">
                        <label for="policyRemarks">Remarks</label>
                        <textarea name="remarks" id="policyRemarks" class="form-control" rows="3"
                                  placeholder="Optional notes"><?php echo htmlspecialchars($val('remarks')); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions sticky-actions">
                <a href="<?php echo app_url('policies/index.php'); ?>" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" id="btnSavePolicy">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?php echo $row ? 'Update Policy' : 'Save Policy'; ?>
                </button>
            </div>
        </form>
    </div>
</main>
<?php if ($formError !== ''): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 6000 };
toastr.error(<?php echo json_encode($formError); ?>);
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>



