<?php
/**
 * Seed a local fake Sales recruitment application for PDF testing.
 * Run: php scripts/seed_fake_recruitment.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/document_print.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

$conn = getDBConnection();
ensureRecruitmentTables($conn);

/* Ensure letterhead path is set */
$lhRel = 'assets/uploads/letterhead/Letterhead_Approved_Final.png';
$lhAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lhRel);
if (is_file($lhAbs)) {
    ensureCompanySettingsSchema($conn);
    $conn->query(
        "UPDATE company_settings SET letterhead_image = '" . $conn->real_escape_string($lhRel) . "'"
    );
}

$data = [
    'department_id' => 0,
    'designation_id' => 0,
    'department_name' => 'Product approval manager',
    'position_name' => 'Sales',
    'full_name' => 'Durg Singh (LOCAL TEST)',
    'email' => 'durgsingh.test@example.com',
    'mobile' => '9875787152',
    'alt_mobile' => '6269847908',
    'gender' => 'Male',
    'dob' => '2002-09-17',
    'age_years' => 24,
    'marital_status' => 'Single',
    'address' => 'Bhatiyo ka vas kakelaw jodhpur',
    'city' => 'Jodhpur',
    'state_name' => 'Rajasthan',
    'pincode' => '342001',
    'aadhaar_no' => 'XXXX-XXXX-1234',
    'pan_no' => 'ABCDE1234F',
    'bank_name' => 'State Bank of India',
    'bank_account' => '12345678901',
    'bank_ifsc' => 'SBIN0001234',
    'current_salary' => 34000,
    'expected_salary' => 42000,
    'notice_period' => '15 days',
    'total_experience' => '2 years 4 months',
    'ip_address' => '127.0.0.1',
    'user_agent' => 'seed_fake_recruitment',
];

$education = [
    [
        'degree' => 'BA',
        'institution' => 'JNVU Jodhpur',
        'year_of_passing' => '2022',
        'percentage' => '48%',
    ],
];

$experience = [
    ['company_name' => 'Quess Corp', 'designation' => 'Sales Executive', 'from_date' => '2023-01', 'to_date' => '2023-06', 'is_current' => 0, 'last_salary' => 22000],
    ['company_name' => 'HDFC Life', 'designation' => 'Advisor', 'from_date' => '2023-07', 'to_date' => '2024-01', 'is_current' => 0, 'last_salary' => 25000],
    ['company_name' => 'Policy Bazaar', 'designation' => 'Sales Associate', 'from_date' => '2024-02', 'to_date' => '2024-08', 'is_current' => 0, 'last_salary' => 30000],
    ['company_name' => 'Solar Square', 'designation' => 'Solar Consultant', 'from_date' => '2024-09', 'to_date' => '', 'is_current' => 1, 'last_salary' => 34000],
];

$descriptive = [
    1 => 'My name is Durg Singh. I have completed my B.A. from JNVU Jodhpur. I have 2.4 years sales experience. There are six members in my family.',
    2 => 'I have been in the sales field for 2 years 4 months.',
    3 => 'I prefer sales because I like meeting people and achieving targets.',
    4 => 'My hobbies are cricket, reading, and traveling.',
    5 => 'I used customer-focused communication and patience to handle hardcore situations.',
    6 => 'Usually 3 to 4 visits were needed and it took about 7 to 10 days.',
    7 => 'Previous company product range included insurance and solar solutions.',
    8 => 'I used a customer-focused sales strategy to sell the full product range.',
    9 => 'Time management is critical for daily visits, follow-ups and closing.',
    10 => 'Targets were monthly; I usually achieved 80-100% of assigned target.',
    11 => 'If a sale does not close I analyse objections, follow up and stay motivated.',
    12 => 'Getting business may take 2-4 weeks; managing the account is ongoing.',
    13 => 'I listen carefully, escalate if needed, and resolve complaints promptly.',
    14 => 'Yes, I worked with a team of 5 members and coordinated daily via calls and meetings.',
    16 => '1) New territory 2) Price objection 3) Competition 4) Follow-up discipline 5) Target pressure',
    17 => 'Example: delayed payment customer — I scheduled follow-ups and closed with revised terms.',
    18 => 'If no appointment, I request alternate slot, send product info, and follow up politely.',
];

$res = saveRecruitmentApplication($data, $education, $experience, $descriptive, $conn);
if (empty($res['ok'])) {
    fwrite(STDERR, 'SEED FAILED: ' . ($res['error'] ?? 'unknown') . PHP_EOL);
    exit(1);
}

$brand = getCompanyDocumentBranding();
$id = (int) $res['id'];
$appNo = $res['application_no'];

echo "OK id={$id} app_no={$appNo}\n";
echo "letterhead_src=" . ($brand['letterhead_src'] ?? '') . "\n";
echo "has_letterhead=" . (!empty($brand['has_letterhead']) ? '1' : '0') . "\n";
echo "pdf_url=" . app_url('recruitment/pdf.php?id=' . $id) . "\n";

$conn->close();
