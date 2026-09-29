<?php
/**
 * Offer Letter — company header/footer + logo watermark from settings
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? getRecruitmentApplication($id) : null;
if (!$row || ($row['status'] ?? '') !== 'selected') {
    header('Location: ' . app_url('recruitment/interviews.php'));
    exit;
}

$issued = recruitmentIssueOfferLetter($id);
$row = getRecruitmentApplication($id) ?: $row;

$brand = getCompanyDocumentBranding();
$company = $brand['company_name'];

$offerNo = trim((string) ($row['offer_letter_no'] ?? ($issued['offer_letter_no'] ?? '')));
$offerDate = !empty($row['offer_letter_date'])
    ? date('d M Y', strtotime((string) $row['offer_letter_date']))
    : date('d M Y');

$v = static function ($x) {
    $x = trim((string) $x);
    return $x !== '' ? $x : '—';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offer Letter · <?php echo htmlspecialchars($offerNo ?: (string) $row['application_no']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 18px;
            background: #e5e7eb;
            color: #1a1a1a;
            font-family: Calibri, Candara, Segoe UI, Optima, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        .bar {
            max-width: 820px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            font-size: 13px;
        }
        .bar button {
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 9px 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .sheet {
            max-width: 820px;
            margin: 0 auto;
            border: 2px solid #111;
            padding: 28px 36px 32px;
            min-height: 1040px;
            font-family: Calibri, Candara, Segoe UI, Optima, Arial, sans-serif;
        }
        <?php echo companyDocPrintCss(); ?>
        .cdoc-header-text .cdoc-company,
        .cdoc-header-text .cdoc-header-details,
        .cdoc-footer {
            font-family: Calibri, Candara, Segoe UI, Optima, Arial, sans-serif;
        }

        .doc-title {
            text-align: center;
            margin: 16px 0 18px;
            font-size: 20px;
            font-weight: 700;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #111;
            text-decoration: underline;
            text-underline-offset: 5px;
        }

        /* Matching Offer No + Date boxes */
        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 0 0 22px;
        }
        .meta-box {
            border: 1.5px solid #334155;
            background: #fff;
            display: flex;
            flex-direction: column;
            min-height: 62px;
            overflow: hidden;
        }
        .meta-box .meta-label {
            display: block;
            margin: 0;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            background: #d2232a;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            border-bottom: 1px solid #9f1c22;
        }
        .meta-box .meta-value {
            display: block;
            flex: 1;
            padding: 10px 12px;
            font-size: 15px;
            font-weight: 700;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            letter-spacing: .02em;
            background: #fffafa;
        }
        .meta-box.meta-date .meta-value {
            text-align: right;
        }

        .body-text {
            font-family: Calibri, Candara, Segoe UI, Optima, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.65;
            color: #1a1a1a;
        }
        .body-text p {
            margin: 0 0 13px;
            text-align: justify;
        }
        .body-text strong { font-weight: 700; }
        .addr-block {
            margin: 0 0 14px;
            line-height: 1.55;
        }
        .addr-block .to-label {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .subject-line {
            margin: 0 0 14px;
            padding: 8px 12px;
            border-left: 3px solid #d2232a;
            background: #f8fafc;
            font-size: 14px;
        }

        .ref-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 18px;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            font-size: 13.5px;
        }
        .ref-table th,
        .ref-table td {
            border: 1px solid #64748b;
            padding: 9px 12px;
            text-align: left;
            vertical-align: top;
        }
        .ref-table th {
            width: 34%;
            background: #f1f5f9;
            font-weight: 700;
            color: #1e293b;
            font-size: 13px;
        }
        .ref-table td {
            font-weight: 600;
            color: #111;
            background: #fff;
        }

        .closing { margin-top: 20px; }
        .sign-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 28px;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
        }
        .sign-box {
            width: 42%;
            text-align: center;
            border-top: 1.5px solid #111;
            padding-top: 8px;
            margin-top: 52px;
        }
        .sign-box small {
            display: block;
            margin-top: 3px;
            font-weight: 600;
            color: #666;
            font-size: 11.5px;
        }
        .foot-note {
            margin-top: 18px;
            font-size: 11px;
            color: #666;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .bar { display: none !important; }
            .sheet {
                max-width: none;
                border: 0;
                min-height: auto;
                padding: 12mm 14mm;
            }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="bar">
    <div>
        <strong><?php echo htmlspecialchars($offerNo ?: (string) $row['application_no']); ?></strong>
        · Offer Letter
    </div>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="sheet cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Recruitment · Offer of Employment'); ?>

        <h2 class="doc-title">Offer Letter</h2>

        <div class="meta-row">
            <div class="meta-box">
                <span class="meta-label">Offer Letter No.</span>
                <span class="meta-value"><?php echo htmlspecialchars($offerNo !== '' ? $offerNo : '—'); ?></span>
            </div>
            <div class="meta-box meta-date">
                <span class="meta-label">Date</span>
                <span class="meta-value"><?php echo htmlspecialchars($offerDate); ?></span>
            </div>
        </div>

        <div class="body-text">
            <div class="addr-block">
                <div class="to-label">To,</div>
                <strong><?php echo htmlspecialchars((string) $row['full_name']); ?></strong><br>
                <?php if (!empty($row['address'])): ?>
                    <?php echo nl2br(htmlspecialchars((string) $row['address'])); ?><br>
                <?php endif; ?>
                Mobile: <?php echo htmlspecialchars($v($row['mobile'] ?? '')); ?>
                <?php if (!empty($row['email'])): ?>
                    &nbsp;·&nbsp; Email: <?php echo htmlspecialchars((string) $row['email']); ?>
                <?php endif; ?>
            </div>

            <p class="subject-line">
                <strong>Subject:</strong> Offer of Employment — <?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?>
            </p>

            <p>Dear <strong><?php echo htmlspecialchars((string) $row['full_name']); ?></strong>,</p>

            <p>
                We are pleased to offer you employment with
                <strong><?php echo htmlspecialchars($company); ?></strong>
                for the position of
                <strong><?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></strong>
                in the
                <strong><?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></strong>
                department, subject to successful completion of joining formalities and verification of documents.
            </p>

            <table class="ref-table">
                <tr>
                    <th>Application No.</th>
                    <td><?php echo htmlspecialchars((string) $row['application_no']); ?></td>
                </tr>
                <tr>
                    <th>Position / Designation</th>
                    <td><?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td><?php echo htmlspecialchars($v($row['department_name'] ?? '')); ?></td>
                </tr>
                <tr>
                    <th>Discussed Salary (Ref.)</th>
                    <td><?php
                        echo $row['expected_salary'] !== null
                            ? ('₹ ' . number_format((float) $row['expected_salary'], 0) . ' / month')
                            : '—';
                    ?> (final as per Appointment Letter)</td>
                </tr>
                <tr>
                    <th>Total Experience</th>
                    <td><?php echo htmlspecialchars($v($row['total_experience'] ?? '')); ?></td>
                </tr>
            </table>

            <p>
                Kindly confirm your acceptance of this offer and your proposed date of joining.
                You are requested to report to the HR department with original documents for verification
                and complete biometric / onboarding formalities.
            </p>

            <p>
                This offer is provisional and does not create an employment relationship until the
                Appointment Letter is issued and you appear for duty as scheduled.
            </p>

            <div class="closing">
                <p>We look forward to welcoming you to the team.</p>
                <p>
                    For <strong><?php echo htmlspecialchars($company); ?></strong><br>
                    Human Resources
                </p>
            </div>

            <div class="sign-row">
                <div class="sign-box">Candidate Acceptance<small>Sign / Date</small></div>
                <div class="sign-box">Authorized Signatory · HR<small>Company Seal</small></div>
            </div>

            <p class="foot-note">
                Generated from <?php echo htmlspecialchars($company); ?> HRMS ·
                App <?php echo htmlspecialchars((string) $row['application_no']); ?>
                <?php if ($offerNo !== ''): ?> · Letter <?php echo htmlspecialchars($offerNo); ?><?php endif; ?>
            </p>
        </div>

        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>
</body>
</html>
