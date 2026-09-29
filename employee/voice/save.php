<?php
/**
 * Employee Voice — save submission
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/employee_helper.php';
require_once __DIR__ . '/../../includes/employee_voice_helper.php';

requireLogin();
if (!canSubmitEmployeeVoice() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('employee/voice/index.php'));
    exit;
}

$empId = (int) $_SESSION['employee_id'];
$emp = getEmployeeById($empId);
if (!$emp) {
    header('Location: ' . app_url('employee/voice/index.php'));
    exit;
}

$type = strtoupper(trim((string) ($_POST['module_type'] ?? '')));
$data = [
    'module_type' => $type,
    'employee_id' => $empId,
    'department_id' => (int) ($emp['department_id'] ?? 0),
    'location_name' => $_POST['location_name'] ?? '',
    'category' => $_POST['category'] ?? '',
    'subject' => $_POST['subject'] ?? '',
    'description' => $_POST['description'] ?? '',
    'confidentiality' => $_POST['confidentiality'] ?? 'Normal',
    'submission_mode' => 'Portal',
    'created_by' => (int) ($_SESSION['user_id'] ?? 0),
    // grievance
    'complaint_against' => $_POST['complaint_against'] ?? '',
    'incident_date' => $_POST['incident_date'] ?? '',
    'incident_location' => $_POST['incident_location'] ?? '',
    'confidential_handling' => !empty($_POST['confidential_handling']),
    'preferred_contact' => $_POST['preferred_contact'] ?? '',
    'immediate_assistance' => !empty($_POST['immediate_assistance']),
    'requested_resolution' => $_POST['requested_resolution'] ?? '',
    // suggestion
    'current_problem' => $_POST['current_problem'] ?? '',
    'proposed_improvement' => $_POST['proposed_improvement'] ?? '',
    'expected_benefit' => $_POST['expected_benefit'] ?? '',
    'estimated_saving' => $_POST['estimated_saving'] ?? '',
    'estimated_impl_cost' => $_POST['estimated_impl_cost'] ?? '',
    'help_implement' => !empty($_POST['help_implement']),
    // safety
    'hazard_type' => $_POST['hazard_type'] ?? '',
    'exact_location' => $_POST['exact_location'] ?? '',
    'equipment_ref' => $_POST['equipment_ref'] ?? '',
    'risk_severity' => $_POST['risk_severity'] ?? 'Medium',
    'injury_near_miss' => !empty($_POST['injury_near_miss']),
    'immediate_danger' => !empty($_POST['immediate_danger']),
    'immediate_action_taken' => $_POST['immediate_action_taken'] ?? '',
];

$result = evCreateTicket($data);
if (empty($result['ok'])) {
    $_SESSION['ev_flash_error'] = $result['error'] ?? 'Could not submit';
    header('Location: ' . app_url('employee/voice/submit.php?type=' . urlencode($type)));
    exit;
}

$ticketId = (int) $result['ticket_id'];
if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    evSaveAttachment($ticketId, $_FILES['attachment'], (int) ($_SESSION['user_id'] ?? 0));
}

header('Location: ' . app_url('employee/voice/view.php?id=' . $ticketId . '&created=1'));
exit;
