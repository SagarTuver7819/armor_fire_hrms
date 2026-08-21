<?php
/**
 * Sample attendance Excel (HTML .xls) template
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$filename = 'Attendance_Import_Sample_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$headers = [
    'employee_code',
    'biometric_user_id',
    'employee_name',
    'attendance_date',
    'punch_in_time',
    'punch_out_time',
    'attendance_type',
    'shift',
    'remarks',
];
$sample = [
    ['AS1001', 'AS1001', 'Sample Employee', date('Y-m-d', strtotime('-1 day')), '09:00:00', '18:00:00', 'in', 'General', 'Sample row'],
    ['AS1001', 'AS1001', 'Sample Employee', date('Y-m-d'), '09:05:00', '17:55:00', 'in', 'General', ''],
];

echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h) . '</th>';
}
echo '</tr>';
foreach ($sample as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
    }
    echo '</tr>';
}
echo '</table></body></html>';
