<?php
/**
 * Offer Letter — company sample format (Ref / Date / body / docs list)
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
$company = trim((string) $brand['company_name']);
if ($company === '' || strcasecmp($company, 'Armor Fire') === 0) {
    $company = 'Armor Steel Industries Pvt. Ltd.';
}
$companyShort = 'Armor Steel Industries Pvt. Ltd.';

$offerNo = trim((string) ($row['offer_letter_no'] ?? ($issued['offer_letter_no'] ?? '')));
$offerDateRaw = !empty($row['offer_letter_date'])
    ? (string) $row['offer_letter_date']
    : date('Y-m-d');
$offerDate = formatDateDisplay($offerDateRaw);

$position = trim((string) ($row['position_name'] ?? ''));
$department = trim((string) ($row['department_name'] ?? ''));
$fullName = strtoupper(trim((string) ($row['full_name'] ?? '')));

$gender = strtolower(trim((string) ($row['gender'] ?? '')));
$title = 'Mr.';
if (in_array($gender, ['female', 'f', 'woman', 'lady'], true)) {
    $title = 'Ms.';
} elseif (in_array($gender, ['other', 'o'], true)) {
    $title = '';
}
$displayName = trim(($title !== '' ? $title . ' ' : '') . $fullName);

$region = 'RAJKOT - GUJARAT Region';
$joiningDate = !empty($row['joining_date'])
    ? formatDateDisplay((string) $row['joining_date'])
    : 'as mutually agreed';

$workLocation = $companyShort . ', Rajkot, Gujarat';
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
            padding: 12px;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Optima, Arial, sans-serif;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .bar {
            width: 210mm;
            max-width: 100%;
            margin: 0 auto 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            font-size: 13px;
        }
        .bar button {
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
        }
        .sheet {
            width: 210mm;
            max-width: 100%;
            height: 297mm;
            margin: 0 auto;
            border: 1px solid #111;
            padding: 11mm 14mm 10mm;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        <?php echo companyDocPrintCss(); ?>
        .cdoc-sheet > .cdoc-inner {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
        }
        .cdoc-header {
            gap: 12px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom-width: 2.5px;
            align-items: center;
            flex-shrink: 0;
        }
        .cdoc-header-logo {
            width: 68px;
            height: 68px;
        }
        .cdoc-header-text .cdoc-company {
            font-size: 20px;
            font-weight: 800;
            color: #b91c1c;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            margin: 0 0 4px;
            line-height: 1.2;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            line-height: 1.4;
        }
        .cdoc-footer {
            margin-top: 0;
            padding-top: 8px;
            border-top-width: 1.5px;
            font-size: 10.5px;
            line-height: 1.4;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            flex-shrink: 0;
        }
        .cdoc-watermark img {
            width: min(55%, 340px);
            max-height: 340px;
            opacity: 0.07;
        }

        .ref-date-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin: 0 0 6px;
            font-size: 13.5px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .doc-title {
            text-align: center;
            margin: 4px 0 12px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            text-decoration: underline;
            text-underline-offset: 3px;
            flex-shrink: 0;
        }

        .letter-main {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 0;
        }
        .letter-body {
            font-size: 13.5px;
            line-height: 1.55;
            text-align: justify;
        }
        .letter-body p { margin: 0 0 11px; }
        .letter-body .to-block {
            margin: 0 0 12px;
            text-align: left;
            line-height: 1.4;
        }
        .letter-body .to-block .name {
            font-weight: 700;
            text-transform: uppercase;
        }
        .letter-body strong { font-weight: 700; }

        .docs-head {
            margin: 12px 0 6px;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
            text-align: left;
        }
        .docs-list {
            margin: 0 0 12px;
            padding-left: 20px;
            text-align: left;
        }
        .docs-list li {
            margin: 0 0 3px;
            padding-left: 2px;
            line-height: 1.4;
        }

        .letter-bottom {
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 8px;
        }
        .closing-block {
            margin: 0 0 14px;
            text-align: left;
            page-break-inside: avoid;
        }
        .closing-block .sincerely {
            margin: 0 0 36px;
        }
        .sign-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 18px;
        }
        .sign-left {
            font-weight: 700;
            line-height: 1.4;
            font-size: 13.5px;
        }
        .sign-right {
            width: 40%;
            text-align: center;
            font-weight: 700;
            font-size: 13.5px;
        }
        .sign-right .line {
            border-top: 1.5px solid #111;
            margin: 0 0 6px;
            min-height: 1px;
        }

        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm;
                height: 297mm;
            }
            .bar { display: none !important; }
            .sheet {
                width: 210mm;
                max-width: 210mm;
                height: 297mm;
                min-height: 297mm;
                margin: 0;
                border: 0;
                padding: 12mm 14mm 10mm;
                box-shadow: none;
                overflow: hidden;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            .letter-bottom,
            .cdoc-footer,
            .closing-block {
                page-break-inside: avoid;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
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
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Recruitment'); ?>

        <div class="ref-date-row">
            <div class="ref-no">Ref: <?php echo htmlspecialchars($offerNo !== '' ? $offerNo : '—'); ?></div>
            <div class="ref-date">Date:- <?php echo htmlspecialchars($offerDate); ?></div>
        </div>

        <h2 class="doc-title">Offer-Letter</h2>

        <div class="letter-main">
            <div class="letter-body">
                <div class="to-block">
                    To,<br>
                    <span class="name"><?php echo htmlspecialchars($displayName); ?></span>
                </div>

                <p>
                    We are pleased to offer you the position of
                    <strong><?php echo htmlspecialchars($position !== '' ? $position : '—'); ?></strong>
                    in
                    <strong><?php echo htmlspecialchars($department !== '' ? $department : '—'); ?></strong>
                    department at
                    <strong><?php echo htmlspecialchars($region); ?>.</strong>
                </p>

                <p>
                    Your joining date at the company will be
                    <strong><?php echo htmlspecialchars($joiningDate); ?></strong>
                    at
                    <strong><?php echo htmlspecialchars($workLocation); ?>.</strong>
                </p>

                <p>Your salary remuneration will be <strong>as discussed.</strong></p>

                <p>You will be on probation for <strong>3 months</strong> from your joining.</p>

                <p>
                    Company will review your performance after 3 months according to your performance,
                    the company has all rights to continue or terminate your service.
                </p>

                <p>
                    We warmly welcome you to our Armor family and hope it will be the beginning of a long
                    and mutually beneficial future ahead.
                </p>

                <p>Kindly acknowledge the mail and acceptance of this Letter of Intent.</p>

                <p class="docs-head">You are requested to submit</p>
                <ol class="docs-list">
                    <li>Aadhar Card</li>
                    <li>PAN Card</li>
                    <li>Education Certificate</li>
                    <li>Bank Details</li>
                    <li>5 Passport Size Photos</li>
                    <li>Experience Letter of last company</li>
                    <li>Salary Proof of last 3 months at the time of Joining</li>
                </ol>

                <p>
                    If you wish to resign from your designation for any specific reason you are bound to
                    submit 1-month prior notice to the company and submit the resignation letter to the
                    respected authority.
                </p>

                <p>For any query feel free to contact undersigned.</p>
            </div>

            <div class="letter-bottom">
                <div class="closing-block">
                    <p class="sincerely">Sincerely,</p>
                    <div class="sign-row">
                        <div class="sign-left">
                            HR Department<br>
                            <?php echo htmlspecialchars($companyShort); ?>
                        </div>
                        <div class="sign-right">
                            <div class="line"></div>
                            Candidate Signature
                        </div>
                    </div>
                </div>
                <?php echo companyDocRenderFooter($brand); ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
