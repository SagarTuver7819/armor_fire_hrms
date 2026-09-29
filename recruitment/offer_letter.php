<?php
/**
 * Offer Letter — print form with company logo header + watermark
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
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

$company = trim((string) getCompanyName());
if ($company === '' || strcasecmp($company, 'Armor Fire') === 0) {
    $company = 'Armor Steel Industries Pvt Ltd';
}

$logo = getLoginLogo();
if (!$logo && function_exists('getCompanyLogo')) {
    $logo = getCompanyLogo();
}
$logoSrc = $logo;
if ($logoSrc && strpos($logoSrc, 'http') !== 0 && strpos($logoSrc, '/') !== 0) {
    $logoSrc = app_url($logoSrc);
}

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
            color: #111;
            font-family: "Times New Roman", Times, Georgia, serif;
            font-size: 13.5px;
            line-height: 1.55;
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
        }
        .bar button {
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 9px 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: Arial, Helvetica, sans-serif;
        }
        .sheet {
            position: relative;
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #111;
            padding: 28px 36px 32px;
            min-height: 1040px;
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
        }
        .watermark img {
            width: min(62%, 420px);
            max-height: 420px;
            object-fit: contain;
            opacity: 0.08;
            filter: grayscale(10%);
        }
        .content { position: relative; z-index: 1; }

        .head {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 3px solid #d2232a;
            margin-bottom: 6px;
        }
        .head-logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
            flex-shrink: 0;
            border: 1px solid #e5e7eb;
            padding: 4px;
            background: #fff;
        }
        .head-copy h1 {
            margin: 0;
            font-size: 20px;
            color: #d2232a;
            font-family: Arial, Helvetica, sans-serif;
            letter-spacing: .01em;
            line-height: 1.25;
        }
        .head-copy .tag {
            margin: 4px 0 0;
            font-size: 12px;
            font-weight: 700;
            color: #444;
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .head-copy .sub {
            margin: 3px 0 0;
            font-size: 11px;
            color: #666;
            font-family: Arial, Helvetica, sans-serif;
        }

        .doc-title {
            text-align: center;
            margin: 18px 0 8px;
            font-size: 18px;
            font-weight: 800;
            font-family: Arial, Helvetica, sans-serif;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #111;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 14px 0 18px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
        }
        .meta-box {
            border: 1px solid #94a3b8;
            padding: 8px 10px;
            background: #fffafa;
            min-width: 42%;
        }
        .meta-box span {
            display: block;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .body-text p { margin: 0 0 12px; text-align: justify; }
        .body-text strong { font-weight: 700; }
        .ref-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 16px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }
        .ref-table th,
        .ref-table td {
            border: 1px solid #64748b;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        .ref-table th {
            width: 32%;
            background: #f8fafc;
            font-weight: 800;
            color: #334155;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: .03em;
        }

        .closing { margin-top: 22px; }
        .sign-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 48px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
        }
        .sign-box {
            width: 42%;
            text-align: center;
            border-top: 1px solid #111;
            padding-top: 8px;
            margin-top: 56px;
        }
        .foot-note {
            margin-top: 28px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #666;
            font-family: Arial, Helvetica, sans-serif;
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

<div class="sheet">
    <?php if ($logoSrc): ?>
    <div class="watermark" aria-hidden="true">
        <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="">
    </div>
    <?php endif; ?>

    <div class="content">
        <header class="head">
            <?php if ($logoSrc): ?>
                <img class="head-logo" src="<?php echo htmlspecialchars($logoSrc); ?>" alt="<?php echo htmlspecialchars($company); ?>">
            <?php endif; ?>
            <div class="head-copy">
                <h1><?php echo htmlspecialchars($company); ?></h1>
                <p class="tag">Human Resource · Recruitment</p>
                <p class="sub">Offer of Employment</p>
            </div>
        </header>

        <h2 class="doc-title">Offer Letter</h2>

        <div class="meta-row">
            <div class="meta-box">
                <span>Offer Letter No.</span>
                <?php echo htmlspecialchars($offerNo !== '' ? $offerNo : '—'); ?>
            </div>
            <div class="meta-box" style="text-align:right;">
                <span>Date</span>
                <?php echo htmlspecialchars($offerDate); ?>
            </div>
        </div>

        <div class="body-text">
            <p>
                <strong>To,</strong><br>
                <strong><?php echo htmlspecialchars((string) $row['full_name']); ?></strong><br>
                <?php if (!empty($row['address'])): ?>
                    <?php echo nl2br(htmlspecialchars((string) $row['address'])); ?><br>
                <?php endif; ?>
                Mobile: <?php echo htmlspecialchars($v($row['mobile'] ?? '')); ?>
                <?php if (!empty($row['email'])): ?>
                    · Email: <?php echo htmlspecialchars((string) $row['email']); ?>
                <?php endif; ?>
            </p>

            <p><strong>Subject:</strong> Offer of Employment — <?php echo htmlspecialchars($v($row['position_name'] ?? '')); ?></p>

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
                <div class="sign-box">Candidate Acceptance<br><small style="font-weight:600;color:#666;">Sign / Date</small></div>
                <div class="sign-box">Authorized Signatory · HR<br><small style="font-weight:600;color:#666;">Company Seal</small></div>
            </div>

            <p class="foot-note">
                Generated from <?php echo htmlspecialchars($company); ?> HRMS ·
                App <?php echo htmlspecialchars((string) $row['application_no']); ?>
                <?php if ($offerNo !== ''): ?> · Letter <?php echo htmlspecialchars($offerNo); ?><?php endif; ?>
            </p>
        </div>
    </div>
</div>
</body>
</html>
