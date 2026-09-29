<?php
/**
 * Appointment Letter — 3-page A4, letterhead + full terms
 * Doc No: ASIPL/HR/APPOINT/{FY}/####
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('employees', 'view')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$emp = $id > 0 ? getEmployeeById($id) : null;
if (!$emp) {
    header('Location: ' . app_url('employees/index.php'));
    exit;
}

$rowDeptId = (int) ($emp['department_id'] ?? 0);
if (!isAdmin() && !isHR() && !canAccess('employees', 'view', $rowDeptId)) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$issued = employeeIssueAppointmentLetter($id);
$emp = getEmployeeById($id) ?: $emp;

$brand = getCompanyDocumentBranding();
$company = trim((string) $brand['company_name']);
if ($company === '' || strcasecmp($company, 'Armor Fire') === 0) {
    $company = 'Armor Steel Industries Pvt Ltd';
}
$companyShort = 'Armor Steel Industries Pvt Ltd';

$appointNo = trim((string) ($emp['appointment_letter_no'] ?? ($issued['appointment_letter_no'] ?? '')));
$appointDateRaw = !empty($emp['appointment_letter_date'])
    ? (string) $emp['appointment_letter_date']
    : date('Y-m-d');
$appointDate = formatDateDisplay($appointDateRaw);

$position = trim((string) ($emp['designation'] ?? ''));
if ($position === '') {
    $position = '—';
}
$department = trim((string) ($emp['department_name'] ?? ''));
if ($department === '') {
    $department = '—';
}
$fullName = strtoupper(trim((string) ($emp['employee_name'] ?? '')));
$empCode = trim((string) ($emp['employee_code'] ?? ''));
$mobile = trim((string) ($emp['mobile_number'] ?? ''));
if ($mobile === '') {
    $mobile = trim((string) ($emp['office_mobile'] ?? ''));
}
$email = strtoupper(trim((string) ($emp['office_email'] ?? '')));

$gender = strtolower(trim((string) ($emp['gender'] ?? '')));
$title = 'Mr.';
$dearTitle = 'Mr.';
if (in_array($gender, ['female', 'f', 'woman', 'lady'], true)) {
    $title = 'Ms.';
    $dearTitle = 'Mrs.';
} elseif (in_array($gender, ['other', 'o'], true)) {
    $title = '';
    $dearTitle = '';
}
$displayName = trim(($title !== '' ? $title . ' ' : '') . $fullName);
$dearName = trim(($dearTitle !== '' ? $dearTitle . ' ' : '') . $fullName);

$joiningDate = !empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00'
    ? formatDateDisplay((string) $emp['date_of_joining'])
    : '—';

$reportingTo = trim((string) ($emp['reporting_head'] ?? ''));
if ($reportingTo === '') {
    $reportingTo = 'Priteshbhai - Managing Director - ' . $companyShort;
}

$postingPlace = 'Lothda, Rajkot, Gujarat';
$workHours = '09:00 AM to 06:00 PM';

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Letter · <?php echo $h($appointNo ?: $empCode); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            background: #e5e7eb;
            color: #111;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
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
            font-family: Calibri, Segoe UI, Arial, sans-serif;
        }
        .bar button {
            background: #d2232a;
            color: #fff;
            border: 0;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: Calibri, Segoe UI, Arial, sans-serif;
        }
        <?php echo companyDocPrintCss(); ?>
        .page {
            width: 210mm;
            max-width: 100%;
            height: 297mm;
            margin: 0 auto 14px;
            border: 1px solid #111;
            padding: 12mm 16mm 12mm;
            background: #fff;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .page > .cdoc-inner {
            position: relative;
            z-index: 1;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
        }
        .cdoc-header {
            gap: 12px;
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom-width: 2.5px;
            align-items: center;
            flex-shrink: 0;
        }
        .cdoc-header-logo { width: 88px; height: 88px; }
        .cdoc-header-text .cdoc-company {
            font-size: 24px;
            font-weight: 800;
            color: #b91c1c;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            margin: 0 0 3px;
            line-height: 1.2;
        }
        .cdoc-header-text .cdoc-header-details {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            line-height: 1.35;
        }
        .cdoc-footer {
            margin-top: auto;
            padding-top: 8px;
            border-top-width: 1.5px;
            font-size: 10.5px;
            line-height: 1.35;
            font-family: Calibri, Candara, Segoe UI, Arial, sans-serif;
            flex-shrink: 0;
        }
        .cdoc-watermark img {
            width: min(52%, 320px);
            max-height: 320px;
            opacity: 0.06;
        }
        .page-body {
            flex: 1 1 auto;
            min-height: 0;
        }
        .ref-date-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 12px;
            margin: 0 0 10px;
            font-size: 13.5px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .to-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin: 0 0 10px;
        }
        .to-block {
            text-align: left;
            line-height: 1.5;
            flex: 1 1 auto;
            font-size: 13.5px;
        }
        .to-block .name {
            font-weight: 700;
            text-transform: uppercase;
        }
        .to-date {
            flex: 0 0 auto;
            font-weight: 700;
            white-space: nowrap;
            font-size: 13.5px;
            text-align: right;
            line-height: 1.45;
        }
        .to-date .lbl { font-weight: 700; }
        .doc-title {
            text-align: center;
            margin: 6px 0 12px;
            font-size: 15.5px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .letter-body {
            font-size: 13.5px;
            line-height: 1.5;
            text-align: justify;
        }
        .letter-body p { margin: 0 0 9px; }
        .letter-body strong { font-weight: 700; }
        .cont-label {
            font-size: 12.5px;
            font-weight: 700;
            margin: 0 0 10px;
            color: #334155;
        }
        .clause { margin: 0 0 11px; }
        .clause-title {
            font-weight: 700;
            margin: 0 0 4px;
            font-size: 13.5px;
        }
        .clause p { margin: 0 0 5px; }
        .clause ol, .clause ul {
            margin: 4px 0 6px;
            padding-left: 22px;
        }
        .clause li { margin: 0 0 4px; }
        .clause .note {
            margin: 6px 0 0;
            font-size: 12.5px;
        }
        .closing { margin-top: 14px; }
        .closing .mgmt {
            margin-top: 26px;
            font-weight: 700;
            line-height: 1.45;
            font-size: 13.5px;
        }
        .sign-accept {
            margin-top: 28px;
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }
        .sign-box {
            width: 42%;
            text-align: center;
            font-weight: 700;
            font-size: 13.5px;
        }
        .sign-box .line {
            border-top: 1.5px solid #111;
            margin: 46px 0 6px;
        }
        .page-no {
            text-align: center;
            font-size: 11px;
            color: #64748b;
            margin-top: 6px;
            flex-shrink: 0;
            font-family: Calibri, Segoe UI, Arial, sans-serif;
        }
        @media print {
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .bar { display: none !important; }
            .page {
                width: 210mm;
                max-width: 210mm;
                height: 297mm;
                min-height: 297mm;
                margin: 0;
                border: 0;
                padding: 12mm 16mm 10mm;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
                overflow: hidden;
            }
            .page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
            .page-no { display: none; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>
<div class="bar">
    <div>
        <strong><?php echo $h($appointNo !== '' ? $appointNo : $empCode); ?></strong>
        · Appointment Letter (3 pages)
        <?php if ($empCode !== ''): ?>
            · <?php echo $h($empCode); ?>
        <?php endif; ?>
    </div>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<!-- ===================== PAGE 1 ===================== -->
<div class="page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Appointment'); ?>

        <div class="ref-date-row">
            <div>Ref: <?php echo $h($appointNo !== '' ? $appointNo : '—'); ?></div>
            <div>Letter Date: <?php echo $h($appointDate); ?></div>
        </div>

        <div class="to-head">
            <div class="to-block">
                To,<br>
                <span class="name"><?php echo $h($displayName); ?></span><br>
                <?php echo $h($position); ?><br>
                <?php echo $h($department); ?><br>
                <?php echo $h($companyShort); ?>
                <?php if ($mobile !== '' || $email !== ''): ?>
                    <br>
                    <?php if ($mobile !== ''): ?>Mobile: <?php echo $h($mobile); ?><?php endif; ?>
                    <?php if ($mobile !== '' && $email !== ''): ?>&nbsp; <?php endif; ?>
                    <?php if ($email !== ''): ?>E Mail: <?php echo $h($email); ?><?php endif; ?>
                <?php endif; ?>
                <?php if ($empCode !== ''): ?>
                    <br>Emp. Code: <strong><?php echo $h($empCode); ?></strong>
                <?php endif; ?>
            </div>
            <div class="to-date">
                <span class="lbl">Date of Joining:</span><br>
                <?php echo $h($joiningDate); ?>
            </div>
        </div>

        <h2 class="doc-title">Subject : Appointment Letter</h2>

        <div class="page-body letter-body">
            <p>Dear <?php echo $h($dearName); ?>,</p>
            <p>
                We are pleased to offer you, the position of
                <strong><?php echo $h($position); ?></strong>
                with
                <strong><?php echo $h($companyShort); ?></strong>
                on the following terms and conditions:
            </p>

            <div class="clause">
                <div class="clause-title">1. Commencement of Employment</div>
                <p>Your employment will be effective, as of <strong><?php echo $h($joiningDate); ?></strong></p>
            </div>

            <div class="clause">
                <div class="clause-title">2. Job Title</div>
                <p>
                    Your job title will be <strong><?php echo $h($position); ?></strong>
                    and you will report to
                    <strong><?php echo $h($reportingTo); ?></strong>
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">3. Salary</div>
                <p>Your salary and other benefits will be as set out in Schedule I, hereto.</p>
            </div>

            <div class="clause">
                <div class="clause-title">4. Place of Posting</div>
                <p>
                    You will be posted at <strong><?php echo $h($postingPlace); ?></strong>.
                    You may however be required to work at any place of business at where Company has, or may later acquire.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">5. Probation Period</div>
                <p>
                    Your probation period will be <strong>3 month</strong> and in this probation period either the employer or employee
                    can end the employment without notice.
                </p>
                <p><strong>Extension option</strong><br>
                    If performance needs improvement, the company may extend the probation period.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">6. Working Hours</div>
                <p>
                    You will be required to work for such hours as necessary for the proper discharge of your duties to
                    the Company. The normal working hours are from
                    <strong><?php echo $h($workHours); ?></strong>.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">7. Leave / Holidays</div>
                <ol>
                    <li>Week off will be on Sunday.</li>
                    <li>You are entitled to <strong>AS PER LEAVE POLICY</strong> working days of paid Leave.</li>
                    <li>The Company shall notify a list of declared holidays in the beginning of each year.</li>
                    <li>For Extra leaves Candidate has to Inform Company before taking leave.</li>
                </ol>
                <p class="note">
                    <strong>NOTE:</strong> Candidate Must have to attain calls and responses from Clientele or Company Side on any kind of Leaves.
                </p>
            </div>
        </div>

        <div class="page-no">Page 1 of 3</div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>

<!-- ===================== PAGE 2 ===================== -->
<div class="page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Appointment'); ?>

        <div class="ref-date-row">
            <div>Ref: <?php echo $h($appointNo !== '' ? $appointNo : '—'); ?></div>
            <div>Date of Joining: <?php echo $h($joiningDate); ?></div>
        </div>
        <div class="cont-label">Appointment Letter (Continued) · <?php echo $h($displayName); ?></div>

        <div class="page-body letter-body">
            <div class="clause">
                <div class="clause-title">8. Nature of Duties</div>
                <p>
                    You will perform to the best of your ability all the duties as are inherent in your post and such
                    additional duties as the company may call upon you to perform, from time to time.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">9. Company Property</div>
                <p>
                    You will always maintain in good condition Company property, which may be entrusted to you for
                    official use during the course of your employment and shall return all such property to the Company
                    prior to relinquishment of your charge, failing which the cost of the same will be recovered from you
                    by the Company.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">10. Borrowing / Accepting Gifts</div>
                <p>
                    You will not borrow or accept any money, gift, reward or compensation for your personal gains from
                    or otherwise place yourself under pecuniary obligation to any person/client with whom you may be
                    having official dealings.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">11. Termination</div>
                <ol>
                    <li>If performance is not satisfactory, the company can terminate the job without notice period.</li>
                    <li>The company can terminate employment without notice period for misconduct or damage to the company.</li>
                    <li>If the company employe does not follow the given target or visit schedule, the company can terminate the job without any notice.</li>
                    <li>An employee can terminate employment by giving a notice period of <strong>1 month</strong>.</li>
                </ol>
                <p>
                    <strong>11.5</strong> On the termination of your employment for whatever reason, you will return to the Company all
                    property; documents and paper, both original and copies thereof, including any samples, literature,
                    contracts, records, lists, drawings, blueprints, letters, notes, data and the like; and Confidential
                    Information, in your possession or under your control relating to your employment or to clients’
                    business affairs.
                </p>
                <p>
                    <strong>11.6</strong> The notice period will be valid in circumstances where the employee and the company agree.
                    Otherwise, the notice period will not be valid in any other work.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">12. Confidential Information</div>
                <p>
                    <strong>12.1</strong> During your employment with the Company you will devote your whole time, attention and skill to
                    the best of your ability for its business. You shall not, directly or indirectly, engage or associate
                    yourself with, be connected with, concerned, employed or engaged in any other business or
                    activities or any other post or work part time or pursue any course of study whatsoever, without the
                    prior permission of the Company.
                </p>
                <p>
                    <strong>12.2</strong> You must always maintain the highest degree of confidentiality and keep as confidential the
                    records, documents and other Confidential Information relating to the business of the Company
                    which may be known to you or confided in you by any means and you will use such records,
                    documents and information only in a duly authorized manner in the interest of the Company. For
                    the purposes of this clause ‘Confidential Information’ means information about the Company’s
                    business and that of its customers which is not available to the general public and which may be
                    learnt by you in the course of your employment. This includes, but is not limited to, information
                    relating to the organization, its customer lists, employment policies, personnel, and information
                    about the Company’s products, processes including ideas, concepts, projections, technology,
                    manuals, drawing, designs, specifications, and all papers, resumes, records and other documents
                    containing such Confidential Information.
                </p>
            </div>
        </div>

        <div class="page-no">Page 2 of 3</div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>

<!-- ===================== PAGE 3 ===================== -->
<div class="page cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Appointment'); ?>

        <div class="ref-date-row">
            <div>Ref: <?php echo $h($appointNo !== '' ? $appointNo : '—'); ?></div>
            <div>Date of Joining: <?php echo $h($joiningDate); ?></div>
        </div>
        <div class="cont-label">Appointment Letter (Continued) · <?php echo $h($displayName); ?></div>

        <div class="page-body letter-body">
            <div class="clause">
                <div class="clause-title">12. Confidential Information (Continued)</div>
                <p><strong>12.3</strong> At no time, will you remove any Confidential Information from the office without permission.</p>
                <p>
                    <strong>12.4</strong> Your duty to safeguard and not disclose Confidential Information will survive the expiration or
                    termination of this Agreement and/or your employment with the Company.
                </p>
                <p>
                    <strong>12.5</strong> Breach of the conditions of this clause will render you liable to summary dismissal under clause
                    above in addition to any other remedy the Company may have against you in law.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">13. Notices</div>
                <p>
                    Notices may be given by you to the Company at its registered office address. Notices may be given by the
                    Company to you at the address intimated by you in the official records.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">14. Applicability of Company Policy</div>
                <p>
                    The Company shall be entitled to make policy declarations from time to time pertaining to matters like
                    leave entitlement, maternity leave, employees’ benefits, working hours, transfer policies, etc., and may
                    alter the same from time to time at its sole discretion. All such policy decisions of the Company shall be
                    binding on you and shall override this Agreement to that extent.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">15. Governing Law / Jurisdiction</div>
                <p>
                    Your employment with the Company is subject to Indian laws. All disputes shall be subject to the
                    jurisdiction of Rajkot, Gujarat only.
                </p>
            </div>

            <div class="clause">
                <div class="clause-title">16. Acceptance of our offer</div>
                <p>
                    Please confirm your acceptance of this Contract of Employment by signing and returning the
                    duplicate copy.
                </p>
            </div>

            <div class="closing">
                <p>We welcome you, and look forward to receiving your acceptance and to working with you</p>
                <div class="mgmt">
                    Management<br>
                    <?php echo $h($companyShort); ?><br>
                    Rajkot, Gujarat
                </div>
            </div>

            <div class="sign-accept">
                <div class="sign-box">
                    <div class="line"></div>
                    Employee Signature
                </div>
                <div class="sign-box">
                    <div class="line"></div>
                    Authorized Signatory
                </div>
            </div>
        </div>

        <div class="page-no">Page 3 of 3</div>
        <?php echo companyDocRenderFooter($brand); ?>
    </div>
</div>
</body>
</html>
