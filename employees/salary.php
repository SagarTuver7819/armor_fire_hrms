<?php
/**
 * Employee-wise salary details
 * Salary → components from Salary Master
 * Jobwork → map to salary slab
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensurePayrollTables();
$payType = normalizePayType($emp['pay_type'] ?? 'Salary');
$deptId = (int) $emp['department_id'];
$saved = getEmployeeSalaryDetails($id);
$components = getActiveMasterRows('salary_components', 'id ASC');
$slabs = getActiveMasterRows('salary_slabs', 'min_amount ASC');

$savedMap = [];
$savedSlabId = 0;
foreach ($saved as $row) {
    if (($row['line_type'] ?? '') === 'slab') {
        $savedSlabId = (int) $row['slab_id'];
    } else {
        $savedMap[(int) ($row['component_id'] ?? 0)] = $row;
    }
}

$pageTitle = 'Salary Details';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'join_employee';

require_once __DIR__ . '/../includes/header.php';

$basic = (float) ($emp['decided_salary'] ?? 0);
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employees/view.php?id=' . $id); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Salary Details</h1>
                <p>
                    <?php echo htmlspecialchars($emp['employee_code'] . ' — ' . $emp['employee_name']); ?>
                    · <span class="pay-pill <?php echo $payType === 'Jobwork' ? 'is-jobwork' : 'is-salary'; ?>"><?php echo htmlspecialchars($payType); ?></span>
                </p>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="login-alert" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;margin-bottom:16px;">
                Salary details saved.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo app_url('employees/salary_save.php'); ?>" class="employee-form">
            <input type="hidden" name="employee_id" value="<?php echo $id; ?>">
            <input type="hidden" name="pay_type" value="<?php echo htmlspecialchars($payType); ?>">

            <?php if ($payType === 'Jobwork'): ?>
                <div class="form-section">
                    <h3><i class="fa-solid fa-layer-group"></i> Jobwork → Salary Slab</h3>
                    <p class="form-hint" style="margin-bottom:12px;">
                        Jobwork earning month-end slab ma convert thase. Slab Master: Masters → Salary Slab.
                    </p>
                    <div class="form-grid form-grid-3">
                        <div class="form-group span-2">
                            <label>Salary Slab</label>
                            <select name="slab_id" class="form-control" required>
                                <option value="">-- Select slab --</option>
                                <?php foreach ($slabs as $slab): ?>
                                    <option value="<?php echo (int) $slab['id']; ?>" <?php echo $savedSlabId === (int) $slab['id'] ? 'selected' : ''; ?>>
                                        <?php
                                        echo htmlspecialchars(
                                            $slab['slab_name'] . ' · JW '
                                            . number_format((float) $slab['min_amount'], 0) . '-' . number_format((float) $slab['max_amount'], 0)
                                            . ' → Salary ' . number_format((float) $slab['monthly_salary'], 0)
                                        );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$slabs): ?>
                                <small class="form-hint">No slabs found. Add in Masters → Salary Slab Master.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-section">
                    <h3><i class="fa-solid fa-indian-rupee-sign"></i> Salary Components</h3>
                    <p class="form-hint" style="margin-bottom:12px;">
                        Values Salary Master mathi aave. Percentage Basic (Decided Salary
                        <?php echo $basic > 0 ? number_format($basic, 2) : '0'; ?>) par calculate thay.
                    </p>
                    <div class="table-wrap">
                        <table class="data-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Component</th>
                                    <th>Type</th>
                                    <th>Calc</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($components as $c): ?>
                                <?php
                                $cid = (int) $c['id'];
                                $amt = isset($savedMap[$cid]) ? (float) $savedMap[$cid]['amount'] : (float) $c['default_value'];
                                if (($c['calculation'] ?? '') === 'Percentage' && !isset($savedMap[$cid]) && $basic > 0) {
                                    $amt = round($basic * ((float) $c['default_value']) / 100, 2);
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['component_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['component_type']); ?></td>
                                    <td><?php echo htmlspecialchars($c['calculation']); ?></td>
                                    <td>
                                        <input type="hidden" name="comp_id[]" value="<?php echo $cid; ?>">
                                        <input type="hidden" name="comp_label[]" value="<?php echo htmlspecialchars($c['component_name']); ?>">
                                        <input type="hidden" name="comp_type[]" value="<?php echo htmlspecialchars($c['component_type']); ?>">
                                        <input type="hidden" name="comp_calc[]" value="<?php echo htmlspecialchars($c['calculation']); ?>">
                                        <input type="number" step="0.01" name="comp_amount[]" class="form-control" value="<?php echo htmlspecialchars((string) $amt); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$components): ?>
                                <tr><td colspan="4">No salary components. Add in Salary Master first.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Salary Details
                </button>
                <a href="<?php echo app_url('employees/view.php?id=' . $id); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
