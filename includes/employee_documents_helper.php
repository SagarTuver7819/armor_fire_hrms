<?php
/**
 * Employee Related Documents — optional checklist (PDF / passport photo)
 * Employee upload + view · HR/Admin view + edit · staff notifications
 */

require_once __DIR__ . '/employee_helper.php';

/**
 * @return array<string, array{label:string, accept:string, exts:string[], icon:string}>
 */
function employeeRelatedDocumentTypes()
{
    return [
        'ssc_marksheet' => [
            'label' => 'SSC Marksheet (10th)',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'hsc_marksheet' => [
            'label' => 'HSC Marksheet (12th)',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'diploma' => [
            'label' => 'Diploma',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'degree_marksheet' => [
            'label' => 'Degree Marksheet',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'bachelor_certificate' => [
            'label' => 'Bachelor Degree Certificate',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'master_marksheet' => [
            'label' => 'Master Degree Marksheet',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'master_certificate' => [
            'label' => 'Master Degree Certificate',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'experience_letter' => [
            'label' => 'Experience Letter',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-file-pdf',
        ],
        'aadhar_card' => [
            'label' => 'Aadhar Card',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-id-card',
        ],
        'pan_card' => [
            'label' => 'PAN Card',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-id-card',
        ],
        'bank_passbook' => [
            'label' => 'Bank Passbook / Cheque',
            'accept' => '.pdf,application/pdf',
            'exts' => ['pdf'],
            'icon' => 'fa-building-columns',
        ],
        'passport_photo' => [
            'label' => 'Passport Size Photo',
            'accept' => '.jpg,.jpeg,.png,image/jpeg,image/png',
            'exts' => ['jpg', 'jpeg', 'png'],
            'icon' => 'fa-image',
        ],
    ];
}

function ensureEmployeeRelatedDocumentsTables($conn = null)
{
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_related_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            doc_type VARCHAR(40) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            uploaded_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_emp_rel_doc (employee_id, doc_type),
            INDEX idx_emp_rel_doc_emp (employee_id),
            INDEX idx_emp_rel_doc_type (doc_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS employee_document_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            employee_id INT NOT NULL,
            doc_type VARCHAR(40) NOT NULL,
            event_type VARCHAR(30) NOT NULL DEFAULT 'Uploaded',
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            INDEX idx_edn_user_unread (user_id, read_at),
            INDEX idx_edn_employee (employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    if ($close) {
        $conn->close();
    }
}

function employeeRelatedDocLabel($docType)
{
    $types = employeeRelatedDocumentTypes();
    $docType = trim((string) $docType);
    return $types[$docType]['label'] ?? $docType;
}

/**
 * Map of doc_type => row for an employee
 * @return array<string, array>
 */
function getEmployeeRelatedDocuments($employeeId, $conn = null)
{
    $employeeId = (int) $employeeId;
    if ($employeeId <= 0) {
        return [];
    }
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeRelatedDocumentsTables($conn);

    $out = [];
    $st = $conn->prepare(
        'SELECT id, employee_id, doc_type, file_path, uploaded_by, created_at, updated_at
         FROM employee_related_documents
         WHERE employee_id = ?'
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $out[(string) $row['doc_type']] = $row;
    }
    $st->close();
    if ($close) {
        $conn->close();
    }
    return $out;
}

/**
 * @return array{done:int,pending:int,total:int,pending_labels:string[],done_labels:string[]}
 */
function employeeRelatedDocumentsSummary($employeeId, $conn = null)
{
    $types = employeeRelatedDocumentTypes();
    $docs = getEmployeeRelatedDocuments($employeeId, $conn);
    $pending = [];
    $done = [];
    foreach ($types as $key => $meta) {
        if (!empty($docs[$key]['file_path'])) {
            $done[] = $meta['label'];
        } else {
            $pending[] = $meta['label'];
        }
    }
    return [
        'done' => count($done),
        'pending' => count($pending),
        'total' => count($types),
        'pending_labels' => $pending,
        'done_labels' => $done,
    ];
}

function saveEmployeeRelatedDocumentFile(array $file, $employeeId, $docType)
{
    $types = employeeRelatedDocumentTypes();
    $docType = trim((string) $docType);
    if (!isset($types[$docType])) {
        throw new RuntimeException('Invalid document type.');
    }
    $meta = $types[$docType];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        throw new RuntimeException('Please choose a file to upload.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File could not be uploaded. Please try again.');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $meta['exts'], true)) {
        if ($docType === 'passport_photo') {
            throw new RuntimeException('Passport photo must be JPG or PNG.');
        }
        throw new RuntimeException($meta['label'] . ' must be a PDF file.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('File must be 5MB or smaller.');
    }
    $dir = dirname(__DIR__) . '/assets/uploads/docs';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create document upload folder.');
    }
    $safeType = preg_replace('/[^a-z0-9_]/', '', $docType);
    $name = 'emp_' . (int) $employeeId . '_rel_' . $safeType . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save document.');
    }
    return 'assets/uploads/docs/' . $name;
}

/**
 * Upsert one related document. Returns true when a new/replaced file was saved.
 */
function upsertEmployeeRelatedDocument($employeeId, $docType, $filePath, $uploadedBy = null, $conn = null)
{
    $employeeId = (int) $employeeId;
    $docType = trim((string) $docType);
    $filePath = trim((string) $filePath);
    $uploadedBy = $uploadedBy !== null ? (int) $uploadedBy : 0;
    if ($employeeId <= 0 || $filePath === '' || !isset(employeeRelatedDocumentTypes()[$docType])) {
        return false;
    }

    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeRelatedDocumentsTables($conn);

    $oldPath = '';
    $st = $conn->prepare(
        'SELECT file_path FROM employee_related_documents WHERE employee_id = ? AND doc_type = ? LIMIT 1'
    );
    $st->bind_param('is', $employeeId, $docType);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        $oldPath = (string) ($row['file_path'] ?? '');
        $st = $conn->prepare(
            'UPDATE employee_related_documents
             SET file_path = ?, uploaded_by = ?, updated_at = NOW()
             WHERE employee_id = ? AND doc_type = ?'
        );
        $st->bind_param('siis', $filePath, $uploadedBy, $employeeId, $docType);
        $st->execute();
        $st->close();
    } else {
        $st = $conn->prepare(
            'INSERT INTO employee_related_documents
                (employee_id, doc_type, file_path, uploaded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $st->bind_param('issi', $employeeId, $docType, $filePath, $uploadedBy);
        $st->execute();
        $st->close();
    }

    if ($oldPath !== '' && $oldPath !== $filePath) {
        deleteEmployeeDocument($oldPath);
    }

    if ($close) {
        $conn->close();
    }
    return true;
}

function empDocNotifyStaffUserIds($conn)
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
 * Notify Admin/HR when a related document is uploaded/updated.
 */
function notifyStaffEmployeeDocumentUploaded($employeeId, $docType, $excludeUserId = 0, $conn = null)
{
    $employeeId = (int) $employeeId;
    $docType = trim((string) $docType);
    $excludeUserId = (int) $excludeUserId;
    if ($employeeId <= 0 || !isset(employeeRelatedDocumentTypes()[$docType])) {
        return false;
    }

    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeRelatedDocumentsTables($conn);

    $emp = null;
    $st = $conn->prepare(
        'SELECT employee_code, employee_name FROM employees WHERE id = ? LIMIT 1'
    );
    $st->bind_param('i', $employeeId);
    $st->execute();
    $emp = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$emp) {
        if ($close) {
            $conn->close();
        }
        return false;
    }

    $code = trim((string) ($emp['employee_code'] ?? ''));
    $name = trim((string) ($emp['employee_name'] ?? ''));
    $label = employeeRelatedDocLabel($docType);
    $who = trim(($code !== '' ? $code . ' · ' : '') . $name);
    $title = 'Document uploaded · ' . $label;
    $body = $who !== '' ? $who : ('Employee #' . $employeeId);
    $summary = employeeRelatedDocumentsSummary($employeeId, $conn);
    if ($summary['pending'] > 0) {
        $body .= ' · Pending: ' . $summary['pending'] . '/' . $summary['total'];
    } else {
        $body .= ' · All related documents done';
    }

    $ok = false;
    foreach (empDocNotifyStaffUserIds($conn) as $uid) {
        if ($excludeUserId > 0 && $uid === $excludeUserId) {
            continue;
        }
        $st = $conn->prepare(
            'INSERT INTO employee_document_notifications
                (user_id, employee_id, doc_type, event_type, title, body, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $event = 'Uploaded';
        $st->bind_param('iissss', $uid, $employeeId, $docType, $event, $title, $body);
        if ($st->execute()) {
            $ok = true;
        }
        $st->close();
    }

    if ($close) {
        $conn->close();
    }
    return $ok;
}

function fetchUnreadEmployeeDocumentNotifications($userId, $limit = 8, $conn = null)
{
    $userId = (int) $userId;
    $limit = max(1, min(20, (int) $limit));
    if ($userId <= 0) {
        return [];
    }
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeRelatedDocumentsTables($conn);

    $rows = [];
    $st = $conn->prepare(
        'SELECT n.id, n.user_id, n.employee_id, n.doc_type, n.event_type, n.title, n.body,
                n.created_at, DATE(n.created_at) AS notify_date,
                e.employee_code, e.employee_name
         FROM employee_document_notifications n
         LEFT JOIN employees e ON e.id = n.employee_id
         WHERE n.user_id = ? AND n.read_at IS NULL
         ORDER BY n.id DESC
         LIMIT ' . $limit
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    if ($close) {
        $conn->close();
    }
    return $rows;
}

function markEmployeeDocumentNotificationRead($notificationId, $userId)
{
    $notificationId = (int) $notificationId;
    $userId = (int) $userId;
    if ($notificationId <= 0 || $userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureEmployeeRelatedDocumentsTables($conn);
    $st = $conn->prepare(
        'UPDATE employee_document_notifications
         SET read_at = NOW()
         WHERE id = ? AND user_id = ? AND read_at IS NULL'
    );
    $st->bind_param('ii', $notificationId, $userId);
    $st->execute();
    $ok = $st->affected_rows > 0;
    $st->close();
    $conn->close();
    return $ok;
}

function markAllEmployeeDocumentNotificationsRead($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }
    $conn = getDBConnection();
    ensureEmployeeRelatedDocumentsTables($conn);
    $st = $conn->prepare(
        'UPDATE employee_document_notifications
         SET read_at = NOW()
         WHERE user_id = ? AND read_at IS NULL'
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $st->close();
    $conn->close();
    return true;
}

/**
 * Pending related-doc snapshot for Admin/HR toaster (active employees only).
 * @return list<array{employee_id:int,employee_code:string,employee_name:string,pending:int,total:int,pending_labels:string[]}>
 */
function getEmployeesWithPendingRelatedDocuments($limit = 8, $conn = null)
{
    $limit = max(1, min(25, (int) $limit));
    $close = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $close = true;
    }
    ensureEmployeeRelatedDocumentsTables($conn);

    $types = array_keys(employeeRelatedDocumentTypes());
    $total = count($types);
    $rows = [];
    $res = $conn->query(
        "SELECT e.id, e.employee_code, e.employee_name,
                COUNT(d.id) AS done_count
         FROM employees e
         LEFT JOIN employee_related_documents d ON d.employee_id = e.id
         WHERE e.status = 1
         GROUP BY e.id, e.employee_code, e.employee_name
         HAVING done_count > 0 AND done_count < {$total}
         ORDER BY done_count ASC, e.employee_name ASC
         LIMIT {$limit}"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $empId = (int) $row['id'];
            $summary = employeeRelatedDocumentsSummary($empId, $conn);
            if ($summary['pending'] <= 0) {
                continue;
            }
            $rows[] = [
                'employee_id' => $empId,
                'employee_code' => (string) ($row['employee_code'] ?? ''),
                'employee_name' => (string) ($row['employee_name'] ?? ''),
                'pending' => $summary['pending'],
                'total' => $summary['total'],
                'pending_labels' => $summary['pending_labels'],
            ];
        }
    }

    if ($close) {
        $conn->close();
    }
    return $rows;
}
