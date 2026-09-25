<?php
/**
 * AJAX — revise decided salary (increase / decrease) with effective date + history
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/employee_helper.php';
require_once __DIR__ . '/../includes/date_helper.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$changeAmount = (float) ($_POST['change_amount'] ?? 0);
$effectiveRaw = (string) ($_POST['effective_date'] ?? '');
$effectiveDate = parseDateInput($effectiveRaw) ?: (preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveRaw) ? $effectiveRaw : '');
$remarks = trim((string) ($_POST['remarks'] ?? ''));
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($employeeId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Employee required']);
    exit;
}

$conn = getDBConnection();
ensureEmployeesTable($conn);

try {
    $result = employeeReviseSalary($conn, $employeeId, $changeAmount, $effectiveDate, $userId, $remarks);
    $historyHtml = [];
    foreach ($result['history'] as $h) {
        $chg = (float) $h['change_amount'];
        $historyHtml[] = [
            'id' => (int) $h['id'],
            'old_salary' => number_format((float) $h['old_salary'], 2),
            'change_amount' => number_format($chg, 2),
            'change_signed' => ($chg >= 0 ? '+' : '') . number_format($chg, 2),
            'new_salary' => number_format((float) $h['new_salary'], 2),
            'effective_date' => formatDateDisplay($h['effective_date']),
            'remarks' => (string) ($h['remarks'] ?? ''),
            'changed_by' => (string) ($h['changed_by_label'] ?? '—'),
            'created_at' => !empty($h['created_at']) ? date('d-m-Y H:i', strtotime($h['created_at'])) : '',
            'is_increase' => $chg >= 0,
        ];
    }
    $conn->close();
    echo json_encode([
        'ok' => true,
        'old_salary' => $result['old_salary'],
        'change_amount' => $result['change_amount'],
        'new_salary' => $result['new_salary'],
        'current_salary' => $result['current_salary'],
        'effective_date' => $result['effective_date'],
        'effective_date_display' => formatDateDisplay($result['effective_date']),
        'history' => $historyHtml,
        'message' => 'Salary updated from ' . formatDateDisplay($result['effective_date']),
    ]);
} catch (Throwable $e) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
