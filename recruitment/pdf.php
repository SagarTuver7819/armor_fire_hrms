<?php
/**
 * Recruitment Application — Print / PDF
 * Content packs into A4 pages (fill available space, then next page).
 * Letterhead repeats on every page; page numbers shown.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row) {
    die('Application not found.');
}

$labels = recruitmentStatusLabels();
$modes = recruitmentInterviewModes();
$sides = recruitmentAwaitedSides();
$brand = getCompanyDocumentBranding();
$company = $brand['company_name'];
$letterheadSrc = (string) ($brand['letterhead_src'] ?? '');

$criteria = getRecruitmentCriteriaForPosition((string) ($row['position_name'] ?? ''));
$marks = getRecruitmentApplicationMarks((int) $row['id']);
foreach ($marks as $m) {
    $found = false;
    foreach ($criteria as $c) {
        if (strcasecmp((string) $c['criteria_label'], (string) $m['criteria_label']) === 0) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $criteria[] = [
            'id' => (int) ($m['criteria_id'] ?? 0),
            'criteria_label' => $m['criteria_label'],
            'position_name' => '',
            'answer_type' => 'text',
        ];
    }
}

$isSales = strcasecmp(trim((string) ($row['position_name'] ?? '')), 'Sales') === 0;
$hasDescriptive = $isSales && !empty($row['descriptive']);

$v = static function ($x) {
    $x = trim((string) $x);
    return $x !== '' ? $x : '—';
};
$money = static function ($x) {
    if ($x === null || $x === '') {
        return '—';
    }
    return '₹ ' . number_format((float) $x, 0);
};
$cell = static function ($label, $value, $span = 1) {
    $colspan = $span > 1 ? ' colspan="' . (int) $span . '"' : '';
    echo '<td class="fld"' . $colspan . '><span class="lbl">' . htmlspecialchars($label) . '</span><span class="val">' . $value . '</span></td>';
};

$sheetClass = companyDocSheetClass($brand, 'sheet');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application PDF · <?php echo htmlspecialchars((string) $row['application_no']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            margin: 0;
            padding: 16px;
            font-size: 11px;
            line-height: 1.35;
            background: #e5e7eb;
        }
        .bar {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
        }
        .bar .hint { font-size: 11px; color: #64748b; }
        .bar button {
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 9px 14px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
        }
        #pack-out { width: 210mm; max-width: 100%; margin: 0 auto; }
        .sheet {
            width: 210mm;
            max-width: 100%;
            height: 297mm;
            min-height: 297mm;
            max-height: 297mm;
            margin: 0 auto 16px;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
        }
        <?php echo companyDocPrintCss(); ?>
        .sheet.has-letterhead,
        .sheet:has(.cdoc-letterhead) {
            padding: 0 !important;
            border: 0 !important;
            overflow: hidden !important;
        }
        .sheet .cdoc-body-pad {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 42mm 14mm 36mm 14mm !important;
            box-sizing: border-box;
        }
        .sheet:not(.has-letterhead) .cdoc-body-pad {
            padding: 12mm 14mm 12mm 14mm !important;
        }
        .page-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }
        .page-no {
            flex-shrink: 0;
            text-align: center;
            font-size: 9.5px;
            font-weight: 700;
            color: #475569;
            margin-top: 4px;
            letter-spacing: .04em;
        }
        .doc-title {
            text-align: center;
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: underline;
            text-underline-offset: 3px;
            color: #111;
        }
        .cand {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px 12px;
            align-items: start;
            padding: 8px 10px;
            margin: 0 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: rgba(255,255,255,.92);
        }
        .cand-name {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 4px;
            color: #111;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .cand-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 12px;
            color: #334155;
            font-weight: 700;
            font-size: 10.5px;
        }
        .cand-meta .k {
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: .04em;
        }
        .badge {
            display: inline-block;
            padding: 4px 9px;
            border: 1px solid #9f1239;
            background: #fee2e2;
            color: #9f1239;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
            border-radius: 999px;
            justify-self: end;
            height: fit-content;
        }
        .sec { margin: 0 0 6px; }
        .sec-h {
            margin: 0;
            padding: 5px 10px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #fff;
            background: #d2232a;
            font-weight: 800;
        }
        .sec-h small { font-weight: 600; text-transform: none; letter-spacing: 0; opacity: .9; }
        table.boxes {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.boxes td.fld {
            border: 1px solid #94a3b8;
            padding: 4px 6px;
            vertical-align: top;
            width: 25%;
            background: #fff;
        }
        table.boxes td.fld .lbl {
            display: block;
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: .03em;
            margin-bottom: 1px;
        }
        table.boxes td.fld .val {
            display: block;
            font-size: 10.5px;
            font-weight: 700;
            color: #111;
            min-height: 12px;
            word-break: break-word;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #64748b;
            padding: 4px 6px;
            text-align: left;
            font-size: 10px;
            vertical-align: top;
        }
        table.grid th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: .03em;
        }
        table.grid td.ans { font-weight: 700; width: 28%; }
        .mark-yes { color: #15803d; }
        .mark-no { color: #b91c1c; }
        .foot {
            padding: 8px 0 0;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 9.5px;
            color: #444;
        }
        .sign {
            width: 30%;
            text-align: center;
            border-top: 1px solid #111;
            margin-top: 28px;
            padding-top: 4px;
            font-weight: 700;
        }
        .gen { margin-top: 4px; color: #666; }
        .cont-note {
            margin: 0 0 6px;
            font-size: 9px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        /* Measure source — off-screen, same width as page body */
        #pack-src {
            position: absolute;
            left: -10000px;
            top: 0;
            width: 182mm; /* 210mm - 14mm*2 side padding */
            visibility: hidden;
            pointer-events: none;
        }
        @media print {
            <?php echo companyDocLetterheadPageCss($brand); ?>
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .bar { display: none !important; }
            #pack-src { display: none !important; }
            #pack-out { width: 210mm; margin: 0; }
            .sheet {
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                margin: 0 !important;
                box-shadow: none !important;
                overflow: hidden !important;
                page-break-after: always;
                break-after: page;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .sheet:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .sheet .cdoc-body-pad {
                padding: 42mm 14mm 36mm 14mm !important;
            }
            .sheet:not(.has-letterhead) .cdoc-body-pad {
                padding: 12mm 14mm 12mm 14mm !important;
            }
        }
    </style>
</head>
<body>
<div class="bar">
    <div>
        <strong><?php echo htmlspecialchars((string) $row['application_no']); ?></strong> · Application Print Form
        <div class="hint">Print → Margins: <strong>None</strong> · Background graphics: On</div>
    </div>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<div id="pack-out"
     data-sheet-class="<?php echo htmlspecialchars($sheetClass); ?>"
     data-letterhead="<?php echo htmlspecialchars($letterheadSrc, ENT_QUOTES, 'UTF-8'); ?>"
     data-has-letterhead="<?php echo !empty($brand['has_letterhead']) ? '1' : '0'; ?>"></div>

<!-- Source content: packed into pages by JS (fill space, then next page) -->
<div id="pack-src">
    <div class="pack-item" data-keep="1">
        <h1 class="doc-title">Application Form</h1>
        <div class="cand">
            <div>
                <p class="cand-name"><?php echo htmlspecialchars((string) $row['full_name']); ?></p>
                <div class="cand-meta">
                    <span><span class="k">App No</span> <?php echo htmlspecialchars((string) $row['application_no']); ?></span>
                    <span><span class="k">Position</span> <?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></span>
                    <span><span class="k">Department</span> <?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></span>
                    <?php if (!empty($row['created_at'])): ?>
                        <span><span class="k">Applied</span> <?php echo htmlspecialchars(formatDateDisplay($row['created_at'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge"><?php echo htmlspecialchars($labels[$row['status']] ?? $row['status']); ?></span>
        </div>
    </div>

    <div class="pack-item sec">
        <h2 class="sec-h">Personal Details</h2>
        <table class="boxes">
            <tr>
                <?php
                $cell('Mobile', htmlspecialchars($v($row['mobile'] ?? '')));
                $cell('Alternate Mobile', htmlspecialchars($v($row['alt_mobile'] ?? '')));
                $cell('Email', htmlspecialchars($v($row['email'] ?? '')), 2);
                ?>
            </tr>
            <tr>
                <?php
                $dobAge = (!empty($row['dob']) ? formatDateDisplay($row['dob']) : '—')
                    . (!empty($row['age_years']) ? (' / ' . (int) $row['age_years'] . ' yrs') : '');
                $cell('Date of Birth / Age', htmlspecialchars($dobAge));
                $cell('Gender', htmlspecialchars($v($row['gender'] ?? '')));
                $cell('Marital Status', htmlspecialchars($v($row['marital_status'] ?? '')));
                $cell('City', htmlspecialchars($v($row['city'] ?? '')));
                ?>
            </tr>
            <tr>
                <?php
                $cell('State', htmlspecialchars($v($row['state_name'] ?? '')));
                $cell('Pincode', htmlspecialchars($v($row['pincode'] ?? '')));
                $cell('Address', nl2br(htmlspecialchars($v($row['address'] ?? ''))), 2);
                ?>
            </tr>
        </table>
    </div>

    <div class="pack-item sec">
        <h2 class="sec-h">ID &amp; Bank Details</h2>
        <table class="boxes">
            <tr>
                <?php
                $cell('Aadhaar No.', htmlspecialchars($v($row['aadhaar_no'] ?? '')));
                $cell('PAN No.', htmlspecialchars($v($row['pan_no'] ?? '')));
                $cell('Bank Name', htmlspecialchars($v($row['bank_name'] ?? '')));
                $cell('Account No.', htmlspecialchars($v($row['bank_account'] ?? '')));
                ?>
            </tr>
            <tr>
                <?php $cell('IFSC', htmlspecialchars($v($row['bank_ifsc'] ?? '')), 4); ?>
            </tr>
        </table>
    </div>

    <div class="pack-item sec">
        <h2 class="sec-h">Salary &amp; Experience Summary</h2>
        <table class="boxes">
            <tr>
                <?php
                $cell('Current / Last Salary', $money($row['current_salary'] ?? null));
                $cell('Expected Salary', $money($row['expected_salary'] ?? null));
                $cell('Notice Period', htmlspecialchars($v($row['notice_period'] ?? '')));
                $cell('Total Experience', htmlspecialchars($v($row['total_experience'] ?? '')));
                ?>
            </tr>
        </table>
    </div>

    <div class="pack-item sec pack-split">
        <h2 class="sec-h">Educational Details</h2>
        <table class="grid pack-rows">
            <thead><tr><th>Degree</th><th>Institution</th><th>Year</th><th>% / CGPA</th></tr></thead>
            <tbody>
            <?php if (empty($row['education'])): ?>
                <tr><td colspan="4">—</td></tr>
            <?php else: foreach ($row['education'] as $ed): ?>
                <tr>
                    <td><?php echo htmlspecialchars($v($ed['degree'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($v($ed['institution'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($v($ed['year_of_passing'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($v($ed['percentage'] ?? '')); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pack-item sec pack-split">
        <h2 class="sec-h">Experience Details</h2>
        <table class="grid pack-rows">
            <thead><tr><th>Company</th><th>Designation</th><th>From</th><th>To</th><th>Salary</th></tr></thead>
            <tbody>
            <?php if (empty($row['experience'])): ?>
                <tr><td colspan="5">—</td></tr>
            <?php else: foreach ($row['experience'] as $ex): ?>
                <tr>
                    <td><?php echo htmlspecialchars($v($ex['company_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($v($ex['designation'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($v($ex['from_date'] ?? '')); ?></td>
                    <td><?php echo !empty($ex['is_current']) ? 'Present' : htmlspecialchars($v($ex['to_date'] ?? '')); ?></td>
                    <td><?php echo $money($ex['last_salary'] ?? null); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($hasDescriptive): ?>
    <div class="pack-item sec pack-split">
        <h2 class="sec-h">Reasoning Descriptive Questions</h2>
        <table class="grid pack-rows">
            <thead><tr><th style="width:36px;">#</th><th>Question (HI / EN)</th><th style="width:38%;">Answer</th></tr></thead>
            <tbody>
            <?php foreach ($row['descriptive'] as $dq): ?>
                <tr>
                    <td><?php echo (int) ($dq['question_no'] ?? 0); ?></td>
                    <td>
                        <div><?php echo htmlspecialchars((string) ($dq['question_hi'] ?? '')); ?></div>
                        <div style="color:#64748b;margin-top:3px;"><?php echo htmlspecialchars((string) ($dq['question_en'] ?? '')); ?></div>
                    </td>
                    <td><?php echo nl2br(htmlspecialchars(trim((string) ($dq['answer'] ?? '')) !== '' ? (string) $dq['answer'] : '—')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="pack-item sec pack-split">
        <h2 class="sec-h">HR Round · Interview Matrix
            <small>(<?php echo htmlspecialchars((string) ($row['position_name'] ?: 'All Positions')); ?>)</small>
        </h2>
        <table class="grid pack-rows">
            <thead><tr><th style="width:36px;">#</th><th>Question / Criteria</th><th style="width:28%;">Answer</th></tr></thead>
            <tbody>
            <?php if (!$criteria): ?>
                <tr><td colspan="3">No criteria marked.</td></tr>
            <?php else: $i = 1; foreach ($criteria as $c):
                $key = strtolower(trim((string) $c['criteria_label']));
                $type = (string) ($c['answer_type'] ?? 'yesno');
                $mark = $marks[$key] ?? [];
                $display = function_exists('recruitmentFormatMarkAnswer')
                    ? recruitmentFormatMarkAnswer($mark, $type)
                    : '—';
                $isYes = ($display === 'Yes');
                $isNo = ($display === 'No');
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars((string) $c['criteria_label']); ?></td>
                    <td class="ans <?php echo $isYes ? 'mark-yes' : ($isNo ? 'mark-no' : ''); ?>">
                        <?php echo nl2br(htmlspecialchars($display)); ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pack-item sec">
        <h2 class="sec-h">Interview Result</h2>
        <table class="boxes">
            <tr>
                <?php
                $m = (string) ($row['interview_mode'] ?? '');
                $aw = (string) ($row['awaited_with'] ?? '');
                $cell('Interview Mode', htmlspecialchars($modes[$m] ?? $v($m)));
                $cell('Interview Date', !empty($row['interview_date']) ? htmlspecialchars(formatDateDisplay($row['interview_date'])) : '—');
                $cell('Awaited With', htmlspecialchars($sides[$aw] ?? $v($aw)));
                $cell('Status', htmlspecialchars($labels[$row['status']] ?? $row['status']));
                ?>
            </tr>
            <tr>
                <?php
                $cell('Interview Notes', nl2br(htmlspecialchars($v($row['interview_notes'] ?? ''))), 2);
                $cell('Not Selected Reason', nl2br(htmlspecialchars($v($row['not_selected_reason'] ?? ''))), 2);
                ?>
            </tr>
            <tr>
                <?php $cell('HR Remarks', nl2br(htmlspecialchars($v($row['hr_remarks'] ?? ''))), 4); ?>
            </tr>
        </table>
    </div>

    <div class="pack-item">
        <div class="foot">
            <div class="gen">Generated on <?php echo htmlspecialchars(formatDateTimeDisplay(date('Y-m-d H:i:s'))); ?> · <?php echo htmlspecialchars($company); ?></div>
            <div style="display:flex;gap:20px;width:70%;justify-content:flex-end;">
                <div class="sign">HR Signature</div>
                <div class="sign">Interviewer Signature</div>
                <div class="sign">Authorized Signatory</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var out = document.getElementById('pack-out');
    var src = document.getElementById('pack-src');
    if (!out || !src) return;

    var sheetClass = out.getAttribute('data-sheet-class') || 'cdoc-sheet sheet';
    var lh = out.getAttribute('data-letterhead') || '';
    var hasLh = out.getAttribute('data-has-letterhead') === '1';

    function mmToPx(mm) {
        var probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;left:-9999px;width:100mm;height:100mm;';
        document.body.appendChild(probe);
        var px = probe.offsetWidth / 100;
        document.body.removeChild(probe);
        return mm * px;
    }

    function createPage() {
        var section = document.createElement('section');
        section.className = sheetClass;
        if (hasLh && lh) {
            var lhDiv = document.createElement('div');
            lhDiv.className = 'cdoc-letterhead';
            lhDiv.setAttribute('aria-hidden', 'true');
            lhDiv.style.backgroundImage = 'url("' + lh.replace(/"/g, '\\"') + '")';
            section.appendChild(lhDiv);
        }
        var inner = document.createElement('div');
        inner.className = 'cdoc-inner cdoc-body-pad';
        var body = document.createElement('div');
        body.className = 'page-body';
        var pageNo = document.createElement('div');
        pageNo.className = 'page-no';
        inner.appendChild(body);
        inner.appendChild(pageNo);
        section.appendChild(inner);
        out.appendChild(section);
        return { section: section, body: body, pageNo: pageNo, inner: inner };
    }

    function fits(pageBody, el, maxH) {
        pageBody.appendChild(el);
        var ok = pageBody.scrollHeight <= maxH + 0.5;
        if (!ok) pageBody.removeChild(el);
        return ok;
    }

    function cloneRowsTable(templateSec, rows) {
        var wrap = templateSec.cloneNode(false);
        var heading = templateSec.querySelector('.sec-h');
        if (heading) wrap.appendChild(heading.cloneNode(true));
        var table = templateSec.querySelector('table.pack-rows');
        if (!table) return wrap;
        var t = table.cloneNode(false);
        var thead = table.querySelector('thead');
        if (thead) t.appendChild(thead.cloneNode(true));
        var tb = document.createElement('tbody');
        rows.forEach(function (r) { tb.appendChild(r); });
        t.appendChild(tb);
        wrap.appendChild(t);
        wrap.className = templateSec.className;
        return wrap;
    }

    function pack() {
        out.innerHTML = '';
        var items = Array.prototype.slice.call(src.querySelectorAll(':scope > .pack-item'));
        var pageH = mmToPx(297);
        var padTop = hasLh ? mmToPx(42) : mmToPx(12);
        var padBot = hasLh ? mmToPx(36) : mmToPx(12);
        var pageNoH = mmToPx(6);
        var maxH = pageH - padTop - padBot - pageNoH;

        var page = createPage();
        var pages = [page];

        items.forEach(function (item) {
            var canSplit = item.classList.contains('pack-split') && item.querySelector('table.pack-rows tbody');

            if (!canSplit) {
                var node = item.cloneNode(true);
                if (!fits(page.body, node, maxH)) {
                    page = createPage();
                    pages.push(page);
                    page.body.appendChild(node);
                    /* if still oversized, leave it (rare) */
                    if (page.body.scrollHeight > maxH + 0.5) {
                        /* keep anyway — better than drop */
                    }
                }
                return;
            }

            /* Split table rows across pages to fill space */
            var rows = Array.prototype.slice.call(item.querySelectorAll('table.pack-rows tbody > tr'));
            var pending = rows.map(function (r) { return r.cloneNode(true); });

            while (pending.length) {
                var placed = [];
                var probe = cloneRowsTable(item, []);
                page.body.appendChild(probe);
                var tbody = probe.querySelector('tbody');

                while (pending.length) {
                    var row = pending[0];
                    tbody.appendChild(row);
                    if (page.body.scrollHeight > maxH + 0.5) {
                        tbody.removeChild(row);
                        break;
                    }
                    pending.shift();
                    placed.push(row);
                }

                if (!placed.length) {
                    /* Row alone taller than page — force place on fresh page */
                    page.body.removeChild(probe);
                    if (page.body.childNodes.length) {
                        page = createPage();
                        pages.push(page);
                    }
                    var forced = cloneRowsTable(item, [pending.shift()]);
                    page.body.appendChild(forced);
                    continue;
                }

                if (pending.length) {
                    /* Page full — continue remaining rows on next page */
                    page = createPage();
                    pages.push(page);
                }
            }
        });

        var total = pages.length;
        pages.forEach(function (p, i) {
            p.pageNo.textContent = 'Page ' + (i + 1) + ' of ' + total;
        });
    }

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(pack).catch(pack);
    } else {
        window.addEventListener('load', pack);
        pack();
    }
    window.addEventListener('resize', function () {
        clearTimeout(window.__packT);
        window.__packT = setTimeout(pack, 200);
    });
})();
</script>
</body>
</html>
