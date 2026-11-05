<?php
/**
 * CNC Clone NEFT print — same columns as the payroll NEFT print.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';
require_once __DIR__ . '/../../includes/ops_clone_report.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/data.php';

$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$neftRows = cncCloneNeftRows(cncCloneNeftRegisterRows($month, $year));
$sec = cncCloneNeftSummary($neftRows);
$export = cncCloneNfpsExport($neftRows, $month, $year);
if (!$neftRows) {
    die('Clone NEFT is empty for this month.');
}
$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Armor Fire';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clone NEFT — <?php echo htmlspecialchars($monthLabel); ?></title>
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
        .neft-totals { width: 100%; border-collapse: collapse; margin-top: 0; page-break-inside: avoid; }
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
        <button type="button" onclick="window.print()">Print / Save PDF</button>
        <button type="button" onclick="window.close()">Close</button>
        <span style="margin-left:8px;color:#444;">Print dialog ma Destination = Save as PDF.</span>
    </div>
    <h1><?php echo htmlspecialchars($companyName); ?> — Clone NEFT / Bank Transfer Sheet</h1>
    <div class="meta">
        <?php echo htmlspecialchars($monthLabel); ?> · CNC + BUFF · Clone Salary Register Net<br>
        CREDIT_NARR=<?php echo htmlspecialchars($export['credit_narr']); ?>
        · PYMT_DATE=<?php echo htmlspecialchars($export['pay_date']); ?>
        · EMAIL=<?php echo htmlspecialchars($export['cfg']['email_id']); ?>
    </div>
    <div class="chips meta">
        <span><?php echo count($neftRows); ?> employees · ₹ <?php echo cncCloneNum($sec['final_total'], 0); ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Sr</th>
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
        <?php foreach ($neftRows as $r): ?>
            <?php $missBank = !empty($r['missing_bank']); ?>
            <tr>
                <td><?php echo (int) $r['sr']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars(cncCloneBeneName($r['employee_name'])); ?></strong>
                    <div class="sub"><?php echo htmlspecialchars($r['employee_code']); ?><?php echo $missBank ? ' · No bank A/C' : ''; ?></div>
                </td>
                <td><?php echo htmlspecialchars($r['department']); ?></td>
                <td><?php echo htmlspecialchars($missBank ? '—' : $r['account']); ?></td>
                <td><?php echo htmlspecialchars($r['ifsc'] !== '' ? $r['ifsc'] : '-'); ?></td>
                <td class="num"><?php echo cncCloneNum($r['amount'], 0); ?></td>
                <td><?php echo htmlspecialchars($export['credit_narr']); ?></td>
                <td><?php echo htmlspecialchars($export['pay_date']); ?></td>
                <td><?php echo htmlspecialchars($export['cfg']['email_id']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <table class="neft-totals">
        <tr class="sec-f">
            <td colspan="5">NEFT Total (<?php echo count($neftRows); ?>)</td>
            <td class="num"><?php echo cncCloneNum($sec['final_total'], 0); ?></td>
            <td colspan="3"></td>
        </tr>
    </table>
</body>
</html>
