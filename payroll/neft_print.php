<?php
/**
 * NEFT sheet print — same layout / totals as on-screen NEFT (Normal + Jobwork + Final).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll_reports_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/payroll_approval_helper.php';

requireLogin();

$deptId = (int) ($_GET['department_id'] ?? 0);
requirePayrollRegisterAccess($deptId);
ensurePayrollApprovalTables();
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$data = getCombinedNeftSheetData($month, $year, $deptId);
$typeLabel = $deptId > 0 ? 'Selected department · Normal + Jobwork' : 'All departments · Normal + Jobwork';
if (!$data || empty($data['rows'])) {
    die('NEFT sheet empty. Open Salary Register (Normal + Jobwork), fill attendance / qty, then retry.');
}

$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';
$nfpsCfg = neftNfpsSettings();
$creditNarr = 'SALARY ' . strtoupper(date('F', mktime(0, 0, 0, $month, 1, $year))) . ' ' . $year;
$payDate = date('d-m-Y');
$payRaw = (string) ($data['lock']['approved_at'] ?? $data['lock']['locked_at'] ?? '');
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $payRaw, $pm)) {
    $payDate = $pm[3] . '-' . $pm[2] . '-' . $pm[1];
}

$rows = $data['rows'];
$normalCount = (int) ($data['normal_count'] ?? 0);
$jobworkCount = (int) ($data['jobwork_count'] ?? 0);
$normalTotal = (float) ($data['normal_total'] ?? 0);
$jobworkTotal = (float) ($data['jobwork_total'] ?? 0);
$finalTotal = (float) ($data['final_total'] ?? $data['neft_total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NEFT — <?php echo htmlspecialchars($monthLabel); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #444; margin-bottom: 10px; line-height: 1.45; }
        .chips span { display: inline-block; margin: 0 8px 6px 0; padding: 2px 8px; background: #f1f5f9; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #1e3a5f; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .sub { font-size: 10px; color: #555; }
        /* Totals after </table> (not tfoot) — browsers repeat <tfoot> on every print page */
        .neft-totals { width: 100%; border-collapse: collapse; margin-top: 0; page-break-inside: avoid; break-inside: avoid; }
        .neft-totals td { border: 1px solid #333; padding: 5px 6px; font-weight: 700; }
        .neft-totals .sec-n { background: #ecfdf5; }
        .neft-totals .sec-j { background: #eff6ff; }
        .neft-totals .sec-f { background: #fef3c7; font-size: 12px; }
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
        · Locked <?php echo htmlspecialchars((string) ($data['lock']['locked_at'] ?? '')); ?><br>
        CREDIT_NARR=<?php echo htmlspecialchars($creditNarr); ?>
        · PYMT_DATE=<?php echo htmlspecialchars($payDate); ?>
        · EMAIL=<?php echo htmlspecialchars($nfpsCfg['email_id']); ?>
    </div>
    <div class="chips meta">
        <span><?php echo count($rows); ?> total</span>
        <span>Normal <?php echo $normalCount; ?> · ₹ <?php echo formatIndianNumber($normalTotal, 2); ?></span>
        <span>Jobwork <?php echo $jobworkCount; ?> · ₹ <?php echo formatIndianNumber($jobworkTotal, 2); ?></span>
        <span><strong>Final ₹ <?php echo formatIndianNumber($finalTotal, 2); ?></strong></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Sr</th>
                <th>Type</th>
                <th>BNF_NAME</th>
                <th>Department</th>
                <th>BENE_ACC_NO</th>
                <th>BENE_IFSC</th>
                <th class="num">AMOUNT</th>
                <th>CREDIT_NARR</th>
                <th>PYMT_DATE</th>
                <th>EMAIL_ID</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $missBank = !empty($r['missing_bank']) || trim((string) ($r['account'] ?? '')) === '' || trim((string) ($r['account'] ?? '')) === '-';
            $kind = (string) ($r['register_kind'] ?? 'Normal');
            ?>
            <tr>
                <td><?php echo (int) $r['sr']; ?></td>
                <td><?php echo htmlspecialchars($kind); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars(neftNfpsBeneName($r['employee_name'] ?? '')); ?></strong>
                    <div class="sub"><?php echo htmlspecialchars($r['employee_code'] ?? ''); ?><?php echo $missBank ? ' · No bank A/C' : ''; ?></div>
                </td>
                <td><?php echo htmlspecialchars($r['department'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($missBank ? '—' : (string) ($r['account'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($r['ifsc'] ?: '-'); ?></td>
                <td class="num"><?php echo formatIndianNumber((float) $r['amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($creditNarr); ?></td>
                <td><?php echo htmlspecialchars($payDate); ?></td>
                <td><?php echo htmlspecialchars($nfpsCfg['email_id']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <table class="neft-totals" aria-label="NEFT totals">
        <tr class="sec-n">
            <td colspan="6">Normal Salary Total (<?php echo $normalCount; ?>)</td>
            <td class="num"><?php echo formatIndianNumber($normalTotal, 2); ?></td>
            <td colspan="3"></td>
        </tr>
        <tr class="sec-j">
            <td colspan="6">Jobwork Total (<?php echo $jobworkCount; ?>)</td>
            <td class="num"><?php echo formatIndianNumber($jobworkTotal, 2); ?></td>
            <td colspan="3"></td>
        </tr>
        <tr class="sec-f">
            <td colspan="6">Final NEFT Total (Normal + Jobwork · All departments)</td>
            <td class="num"><?php echo formatIndianNumber($finalTotal, 2); ?></td>
            <td colspan="3"></td>
        </tr>
    </table>
</body>
</html>
