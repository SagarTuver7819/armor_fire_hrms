<?php
/**
 * Save public career application
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

function recruitmentRedirectError($msg)
{
    header('Location: ' . app_url('recruitment/apply.php?err=' . urlencode($msg)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('recruitment/apply.php'));
    exit;
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
$totalExp = trim((string) ($_POST['total_experience'] ?? ''));
$declare = !empty($_POST['declare']);

if ($deptName === '') {
    recruitmentRedirectError('Please enter department.');
}
if ($posName === '') {
    recruitmentRedirectError('Please enter position / designation.');
}
if ($fullName === '' || strlen($mobile) < 10 || $email === '' || $address === '') {
    recruitmentRedirectError('Please fill all required personal details.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    recruitmentRedirectError('Please enter a valid email address.');
}
if ($expectedSalary === '') {
    recruitmentRedirectError('Please enter expected salary.');
}
if (!$declare) {
    recruitmentRedirectError('Please accept the declaration to submit.');
}

$bankUp = recruitmentUploadFile('bank_statement', 'bank');
if (!$bankUp['ok']) {
    recruitmentRedirectError($bankUp['error']);
}
$slipUp = recruitmentUploadFile('salary_slip', 'slip');
if (!$slipUp['ok']) {
    recruitmentRedirectError($slipUp['error']);
}
$resumeUp = recruitmentUploadFile('resume_file', 'resume');
if (!$resumeUp['ok']) {
    recruitmentRedirectError($resumeUp['error']);
}

if ($bankUp['path'] === '' && $slipUp['path'] === '') {
    recruitmentRedirectError('Please upload last 3 months bank statement or salary slip.');
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
        $experience[] = [
            'company_name' => $row['company'] ?? '',
            'designation' => $row['designation'] ?? '',
            'from_date' => $row['from'] ?? '',
            'to_date' => $row['to'] ?? '',
            'is_current' => !empty($row['current']),
            'last_salary' => $row['salary'] ?? '',
            'responsibilities' => $row['responsibilities'] ?? '',
        ];
    }
}

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
    'marital_status' => $marital,
    'address' => $address,
    'city' => $city,
    'state_name' => $state,
    'pincode' => $pincode,
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

$res = saveRecruitmentApplication($data, $education, $experience);

if (empty($res['ok'])) {
    recruitmentRedirectError($res['error'] ?? 'Could not submit application. Please try again.');
}

header('Location: ' . app_url(
    'recruitment/thankyou.php?no=' . urlencode((string) $res['application_no'])
));
exit;
