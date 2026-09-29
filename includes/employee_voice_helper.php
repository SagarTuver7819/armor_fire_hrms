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
            'Feedback Pending', 'Closed', 'Reopened',
        ],
        'SUGGESTION' => [
            'Submitted', 'Screening', 'Under Evaluation', 'Approved',
            'Rejected', 'On Hold', 'Implementation in Progress',
            'Impact Measurement', 'Recognition Pending', 'Closed',
        ],
        'SAFETY' => [
            'Reported', 'Risk Triage', 'Containment in Progress',
            'Investigation', 'Corrective Action Pending',
            'Verification Pending', 'Verified', 'Closed', 'Reopened',
        ],
    ];
    return $map[$moduleType] ?? ['Submitted', 'Closed'];
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
    if ($close) {
        $conn->close();
    }
    return $rows;
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
         WHERE is_deleted = 0 AND status NOT IN ('Closed','Rejected')"
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
