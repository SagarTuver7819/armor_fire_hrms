<?php
/**
 * Department Salary Structure — grid of all employees with structure set
 * (Replaces old attendance-style Salary Diary UI)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/payroll_helper.php';

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$department = $deptId > 0 ? getDepartmentById($deptId) : null;
if (!$department) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

ensurePayrollTables();
$conn = getDBConnection();
ensureEmployeesTable($conn);

$stmt = $conn->prepare(
    "SELECT e.id, e.employee_code, e.employee_name, e.pay_type, e.decided_salary, e.pf_deduction,
            (SELECT COUNT(*) FROM employee_salary_details d WHERE d.employee_id = e.id) AS structure_lines,
            (SELECT COALESCE(SUM(d.amount), 0) FROM employee_salary_details d
              WHERE d.employee_id = e.id AND d.component_type <> 'Deduction') AS earn_total,
            (SELECT COALESCE(SUM(d.amount), 0) FROM employee_salary_details d
              WHERE d.employee_id = e.id AND d.component_type = 'Deduction') AS ded_total
     FROM employees e
     WHERE e.status = 1 AND e.department_id = ? AND e.pay_type IN ('Salary','Jobwork')
     ORDER BY e.pay_type ASC, e.employee_name ASC"
);
$stmt->bind_param('i', $deptId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$pageTitle = 'Salary Structure';
$useSidebar = true;
$sidebarMode = 'department';
$sidebarDeptId = $deptId;
$sidebarActive = 'diary';
$msg = (string) ($_GET['msg'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <div class="page-toolbar flex-between">
        <a href="<?php echo app_url('department.php?id=' . $deptId); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Modules
        </a>
        <a href="<?php echo app_url('payroll/structure_edit.php?department_id=' . $deptId); ?>" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Add Salary Structure
        </a>
    </div>

    <div class="form-page-card">
        <div class="form-page-header">
            <div>
                <h1>Salary Structure</h1>
                <p>
                    <?php echo htmlspecialchars($department['department_name']); ?>
                    · Set individual salary · PF = min(₹15,000, salary) × 12% · PT = ₹200 if salary ≥ ₹12,001
                </p>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="login-alert" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857;margin-bottom:16px;">
                Salary structure saved successfully.
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table" style="width:100%">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="num">Basic / Decided</th>
                        <th class="num">Earnings</th>
                        <th>PF</th>
                        <th class="num">PF Amt</th>
                        <th class="num">PT Amt</th>
                        <th>Structure</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10">No Salary / Jobwork employees in this department.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r):
                    $basic = (float) ($r['decided_salary'] ?? 0);
                    $earn = (float) ($r['earn_total'] ?? 0);
                    $grossForNorm = $basic > 0 ? $basic : $earn;
                    $pfYes = (($r['pf_deduction'] ?? 'No') === 'Yes');
                    $pfAmt = statutoryPf($grossForNorm, $r);
                    $ptAmt = statutoryPt($grossForNorm);
                    $hasStructure = ((int) ($r['structure_lines'] ?? 0) > 0) || $basic > 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['employee_code']); ?></td>
                        <td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td>
                        <td>
                            <span class="pay-pill <?php echo ($r['pay_type'] ?? '') === 'Jobwork' ? 'is-jobwork' : 'is-salary'; ?>">
                                <?php echo htmlspecialchars($r['pay_type'] ?? 'Salary'); ?>
                            </span>
                        </td>
                        <td class="num"><?php echo number_format($basic, 2); ?></td>
                        <td class="num"><?php echo number_format($earn, 2); ?></td>
                        <td><?php echo $pfYes ? 'Yes' : 'No'; ?></td>
                        <td class="num"><?php echo number_format($pfAmt, 2); ?></td>
                        <td class="num"><?php echo number_format($ptAmt, 2); ?></td>
                        <td>
                            <?php if ($hasStructure): ?>
                                <span class="pay-pill is-salary">Set</span>
                            <?php else: ?>
                                <span class="pay-pill" style="background:#fef3c7;color:#92400e;">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="action-btn edit" title="Set / Edit Structure"
                               href="<?php echo app_url('payroll/structure_edit.php?department_id=' . $deptId . '&employee_id=' . (int) $r['id']); ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
