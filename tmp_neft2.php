<?php
ini_set('memory_limit', '2048M');
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/payroll_reports_helper.php';

$month = 10;
$year = 2026;

foreach (['salary', 'jobwork_actual'] as $type) {
    $groups = getSalaryRegisterData($type, $month, $year, 0, 0);
    $flat = flattenSalaryRegisterGroups($groups);
    $netPos = 0;
    foreach ($flat as $r) {
        if ((float) ($r['net'] ?? 0) > 0) {
            $netPos++;
        }
    }
    echo "$type flat=" . count($flat) . " net>0=$netPos\n";
}

$data = getCombinedNeftSheetData($month, $year, 0);
echo "combined rows=" . ($data ? count($data['rows']) : 0)
    . " total=" . ($data['neft_total'] ?? 0)
    . " missing=" . ($data['missing_bank_count'] ?? 0)
    . " register=" . ($data['register_row_count'] ?? 0)
    . " status=" . ($data['lock']['approval_status'] ?? '')
    . " from_live=" . (!empty($data['from_live']) ? 'yes' : 'no') . "\n";
