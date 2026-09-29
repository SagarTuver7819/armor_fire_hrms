<?php
/**
 * Employee Voice — Grievance / Suggestion / Safety
 */

require_once __DIR__ . '/../config/database.php';

function evModuleTypes()
{
    return [
        'GRIEVANCE' => [
            'key' => 'GRIEVANCE',
            'label' => 'Grievance / Complaint',
            'short' => 'Grievance',
            'color' => '#dc2626',
            'bg' => '#fef2f2',
            'icon' => 'fa-box-tissue',
            'prefix' => 'EVG',
        ],
        'SUGGESTION' => [
            'key' => 'SUGGESTION',
            'label' => 'Employee Suggestion',
            'short' => 'Suggestion',
            'color' => '#16a34a',
            'bg' => '#f0fdf4',
            'icon' => 'fa-lightbulb',
            'prefix' => 'EVS',
        ],
        'SAFETY' => [
            'key' => 'SAFETY',
            'label' => 'Safety Concern',
            'short' => 'Safety',
            'color' => '#2563eb',
            'bg' => '#eff6ff',
            'icon' => 'fa-shield-halved',
            'prefix' => 'EVF',
        ],
    ];
}

function evStatusesByModule($moduleType)
{
    $moduleType = strtoupper(trim((string) $moduleType));
    $map = [
        'GRIEVANCE' => [
            'Submitted', 'Screening', 'Assigned', 'Investigation',
            'Action Pending', 'Action in Progress', 'Resolved',
            'Feedback Pending', 'Closed', 'Reopened', 'Withdrawn',
        ],
        'SUGGESTION' => [
            'Submitted', 'Screening', 'Under Evaluation', 'Approved',
            'Rejected', 'On Hold', 'Implementation in Progress',
            'Impact Measurement', 'Recognition Pending', 'Closed', 'Withdrawn',
        ],
        'SAFETY' => [
            'Reported', 'Risk Triage', 'Containment in Progress',
            'Investigation', 'Corrective Action Pending',
            'Verification Pending', 'Verified', 'Closed', 'Reopened', 'Withdrawn',
        ],
    ];
    return $map[$moduleType] ?? ['Submitted', 'Closed', 'Withdrawn'];
}

/**
 * Employee may edit/withdraw only while ticket is still early-stage.
 */
function evEmployeeCanModifyTicket($ticket)
{
    if (!$ticket || !empty($ticket['is_deleted'])) {
        return false;
    }
    $status = trim((string) ($ticket['status'] ?? ''));
    $module = strtoupper(trim((string) ($ticket['module_type'] ?? '')));
    $allowed = [
        'GRIEVANCE' => ['Submitted', 'Screening'],
        'SUGGESTION' => ['Submitted', 'Screening'],
        'SAFETY' => ['Reported', 'Risk Triage'],
    ];
    $ok = $allowed[$module] ?? ['Submitted'];
    return in_array($status, $ok, true);
}

/**
 * Target status for HR "Complete" quick action
 */
function evCompleteStatusForModule($moduleType)
{
    $moduleType = strtoupper(trim((string) $moduleType));
    if ($moduleType === 'SAFETY') {
        return 'Verified';
    }
    if ($moduleType === 'SUGGESTION') {
        return 'Closed';
    }
    return 'Resolved';
}

function evDefaultStatus($moduleType)
{
    $moduleType = strtoupper(trim((string) $moduleType));
    return $moduleType === 'SAFETY' ? 'Reported' : 'Submitted';
}

function evCategories($moduleType)
{
    $moduleType = strtoupper(trim((string) $moduleType));
    $map = [
        'GRIEVANCE' => [
            'Workplace Complaint', 'Employee Relations', 'Facilities',
            'Discipline', 'Harassment / Misconduct', 'Other',
        ],
        'SUGGESTION' => [
            'Cost Saving', 'Productivity', 'Quality',
            'Automation', 'Process Improvement', 'Welfare', 'Other',
        ],
        'SAFETY' => [
            'Unsafe Machine', 'PPE', 'Fire Hazard',
            'Near Miss', 'Emergency Exit', 'Electrical', 'Other',
        ],
    ];
    return $map[$moduleType] ?? ['Other'];
}

function evConfidentialityOptions()
{
    return ['Normal', 'Confidential', 'Anonymous'];
}

function canManageEmployeeVoice()
{
    return isAdmin() || isHR() || (function_exists('isStaffUser') && isStaffUser());
}

function canSubmitEmployeeVoice()
{
    return (int) ($_SESSION['employee_id'] ?? 0) > 0;
}

function ensureEmployeeVoiceTables($conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_ticket (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_no VARCHAR(30) NOT NULL,
            module_type VARCHAR(20) NOT NULL,
            employee_id INT DEFAULT NULL,
            department_id INT DEFAULT NULL,
            location_name VARCHAR(150) DEFAULT NULL,
            category VARCHAR(100) NOT NULL DEFAULT '',
            subject VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            confidentiality VARCHAR(30) NOT NULL DEFAULT 'Normal',
            priority VARCHAR(20) NOT NULL DEFAULT 'Medium',
            status VARCHAR(50) NOT NULL DEFAULT 'Submitted',
            submission_mode VARCHAR(30) NOT NULL DEFAULT 'Portal',
            assigned_to INT DEFAULT NULL,
            admin_notes TEXT,
            submitted_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            closed_at DATETIME DEFAULT NULL,
            created_by INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_ev_ticket_no (ticket_no),
            KEY idx_ev_module (module_type),
            KEY idx_ev_emp (employee_id),
            KEY idx_ev_status (status),
            KEY idx_ev_submitted (submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_grievance_detail (
            ticket_id INT PRIMARY KEY,
            complaint_against VARCHAR(50) DEFAULT NULL,
            incident_date DATE DEFAULT NULL,
            incident_location VARCHAR(200) DEFAULT NULL,
            confidential_handling TINYINT(1) NOT NULL DEFAULT 0,
            preferred_contact VARCHAR(50) DEFAULT NULL,
            immediate_assistance TINYINT(1) NOT NULL DEFAULT 0,
            requested_resolution TEXT,
            CONSTRAINT fk_ev_grievance_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_suggestion_detail (
            ticket_id INT PRIMARY KEY,
            current_problem TEXT,
            proposed_improvement TEXT,
            expected_benefit VARCHAR(50) DEFAULT NULL,
            estimated_saving VARCHAR(100) DEFAULT NULL,
            estimated_impl_cost VARCHAR(100) DEFAULT NULL,
            help_implement TINYINT(1) NOT NULL DEFAULT 0,
            CONSTRAINT fk_ev_suggestion_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_safety_detail (
            ticket_id INT PRIMARY KEY,
            hazard_type VARCHAR(100) DEFAULT NULL,
            exact_location VARCHAR(200) DEFAULT NULL,
            equipment_ref VARCHAR(150) DEFAULT NULL,
            risk_severity VARCHAR(20) NOT NULL DEFAULT 'Medium',
            injury_near_miss TINYINT(1) NOT NULL DEFAULT 0,
            immediate_danger TINYINT(1) NOT NULL DEFAULT 0,
            immediate_action_taken TEXT,
            CONSTRAINT fk_ev_safety_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_mime VARCHAR(100) DEFAULT NULL,
            file_size INT NOT NULL DEFAULT 0,
            uploaded_by INT DEFAULT NULL,
            uploaded_at DATETIME NOT NULL,
            KEY idx_ev_att_ticket (ticket_id),
            CONSTRAINT fk_ev_att_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            from_status VARCHAR(50) DEFAULT NULL,
            to_status VARCHAR(50) NOT NULL,
            changed_by INT DEFAULT NULL,
            reason TEXT,
            changed_at DATETIME NOT NULL,
            KEY idx_ev_hist_ticket (ticket_id),
            CONSTRAINT fk_ev_hist_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            comment_text TEXT NOT NULL,
            is_internal TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_ev_cmt_ticket (ticket_id),
            CONSTRAINT fk_ev_cmt_ticket FOREIGN KEY (ticket_id) REFERENCES ev_ticket(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS ev_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            employee_id INT DEFAULT NULL,
            event_type VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            INDEX idx_evn_user_unread (user_id, read_at),
            INDEX idx_evn_ticket (ticket_id),
            INDEX idx_evn_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($close) {
        $conn->close();
    }
}

function evSqlStr($conn, $v)
{
    if ($v === null) {
        return 'NULL';
    }
    return "'" . $conn->real_escape_string((string) $v) . "'";
}

function evNextTicketNo($moduleType, $conn)
{
    $types = evModuleTypes();
    $moduleType = strtoupper(trim((string) $moduleType));
    $prefix = $types[$moduleType]['prefix'] ?? 'EVX';
    $fyStart = ((int) date('n') >= 4) ? (int) date('Y') : ((int) date('Y') - 1);
    $fy = substr((string) $fyStart, -2) . '-' . substr((string) ($fyStart + 1), -2);
    $like = $prefix . '/' . $fy . '/%';
    $res = $conn->query(
        "SELECT ticket_no FROM ev_ticket
         WHERE ticket_no LIKE '" . $conn->real_escape_string($like) . "'
         ORDER BY id DESC LIMIT 1"
    );
    $n = 1;
    if ($res && ($r = $res->fetch_assoc())) {
        if (preg_match('/\/(\d+)$/', (string) $r['ticket_no'], $m)) {
            $n = ((int) $m[1]) + 1;
        }
    }
    return $prefix . '/' . $fy . '/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function evCreateTicket(array $data, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);

    $moduleType = strtoupper(trim((string) ($data['module_type'] ?? '')));
    $types = evModuleTypes();
    if (!isset($types[$moduleType])) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Invalid module type'];
    }

    $subject = trim((string) ($data['subject'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    if ($subject === '' || $description === '' || $category === '') {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Subject, description and category are required'];
    }

    $employeeId = (int) ($data['employee_id'] ?? 0);
    $departmentId = (int) ($data['department_id'] ?? 0);
    $confidentiality = trim((string) ($data['confidentiality'] ?? 'Normal'));
    if (!in_array($confidentiality, evConfidentialityOptions(), true)) {
        $confidentiality = 'Normal';
    }
    // Anonymous: keep employee_id in DB for audit but hide in employee-facing lists for others
    $storeEmpId = $employeeId > 0 ? $employeeId : null;
    if ($confidentiality === 'Anonymous') {
        // Still store for admin audit; UI hides identity from non-managers
    }

    $priority = 'Medium';
    if ($moduleType === 'SAFETY') {
        $sev = strtolower(trim((string) ($data['risk_severity'] ?? 'medium')));
        $immediate = !empty($data['immediate_danger']);
        if ($immediate || $sev === 'critical') {
            $priority = 'Critical';
        } elseif ($sev === 'high') {
            $priority = 'High';
        } elseif ($sev === 'low') {
            $priority = 'Low';
        }
    } elseif (!empty($data['immediate_assistance'])) {
        $priority = 'High';
    }

    $status = evDefaultStatus($moduleType);
    $ticketNo = evNextTicketNo($moduleType, $conn);
    $location = trim((string) ($data['location_name'] ?? ''));
    $createdBy = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    $mode = trim((string) ($data['submission_mode'] ?? 'Portal'));

    $empSql = $storeEmpId ? (string) (int) $storeEmpId : 'NULL';
    $deptSql = $departmentId > 0 ? (string) $departmentId : 'NULL';
    $bySql = $createdBy > 0 ? (string) $createdBy : 'NULL';

    $ok = $conn->query(
        'INSERT INTO ev_ticket (
            ticket_no, module_type, employee_id, department_id, location_name,
            category, subject, description, confidentiality, priority, status,
            submission_mode, submitted_at, created_by
         ) VALUES (
            ' . evSqlStr($conn, $ticketNo) . ',
            ' . evSqlStr($conn, $moduleType) . ',
            ' . $empSql . ',
            ' . $deptSql . ',
            ' . evSqlStr($conn, $location !== '' ? $location : null) . ',
            ' . evSqlStr($conn, $category) . ',
            ' . evSqlStr($conn, $subject) . ',
            ' . evSqlStr($conn, $description) . ',
            ' . evSqlStr($conn, $confidentiality) . ',
            ' . evSqlStr($conn, $priority) . ',
            ' . evSqlStr($conn, $status) . ',
            ' . evSqlStr($conn, $mode) . ',
            NOW(),
            ' . $bySql . '
         )'
    );
    if (!$ok) {
        $err = $conn->error;
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Could not create ticket: ' . $err];
    }
    $ticketId = (int) $conn->insert_id;

    if ($moduleType === 'GRIEVANCE') {
        $conn->query(
            'INSERT INTO ev_grievance_detail (
                ticket_id, complaint_against, incident_date, incident_location,
                confidential_handling, preferred_contact, immediate_assistance, requested_resolution
             ) VALUES (
                ' . $ticketId . ',
                ' . evSqlStr($conn, $data['complaint_against'] ?? null) . ',
                ' . (!empty($data['incident_date']) ? evSqlStr($conn, $data['incident_date']) : 'NULL') . ',
                ' . evSqlStr($conn, $data['incident_location'] ?? null) . ',
                ' . (!empty($data['confidential_handling']) ? '1' : '0') . ',
                ' . evSqlStr($conn, $data['preferred_contact'] ?? null) . ',
                ' . (!empty($data['immediate_assistance']) ? '1' : '0') . ',
                ' . evSqlStr($conn, $data['requested_resolution'] ?? null) . '
             )'
        );
    } elseif ($moduleType === 'SUGGESTION') {
        $conn->query(
            'INSERT INTO ev_suggestion_detail (
                ticket_id, current_problem, proposed_improvement, expected_benefit,
                estimated_saving, estimated_impl_cost, help_implement
             ) VALUES (
                ' . $ticketId . ',
                ' . evSqlStr($conn, $data['current_problem'] ?? null) . ',
                ' . evSqlStr($conn, $data['proposed_improvement'] ?? null) . ',
                ' . evSqlStr($conn, $data['expected_benefit'] ?? null) . ',
                ' . evSqlStr($conn, $data['estimated_saving'] ?? null) . ',
                ' . evSqlStr($conn, $data['estimated_impl_cost'] ?? null) . ',
                ' . (!empty($data['help_implement']) ? '1' : '0') . '
             )'
        );
    } else {
        $conn->query(
            'INSERT INTO ev_safety_detail (
                ticket_id, hazard_type, exact_location, equipment_ref, risk_severity,
                injury_near_miss, immediate_danger, immediate_action_taken
             ) VALUES (
                ' . $ticketId . ',
                ' . evSqlStr($conn, $data['hazard_type'] ?? null) . ',
                ' . evSqlStr($conn, $data['exact_location'] ?? null) . ',
                ' . evSqlStr($conn, $data['equipment_ref'] ?? null) . ',
                ' . evSqlStr($conn, $data['risk_severity'] ?? 'Medium') . ',
                ' . (!empty($data['injury_near_miss']) ? '1' : '0') . ',
                ' . (!empty($data['immediate_danger']) ? '1' : '0') . ',
                ' . evSqlStr($conn, $data['immediate_action_taken'] ?? null) . '
             )'
        );
    }

    $conn->query(
        'INSERT INTO ev_status_history (ticket_id, from_status, to_status, changed_by, reason, changed_at)
         VALUES (' . $ticketId . ', NULL, ' . evSqlStr($conn, $status) . ', ' . $bySql . ',
         ' . evSqlStr($conn, 'Ticket created') . ', NOW())'
    );

    evNotifyStaffOnNewTicket($ticketId, $conn);

    if ($close) {
        $conn->close();
    }
    return [
        'ok' => true,
        'ticket_id' => $ticketId,
        'ticket_no' => $ticketNo,
        'priority' => $priority,
        'status' => $status,
    ];
}

/**
 * Employee update of own early-stage ticket
 */
function evUpdateTicket($ticketId, array $data, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticketId = (int) $ticketId;
    $ticket = evGetTicket($ticketId, $conn);
    if (!$ticket) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Ticket not found'];
    }
    if (!evEmployeeCanModifyTicket($ticket)) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'This ticket can no longer be edited'];
    }

    $moduleType = (string) $ticket['module_type'];
    $subject = trim((string) ($data['subject'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    if ($subject === '' || $description === '' || $category === '') {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Subject, description and category are required'];
    }

    $confidentiality = trim((string) ($data['confidentiality'] ?? $ticket['confidentiality']));
    if (!in_array($confidentiality, evConfidentialityOptions(), true)) {
        $confidentiality = (string) $ticket['confidentiality'];
    }
    $location = trim((string) ($data['location_name'] ?? ''));

    $priority = (string) ($ticket['priority'] ?? 'Medium');
    if ($moduleType === 'SAFETY') {
        $sev = strtolower(trim((string) ($data['risk_severity'] ?? 'medium')));
        $immediate = !empty($data['immediate_danger']);
        if ($immediate || $sev === 'critical') {
            $priority = 'Critical';
        } elseif ($sev === 'high') {
            $priority = 'High';
        } elseif ($sev === 'low') {
            $priority = 'Low';
        } else {
            $priority = 'Medium';
        }
    } elseif (!empty($data['immediate_assistance'])) {
        $priority = 'High';
    } else {
        $priority = 'Medium';
    }

    $ok = $conn->query(
        'UPDATE ev_ticket SET
            location_name = ' . evSqlStr($conn, $location !== '' ? $location : null) . ',
            category = ' . evSqlStr($conn, $category) . ',
            subject = ' . evSqlStr($conn, $subject) . ',
            description = ' . evSqlStr($conn, $description) . ',
            confidentiality = ' . evSqlStr($conn, $confidentiality) . ',
            priority = ' . evSqlStr($conn, $priority) . '
         WHERE id = ' . $ticketId . ' AND is_deleted = 0'
    );
    if (!$ok) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Could not update ticket'];
    }

    if ($moduleType === 'GRIEVANCE') {
        $conn->query(
            'UPDATE ev_grievance_detail SET
                complaint_against = ' . evSqlStr($conn, $data['complaint_against'] ?? null) . ',
                incident_date = ' . (!empty($data['incident_date']) ? evSqlStr($conn, $data['incident_date']) : 'NULL') . ',
                incident_location = ' . evSqlStr($conn, $data['incident_location'] ?? null) . ',
                confidential_handling = ' . (!empty($data['confidential_handling']) ? '1' : '0') . ',
                preferred_contact = ' . evSqlStr($conn, $data['preferred_contact'] ?? null) . ',
                immediate_assistance = ' . (!empty($data['immediate_assistance']) ? '1' : '0') . ',
                requested_resolution = ' . evSqlStr($conn, $data['requested_resolution'] ?? null) . '
             WHERE ticket_id = ' . $ticketId
        );
    } elseif ($moduleType === 'SUGGESTION') {
        $conn->query(
            'UPDATE ev_suggestion_detail SET
                current_problem = ' . evSqlStr($conn, $data['current_problem'] ?? null) . ',
                proposed_improvement = ' . evSqlStr($conn, $data['proposed_improvement'] ?? null) . ',
                expected_benefit = ' . evSqlStr($conn, $data['expected_benefit'] ?? null) . ',
                estimated_saving = ' . evSqlStr($conn, $data['estimated_saving'] ?? null) . ',
                estimated_impl_cost = ' . evSqlStr($conn, $data['estimated_impl_cost'] ?? null) . ',
                help_implement = ' . (!empty($data['help_implement']) ? '1' : '0') . '
             WHERE ticket_id = ' . $ticketId
        );
    } else {
        $conn->query(
            'UPDATE ev_safety_detail SET
                hazard_type = ' . evSqlStr($conn, $data['hazard_type'] ?? null) . ',
                exact_location = ' . evSqlStr($conn, $data['exact_location'] ?? null) . ',
                equipment_ref = ' . evSqlStr($conn, $data['equipment_ref'] ?? null) . ',
                risk_severity = ' . evSqlStr($conn, $data['risk_severity'] ?? 'Medium') . ',
                injury_near_miss = ' . (!empty($data['injury_near_miss']) ? '1' : '0') . ',
                immediate_danger = ' . (!empty($data['immediate_danger']) ? '1' : '0') . ',
                immediate_action_taken = ' . evSqlStr($conn, $data['immediate_action_taken'] ?? null) . '
             WHERE ticket_id = ' . $ticketId
        );
    }

    if ($close) {
        $conn->close();
    }
    return ['ok' => true, 'ticket_id' => $ticketId];
}

/**
 * Employee withdraw (soft close as Withdrawn)
 */
function evWithdrawTicket($ticketId, $employeeId, $changedBy = 0, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticket = evGetTicket((int) $ticketId, $conn);
    if (!$ticket || (int) ($ticket['employee_id'] ?? 0) !== (int) $employeeId) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Ticket not found'];
    }
    if (!evEmployeeCanModifyTicket($ticket)) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'This ticket can no longer be withdrawn'];
    }
    $res = evUpdateStatus((int) $ticketId, 'Withdrawn', 'Withdrawn by employee', (int) $changedBy, $conn);
    if (!empty($res['ok'])) {
        // Notify HR that employee withdrew
        $types = evModuleTypes();
        $label = $types[$ticket['module_type']]['short'] ?? 'Ticket';
        $title = $label . ' withdrawn · ' . $ticket['ticket_no'];
        $body = (string) ($ticket['subject'] ?? '');
        foreach (evStaffNotifyUserIds($conn) as $uid) {
            evInsertNotification((int) $ticketId, $uid, (int) $employeeId, 'Withdrawn', $title, $body, $conn);
        }
    }
    if ($close) {
        $conn->close();
    }
    return $res;
}

function evSaveAttachment($ticketId, array $file, $uploadedBy = 0, $conn = null)
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No file'];
    }
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Invalid file type'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'File must be under 5 MB'];
    }

    $dir = __DIR__ . '/../assets/uploads/employee_voice';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $safe = 'ev_' . (int) $ticketId . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
    $dest = $dir . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Upload failed'];
    }
    $rel = 'assets/uploads/employee_voice/' . $safe;
    $mime = (string) ($file['type'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $name = basename((string) $file['name']);
    $by = (int) $uploadedBy;
    $bySql = $by > 0 ? (string) $by : 'NULL';

    $conn->query(
        'INSERT INTO ev_attachments (ticket_id, file_name, file_path, file_mime, file_size, uploaded_by, uploaded_at)
         VALUES (
            ' . (int) $ticketId . ',
            ' . evSqlStr($conn, $name) . ',
            ' . evSqlStr($conn, $rel) . ',
            ' . evSqlStr($conn, $mime) . ',
            ' . $size . ',
            ' . $bySql . ',
            NOW()
         )'
    );
    $attId = (int) $conn->insert_id;
    if ($close) {
        $conn->close();
    }
    return ['ok' => true, 'id' => $attId, 'path' => $rel];
}

function evGetTicket($ticketId, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticketId = (int) $ticketId;
    $sql = "SELECT t.*,
                   e.employee_code, e.employee_name, e.mobile_number,
                   d.department_name
            FROM ev_ticket t
            LEFT JOIN employees e ON e.id = t.employee_id
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE t.id = {$ticketId} AND t.is_deleted = 0
            LIMIT 1";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        if ($close) {
            $conn->close();
        }
        return null;
    }
    $mt = (string) $row['module_type'];
    $detail = null;
    if ($mt === 'GRIEVANCE') {
        $dr = $conn->query('SELECT * FROM ev_grievance_detail WHERE ticket_id = ' . $ticketId . ' LIMIT 1');
        $detail = $dr ? $dr->fetch_assoc() : null;
    } elseif ($mt === 'SUGGESTION') {
        $dr = $conn->query('SELECT * FROM ev_suggestion_detail WHERE ticket_id = ' . $ticketId . ' LIMIT 1');
        $detail = $dr ? $dr->fetch_assoc() : null;
    } else {
        $dr = $conn->query('SELECT * FROM ev_safety_detail WHERE ticket_id = ' . $ticketId . ' LIMIT 1');
        $detail = $dr ? $dr->fetch_assoc() : null;
    }
    $row['detail'] = $detail ?: [];

    $atts = [];
    $ar = $conn->query('SELECT * FROM ev_attachments WHERE ticket_id = ' . $ticketId . ' ORDER BY id ASC');
    if ($ar) {
        while ($a = $ar->fetch_assoc()) {
            $atts[] = $a;
        }
    }
    $row['attachments'] = $atts;

    $hist = [];
    $hr = $conn->query('SELECT * FROM ev_status_history WHERE ticket_id = ' . $ticketId . ' ORDER BY id DESC');
    if ($hr) {
        while ($h = $hr->fetch_assoc()) {
            $hist[] = $h;
        }
    }
    $row['status_history'] = $hist;

    $comments = [];
    $cr = $conn->query('SELECT * FROM ev_comments WHERE ticket_id = ' . $ticketId . ' ORDER BY id ASC');
    if ($cr) {
        while ($c = $cr->fetch_assoc()) {
            $comments[] = $c;
        }
    }
    $row['comments'] = $comments;

    if ($close) {
        $conn->close();
    }
    return $row;
}

function evListTickets(array $filters = [], $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);

    $where = ['t.is_deleted = 0'];
    if (!empty($filters['employee_id'])) {
        $where[] = 't.employee_id = ' . (int) $filters['employee_id'];
    }
    if (!empty($filters['module_type'])) {
        $where[] = 't.module_type = ' . evSqlStr($conn, strtoupper((string) $filters['module_type']));
    }
    if (!empty($filters['status'])) {
        $where[] = 't.status = ' . evSqlStr($conn, $filters['status']);
    }
    if (!empty($filters['priority'])) {
        $where[] = 't.priority = ' . evSqlStr($conn, $filters['priority']);
    }
    if (!empty($filters['q'])) {
        $q = $conn->real_escape_string('%' . trim((string) $filters['q']) . '%');
        $where[] = "(t.ticket_no LIKE '{$q}' OR t.subject LIKE '{$q}' OR e.employee_name LIKE '{$q}' OR e.employee_code LIKE '{$q}')";
    }

    $sql = 'SELECT t.*, e.employee_code, e.employee_name, d.department_name
            FROM ev_ticket t
            LEFT JOIN employees e ON e.id = t.employee_id
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY
                CASE WHEN t.priority = \'Critical\' THEN 0 WHEN t.priority = \'High\' THEN 1 ELSE 2 END,
                t.submitted_at DESC
            LIMIT 500';
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    if (!empty($filters['with_hr_reply']) && $rows) {
        evAttachLatestHrReplies($rows, $conn);
    }
    if ($close) {
        $conn->close();
    }
    return $rows;
}

/**
 * Attach latest employee-visible HR comment to each ticket row.
 * @param array<int,array> $rows
 */
function evAttachLatestHrReplies(array &$rows, $conn)
{
    $ids = [];
    foreach ($rows as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    if (!$ids) {
        return;
    }
    $idList = implode(',', array_map('intval', $ids));
    $map = [];
    $res = $conn->query(
        "SELECT c.ticket_id, c.comment_text, c.created_at
         FROM ev_comments c
         INNER JOIN (
             SELECT ticket_id, MAX(id) AS max_id
             FROM ev_comments
             WHERE is_internal = 0 AND ticket_id IN ({$idList})
             GROUP BY ticket_id
         ) x ON x.max_id = c.id"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['ticket_id']] = $row;
        }
    }
    foreach ($rows as &$r) {
        $tid = (int) ($r['id'] ?? 0);
        $reply = $map[$tid] ?? null;
        $r['hr_reply'] = $reply ? (string) ($reply['comment_text'] ?? '') : '';
        $r['hr_reply_at'] = $reply ? (string) ($reply['created_at'] ?? '') : '';
    }
    unset($r);
}

function evUpdateStatus($ticketId, $newStatus, $reason = '', $changedBy = 0, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticket = evGetTicket($ticketId, $conn);
    if (!$ticket) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Ticket not found'];
    }
    $allowed = evStatusesByModule($ticket['module_type']);
    $newStatus = trim((string) $newStatus);
    if (!in_array($newStatus, $allowed, true)) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Invalid status'];
    }
    $old = (string) $ticket['status'];
    $extra = '';
    if (in_array($newStatus, ['Resolved', 'Verified'], true)) {
        $extra .= ', resolved_at = IFNULL(resolved_at, NOW())';
    }
    if ($newStatus === 'Closed') {
        $extra .= ', closed_at = IFNULL(closed_at, NOW())';
    }
    $conn->query(
        'UPDATE ev_ticket SET status = ' . evSqlStr($conn, $newStatus) . $extra .
        ' WHERE id = ' . (int) $ticketId
    );
    $bySql = $changedBy > 0 ? (string) (int) $changedBy : 'NULL';
    $conn->query(
        'INSERT INTO ev_status_history (ticket_id, from_status, to_status, changed_by, reason, changed_at)
         VALUES (' . (int) $ticketId . ', ' . evSqlStr($conn, $old) . ', ' . evSqlStr($conn, $newStatus) . ',
         ' . $bySql . ', ' . evSqlStr($conn, $reason) . ', NOW())'
    );
    if ($old !== $newStatus) {
        $body = $reason !== '' ? $reason : ('Status: ' . $old . ' → ' . $newStatus);
        evNotifyEmployeeOnTicketUpdate((int) $ticketId, 'Status', $body, $conn);
    }
    if ($close) {
        $conn->close();
    }
    return ['ok' => true];
}

function evSaveAdminNotes($ticketId, $notes, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $conn->query(
        'UPDATE ev_ticket SET admin_notes = ' . evSqlStr($conn, $notes) .
        ' WHERE id = ' . (int) $ticketId
    );
    if ($close) {
        $conn->close();
    }
    return ['ok' => true];
}

function evDashboardCounts($conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $out = [
        'total' => 0,
        'open' => 0,
        'critical' => 0,
        'by_module' => ['GRIEVANCE' => 0, 'SUGGESTION' => 0, 'SAFETY' => 0],
    ];
    $res = $conn->query(
        "SELECT module_type, COUNT(*) AS c FROM ev_ticket WHERE is_deleted = 0 GROUP BY module_type"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $mt = (string) $r['module_type'];
            $c = (int) $r['c'];
            $out['by_module'][$mt] = $c;
            $out['total'] += $c;
        }
    }
    $r2 = $conn->query(
        "SELECT COUNT(*) AS c FROM ev_ticket
         WHERE is_deleted = 0 AND status NOT IN ('Closed','Rejected','Withdrawn','Verified','Resolved')"
    );
    if ($r2 && ($x = $r2->fetch_assoc())) {
        $out['open'] = (int) $x['c'];
    }
    $r3 = $conn->query(
        "SELECT COUNT(*) AS c FROM ev_ticket
         WHERE is_deleted = 0 AND (priority = 'Critical' OR priority = 'High')
           AND status NOT IN ('Closed','Verified')"
    );
    if ($r3 && ($x = $r3->fetch_assoc())) {
        $out['critical'] = (int) $x['c'];
    }
    if ($close) {
        $conn->close();
    }
    return $out;
}

function evStatusBadgeStyle($status)
{
    $s = strtolower((string) $status);
    if (strpos($s, 'withdraw') !== false) {
        return 'background:#f1f5f9;color:#64748b;';
    }
    if (strpos($s, 'closed') !== false || strpos($s, 'verified') !== false || strpos($s, 'resolved') !== false) {
        return 'background:#dcfce7;color:#15803d;';
    }
    if (strpos($s, 'reject') !== false) {
        return 'background:#fee2e2;color:#b91c1c;';
    }
    if (strpos($s, 'progress') !== false || strpos($s, 'investigation') !== false || strpos($s, 'evaluation') !== false) {
        return 'background:#dbeafe;color:#1d4ed8;';
    }
    return 'background:#fef3c7;color:#b45309;';
}

function evPriorityBadgeStyle($priority)
{
    $p = strtolower((string) $priority);
    if ($p === 'critical') {
        return 'background:#7f1d1d;color:#fff;';
    }
    if ($p === 'high') {
        return 'background:#fee2e2;color:#b91c1c;';
    }
    if ($p === 'low') {
        return 'background:#f1f5f9;color:#64748b;';
    }
    return 'background:#e0e7ff;color:#3730a3;';
}

/**
 * Active Admin/HR user IDs for Employee Voice alerts
 * @return int[]
 */
function evStaffNotifyUserIds($conn)
{
    $ids = [];
    $res = $conn->query(
        "SELECT id FROM users
         WHERE status = 1 AND role IN ('admin', 'hr')
         ORDER BY id ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
    }
    return $ids;
}

/**
 * Portal login user linked to an employee
 */
function evEmployeePortalUserId($employeeId, $conn)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return 0;
    }
    $st = $conn->prepare(
        "SELECT id FROM users
         WHERE employee_id = ? AND role = 'employee' AND status = 1
         ORDER BY id DESC
         LIMIT 1"
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int) $row['id'] : 0;
}

function evInsertNotification($ticketId, $userId, $employeeId, $eventType, $title, $body, $conn)
{
    $ticketId = (int) $ticketId;
    $userId = (int) $userId;
    $employeeId = (int) $employeeId;
    if ($ticketId <= 0 || $userId <= 0) {
        return false;
    }
    $eventType = substr(trim((string) $eventType), 0, 30);
    $title = substr(trim((string) $title), 0, 255);
    $body = substr(trim((string) $body), 0, 500);
    $empSql = $employeeId > 0 ? (string) $employeeId : 'NULL';
    return (bool) $conn->query(
        'INSERT INTO ev_notifications
            (ticket_id, user_id, employee_id, event_type, title, body, created_at)
         VALUES ('
        . $ticketId . ', ' . $userId . ', ' . $empSql . ', '
        . evSqlStr($conn, $eventType) . ', '
        . evSqlStr($conn, $title) . ', '
        . evSqlStr($conn, $body !== '' ? $body : null) . ', NOW())'
    );
}

/**
 * New ticket → notify all Admin/HR users
 */
function evNotifyStaffOnNewTicket($ticketId, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticket = evGetTicket($ticketId, $conn);
    if (!$ticket) {
        if ($close) {
            $conn->close();
        }
        return false;
    }

    $types = evModuleTypes();
    $module = (string) ($ticket['module_type'] ?? '');
    $label = $types[$module]['short'] ?? $module;
    $ticketNo = (string) ($ticket['ticket_no'] ?? '');
    $subject = (string) ($ticket['subject'] ?? '');
    $priority = (string) ($ticket['priority'] ?? 'Medium');
    $title = $label . ' raised · ' . $ticketNo;
    $body = $subject;
    if ($priority === 'Critical' || $priority === 'High') {
        $body = $priority . ' · ' . $body;
    }
    $empId = (int) ($ticket['employee_id'] ?? 0);
    $excludeUser = (int) ($ticket['created_by'] ?? 0);

    $ok = false;
    foreach (evStaffNotifyUserIds($conn) as $uid) {
        if ($excludeUser > 0 && $uid === $excludeUser) {
            continue;
        }
        if (evInsertNotification((int) $ticketId, $uid, $empId, 'New', $title, $body, $conn)) {
            $ok = true;
        }
    }
    if ($close) {
        $conn->close();
    }
    return $ok;
}

/**
 * Status change / HR reply → notify employee portal user
 */
function evNotifyEmployeeOnTicketUpdate($ticketId, $eventType, $body = '', $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticket = evGetTicket($ticketId, $conn);
    if (!$ticket) {
        if ($close) {
            $conn->close();
        }
        return false;
    }
    $empId = (int) ($ticket['employee_id'] ?? 0);
    $userId = evEmployeePortalUserId($empId, $conn);
    if ($userId <= 0) {
        if ($close) {
            $conn->close();
        }
        return false;
    }

    $types = evModuleTypes();
    $module = (string) ($ticket['module_type'] ?? '');
    $label = $types[$module]['short'] ?? $module;
    $ticketNo = (string) ($ticket['ticket_no'] ?? '');
    $eventType = trim((string) $eventType);
    if ($eventType === '') {
        $eventType = 'Update';
    }
    if (strcasecmp($eventType, 'Reply') === 0) {
        $title = 'HR reply · ' . $ticketNo;
    } elseif (strcasecmp($eventType, 'Status') === 0) {
        $title = 'Status update · ' . $ticketNo;
    } else {
        $title = $label . ' update · ' . $ticketNo;
    }
    $body = trim((string) $body);
    if ($body === '') {
        $body = (string) ($ticket['subject'] ?? '');
    }

    $ok = evInsertNotification((int) $ticketId, $userId, $empId, $eventType, $title, $body, $conn);
    if ($close) {
        $conn->close();
    }
    return $ok;
}

/**
 * Add admin comment; notify employee when visible
 */
function evAddComment($ticketId, $text, $visibleToEmployee = false, $createdBy = 0, $conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeVoiceTables($conn);
    $ticketId = (int) $ticketId;
    $text = trim((string) $text);
    if ($ticketId <= 0 || $text === '') {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Comment required'];
    }
    $createdBy = (int) $createdBy;
    $isInternal = $visibleToEmployee ? 0 : 1;
    $bySql = $createdBy > 0 ? (string) $createdBy : 'NULL';
    $ok = $conn->query(
        'INSERT INTO ev_comments (ticket_id, comment_text, is_internal, created_by, created_at) VALUES ('
        . $ticketId . ', ' . evSqlStr($conn, $text) . ', ' . $isInternal . ', '
        . $bySql . ', NOW())'
    );
    if (!$ok) {
        if ($close) {
            $conn->close();
        }
        return ['ok' => false, 'error' => 'Could not save comment'];
    }
    if ($visibleToEmployee) {
        $snippet = (strlen($text) > 120) ? (substr($text, 0, 117) . '...') : $text;
        evNotifyEmployeeOnTicketUpdate($ticketId, 'Reply', $snippet, $conn);
    }
    if ($close) {
        $conn->close();
    }
    return ['ok' => true];
}

/**
 * @return array<int,array>
 */
function fetchUnreadEvNotifications($userId, $limit = 12)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [];
    }
    $conn = getDBConnection();
    ensureEmployeeVoiceTables($conn);
    $limit = max(1, min(30, (int) $limit));
    $st = $conn->prepare(
        "SELECT id, ticket_id, event_type, title, body, created_at,
                DATE(created_at) AS notify_date
         FROM ev_notifications
         WHERE user_id = ?
           AND read_at IS NULL
         ORDER BY created_at DESC, id DESC
         LIMIT " . $limit
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    $conn->close();
    return $rows;
}

function markEvNotificationRead($id, $userId)
{
    $id = (int) $id;
    $userId = (int) $userId;
    if ($id <= 0 || $userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureEmployeeVoiceTables($conn);
    $st = $conn->prepare(
        "UPDATE ev_notifications
         SET read_at = NOW()
         WHERE id = ? AND user_id = ? AND read_at IS NULL"
    );
    $st->bind_param('ii', $id, $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

function markAllEvNotificationsRead($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureEmployeeVoiceTables($conn);
    $st = $conn->prepare(
        "UPDATE ev_notifications
         SET read_at = NOW()
         WHERE user_id = ? AND read_at IS NULL"
    );
    $st->bind_param('i', $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

function getEvNotificationById($id, $userId = 0)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    $conn = getDBConnection();
    ensureEmployeeVoiceTables($conn);
    if ($userId > 0) {
        $st = $conn->prepare(
            'SELECT * FROM ev_notifications WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $uid = (int) $userId;
        $st->bind_param('ii', $id, $uid);
    } else {
        $st = $conn->prepare('SELECT * FROM ev_notifications WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
    }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $conn->close();
    return $row ?: null;
}
