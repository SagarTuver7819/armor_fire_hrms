<?php
/**
 * Recruitment Application — Print / PDF
 * Continuous content: fills each page, then flows to next with letterhead.
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
            line-height: 1.4;
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
        /* Continuous sheet — content flows; print engine fills pages */
        .sheet {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            height: auto;
            margin: 0 auto;
            background: #fff;
            border: 0;
            padding: 12mm 14mm;
            position: relative;
            overflow: visible;
            box-sizing: border-box;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
        }
        <?php echo companyDocPrintCss(); ?>
        .sheet.has-letterhead,
        .sheet:has(.cdoc-letterhead) {
            padding: 0 !important;
            border: 0 !important;
            overflow: visible !important;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
        }
        .sheet.has-letterhead > .cdoc-inner,
        .sheet:has(.cdoc-letterhead) > .cdoc-inner {
            padding: 46mm 14mm 18mm 14mm !important;
            overflow: visible !important;
            max-height: none !important;
            height: auto !important;
        }
        .doc-title {
            text-align: center;
            margin: 0 0 10px;
            font-size: 15px;
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
            gap: 8px 14px;
            align-items: start;
            padding: 10px 12px;
            margin: 0 0 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: rgba(255,255,255,.92);
        }
        .cand-name {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 6px;
            color: #111;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .cand-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            color: #334155;
            font-weight: 700;
            font-size: 11px;
        }
        .cand-meta .k {
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: .04em;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border: 1px solid #9f1239;
            background: #fee2e2;
            color: #9f1239;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
            border-radius: 999px;
            height: fit-content;
        }
        .sec {
            margin: 0 0 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .sec-h {
            margin: 0;
            padding: 7px 12px;
            font-size: 11px;
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
            padding: 6px 8px;
            vertical-align: top;
            width: 25%;
            background: #fff;
        }
        table.boxes td.fld .lbl {
            display: block;
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: .03em;
            margin-bottom: 2px;
        }
        table.boxes td.fld .val {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #111;
            min-height: 14px;
            word-break: break-word;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #64748b;
            padding: 6px 8px;
            text-align: left;
            font-size: 10.5px;
            vertical-align: top;
        }
        table.grid th {
            background: #f1f5f9;
            color: #334155;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: .03em;
        }
        table.grid td.ans { font-weight: 700; width: 28%; }
        table.grid tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .mark-yes { color: #15803d; }
        .mark-no { color: #b91c1c; }
        .foot {
            padding: 10px 0 4px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 10px;
            color: #444;
            page-break-inside: avoid;
        }
        .sign {
            width: 32%;
            text-align: center;
            border-top: 1px solid #111;
            margin-top: 42px;
            padding-top: 6px;
            font-weight: 700;
        }
        .gen { margin-top: 8px; color: #666; }
        .print-page-no {
            display: none;
        }
        @media print {
            <?php echo companyDocLetterheadPageCss($brand, true); ?>
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .bar { display: none !important; }
            .sheet {
                width: auto !important;
                max-width: none !important;
                min-height: 0 !important;
                height: auto !important;
                margin: 0 !important;
                border: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .sheet.has-letterhead,
            .sheet:has(.cdoc-letterhead) {
                padding: 0 !important;
            }
            .sheet.has-letterhead > .cdoc-inner,
            .sheet:has(.cdoc-letterhead) > .cdoc-inner {
                padding: 0 2mm !important;
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

<div class="<?php echo htmlspecialchars(companyDocSheetClass($brand, 'sheet cdoc-flow')); ?>">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Recruitment Application · Interview Evaluation Form'); ?>

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

        <div class="sec">
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

        <div class="sec">
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

        <div class="sec">
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

        <div class="sec">
            <h2 class="sec-h">Educational Details</h2>
            <table class="grid">
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

        <div class="sec">
            <h2 class="sec-h">Experience Details</h2>
            <table class="grid">
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
        <div class="sec" style="page-break-inside:auto;break-inside:auto;">
            <h2 class="sec-h">Reasoning Descriptive Questions</h2>
            <table class="grid">
                <thead><tr><th style="width:40px;">#</th><th>Question (HI / EN)</th><th style="width:40%;">Answer</th></tr></thead>
                <tbody>
                <?php foreach ($row['descriptive'] as $dq): ?>
                    <tr>
                        <td><?php echo (int) ($dq['question_no'] ?? 0); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars((string) ($dq['question_hi'] ?? '')); ?></div>
                            <div style="color:#64748b;margin-top:4px;"><?php echo htmlspecialchars((string) ($dq['question_en'] ?? '')); ?></div>
                        </td>
                        <td><?php echo nl2br(htmlspecialchars(trim((string) ($dq['answer'] ?? '')) !== '' ? (string) $dq['answer'] : '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="sec" style="page-break-inside:auto;break-inside:auto;">
            <h2 class="sec-h">HR Round · Interview Matrix
                <small>(<?php echo htmlspecialchars((string) ($row['position_name'] ?: 'All Positions')); ?>)</small>
            </h2>
            <table class="grid">
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

        <div class="sec">
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

        <div class="foot">
            <div class="gen">Generated on <?php echo htmlspecialchars(formatDateTimeDisplay(date('Y-m-d H:i:s'))); ?> · <?php echo htmlspecialchars($company); ?></div>
            <div style="display:flex;gap:24px;width:70%;justify-content:flex-end;">
                <div class="sign">HR Signature</div>
                <div class="sign">Interviewer Signature</div>
                <div class="sign">Authorized Signatory</div>
            </div>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>
</body>
</html>
