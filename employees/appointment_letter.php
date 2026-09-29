<?php
/**
 * Appointment Letter — same letterhead as Offer Letter
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
    $company = 'Armor Steel Industries Pvt. Ltd.';
}
$companyShort = 'Armor Steel Industries Pvt. Ltd.';

$appointNo = trim((string) ($emp['appointment_letter_no'] ?? ($issued['appointment_letter_no'] ?? '')));
$appointDateRaw = !empty($emp['appointment_letter_date'])
    ? (string) $emp['appointment_letter_date']
    : date('Y-m-d');
$appointDate = formatDateDisplay($appointDateRaw);

$position = trim((string) ($emp['designation'] ?? ''));
$department = trim((string) ($emp['department_name'] ?? ''));
$fullName = strtoupper(trim((string) ($emp['employee_name'] ?? '')));
$empCode = trim((string) ($emp['employee_code'] ?? ''));

$gender = strtolower(trim((string) ($emp['gender'] ?? '')));
$title = 'Mr.';
if (in_array($gender, ['female', 'f', 'woman', 'lady'], true)) {
    $title = 'Ms.';
} elseif (in_array($gender, ['other', 'o'], true)) {
    $title = '';
}
$displayName = trim(($title !== '' ? $title . ' ' : '') . $fullName);

$region = 'RAJKOT - GUJARAT Region';
$joiningDate = !empty($emp['date_of_joining']) && $emp['date_of_joining'] !== '0000-00-00'
    ? formatDateDisplay((string) $emp['date_of_joining'])
    : 'as recorded';
$workLocation = $companyShort . ', Rajkot, Gujarat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Letter · <?php echo htmlspecialchars($appointNo ?: $empCode); ?></title>
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
        .cdoc-header-logo { width: 68px; height: 68px; }
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
        .closing-block .sincerely { margin: 0 0 36px; }
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
            .letter-bottom, .cdoc-footer, .closing-block { page-break-inside: avoid; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>
<div class="bar">
    <div>
        <strong><?php echo htmlspecialchars($appointNo !== '' ? $appointNo : $empCode); ?></strong>
        · Appointment Letter
        <?php if ($empCode !== ''): ?>
            · <?php echo htmlspecialchars($empCode); ?>
        <?php endif; ?>
    </div>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="sheet cdoc-sheet">
    <?php echo companyDocRenderWatermark($brand); ?>
    <div class="cdoc-inner">
        <?php echo companyDocRenderHeader($brand, 'Human Resource · Appointment'); ?>

        <div class="ref-date-row">
            <div class="ref-no">Ref: <?php echo htmlspecialchars($appointNo !== '' ? $appointNo : '—'); ?></div>
            <div class="ref-date">Date:- <?php echo htmlspecialchars($appointDate); ?></div>
        </div>

        <h2 class="doc-title">Appointment Letter</h2>

        <div class="letter-main">
            <div class="letter-body">
                <div class="to-block">
                    To,<br>
                    <span class="name"><?php echo htmlspecialchars($displayName); ?></span>
                    <?php if ($empCode !== ''): ?>
                        <br>Emp. Code: <strong><?php echo htmlspecialchars($empCode); ?></strong>
                    <?php endif; ?>
                </div>

                <p>Dear <?php echo htmlspecialchars($displayName); ?>,</p>

                <p>
                    With reference to your joining, we are pleased to confirm your appointment as
                    <strong><?php echo htmlspecialchars($position !== '' ? $position : '—'); ?></strong>
                    in the
                    <strong><?php echo htmlspecialchars($department !== '' ? $department : '—'); ?></strong>
                    department at
                    <strong><?php echo htmlspecialchars($region); ?>.</strong>
                </p>

                <p>
                    Your date of joining with the company is
                    <strong><?php echo htmlspecialchars($joiningDate); ?></strong>
                    and your place of posting shall be
                    <strong><?php echo htmlspecialchars($workLocation); ?>.</strong>
                </p>

                <p>Your salary / remuneration shall be as per the company records and discussions held with you.</p>

                <p>
                    You will be on probation for a period of <strong>3 months</strong> from the date of joining.
                    On successful completion of probation, your services may be confirmed in writing.
                    During / after probation, the company reserves the right to continue or discontinue your services
                    based on performance and conduct.
                </p>

                <p>
                    You shall abide by the rules, regulations, policies and standing orders of the company
                    as applicable from time to time.
                </p>

                <p>
                    If you wish to resign from your designation for any reason, you are bound to submit
                    <strong>1-month prior notice</strong> to the company and submit the resignation letter to the
                    respected authority.
                </p>

                <p>
                    We welcome you to the Armor family and look forward to a long and mutually beneficial association.
                </p>

                <p>For any query feel free to contact the undersigned.</p>
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
                            Employee Signature
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
