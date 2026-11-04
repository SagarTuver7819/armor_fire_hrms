<?php
/**
 * Clone CNC — Daily letter-pad print (Portrait / Landscape)
 * Content sits in letterhead BODY only (below header / above footer).
 * Staff table includes Salary + empty hajri box for selected day.
 */
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/document_print.php';
require_once __DIR__ . '/../../includes/contractor_split_helper.php';

$id = (int) ($_GET['id'] ?? 0);
$day = (int) ($_GET['day'] ?? (int) date('j'));
$orient = strtolower((string) ($_GET['orient'] ?? 'portrait'));
if (!in_array($orient, ['portrait', 'landscape'], true)) {
    $orient = 'portrait';
}

if ($id <= 0) {
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}

$conn = getDBConnection();
$sheet = getContractorSheetById($id, $conn);
if (!$sheet) {
    $conn->close();
    header('Location: ' . app_url('contractor/operations_clone/index.php'));
    exit;
}

$month = (int) ($sheet['month_no'] ?? date('n'));
$year = (int) ($sheet['year_no'] ?? date('Y'));
$monthDays = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
if ($day < 1 || $day > $monthDays) {
    $day = min(max(1, (int) date('j')), $monthDays);
}

$operation = (string) ($sheet['operation'] ?? 'CNC');
$opGu = contractorOperationGujaratiName($operation);
$items = $sheet['items'] ?? [];
$staffBundle = getOpsCloneDepartmentStaff($operation, (int) ($sheet['employee_id'] ?? 0), $conn);

$productNames = [];
$ids = [];
foreach ($items as $it) {
    $pid = (int) ($it['product_id'] ?? 0);
    if ($pid > 0) {
        $ids[$pid] = true;
    }
}
if ($ids) {
    $idList = implode(',', array_map('intval', array_keys($ids)));
    $res = $conn->query("SELECT id, product_name, process FROM contractor_products WHERE id IN ({$idList})");
    if ($res) {
        while ($p = $res->fetch_assoc()) {
            $label = trim((string) ($p['process'] ?? ''));
            if ($label === '') {
                $label = (string) ($p['product_name'] ?? '');
            }
            $productNames[(int) $p['id']] = $label;
        }
    }
}
$conn->close();

$brand = getCompanyDocumentBranding();
$company = $brand['company_name'] ?? 'Company';
$months = contractorMonths();
$monthLabel = ($months[$month] ?? (string) $month) . ' ' . $year;
$dateLabel = sprintf('%02d-%02d-%04d', $day, $month, $year);
$weekday = date('l', mktime(0, 0, 0, $month, $day, $year));
$empMain = trim(($sheet['employee_code'] ?? '') . ' — ' . ($sheet['employee_name'] ?? ''));
$isLand = $orient === 'landscape';
$pageSize = $isLand ? 'letter landscape' : 'letter portrait';
// Letterhead safe zone: clear printed header band + footer address band
$padTop = $isLand ? '32mm' : '42mm';
$padBottom = $isLand ? '22mm' : '30mm';
$padSide = $isLand ? '12mm' : '14mm';
$backUrl = app_url('contractor/operations_clone/edit.php?id=' . $id . '&operation=' . rawurlencode($operation) . '&month=' . $month . '&year=' . $year);
$selfBase = app_url('contractor/operations_clone/print_daily.php?id=' . $id);
$sheetClass = companyDocSheetClass($brand, 'cdoc-flow daily-sheet');
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <title>CNC Daily Report — <?php echo htmlspecialchars($dateLabel); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        @page {
            size: <?php echo $pageSize; ?>;
            <?php if (!empty($brand['has_letterhead'])): ?>
            margin: <?php echo $padTop; ?> <?php echo $padSide; ?> <?php echo $padBottom; ?> <?php echo $padSide; ?>;
            <?php else: ?>
            margin: 12mm 10mm;
            <?php endif; ?>
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            color: #111;
            background: #e2e8f0;
            font-family: "Noto Sans Gujarati", "Shruti", "Mangal", Arial, Helvetica, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: #0f172a;
            color: #fff;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .toolbar label { font-size: 13px; margin-right: 4px; }
        .toolbar select, .toolbar button, .toolbar a {
            font-size: 13px;
            padding: 7px 12px;
            border-radius: 6px;
            border: 0;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar select { background: #fff; color: #111; }
        .btn-print { background: #d2232a; color: #fff; }
        .btn-orient { background: #334155; color: #fff; }
        .btn-orient.is-on { background: #0ea5e9; }
        .btn-back { background: #e2e8f0; color: #111; }

        .sheet-wrap {
            padding: 16px;
            display: flex;
            justify-content: center;
        }
        .daily-sheet.cdoc-sheet {
            width: <?php echo $isLand ? '11in' : '8.5in'; ?>;
            min-height: <?php echo $isLand ? '8.5in' : '11in'; ?>;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        }
        /* Screen preview: letterhead full page; content only in mid band */
        .daily-sheet.has-letterhead > .cdoc-inner,
        .daily-sheet:has(.cdoc-letterhead) > .cdoc-inner {
            padding: <?php echo $padTop; ?> <?php echo $padSide; ?> <?php echo $padBottom; ?> <?php echo $padSide; ?> !important;
        }
        .daily-sheet .cdoc-letterhead {
            background-size: 100% 100% !important;
        }
        <?php if ($isLand && !empty($brand['has_letterhead'])): ?>
        .daily-sheet.cdoc-flow .cdoc-letterhead {
            height: 100%;
            background-size: 100% 100%;
        }
        <?php endif; ?>

        .doc-title {
            text-align: center;
            margin: 0 0 2px;
            font-size: <?php echo $isLand ? '16px' : '15px'; ?>;
            font-weight: 800;
        }
        .doc-title-gu {
            text-align: center;
            margin: 0 0 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
        }
        .meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            background: rgba(248, 250, 252, 0.92);
            padding: 6px 8px;
            margin-bottom: 8px;
            border-radius: 6px;
        }
        .section-head {
            margin: 10px 0 4px;
            font-size: 12.5px;
            font-weight: 800;
            color: #b01c22;
            border-bottom: 2px solid #fecaca;
            padding-bottom: 3px;
        }
        .section-head small {
            display: block;
            font-weight: 600;
            color: #64748b;
            font-size: 10.5px;
            margin-top: 1px;
        }
        table.daily {
            width: 100%;
            border-collapse: collapse;
            font-size: <?php echo $isLand ? '11.5px' : '10.5px'; ?>;
            background: rgba(255,255,255,0.92);
        }
        table.daily th,
        table.daily td {
            border: 1px solid #94a3b8;
            padding: 4px 5px;
            vertical-align: middle;
        }
        table.daily th {
            background: #fde8e9;
            font-weight: 700;
            text-align: center;
        }
        table.daily td.sr { width: 36px; text-align: center; }
        table.daily td.process { text-align: left; }
        table.daily td.sal {
            width: 90px;
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }
        table.daily .en {
            display: block;
            font-weight: 700;
            color: #0f172a;
        }
        table.daily .gu {
            display: block;
            margin-top: 1px;
            font-size: 10.5px;
            color: #475569;
            font-weight: 600;
        }
        table.daily td.box-col {
            width: <?php echo $isLand ? '70px' : '58px'; ?>;
            text-align: center;
        }
        .empty-box {
            display: inline-block;
            width: <?php echo $isLand ? '40px' : '34px'; ?>;
            height: <?php echo $isLand ? '22px' : '20px'; ?>;
            border: 1.5px solid #334155;
            border-radius: 3px;
            background: #fff;
            vertical-align: middle;
        }
        .code { font-weight: 700; color: #b01c22; }
        .foot-note {
            margin-top: 8px;
            font-size: 9.5px;
            color: #64748b;
        }

        @media print {
            body { background: #fff !important; }
            .toolbar { display: none !important; }
            .sheet-wrap { padding: 0 !important; }
            .daily-sheet.cdoc-sheet {
                width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
            }
            /* @page margins already clear header/footer — keep content tight inside */
            .daily-sheet.has-letterhead > .cdoc-inner,
            .daily-sheet:has(.cdoc-letterhead) > .cdoc-inner,
            .daily-sheet.cdoc-flow.has-letterhead > .cdoc-inner {
                padding: 0 1mm !important;
            }
            .daily-sheet.cdoc-flow.has-letterhead .cdoc-letterhead,
            .daily-sheet.cdoc-flow:has(.cdoc-letterhead) .cdoc-letterhead {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background-size: 100% 100% !important;
                background-position: center top !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            table.daily { page-break-inside: auto; }
            table.daily tr { page-break-inside: avoid; }
            .section-head { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <label>Day</label>
        <select id="daySel" onchange="reloadDaily()">
            <?php for ($d = 1; $d <= $monthDays; $d++): ?>
                <option value="<?php echo $d; ?>" <?php echo $d === $day ? 'selected' : ''; ?>><?php echo $d; ?></option>
            <?php endfor; ?>
        </select>
        <a class="btn-orient <?php echo !$isLand ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($selfBase . '&day=' . $day . '&orient=portrait'); ?>">Portrait</a>
        <a class="btn-orient <?php echo $isLand ? 'is-on' : ''; ?>" href="<?php echo htmlspecialchars($selfBase . '&day=' . $day . '&orient=landscape'); ?>">Landscape</a>
        <button type="button" class="btn-print" onclick="window.print()">Print / PDF</button>
        <a class="btn-back" href="<?php echo htmlspecialchars($backUrl); ?>">Back</a>
    </div>

    <div class="sheet-wrap">
        <div class="<?php echo htmlspecialchars($sheetClass); ?>">
            <?php echo companyDocRenderWatermark($brand); ?>
            <div class="cdoc-inner">
                <?php echo companyDocRenderHeader($brand, 'Human Resource · CNC Daily Report'); ?>

                <div class="doc-title"><?php echo htmlspecialchars($operation); ?> — Daily Report</div>
                <div class="doc-title-gu"><?php echo htmlspecialchars($opGu); ?> — દૈનિક રિપોર્ટ</div>

                <div class="meta-bar">
                    <div><strong>Operation:</strong> <?php echo htmlspecialchars($operation); ?>
                        <span>(<?php echo htmlspecialchars($opGu); ?>)</span>
                    </div>
                    <div><strong>Date:</strong> <?php echo htmlspecialchars($dateLabel); ?> · <?php echo htmlspecialchars($weekday); ?></div>
                    <div><strong>Month:</strong> <?php echo htmlspecialchars($monthLabel); ?></div>
                    <div><strong>Main / Sheet:</strong> <?php echo htmlspecialchars($empMain); ?></div>
                    <div><strong>Paper:</strong> Letter · <?php echo htmlspecialchars(ucfirst($orient)); ?></div>
                </div>

                <div class="section-head">
                    Contract Process — Day <?php echo (int) $day; ?>
                    <small>કોન્ટ્રાક્ટ પ્રોસેસ · તારીખ <?php echo (int) $day; ?> માટે ખાલી બોક્સ</small>
                </div>
                <table class="daily">
                    <thead>
                        <tr>
                            <th style="width:36px;">Sr</th>
                            <th>Contract Process<br><span style="font-weight:600;color:#64748b;">કોન્ટ્રાક્ટ પ્રોસેસ</span></th>
                            <th class="box-col">Day <?php echo (int) $day; ?><br><span style="font-weight:600;color:#64748b;">બોક્સ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sr = 0;
                        if (!$items):
                            ?>
                            <tr><td colspan="3" style="text-align:center;color:#64748b;">No contract process rows on this sheet.</td></tr>
                        <?php else:
                            foreach ($items as $it):
                                $pid = (int) ($it['product_id'] ?? 0);
                                if ($pid <= 0) {
                                    continue;
                                }
                                $sr++;
                                $en = $productNames[$pid] ?? trim((string) ($it['product_label'] ?? ('Product #' . $pid)));
                                $gu = contractorProcessGujaratiName($en);
                                ?>
                                <tr>
                                    <td class="sr"><?php echo $sr; ?></td>
                                    <td class="process">
                                        <span class="en"><?php echo htmlspecialchars($en); ?></span>
                                        <?php if ($gu !== '' && strcasecmp($gu, $en) !== 0): ?>
                                            <span class="gu"><?php echo htmlspecialchars($gu); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="box-col"><span class="empty-box"></span></td>
                                </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>

                <div class="section-head">
                    CNC Department — Normal Salary Employees · Hajri Day <?php echo (int) $day; ?>
                    <small>સી.એન.સી. વિભાગ · Emp Code · Name · Salary · તારીખ <?php echo (int) $day; ?> હાજરી</small>
                </div>
                <table class="daily">
                    <thead>
                        <tr>
                            <th style="width:36px;">Sr</th>
                            <th style="width:100px;">Emp Code<br><span style="font-weight:600;color:#64748b;">કોડ</span></th>
                            <th>Employee Name<br><span style="font-weight:600;color:#64748b;">નામ</span></th>
                            <th style="width:90px;">Salary<br><span style="font-weight:600;color:#64748b;">પગાર</span></th>
                            <th class="box-col">Day <?php echo (int) $day; ?><br><span style="font-weight:600;color:#64748b;">હાજરી</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffBundle['rows'])): ?>
                            <tr><td colspan="5" style="text-align:center;color:#64748b;">No CNC Normal Salary employees (Main Contractor excluded).</td></tr>
                        <?php else: ?>
                            <?php foreach ($staffBundle['rows'] as $i => $st): ?>
                                <tr>
                                    <td class="sr"><?php echo $i + 1; ?></td>
                                    <td><span class="code"><?php echo htmlspecialchars((string) $st['employee_code']); ?></span></td>
                                    <td><?php echo htmlspecialchars((string) $st['employee_name']); ?></td>
                                    <td class="sal"><?php echo formatIndianNumber((float) ($st['decided_salary'] ?? 0), 2); ?></td>
                                    <td class="box-col"><span class="empty-box"></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="foot-note">
                    Printed <?php echo date('d-m-Y h:i A'); ?> · Day <?php echo (int) $day; ?> · Letter · <?php echo htmlspecialchars(ucfirst($orient)); ?>
                </div>

                <?php echo companyDocRenderFooter($brand); ?>
            </div>
        </div>
    </div>

    <script>
        function reloadDaily() {
            var day = document.getElementById('daySel').value;
            window.location.href = <?php echo json_encode($selfBase); ?>
                + '&day=' + encodeURIComponent(day)
                + '&orient=' + encodeURIComponent(<?php echo json_encode($orient); ?>);
        }
    </script>
</body>
</html>
