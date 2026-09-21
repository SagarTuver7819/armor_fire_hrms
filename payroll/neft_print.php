<?php
/**
 * NEFT sheet print view
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';

requireLogin();

$deptId = (int) ($_GET['department_id'] ?? 0);
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
$type = (string) ($_GET['type'] ?? 'salary');
$data = getNeftSheetData($month, $year, $type, $deptId);
if (!$data) {
    die('NEFT sheet not found. Finalize &amp; Lock the Salary Register first.');
}
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$typeLabel = salaryRegisterTypes()[$type] ?? $type;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NEFT — <?php echo htmlspecialchars($monthLabel); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 20px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #1e3a5f; color: #fff; }
        .num { text-align: right; }
        tfoot td { font-weight: 700; background: #f3f4f6; }
        .actions { margin-bottom: 12px; }
        .actions button { padding: 8px 14px; cursor: pointer; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>
    <h1><?php echo htmlspecialchars($companyName); ?> — NEFT / Bank Transfer Sheet</h1>
    <div class="meta">
        <?php echo htmlspecialchars($monthLabel); ?> · <?php echo htmlspecialchars($typeLabel); ?>
        · Locked <?php echo htmlspecialchars((string) ($data['lock']['locked_at'] ?? '')); ?>
    </div>
    <table>
        <thead>
            <tr>
                <th>Sr</th>
                <th>Code</th>
                <th>Beneficiary</th>
                <th>Bank</th>
                <th>Account No.</th>
                <th>IFSC</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
            <tr>
                <td><?php echo (int) $r['sr']; ?></td>
                <td><?php echo htmlspecialchars($r['employee_code']); ?></td>
                <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
                <td><?php echo htmlspecialchars($r['bank_name'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($r['account']); ?></td>
                <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                <td class="num"><?php echo number_format((float) $r['amount'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6">Total NEFT Amount</td>
                <td class="num"><?php echo number_format($data['neft_total'], 2); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
