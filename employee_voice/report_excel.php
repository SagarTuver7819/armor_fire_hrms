<?php
/**
 * Employee Voice Report — Excel export
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/employee_voice_helper.php';
require_once __DIR__ . '/../includes/employee_voice_report_helper.php';

requireLogin();
if (!canManageEmployeeVoice()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$data = evReportLoadData();
$types = $data['types'];
$rows = $data['rows'];
$m = $data['meta'];
$companyName = function_exists('getCompanyName') ? getCompanyName() : 'Company';

$typeLabel = 'All Categories';
if ($m['type'] !== '' && isset($types[$m['type']])) {
    $typeLabel = $types[$m['type']]['label'];
}

$fname = 'Employee_Voice_Report_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');

$headers = [
    'Sr', 'Ticket No', 'Type', 'Employee Code', 'Employee Name', 'Department',
    'Category', 'Subject', 'Priority', 'Status', 'Confidentiality', 'Submitted At',
];

echo '<html><head><meta charset="UTF-8"></head><body>';
echo '<table border="1" cellspacing="0" cellpadding="4">';
echo '<tr><th colspan="' . count($headers) . '" style="background:#1e3a5f;color:#fff;font-size:14px;">'
    . htmlspecialchars($companyName) . ' — Employee Voice Report</th></tr>';
echo '<tr><th colspan="' . count($headers) . '" style="background:#f8fafc;text-align:left;">'
    . 'Type: ' . htmlspecialchars($typeLabel)
    . ' · Period: ' . htmlspecialchars($m['date_from']) . ' to ' . htmlspecialchars($m['date_to'])
    . ' · Total: ' . (int) ($m['total'] ?? count($rows))
    . ' · Open: ' . (int) ($m['open'] ?? 0)
    . ' · Closed: ' . (int) ($m['closed'] ?? 0)
    . '</th></tr><tr>';
foreach ($headers as $h) {
    echo '<th style="background:#d2232a;color:#fff;">' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';

if (!$rows) {
    echo '<tr><td colspan="' . count($headers) . '">No tickets found</td></tr>';
} else {
    foreach ($rows as $i => $r) {
        $mt = (string) ($r['module_type'] ?? '');
        $meta = $types[$mt] ?? null;
        $isAnon = (($r['confidentiality'] ?? '') === 'Anonymous');
        $empName = $isAnon
            ? ('Anonymous' . (!empty($r['employee_name']) ? ' (' . $r['employee_name'] . ')' : ''))
            : (string) ($r['employee_name'] ?? '');
        $cells = [
            $i + 1,
            (string) ($r['ticket_no'] ?? ''),
            (string) ($meta['short'] ?? $mt),
            (string) ($r['employee_code'] ?? ''),
            $empName,
            (string) ($r['department_name'] ?? ''),
            (string) ($r['category'] ?? ''),
            (string) ($r['subject'] ?? ''),
            (string) ($r['priority'] ?? ''),
            (string) ($r['status'] ?? ''),
            (string) ($r['confidentiality'] ?? ''),
            !empty($r['submitted_at']) ? formatDateTimeDisplay($r['submitted_at']) : '',
        ];
        echo '<tr>';
        foreach ($cells as $c) {
            echo '<td>' . htmlspecialchars((string) $c) . '</td>';
        }
        echo '</tr>';
    }
}
echo '</table></body></html>';
exit;
