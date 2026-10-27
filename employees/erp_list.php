<?php
/**
 * ERP Employees — joined from Armor ERP Production → Add Employee form
 * Shown department-wise (Production dept / HRMS dept).
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/employee_helper.php';

requireLogin();

$pageTitle = 'ERP Employees';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
ensureEmployeesTable($conn);

$q = trim((string)($_GET['q'] ?? ''));
$deptFilter = trim((string)($_GET['dept'] ?? ''));

$sql = "SELECT e.id, e.employee_code, e.employee_name, e.mobile_number, e.blood_group,
               e.date_of_joining, e.production_department, e.shift_type, e.status, e.created_at,
               e.erp_join_form_id, d.department_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE (e.source = 'erp' OR (e.erp_join_form_id IS NOT NULL AND e.erp_join_form_id > 0))";
$params = [];
$types = '';
if ($q !== '') {
    $sql .= ' AND (e.employee_name LIKE ? OR e.employee_code LIKE ? OR e.mobile_number LIKE ?
                   OR e.production_department LIKE ? OR d.department_name LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
    $types = 'sssss';
}
if ($deptFilter !== '') {
    $sql .= ' AND (e.production_department = ? OR d.department_name = ?)';
    $params[] = $deptFilter;
    $params[] = $deptFilter;
    $types .= 'ss';
}
$sql .= " ORDER BY COALESCE(NULLIF(TRIM(e.production_department), ''), d.department_name, 'Other') ASC,
                   e.employee_name ASC, e.id DESC
          LIMIT 1000";

$rows = [];
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
        }
        $result->free();
    }
}

// Group by production department (fallback HRMS dept)
$groups = [];
foreach ($rows as $r) {
    $key = trim((string)($r['production_department'] ?? ''));
    if ($key === '') {
        $key = trim((string)($r['department_name'] ?? ''));
    }
    if ($key === '') {
        $key = 'Other / Unmapped';
    }
    if (!isset($groups[$key])) {
        $groups[$key] = [];
    }
    $groups[$key][] = $r;
}
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
$count = count($rows);

// Dept filter options from all ERP employees (ignore current filter)
$deptOpts = [];
$optRes = $conn->query(
    "SELECT DISTINCT TRIM(COALESCE(NULLIF(e.production_department, ''), d.department_name, 'Other')) AS dept_label
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE (e.source = 'erp' OR (e.erp_join_form_id IS NOT NULL AND e.erp_join_form_id > 0))
     ORDER BY dept_label ASC"
);
if ($optRes) {
    while ($o = $optRes->fetch_assoc()) {
        $lab = trim((string)($o['dept_label'] ?? ''));
        if ($lab !== '') {
            $deptOpts[] = $lab;
        }
    }
    $optRes->free();
}
$conn->close();
?>

<style>
    .erp-emp-wrap { max-width: 1120px; margin: 0 auto; padding: 0 12px 40px; }
    .erp-emp-head { text-align: center; margin-bottom: 20px; position: relative; }
    .erp-emp-head h1 {
        font-size: 1.45rem; font-weight: 800; color: #d2232a; text-transform: uppercase;
        letter-spacing: .04em; margin: 12px 0 6px;
    }
    .erp-emp-head p { margin: 0; color: #64748b; font-size: 13px; }
    .erp-emp-card {
        background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;
        box-shadow: 0 8px 24px rgba(15,23,42,.06); overflow: hidden; margin-bottom: 18px;
    }
    .erp-emp-toolbar {
        display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between;
        align-items: flex-end; padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
        background: #fff;
    }
    .erp-emp-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: #fef2f2; color: #d2232a; border: 1px solid #fecaca;
        border-radius: 999px; padding: 5px 12px; font-weight: 800; font-size: 12px;
    }
    .erp-dept-banner {
        display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
        padding: 12px 16px; background: linear-gradient(135deg, #d2232a, #b01c22); color: #fff;
    }
    .erp-dept-banner strong {
        font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    }
    .erp-dept-banner span {
        font-size: 12px; font-weight: 700; background: rgba(255,255,255,.18);
        border-radius: 999px; padding: 3px 10px;
    }
    .erp-emp-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
    .erp-emp-table th {
        background: #f8fafc; color: #334155; text-align: left; padding: 10px 12px;
        font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
        border-bottom: 1px solid #e2e8f0;
    }
    .erp-emp-table td { padding: 10px 12px; border-bottom: 1px solid #eef2f7; vertical-align: middle; }
    .erp-emp-table tr:hover td { background: #fff5f5; }
    .erp-name { font-weight: 800; color: #d2232a; }
    .btn-back-erp {
        display: inline-block; background: #fff; color: #d2232a; border: 2px solid #d2232a;
        border-radius: 10px; padding: 8px 16px; font-weight: 700; text-decoration: none; font-size: 13px;
    }
    .btn-back-erp:hover { background: #d2232a; color: #fff; }
    .erp-empty { text-align: center; padding: 36px 16px; color: #64748b; }
    .erp-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
    .erp-filters label {
        display: block; font-size: 10px; font-weight: 700; color: #64748b;
        text-transform: uppercase; margin-bottom: 3px;
    }
    .erp-filters .form-control, .erp-filters .form-select {
        min-height: 36px; font-size: 13px; min-width: 160px;
    }
    .erp-filters .btn-go {
        background: #d2232a; color: #fff; border: 0; border-radius: 8px;
        padding: 0 14px; height: 36px; font-weight: 700; cursor: pointer;
    }
</style>

<main class="dashboard-main">
<div class="erp-emp-wrap">
    <div class="erp-emp-head">
        <a class="btn-back-erp" href="<?php echo htmlspecialchars(app_url('dashboard.php')); ?>">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1>ERP Employees</h1>
        <p>Armor ERP · Production · Add Employee · Department wise</p>
    </div>

    <div class="erp-emp-card" style="margin-bottom:18px;">
        <div class="erp-emp-toolbar">
            <span class="erp-emp-badge"><i class="fa-solid fa-industry"></i> <?php echo (int)$count; ?> employees · <?php echo count($groups); ?> departments</span>
            <form method="get" class="erp-filters">
                <div>
                    <label for="erp_dept">Department</label>
                    <select id="erp_dept" name="dept" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($deptOpts as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $deptFilter === $opt ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="erp_q">Search</label>
                    <input type="text" id="erp_q" name="q" class="form-control"
                           placeholder="Name / code / mobile…"
                           value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <button type="submit" class="btn-go">Filter</button>
            </form>
        </div>
    </div>

    <?php if (!$groups): ?>
        <div class="erp-emp-card"><div class="erp-empty">No ERP employees yet. Save Add Employee form from Armor ERP Production.</div></div>
    <?php else: foreach ($groups as $deptName => $list): ?>
        <div class="erp-emp-card">
            <div class="erp-dept-banner">
                <strong><i class="fa-solid fa-building me-1"></i> <?php echo htmlspecialchars($deptName); ?></strong>
                <span><?php echo count($list); ?> employee<?php echo count($list) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="table-responsive">
                <table class="erp-emp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>HRMS Dept</th>
                            <th>Mobile</th>
                            <th>Blood</th>
                            <th>Joining</th>
                            <th>Shift</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $i => $r): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$r['employee_code']); ?></strong></td>
                            <td class="erp-name"><?php echo htmlspecialchars((string)$r['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['department_name'] ?: '—')); ?></td>
                            <td><?php echo htmlspecialchars((string)$r['mobile_number']); ?></td>
                            <td><?php echo htmlspecialchars((string)($r['blood_group'] ?: '—')); ?></td>
                            <td><?php echo !empty($r['date_of_joining']) ? date('d-m-Y', strtotime($r['date_of_joining'])) : '—'; ?></td>
                            <td><?php echo htmlspecialchars((string)($r['shift_type'] ?: '—')); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars(app_url('employees/view.php?id=' . (int)$r['id'])); ?>"
                                   style="color:#d2232a;font-weight:700;text-decoration:none;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
