<?php
/**
 * Save public career application
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function recruitmentRedirectError($msg, $step = 6)
{
    // Keep typed data (files cannot be restored)
    $draft = $_POST;
    unset($draft['declare']); // user must re-confirm
    $_SESSION['recruitment_draft'] = [
        'fields' => $draft,
        'error' => (string) $msg,
        'step' => (int) $step,
    ];
    header('Location: ' . app_url('recruitment/apply.php?restore=1'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('recruitment/apply.php'));
    exit;
}

// post_max_size exceeded → PHP empties $_POST / $_FILES
if (empty($_POST) && empty($_FILES)) {
    recruitmentRedirectError('Form data was empty (file may be too large). Please keep each file under 5 MB and try again.', 6);
}

ensureRecruitmentTables();

$deptName = trim((string) ($_POST['department_name'] ?? ''));
$posName = trim((string) ($_POST['position_name'] ?? ''));
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
$altMobile = preg_replace('/\D+/', '', (string) ($_POST['alt_mobile'] ?? ''));
$gender = trim((string) ($_POST['gender'] ?? ''));
$dob = trim((string) ($_POST['dob'] ?? ''));
$marital = trim((string) ($_POST['marital_status'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$state = trim((string) ($_POST['state_name'] ?? ''));
$pincode = trim((string) ($_POST['pincode'] ?? ''));
$currentSalary = trim((string) ($_POST['current_salary'] ?? ''));
$expectedSalary = trim((string) ($_POST['expected_salary'] ?? ''));
$notice = trim((string) ($_POST['notice_period'] ?? ''));
$declare = !empty($_POST['declare']);
$aadhaar = preg_replace('/\D+/', '', (string) ($_POST['aadhaar_no'] ?? ''));
$pan = strtoupper(trim((string) ($_POST['pan_no'] ?? '')));
$bankName = trim((string) ($_POST['bank_name'] ?? ''));
$bankAccount = preg_replace('/\s+/', '', (string) ($_POST['bank_account'] ?? ''));
$bankIfsc = strtoupper(trim((string) ($_POST['bank_ifsc'] ?? '')));
$ageYears = recruitmentCalcAgeYears($dob);

if ($deptName === '') {
    recruitmentRedirectError('Please enter department.', 1);
}
$allowedPositions = ['Sales', 'Other'];
if ($posName === '' || !in_array($posName, $allowedPositions, true)) {
    recruitmentRedirectError('Please select Position / Designation: Sales or Other.', 1);
}
$isSalesPosition = ($posName === 'Sales');
if ($fullName === '' || $email === '' || $address === '') {
    recruitmentRedirectError('Please fill all required personal details.', 2);
}
if (!preg_match('/^[6-9]\d{9}$/', $mobile) && !preg_match('/^0[6-9]\d{9}$/', $mobile)) {
    recruitmentRedirectError('Please enter a valid Indian mobile number (10 or 11 digits).', 2);
}
if ($altMobile !== '' && !preg_match('/^[6-9]\d{9}$/', $altMobile) && !preg_match('/^0[6-9]\d{9}$/', $altMobile)) {
    recruitmentRedirectError('Alternate mobile must be a valid Indian number (10 or 11 digits).', 2);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    recruitmentRedirectError('Please enter a valid email address.', 2);
}
if ($expectedSalary === '') {
    recruitmentRedirectError('Please enter expected salary.', 6);
}
if (!$declare) {
    recruitmentRedirectError('Please accept the declaration to submit.', 6);
}

$bankUp = recruitmentUploadFile('bank_statement', 'bank');
if (!$bankUp['ok']) {
    recruitmentRedirectError($bankUp['error'], 6);
}
$slipUp = recruitmentUploadFile('salary_slip', 'slip');
if (!$slipUp['ok']) {
    recruitmentRedirectError($slipUp['error'], 6);
}
$resumeUp = recruitmentUploadFile('resume_file', 'resume');
if (!$resumeUp['ok']) {
    recruitmentRedirectError($resumeUp['error'], 6);
}

$education = [];
$eduIn = $_POST['edu'] ?? [];
if (is_array($eduIn)) {
    foreach ($eduIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $education[] = [
            'degree' => $row['degree'] ?? '',
            'institution' => $row['institution'] ?? '',
            'year_of_passing' => $row['year'] ?? '',
            'percentage' => $row['percentage'] ?? '',
            'specialization' => $row['specialization'] ?? '',
        ];
    }
}

$experience = [];
$expIn = $_POST['exp'] ?? [];
if (is_array($expIn)) {
    foreach ($expIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $from = trim((string) ($row['from'] ?? ''));
        $to = trim((string) ($row['to'] ?? ''));
        $isCurrent = !empty($row['current']);
        if ($isCurrent) {
            $to = date('Y-m');
        }
        $experience[] = [
            'company_name' => $row['company'] ?? '',
            'designation' => $row['designation'] ?? '',
            'from_date' => $from,
            'to_date' => $to,
            'is_current' => $isCurrent,
            'last_salary' => $row['salary'] ?? '',
            'responsibilities' => $row['responsibilities'] ?? '',
        ];
    }
}

$hasExperience = $currentSalary !== '';
foreach ($experience as $exRow) {
    if (trim((string) ($exRow['company_name'] ?? '')) !== ''
        || trim((string) ($exRow['designation'] ?? '')) !== '') {
        $hasExperience = true;
        break;
    }
}

// Employed candidates must upload bank statement OR salary slip; freshers may skip.
if ($hasExperience && $bankUp['path'] === '' && $slipUp['path'] === '') {
    recruitmentRedirectError('Please upload last 3 months bank statement or salary slip.', 6);
}

$totalExp = recruitmentCalculateTotalExperience($experience);

$descriptive = [];
$reasonIn = $_POST['reason'] ?? [];
if ($isSalesPosition && is_array($reasonIn)) {
    foreach ($reasonIn as $qNo => $ans) {
        $text = trim((string) $ans);
        if ($text !== '') {
            $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($parts) && count($parts) > 200) {
                recruitmentRedirectError('Each Reasoning answer must be within 200 words (Q' . (int) $qNo . ').', 5);
            }
        }
        $descriptive[(int) $qNo] = $text;
    }
    // Sales → every reasoning question is compulsory
    foreach (recruitmentDescriptiveQuestions() as $qNo => $_q) {
        if (trim((string) ($descriptive[(int) $qNo] ?? '')) === '') {
            recruitmentRedirectError('Please answer all Reasoning questions (compulsory for Sales). Missing Q' . (int) $qNo . '.', 5);
        }
    }
}
// Other → Reasoning section skipped; store no descriptive answers

$data = [
    'department_id' => 0,
    'designation_id' => 0,
    'department_name' => $deptName,
    'position_name' => $posName,
    'full_name' => $fullName,
    'email' => $email,
    'mobile' => $mobile,
    'alt_mobile' => $altMobile,
    'gender' => $gender,
    'dob' => $dob,
    'age_years' => $ageYears,
    'marital_status' => $marital,
    'address' => $address,
    'city' => $city,
    'state_name' => $state,
    'pincode' => $pincode,
    'aadhaar_no' => $aadhaar,
    'pan_no' => $pan,
    'bank_name' => $bankName,
    'bank_account' => $bankAccount,
    'bank_ifsc' => $bankIfsc,
    'current_salary' => $currentSalary,
    'expected_salary' => $expectedSalary,
    'notice_period' => $notice,
    'total_experience' => $totalExp,
    'bank_statement_file' => $bankUp['path'],
    'salary_slip_file' => $slipUp['path'],
    'resume_file' => $resumeUp['path'],
    'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
];

$res = saveRecruitmentApplication($data, $education, $experience, $descriptive);

if (empty($res['ok'])) {
    recruitmentRedirectError($res['error'] ?? 'Could not submit application. Please try again.', 6);
}

unset($_SESSION['recruitment_draft']);

header('Location: ' . app_url(
    'recruitment/thankyou.php?no=' . urlencode((string) $res['application_no'])
));
exit;
