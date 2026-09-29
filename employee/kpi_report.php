<?php
/**
 * KPI Report — A4 print (company letterhead = Appointment / Experience letters)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/kpi_helper.php';

requireLogin();

$sheetId = (int) ($_GET['id'] ?? 0);
$sessionEmpId = (int) ($_SESSION['employee_id'] ?? 0);
$asStaff = function_exists('isStaffUser') && isStaffUser();

$conn = getDBConnection();
ensureKpiTables($conn);
$sheet = $sheetId > 0 ? kpiGetSheetById($sheetId, $conn) : null;
if (!$sheet) {
    $conn->close();
    header('Location: ' . app_url($asStaff ? 'hr/kpi.php' : 'employee/kpi_history.php'));
    exit;
}

$isOwn = $sessionEmpId > 0 && (int) $sheet['employee_id'] === $sessionEmpId;
if (!$asStaff && !$isOwn) {
    $conn->close();
    header('Location: ' . app_url('employee/dashboard.php?msg=denied'));
    exit;
}

if (!$asStaff && !kpiCanViewReport($sheet, false)) {
    $conn->close();
    header('Location: ' . app_url('employee/kpi_history.php?msg=wait'));
    exit;
}

$emp = getEmployeeById((int) $sheet['employee_id']);
$entries = kpiGetEntries($sheetId, $conn);
$conn->close();

$slots = [];
foreach ($entries as $e) {
    $slots[] = $e;
}
usort($slots, static function ($a, $b) {
    return ((int) $a['slot_index']) <=> ((int) $b['slot_index']);
});

$brand = getCompanyDocumentBranding();
$companyName = trim((string) ($brand['company_name'] ?? ''));
if ($companyName === '') {
    $companyName = function_exists('getCompanyName') ? getCompanyName() : 'ARMOR';
}

$dateDisp = formatDateDisplay($sheet['kpi_date'] ?? '');
$shiftDisp = trim(($sheet['shift_in'] ?? '') . ' TO ' . ($sheet['shift_out'] ?? ''));
if ($shiftDisp === 'TO' || $shiftDisp === ' TO ') {
    $shiftDisp = '—';
}
$deptLine = trim((string) (($emp['department_name'] ?? '') . (!empty($emp['designation']) ? ' · ' . $emp['designation'] : '')));
if ($deptLine === '') {
    $deptLine = '—';
}
$reporting = trim((string) ($emp['reporting_head'] ?? ''));
if ($reporting === '') {
    $reporting = '—';
}
$preparedBy = trim((string) ($sheet['prepared_by_name'] ?: ($emp['employee_name'] ?? '')));
$empName = trim((string) ($emp['employee_name'] ?? ''));
$empCode = trim((string) ($emp['employee_code'] ?? ''));

$backUrl = $asStaff
    ? app_url('hr/kpi.php?date=' . urlencode((string) $sheet['kpi_date']))
    : app_url('employee/kpi_history.php');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KPI Report · <?php echo $h($empCode ?: $empName); ?> · <?php echo $h($dateDisp); ?></title>
    <style>
        <?php echo companyDocPrintCss(); ?>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .bar {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            background: #111;
            color: #fff;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
        }
        .bar a, .bar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #333;
            color: #fff;
            border: 0;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 13px;
        }
        .bar button.primary { background: #d2232a; }
        .bar .meta { opacity: .9; font-size: 12px; }

        .page {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 14px auto;
            border: 2.25px solid #000;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
        }
        .page-inner {
            min-height: 297mm;
            padding: 7mm 8mm 6mm;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }

        .cdoc-header {
            gap: 12px;
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom-width: 2.5px;
            align-items: center;
            flex-shrink: 0;
        }
        .cdoc-header-logo {
            width: 78px;
            height: 78px;
            border: 0;
            padding: 0;
            background: transparent;
        }
        .cdoc-header-text .cdoc-company {
            font-size: 24px;
            font-weight: 800;
            color: #b91c1c;
            margin: 0 0 3px;
            line-height: 1.12;
            text-transform: uppercase;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.35;
        }
        .cdoc-footer {
            margin-top: auto;
            padding-top: 8px;
            border-top-width: 1.5px;
            font-size: 11.5px;
            line-height: 1.4;
            flex-shrink: 0;
        }
        .cdoc-watermark img {
            width: min(58%, 360px);
            max-height: 360px;
            opacity: 0.05;
        }

        .kpi-title {
            text-align: center;
            margin: 2px 0 8px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            text-decoration: underline;
            text-underline-offset: 4px;
            color: #0f172a;
        }
        .kpi-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin: 0 0 8px;
            font-size: 14.5px;
            font-weight: 700;
            color: #334155;
        }
        .kpi-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-size: 13px;
            font-weight: 800;
        }

        .kpi-info,
        .kpi-hours {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        .kpi-info th,
        .kpi-info td,
        .kpi-hours th,
        .kpi-hours td {
            border: 1px solid #1e293b;
            padding: 7px 10px;
            vertical-align: top;
            word-break: break-word;
            font-size: 14px;
        }
        .kpi-info th,
        .kpi-hours th {
            background: #f1f5f9;
            font-weight: 800;
            text-align: left;
            font-size: 13.5px;
        }
        .kpi-info td:first-child {
            width: 36%;
            font-weight: 700;
            color: #334155;
            background: #fafafa;
        }
        .kpi-hours th {
            text-align: center;
            text-transform: uppercase;
            font-size: 12.5px;
            letter-spacing: .03em;
        }
        .kpi-hours td.num {
            text-align: center;
            font-weight: 800;
            width: 58px;
            font-size: 14.5px;
        }
        .kpi-hours td.time {
            text-align: center;
            font-weight: 700;
            white-space: nowrap;
            width: 140px;
            font-size: 14px;
        }

        .kpi-section {
            margin: 2px 0 6px;
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: .02em;
        }

        .kpi-ack {
            margin: 6px 0 10px;
            padding: 9px 12px;
            border: 1px solid #a7f3d0;
            background: #ecfdf5;
            color: #065f46;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 4px;
        }

        .kpi-signs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 22px;
            margin: 18px 0 8px;
            margin-top: auto;
            padding-top: 12px;
        }
        .kpi-sign-box {
            display: flex;
            flex-direction: column;
            min-height: 110px;
            border-top: 0;
            padding-top: 0;
        }
        .kpi-sign-box .sign-space {
            flex: 1 1 auto;
            min-height: 58px;
            border-bottom: 1.75px solid #111;
            margin-bottom: 8px;
        }
        .kpi-sign-box .lbl {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .kpi-sign-box .val {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            min-height: 1.35em;
            line-height: 1.35;
        }

        @media print {
            @page { size: A4; margin: 0; }
            .no-print { display: none !important; }
            html, body {
                width: 210mm;
                height: 297mm;
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .page {
                width: 210mm;
                min-height: 297mm;
                height: 297mm;
                margin: 0;
                box-shadow: none;
                border: 0;
                page-break-after: always;
                break-after: page;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .page-inner {
                min-height: 297mm;
                height: 297mm;
                padding: 6mm 7mm 5mm;
            }
        }
        @media screen and (max-width: 900px) {
            .page { width: auto; margin: 10px; }
            .bar { width: auto; }
        }
    </style>
</head>
<body>
<div class="bar no-print">
    <a href="<?php echo $h($backUrl); ?>">← Back</a>
    <span class="meta"><?php echo $h($empName); ?> · <?php echo $h($dateDisp); ?></span>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<section class="page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="page-inner cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Key Performance Indicator'); ?>

        <h1 class="kpi-title">Key Performance Indicator (K.P.I.)</h1>

        <div class="kpi-meta">
            <span>તારીખ / Date: <strong><?php echo $h($dateDisp); ?></strong></span>
            <span class="kpi-badge">✓ Submitted</span>
        </div>

        <table class="kpi-info">
            <thead>
                <tr>
                    <th>વિગતો / Details</th>
                    <th>માહિતી / Information</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>કર્મચારીનું નામ / Employee Name</td>
                    <td><strong><?php echo $h($empName); ?></strong></td>
                </tr>
                <tr>
                    <td>કર્મચારી કોડ / Employee Code</td>
                    <td><?php echo $h($empCode !== '' ? $empCode : '—'); ?></td>
                </tr>
                <tr>
                    <td>વિભાગ / Department</td>
                    <td><?php echo $h($deptLine); ?></td>
                </tr>
                <tr>
                    <td>શિફ્ટ સમય / Shift Time</td>
                    <td><?php echo $h($shiftDisp); ?></td>
                </tr>
                <tr>
                    <td>રિપોર્ટિંગ મેનેજર / Reporting Manager</td>
                    <td><?php echo $h($reporting); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="kpi-section">પ્રતિ કલાક K.P.I. શીટ / Hourly K.P.I. Sheet</div>

        <table class="kpi-hours">
            <thead>
                <tr>
                    <th style="width:56px;">ક્રમાંક</th>
                    <th style="width:130px;">સમય / Time</th>
                    <th>Activity / કામની વિગત</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$slots): ?>
                <tr>
                    <td colspan="3" style="text-align:center;color:#64748b;font-weight:700;padding:16px;">No hourly entries</td>
                </tr>
            <?php else: ?>
                <?php foreach ($slots as $slot):
                    $act = trim((string) ($slot['activity_text'] ?? ''));
                    ?>
                <tr>
                    <td class="num"><?php echo (int) $slot['slot_index']; ?></td>
                    <td class="time"><?php echo $h($slot['time_from'] . ' – ' . $slot['time_to']); ?></td>
                    <td><?php echo $act !== '' ? nl2br($h($act)) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($sheet['responsibility_ack'])): ?>
        <div class="kpi-ack">
            Based on my best knowledge, the above information is correct, and I confirm its accuracy. — Confirmed
        </div>
        <?php endif; ?>

        <div class="kpi-signs">
            <div class="kpi-sign-box">
                <div class="sign-space" aria-hidden="true"></div>
                <div class="lbl">તૈયાર કરનાર / Prepared By</div>
                <div class="val"><?php echo $h($preparedBy !== '' ? $preparedBy : '—'); ?></div>
            </div>
            <div class="kpi-sign-box">
                <div class="sign-space" aria-hidden="true"></div>
                <div class="lbl">ચકાસનાર / Checked By</div>
                <div class="val">&nbsp;</div>
            </div>
            <div class="kpi-sign-box">
                <div class="sign-space" aria-hidden="true"></div>
                <div class="lbl">મંજૂર કરનાર / Approved By</div>
                <div class="val">&nbsp;</div>
            </div>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</section>
</body>
</html>
