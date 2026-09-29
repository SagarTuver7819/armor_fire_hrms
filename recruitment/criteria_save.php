<?php
/**
 * Save interview criteria marks (+ optional new criteria for this position)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/recruitment_helper.php';

requireLogin();
if (!isAdmin() && !isHR() && !(function_exists('isStaffUser') && isStaffUser()) && !canAccess('recruitment', 'edit')) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('recruitment/interviews.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$positionName = trim((string) ($_POST['position_name'] ?? ''));
$newLabel = trim((string) ($_POST['new_criteria'] ?? ''));
$scope = trim((string) ($_POST['new_scope'] ?? 'position'));
$newType = trim((string) ($_POST['new_type'] ?? 'text'));

if ($newLabel !== '') {
    $posForNew = ($scope === 'global') ? '' : $positionName;
    addRecruitmentCriteria($posForNew, $newLabel, null, $newType);
}

$items = [];
$marks = $_POST['mark'] ?? [];
if (is_array($marks)) {
    foreach ($marks as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $items[] = [
            'label' => $label,
            'criteria_id' => (int) ($row['criteria_id'] ?? 0),
            'type' => trim((string) ($row['type'] ?? 'yesno')),
            'answer' => $row['answer'] ?? '',
            'score' => $row['score'] ?? '',
            'remarks' => $row['remarks'] ?? '',
            'checked' => $row['checked'] ?? null,
        ];
    }
}

if ($newLabel !== '') {
    $items[] = [
        'label' => $newLabel,
        'criteria_id' => 0,
        'type' => $newType,
        'answer' => $newType === 'yesno' ? 'Yes' : '',
        'score' => '',
        'remarks' => '',
        'checked' => 1,
    ];
}

$res = saveRecruitmentApplicationMarks($id, $items, (int) ($_SESSION['user_id'] ?? 0));
if (empty($res['ok'])) {
    header('Location: ' . app_url('recruitment/interview.php?id=' . $id . '&msg=error&err=' . urlencode('Could not save criteria')));
    exit;
}

header('Location: ' . app_url('recruitment/interview.php?id=' . $id . '&msg=criteria#recCriteria'));
exit;
