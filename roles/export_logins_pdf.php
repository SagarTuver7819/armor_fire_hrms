<?php
/**
 * Admin — Employee Login Credentials sheet (Print / Save as PDF)
 * Columns: Employee Code, Username, Password, Designation, Department
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireAdmin();
ensureRoleTables();

$deptId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$rows = fetchAllEmployeePortalCredentials($deptId);
$company = getCompanyName();
$generated = date('d-m-Y H:i');
$total = count($rows);
$withLogin = 0;
foreach ($rows as $r) {
    if (trim((string) ($r['login_id'] ?? '')) !== '') {
        $withLogin++;
    }
}

$h = static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Logins · <?php echo $h($company); ?></title>
    <style>
        :root { --brand: #d2232a; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: #f1f5f9;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 5;
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }
        .toolbar a, .toolbar button {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 12px; border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff; color: var(--ink);
            font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer;
        }
        .toolbar .btn-print {
            background: var(--brand); color: #fff; border-color: var(--brand);
        }
        .sheet {
            max-width: 980px;
            margin: 18px auto 40px;
            padding: 22px 20px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        .head { margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px solid var(--brand); }
        .head h1 { margin: 0 0 4px; font-size: 20px; }
        .head p { margin: 0; color: var(--muted); font-size: 12.5px; font-weight: 600; }
        .meta {
            display: flex; flex-wrap: wrap; gap: 10px 18px;
            margin: 0 0 12px; font-size: 12px; font-weight: 700; color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #fef2f2;
            color: #991b1b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        td.code, td.user, td.pass {
            font-family: Consolas, "Courier New", monospace;
            font-weight: 700;
            white-space: nowrap;
        }
        td.pass { background: #fffbeb; }
        td.user { background: #eff6ff; color: #1d4ed8; }
        .foot {
            margin-top: 12px;
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                margin: 0; padding: 0;
                border: none; border-radius: 0; box-shadow: none;
                max-width: none;
            }
            th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            td.pass, td.user { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 12mm; size: A4 landscape; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a href="<?php echo $h(app_url('roles/export_logins_excel.php' . ($deptId > 0 ? ('?department_id=' . $deptId) : ''))); ?>">Download Excel</a>
        <a href="<?php echo $h(app_url('roles/bulk_employee_logins.php')); ?>">Back to Employee Logins</a>
    </div>

    <div class="sheet">
        <div class="head">
            <h1><?php echo $h($company); ?> — Employee Login Credentials</h1>
            <p>Confidential · Admin use only · Login ID &amp; Password sheet</p>
        </div>
        <div class="meta">
            <span>Generated: <?php echo $h($generated); ?></span>
            <span>Total employees: <?php echo (int) $total; ?></span>
            <span>With login: <?php echo (int) $withLogin; ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:40px;">Sr</th>
                    <th>Employee Code</th>
                    <th>Employee Name</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Designation</th>
                    <th>Department</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">No active employees found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td class="code"><?php echo $h($row['employee_code'] ?? '—'); ?></td>
                        <td><?php echo $h($row['employee_name'] ?? '—'); ?></td>
                        <td class="user"><?php echo $h(($row['login_id'] ?? '') !== '' ? $row['login_id'] : '—'); ?></td>
                        <td class="pass"><?php echo $h(($row['login_password'] ?? '') !== '' ? $row['login_password'] : '—'); ?></td>
                        <td><?php echo $h(($row['designation'] ?? '') !== '' ? $row['designation'] : '—'); ?></td>
                        <td><?php echo $h(($row['department_name'] ?? '') !== '' ? $row['department_name'] : '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p class="foot">Use Employee Login on the HRMS login page. Username = Employee Code (when provisioned).</p>
    </div>
</body>
</html>
