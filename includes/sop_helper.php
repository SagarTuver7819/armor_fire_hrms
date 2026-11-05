<?php
/**
 * CNC department SOP letters (letterhead + digital signature + share link).
 */

function ensureCncSopTables($conn = null)
{
    $own = false;
    if (!$conn) {
        $conn = getDBConnection();
        $own = true;
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS cnc_sops (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            department_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            sop_date DATE NOT NULL,
            subject VARCHAR(500) NOT NULL,
            department_name VARCHAR(150) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            signer_name VARCHAR(150) NOT NULL DEFAULT '',
            signature_data MEDIUMTEXT NULL,
            share_token CHAR(48) NOT NULL,
            created_by INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cnc_sop_share (share_token),
            KEY idx_cnc_sop_dept (department_id, sop_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if ($own) {
        $conn->close();
    }
}

function cncSopIsCncDepartment($department)
{
    if (!$department || !is_array($department)) {
        return false;
    }
    return (bool) preg_match('/^cnc(\b|\s|$)/i', trim((string) ($department['department_name'] ?? '')));
}

function cncSopCount($conn, $departmentId)
{
    ensureCncSopTables($conn);
    $departmentId = (int) $departmentId;
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM cnc_sops WHERE department_id = ?');
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

function cncSopList($conn, $departmentId)
{
    ensureCncSopTables($conn);
    $departmentId = (int) $departmentId;
    $stmt = $conn->prepare(
        'SELECT id, department_id, title, sop_date, subject, department_name, signer_name, share_token, created_at
         FROM cnc_sops
         WHERE department_id = ?
         ORDER BY sop_date DESC, id DESC'
    );
    $stmt->bind_param('i', $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function cncSopGet($conn, $id)
{
    ensureCncSopTables($conn);
    $id = (int) $id;
    $stmt = $conn->prepare('SELECT * FROM cnc_sops WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function cncSopGetByToken($conn, $token)
{
    ensureCncSopTables($conn);
    $token = trim((string) $token);
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM cnc_sops WHERE share_token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function cncSopShareUrl(array $row)
{
    $token = trim((string) ($row['share_token'] ?? ''));
    return app_url('sop/share.php?t=' . rawurlencode($token));
}

function cncSopNormalizeSignature($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\r\n]+$#', $raw)) {
        return '';
    }
    if (strlen($raw) > 700000) {
        return '';
    }
    return $raw;
}

function cncSopSave($conn, array $data)
{
    ensureCncSopTables($conn);
    $id = (int) ($data['id'] ?? 0);
    $departmentId = (int) ($data['department_id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? ''));
    $departmentName = trim((string) ($data['department_name'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $signerName = trim((string) ($data['signer_name'] ?? ''));
    $sopDate = parseDateInput($data['sop_date'] ?? '');
    $signature = cncSopNormalizeSignature($data['signature_data'] ?? '');
    $clearSignature = !empty($data['clear_signature']);
    $createdBy = (int) ($data['created_by'] ?? 0);

    if ($title === '' || $subject === '' || $departmentName === '' || $body === '' || !$sopDate) {
        return ['ok' => false, 'error' => 'Title, Date, Subject, Department Name and Body are required.'];
    }
    if (strlen($title) > 255 || strlen($subject) > 500 || strlen($departmentName) > 150 || strlen($signerName) > 150) {
        return ['ok' => false, 'error' => 'One of the fields is too long.'];
    }

    if ($id > 0) {
        $existing = cncSopGet($conn, $id);
        if (!$existing || (int) $existing['department_id'] !== $departmentId) {
            return ['ok' => false, 'error' => 'Contractor Agreement not found.'];
        }
        $storedSignature = (string) ($existing['signature_data'] ?? '');
        if ($signature !== '') {
            $storedSignature = $signature;
        } elseif ($clearSignature) {
            $storedSignature = '';
        }
        $stmt = $conn->prepare(
            'UPDATE cnc_sops
             SET title = ?, sop_date = ?, subject = ?, department_name = ?, body = ?, signer_name = ?, signature_data = ?
             WHERE id = ? AND department_id = ?'
        );
        $sigParam = $storedSignature;
        $stmt->bind_param(
            'sssssssii',
            $title,
            $sopDate,
            $subject,
            $departmentName,
            $body,
            $signerName,
            $sigParam,
            $id,
            $departmentId
        );
        $stmt->execute();
        $stmt->close();
        return ['ok' => true, 'id' => $id];
    }

    $token = bin2hex(random_bytes(24));
    $stmt = $conn->prepare(
        'INSERT INTO cnc_sops
            (department_id, title, sop_date, subject, department_name, body, signer_name, signature_data, share_token, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $sigParam = $signature;
    $stmt->bind_param(
        'issssssssi',
        $departmentId,
        $title,
        $sopDate,
        $subject,
        $departmentName,
        $body,
        $signerName,
        $sigParam,
        $token,
        $createdBy
    );
    $stmt->execute();
    $newId = (int) $stmt->insert_id;
    $stmt->close();
    return ['ok' => true, 'id' => $newId];
}

function cncSopDelete($conn, $id, $departmentId)
{
    ensureCncSopTables($conn);
    $id = (int) $id;
    $departmentId = (int) $departmentId;
    $stmt = $conn->prepare('DELETE FROM cnc_sops WHERE id = ? AND department_id = ?');
    $stmt->bind_param('ii', $id, $departmentId);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}
