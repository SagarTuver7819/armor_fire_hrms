<?php
/**
 * Digital Visiting Card — Armor Fire brand · Front + Back · Print/PDF & Image
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
requireAccess('employees', 'view', $deptId);

$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$show = isset($_GET['show']) || $deptId > 0 || $employeeId > 0;

$conn = getDBConnection();
ensureEmployeesTable($conn);

$departments = [];
$dres = $conn->query('SELECT id, department_name FROM departments WHERE status = 1 ORDER BY sort_order ASC, department_name ASC');
if ($dres) {
    while ($r = $dres->fetch_assoc()) {
        $departments[] = $r;
    }
}

$employees = [];
$eqSql = "SELECT id, employee_code, employee_name, department_id, designation, photo_file
          FROM employees WHERE status = 1";
if ($deptId > 0) {
    $eqSql .= ' AND department_id = ' . (int) $deptId;
}
$eqSql .= ' ORDER BY employee_code ASC';
$eq = $conn->query($eqSql);
if ($eq) {
    while ($r = $eq->fetch_assoc()) {
        $employees[] = $r;
    }
}
$conn->close();

$pageTitle = 'Digital Visiting Card';
$useSidebar = true;
$sidebarMode = 'employees';
$sidebarActive = 'visiting_card';
$sidebarDeptId = $deptId;

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('employees/index.php' . ($deptId > 0 ? '?department_id=' . $deptId : '')); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Employees
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <h1>Digital Visiting Card</h1>
            <p>Armor Fire design · Front (logo + company) · Back (name, role, contacts + logo) · Print/PDF &amp; Image</p>
        </div>

        <form method="GET" class="employee-form leave-rpt-filters" id="vcFilterForm">
            <input type="hidden" name="show" value="1">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" id="vcDept" class="form-control">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int) $d['id']; ?>" <?php echo $deptId === (int) $d['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" id="vcEmp" class="form-control">
                        <option value="0" data-dept="0">All Employees (batch)</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo (int) $e['id']; ?>"
                                    data-dept="<?php echo (int) $e['department_id']; ?>"
                                    <?php echo $employeeId === (int) $e['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($e['employee_code'] ?? '') . ' — ' . ($e['employee_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn-secondary" style="width:100%;">
                        <i class="fa-solid fa-eye"></i> Preview List
                    </button>
                </div>
            </div>
        </form>

        <?php if ($show): ?>
            <?php if (!$employees): ?>
                <div class="alert alert-error" style="margin-top:16px;">No active employees for this filter.</div>
            <?php else: ?>
                <div class="ops-live-summary" style="margin:16px 0 12px;">
                    <span class="ops-chip"><?php echo count($employees); ?> employee(s)</span>
                    <span class="ops-chip">Front + Back</span>
                    <span class="ops-chip">Print / PDF &amp; Image</span>
                </div>

                <div style="margin-bottom:16px;">
                    <?php
                    $pdfQs = http_build_query([
                        'department_id' => $deptId,
                        'employee_id' => $employeeId,
                    ]);
                    ?>
                    <a class="btn-primary" href="<?php echo htmlspecialchars(app_url('employees/visiting_card_pdf.php?' . $pdfQs)); ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-id-card"></i>
                        <?php echo $employeeId > 0 ? 'Open Visiting Card (Print / PDF & Image)' : 'Open Visiting Cards (Print / PDF & Image)'; ?>
                    </a>
                </div>

                <div class="leave-rpt-table-wrap">
                    <table class="leave-rpt-table">
                        <thead>
                            <tr>
                                <th class="sr">Sr</th>
                                <th class="txt">Photo</th>
                                <th class="txt">Code</th>
                                <th class="txt">Name</th>
                                <th class="txt">Designation</th>
                                <th class="txt">Department</th>
                                <th class="ctr">Print</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $deptMap = [];
                        foreach ($departments as $d) {
                            $deptMap[(int) $d['id']] = $d['department_name'];
                        }
                        $list = $employees;
                        if ($employeeId > 0) {
                            $list = array_values(array_filter($employees, static function ($e) use ($employeeId) {
                                return (int) $e['id'] === $employeeId;
                            }));
                        }
                        foreach ($list as $i => $e):
                            $photo = employeeDocumentPublicUrl($e['photo_file'] ?? '');
                            $oneQs = http_build_query([
                                'department_id' => $deptId,
                                'employee_id' => (int) $e['id'],
                            ]);
                            ?>
                            <tr>
                                <td class="sr"><?php echo $i + 1; ?></td>
                                <td class="txt">
                                    <?php if ($photo !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:6px;">
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="emp-code"><?php echo htmlspecialchars($e['employee_code'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($e['employee_name'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($e['designation'] ?? ''); ?></td>
                                <td class="txt"><?php echo htmlspecialchars($deptMap[(int) ($e['department_id'] ?? 0)] ?? ''); ?></td>
                                <td class="ctr">
                                    <a class="btn-secondary" style="padding:4px 10px;font-size:12px;"
                                       href="<?php echo htmlspecialchars(app_url('employees/visiting_card_pdf.php?' . $oneQs)); ?>"
                                       target="_blank" rel="noopener">
                                        <i class="fa-solid fa-print"></i> Print / PDF &amp; Image
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
(function () {
    var dept = document.getElementById('vcDept');
    var emp = document.getElementById('vcEmp');
    if (!dept || !emp) return;
    function filterEmp() {
        var d = String(dept.value || '0');
        var keep = emp.value;
        Array.prototype.forEach.call(emp.options, function (opt) {
            if (opt.value === '0') {
                opt.hidden = false;
                return;
            }
            var od = String(opt.getAttribute('data-dept') || '0');
            opt.hidden = (d !== '0' && od !== d);
        });
        var sel = emp.options[emp.selectedIndex];
        if (sel && sel.hidden) {
            emp.value = '0';
        } else if (keep) {
            emp.value = keep;
        }
    }
    dept.addEventListener('change', filterEmp);
    filterEmp();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
