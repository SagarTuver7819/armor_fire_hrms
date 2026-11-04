<?php
/**
 * Clone CNC — Daily letter-pad print (Portrait / Landscape)
 * Same letterhead pattern as Appointment Letter / TC-style docs:
 * - Fixed page box with full letterhead
 * - Content only in mid band (never header / footer zone)
 * - Overflow → next page, letterhead repeats
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
$months = contractorMonths();
$monthLabel = ($months[$month] ?? (string) $month) . ' ' . $year;
$dateLabel = sprintf('%02d-%02d-%04d', $day, $month, $year);
$weekday = date('l', mktime(0, 0, 0, $month, $day, $year));
$empMain = trim(($sheet['employee_code'] ?? '') . ' — ' . ($sheet['employee_name'] ?? ''));
$isLand = $orient === 'landscape';
$pageW = $isLand ? '11in' : '8.5in';
$pageH = $isLand ? '8.5in' : '11in';
// Clear letterhead header + footer bands (same idea as appointment letter 42mm / 32mm)
$padTop = $isLand ? '34mm' : '44mm';
$padBottom = $isLand ? '24mm' : '32mm';
$padSide = $isLand ? '12mm' : '14mm';

$backUrl = app_url('contractor/operations_clone/edit.php?id=' . $id . '&operation=' . rawurlencode($operation) . '&month=' . $month . '&year=' . $year);
$selfBase = app_url('contractor/operations_clone/print_daily.php?id=' . $id);

// ---- Build printable rows ----
$processRows = [];
foreach ($items as $it) {
    $pid = (int) ($it['product_id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $en = $productNames[$pid] ?? trim((string) ($it['product_label'] ?? ('Product #' . $pid)));
    $gu = contractorProcessGujaratiName($en);
    $processRows[] = ['en' => $en, 'gu' => $gu];
}
$staffRows = [];
foreach ($staffBundle['rows'] as $st) {
    $staffRows[] = [
        'code' => (string) ($st['employee_code'] ?? ''),
        'name' => (string) ($st['employee_name'] ?? ''),
        'salary' => (float) ($st['decided_salary'] ?? 0),
    ];
}

// Rows per page (leave room for titles; landscape fits more width but less height)
$procFirst = $isLand ? 10 : 12;
$procCont = $isLand ? 14 : 16;
$staffFirst = $isLand ? 12 : 14;
$staffCont = $isLand ? 16 : 18;

$pages = []; // each: ['show_meta'=>bool, 'proc_title'=>bool, 'proc'=>[], 'staff_title'=>bool, 'staff'=>[], 'proc_cont'=>bool, 'staff_cont'=>bool]

$procChunks = [];
if ($processRows) {
    $procChunks[] = array_splice($processRows, 0, $procFirst);
    while ($processRows) {
        $procChunks[] = array_splice($processRows, 0, $procCont);
    }
} else {
    $procChunks[] = [];
}

$staffChunks = [];
if ($staffRows) {
    $staffChunks[] = array_splice($staffRows, 0, $staffFirst);
    while ($staffRows) {
        $staffChunks[] = array_splice($staffRows, 0, $staffCont);
    }
}

// Page 1: meta + first process chunk
$pages[] = [
    'show_meta' => true,
    'proc_title' => true,
    'proc' => $procChunks[0] ?? [],
    'proc_cont' => false,
    'staff_title' => false,
    'staff' => [],
    'staff_cont' => false,
];
for ($i = 1, $n = count($procChunks); $i < $n; $i++) {
    $pages[] = [
        'show_meta' => false,
        'proc_title' => true,
        'proc' => $procChunks[$i],
        'proc_cont' => true,
        'staff_title' => false,
        'staff' => [],
        'staff_cont' => false,
    ];
}

// Staff pages
foreach ($staffChunks as $si => $chunk) {
    // Try to append staff onto last page if it only has processes and room — keep simple: separate pages
    $pages[] = [
        'show_meta' => false,
        'proc_title' => false,
        'proc' => [],
        'proc_cont' => false,
        'staff_title' => true,
        'staff' => $chunk,
        'staff_cont' => $si > 0,
    ];
}

// If last process page is almost empty and first staff chunk is small, could merge — skip for reliability

$totalPages = count($pages);
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <title>CNC Daily Report — <?php echo $h($dateLabel); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        @page {
            size: <?php echo $isLand ? 'letter landscape' : 'letter portrait'; ?>;
            margin: 0;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            color: #111;
            background: #cbd5e1;
            font-family: "Noto Sans Gujarati", "Shruti", "Mangal", Arial, Helvetica, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: #0f172a;
            color: #fff;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .toolbar label { font-size: 13px; }
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

        .sheet-stack { padding: 16px; }

        /* Discrete letter page — same idea as Appointment Letter / TC */
        .page {
            width: <?php echo $pageW; ?>;
            height: <?php echo $pageH; ?>;
            max-width: 100%;
            margin: 0 auto 18px;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        }
        .page.has-letterhead,
        .page:has(.cdoc-letterhead) {
            padding: 0 !important;
            border: 0 !important;
        }
        .page > .cdoc-inner {
            position: relative;
            z-index: 1;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .page.has-letterhead > .cdoc-inner,
        .page:has(.cdoc-letterhead) > .cdoc-inner {
            padding: <?php echo $padTop; ?> <?php echo $padSide; ?> <?php echo $padBottom; ?> <?php echo $padSide; ?> !important;
            height: 100% !important;
            max-height: 100% !important;
            overflow: hidden !important;
        }
        .page .cdoc-letterhead {
            position: absolute !important;
            inset: 0 !important;
            background-size: 100% 100% !important;
            background-position: center top !important;
            background-repeat: no-repeat !important;
            z-index: 0;
            pointer-events: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }
        .page-no {
            flex-shrink: 0;
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }

        .doc-title {
            text-align: center;
            margin: 0 0 2px;
            font-size: 15px;
            font-weight: 800;
        }
        .doc-title-gu {
            text-align: center;
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        .meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 12px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
            background: rgba(248, 250, 252, 0.95);
            padding: 6px 8px;
            margin-bottom: 8px;
            border-radius: 6px;
        }
        .cont-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            margin: 0 0 6px;
        }
        .section-head {
            margin: 8px 0 4px;
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
            font-size: 10.5px;
            background: rgba(255,255,255,0.94);
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
        table.daily td.sal {
            width: 88px;
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }
        table.daily .en { display: block; font-weight: 700; }
        table.daily .gu {
            display: block;
            margin-top: 1px;
            font-size: 10px;
            color: #475569;
            font-weight: 600;
        }
        table.daily td.box-col {
            width: 56px;
            text-align: center;
        }
        .empty-box {
            display: inline-block;
            width: 32px;
            height: 18px;
            border: 1.5px solid #334155;
            border-radius: 3px;
            background: #fff;
        }
        .code { font-weight: 700; color: #b01c22; }

        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .toolbar { display: none !important; }
            .sheet-stack { padding: 0 !important; }
            .page {
                width: <?php echo $pageW; ?>;
                height: <?php echo $pageH; ?>;
                max-width: none;
                margin: 0 !important;
                box-shadow: none !important;
                page-break-after: always;
                break-after: page;
                overflow: hidden !important;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .page.has-letterhead,
            .page:has(.cdoc-letterhead) {
                padding: 0 !important;
            }
            .page .cdoc-letterhead {
                position: absolute !important;
                inset: 0 !important;
                background-size: 100% 100% !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .page-no { display: none; }
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
        <a class="btn-orient <?php echo !$isLand ? 'is-on' : ''; ?>" href="<?php echo $h($selfBase . '&day=' . $day . '&orient=portrait'); ?>">Portrait</a>
        <a class="btn-orient <?php echo $isLand ? 'is-on' : ''; ?>" href="<?php echo $h($selfBase . '&day=' . $day . '&orient=landscape'); ?>">Landscape</a>
        <button type="button" class="btn-print" onclick="window.print()">Print / PDF</button>
        <a class="btn-back" href="<?php echo $h($backUrl); ?>">Back</a>
        <span style="margin-left:8px;font-size:12px;opacity:.85;"><?php echo (int) $totalPages; ?> page(s) · letterhead repeats</span>
    </div>

    <div class="sheet-stack">
        <?php foreach ($pages as $pi => $pg):
            $pageNo = $pi + 1;
            $srProcBase = 0;
            // Compute process sr offset
            for ($j = 0; $j < $pi; $j++) {
                $srProcBase += count($pages[$j]['proc']);
            }
            $srStaffBase = 0;
            for ($j = 0; $j < $pi; $j++) {
                $srStaffBase += count($pages[$j]['staff']);
            }
            ?>
            <div class="<?php echo $h(companyDocSheetClass($brand, 'page')); ?>">
                <?php echo companyDocRenderWatermark($brand); ?>
                <div class="cdoc-inner">
                    <?php echo companyDocRenderHeader($brand, 'Human Resource · CNC Daily Report'); ?>

                    <div class="page-body">
                        <?php if (!empty($pg['show_meta'])): ?>
                            <div class="doc-title"><?php echo $h($operation); ?> — Daily Report</div>
                            <div class="doc-title-gu"><?php echo $h($opGu); ?> — દૈનિક રિપોર્ટ</div>
                            <div class="meta-bar">
                                <div><strong>Operation:</strong> <?php echo $h($operation); ?> (<?php echo $h($opGu); ?>)</div>
                                <div><strong>Date:</strong> <?php echo $h($dateLabel); ?> · <?php echo $h($weekday); ?></div>
                                <div><strong>Month:</strong> <?php echo $h($monthLabel); ?></div>
                                <div><strong>Main / Sheet:</strong> <?php echo $h($empMain); ?></div>
                                <div><strong>Paper:</strong> Letter · <?php echo $h(ucfirst($orient)); ?></div>
                            </div>
                        <?php elseif (!empty($pg['proc_cont']) || !empty($pg['staff_cont'])): ?>
                            <div class="cont-label">
                                <?php echo $h($operation); ?> · Day <?php echo (int) $day; ?> · Continued · Page <?php echo (int) $pageNo; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($pg['proc_title'])): ?>
                            <div class="section-head">
                                Contract Process — Day <?php echo (int) $day; ?><?php echo !empty($pg['proc_cont']) ? ' (cont.)' : ''; ?>
                                <small>કોન્ટ્રાક્ટ પ્રોસેસ · તારીખ <?php echo (int) $day; ?> · ખાલી બોક્સ</small>
                            </div>
                            <table class="daily">
                                <thead>
                                    <tr>
                                        <th style="width:36px;">Sr</th>
                                        <th>Contract Process<br><span style="font-weight:600;color:#64748b;">કોન્ટ્રાક્ટ પ્રોસેસ</span></th>
                                        <th class="box-col">Day <?php echo (int) $day; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$pg['proc']): ?>
                                        <tr><td colspan="3" style="text-align:center;color:#64748b;">No contract process rows.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pg['proc'] as $ri => $row): ?>
                                            <tr>
                                                <td class="sr"><?php echo $srProcBase + $ri + 1; ?></td>
                                                <td>
                                                    <span class="en"><?php echo $h($row['en']); ?></span>
                                                    <?php if ($row['gu'] !== '' && strcasecmp($row['gu'], $row['en']) !== 0): ?>
                                                        <span class="gu"><?php echo $h($row['gu']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="box-col"><span class="empty-box"></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($pg['staff_title'])): ?>
                            <div class="section-head">
                                CNC Department — Normal Salary Employees · Hajri Day <?php echo (int) $day; ?><?php echo !empty($pg['staff_cont']) ? ' (cont.)' : ''; ?>
                                <small>Emp Code · Name · Salary · હાજરી બોક્સ</small>
                            </div>
                            <table class="daily">
                                <thead>
                                    <tr>
                                        <th style="width:36px;">Sr</th>
                                        <th style="width:100px;">Emp Code</th>
                                        <th>Employee Name</th>
                                        <th style="width:88px;">Salary<br><span style="font-weight:600;color:#64748b;">પગાર</span></th>
                                        <th class="box-col">Day <?php echo (int) $day; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$pg['staff']): ?>
                                        <tr><td colspan="5" style="text-align:center;color:#64748b;">No employees.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pg['staff'] as $ri => $row): ?>
                                            <tr>
                                                <td class="sr"><?php echo $srStaffBase + $ri + 1; ?></td>
                                                <td><span class="code"><?php echo $h($row['code']); ?></span></td>
                                                <td><?php echo $h($row['name']); ?></td>
                                                <td class="sal"><?php echo formatIndianNumber($row['salary'], 2); ?></td>
                                                <td class="box-col"><span class="empty-box"></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="page-no">Page <?php echo (int) $pageNo; ?> / <?php echo (int) $totalPages; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
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
