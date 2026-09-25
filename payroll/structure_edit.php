<?php
/**
 * Add / Edit individual employee salary structure
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/master_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensurePayrollTables();
$conn = getDBConnection();
ensureEmployeesTable($conn);

$empStmt = $conn->prepare(
    "SELECT id, employee_code, employee_name, pay_type, decided_salary, pf_deduction
     FROM employees
     WHERE status = 1 AND department_id = ? AND pay_type IN ('Salary','Jobwork')
     ORDER BY employee_name ASC"
);
$empStmt->bind_param('i', $deptId);
$empStmt->execute();
$empOptions = $empStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$empStmt->close();
$conn->close();

$emp = $employeeId > 0 ? getEmployeeById($employeeId) : null;
if ($emp && (int) ($emp['department_id'] ?? 0) !== $deptId) {
    $emp = null;
    $employeeId = 0;
}

$payType = $emp ? normalizePayType($emp['pay_type'] ?? 'Salary') : 'Salary';
$saved = $employeeId > 0 ? getEmployeeSalaryDetails($employeeId) : [];
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

$basic = $emp ? (float) ($emp['decided_salary'] ?? 0) : 0;
$pfYes = $emp ? (($emp['pf_deduction'] ?? 'No') === 'Yes') : false;
$pfPreview = $emp ? statutoryPf($basic, $emp, 1.0) : 0;
$ptPreview = statutoryPt($basic);
$pfWageBase = $emp ? payrollPfWageBase($emp, $employeeId) : 0;

$pageTitle = $employeeId > 0 ? 'Edit Salary Structure' : 'Add Salary Structure';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'diary';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo app_url('payroll/diary.php?department_id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Salary Structure
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p>
                    <?php echo htmlspecialchars($department['department_name']); ?>
                    · PF = 12% of Basic (ceiling ₹15,000 → max ₹1,800) · PT: ₹200 if salary ≥ ₹12,001
                </p>
            </div>
        </div>

        <form method="GET" class="employee-form" style="margin-bottom:18px;" id="pickEmpForm">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <div class="form-section">
                <h3><i class="fa-solid fa-user"></i> Select Employee</h3>
                <div class="form-grid form-grid-2">
                    <div class="form-group span-2">
                        <label>Employee *</label>
                        <select name="employee_id" class="form-control" required
                                onchange="if (this.value) this.form.submit();">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($empOptions as $opt): ?>
                                <option value="<?php echo (int) $opt['id']; ?>"
                                    <?php echo $employeeId === (int) $opt['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['employee_code'] . ' — ' . $opt['employee_name'] . ' (' . $opt['pay_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($emp): ?>
        <form method="POST" action="<?php echo app_url('payroll/structure_save.php'); ?>" class="employee-form" id="structureForm">
            <input type="hidden" name="department_id" value="<?php echo $deptId; ?>">
            <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">

            <div class="form-section">
                <h3><i class="fa-solid fa-indian-rupee-sign"></i> Basic &amp; Statutory</h3>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label>Basic / Decided Salary *</label>
                        <input type="number" step="0.01" min="0" name="decided_salary" id="decidedSalary"
                               class="form-control" required value="<?php echo htmlspecialchars((string) $basic); ?>">
                    </div>
                    <div class="form-group">
                        <label>PF Deduction</label>
                        <div class="radio-row">
                            <label><input type="radio" name="pf_deduction" value="Yes" class="pf-toggle" <?php echo $pfYes ? 'checked' : ''; ?>> Yes</label>
                            <label><input type="radio" name="pf_deduction" value="No" class="pf-toggle" <?php echo !$pfYes ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pay Type</label>
                        <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($payType); ?>">
                    </div>
                    <div class="form-group">
                        <label>PF Amount (auto)</label>
                        <input type="text" class="form-control" id="pfPreview" readonly value="<?php echo number_format($pfPreview, 2); ?>">
                        <small class="form-hint">Basic ₹<?php echo number_format($pfWageBase, 0); ?> × 12% (ceiling ₹15,000)</small>
                    </div>
                    <div class="form-group">
                        <label>PT Amount (auto)</label>
                        <input type="text" class="form-control" id="ptPreview" readonly value="<?php echo number_format($ptPreview, 2); ?>">
                        <small class="form-hint">₹200 if salary ≥ ₹12,001 else ₹0</small>
                    </div>
                </div>
            </div>

            <?php if ($payType === 'Jobwork'): ?>
                <div class="form-section">
                    <h3><i class="fa-solid fa-layer-group"></i> Jobwork → Salary Slab</h3>
                    <div class="form-grid form-grid-2">
                        <div class="form-group span-2">
                            <label>Salary Slab</label>
                            <select name="slab_id" class="form-control">
                                <option value="">-- Select slab (optional) --</option>
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
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-section">
                    <h3><i class="fa-solid fa-list"></i> Salary Components</h3>
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
                                $ln = strtolower(trim((string) ($c['component_name'] ?? '')));
                                $isPfComp = ($ln !== '' && (
                                    strpos($ln, 'provident') !== false
                                    || preg_match('/\bpf\b/', $ln)
                                    || strpos($ln, 'p.f') !== false
                                ));
                                $isPtComp = ($ln !== '' && (
                                    strpos($ln, 'professional tax') !== false
                                    || preg_match('/\bpt\b/', $ln)
                                    || strpos($ln, 'p.t') !== false
                                ));
                                // PF / PT: always statutory amounts (never 12% of full CTC)
                                if ($isPfComp) {
                                    $amt = $pfYes ? (float) $pfPreview : 0.0;
                                } elseif ($isPtComp) {
                                    $amt = (float) $ptPreview;
                                } elseif (($c['calculation'] ?? '') === 'Percentage' && !isset($savedMap[$cid]) && $basic > 0) {
                                    $amt = round($basic * ((float) $c['default_value']) / 100, 2);
                                }
                                $inputClass = 'form-control';
                                if ($isPfComp) {
                                    $inputClass .= ' js-pf-comp-amount';
                                }
                                if ($isPtComp) {
                                    $inputClass .= ' js-pt-comp-amount';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['component_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['component_type']); ?></td>
                                    <td><?php echo htmlspecialchars($isPfComp ? 'Basic × 12%' : ($c['calculation'] ?? '')); ?></td>
                                    <td>
                                        <input type="hidden" name="comp_id[]" value="<?php echo $cid; ?>">
                                        <input type="hidden" name="comp_label[]" value="<?php echo htmlspecialchars($c['component_name']); ?>">
                                        <input type="hidden" name="comp_type[]" value="<?php echo htmlspecialchars($c['component_type']); ?>">
                                        <input type="hidden" name="comp_calc[]" value="<?php echo htmlspecialchars($c['calculation']); ?>">
                                        <input type="number" step="0.01" name="comp_amount[]" class="<?php echo htmlspecialchars($inputClass); ?>"
                                               value="<?php echo htmlspecialchars((string) $amt); ?>"
                                               <?php echo ($isPfComp || $isPtComp) ? 'readonly' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$components): ?>
                                <tr><td colspan="4">No salary components. Add in Masters → Salary Master first.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions sticky-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Salary Structure
                </button>
                <a href="<?php echo app_url('payroll/diary.php?department_id=' . $deptId); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
        <?php else: ?>
            <p class="form-hint">Select an employee above to set salary structure.</p>
        <?php endif; ?>
    </div>
</main>
<script>
(function () {
    var salaryEl = document.getElementById('decidedSalary');
    var pfPreview = document.getElementById('pfPreview');
    var ptPreview = document.getElementById('ptPreview');
    if (!salaryEl || !pfPreview || !ptPreview) return;

    function recalc() {
        var salary = parseFloat(salaryEl.value) || 0;
        var pfYes = document.querySelector('input[name="pf_deduction"]:checked');
        pfYes = pfYes && pfYes.value === 'Yes';
        // Prefer Basic Salary component amount if present in the form
        var basicWage = salary;
        var rows = document.querySelectorAll('input[name="comp_label[]"]');
        var amts = document.querySelectorAll('input[name="comp_amount[]"]');
        for (var i = 0; i < rows.length; i++) {
            var lab = (rows[i].value || '').toLowerCase();
            if (lab.indexOf('basic') !== -1 && amts[i] && !amts[i].classList.contains('js-pf-comp-amount')) {
                var b = parseFloat(amts[i].value) || 0;
                if (b > 0) {
                    basicWage = b;
                    break;
                }
            }
        }
        var wage = Math.min(15000, basicWage);
        var pf = pfYes ? (wage * 0.12) : 0;
        var pt = salary >= 12001 ? 200 : 0;
        pfPreview.value = pf.toFixed(2);
        ptPreview.value = pt.toFixed(2);
        document.querySelectorAll('.js-pf-comp-amount').forEach(function (el) {
            el.value = pf.toFixed(2);
        });
        document.querySelectorAll('.js-pt-comp-amount').forEach(function (el) {
            el.value = pt.toFixed(2);
        });
    }
    salaryEl.addEventListener('input', recalc);
    document.querySelectorAll('.pf-toggle').forEach(function (el) {
        el.addEventListener('change', recalc);
    });
    document.querySelectorAll('input[name="comp_amount[]"]').forEach(function (el) {
        if (!el.classList.contains('js-pf-comp-amount') && !el.classList.contains('js-pt-comp-amount')) {
            el.addEventListener('input', recalc);
        }
    });
    recalc();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
