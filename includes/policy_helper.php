<?php
/**
 * Company Policies — HR/Admin PDF uploads, department targeting, bell notifications
 */

function ensurePolicyTables($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS policies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            policy_no VARCHAR(100) DEFAULT NULL,
            policy_date DATE NOT NULL,
            added_date DATE NOT NULL,
            remarks TEXT DEFAULT NULL,
            pdf_file VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            apply_all_departments TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_policy_date (policy_date),
            INDEX idx_policy_added (added_date),
            INDEX idx_policy_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $col = $conn->query("SHOW COLUMNS FROM policies LIKE 'apply_all_departments'");
    if ($col && $col->num_rows === 0) {
        $conn->query(
            "ALTER TABLE policies
             ADD COLUMN apply_all_departments TINYINT(1) NOT NULL DEFAULT 0 AFTER original_filename"
        );
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS policy_departments (
            policy_id INT NOT NULL,
            department_id INT NOT NULL,
            PRIMARY KEY (policy_id, department_id),
            INDEX idx_cd_dept (department_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS policy_reads (
            policy_id INT NOT NULL,
            user_id INT NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (policy_id, user_id),
            INDEX idx_cr_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    policyEnsureUploadDir();

    if ($closeAfter) {
        $conn->close();
    }
}

function policyUploadDir()
{
    return dirname(__DIR__) . '/assets/uploads/company_policies';
}

function policyEnsureUploadDir()
{
    $dir = policyUploadDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $keep = $dir . '/.gitkeep';
    if (!is_file($keep)) {
        file_put_contents($keep, '');
    }
    return $dir;
}

function policyAbsolutePath($relativePath)
{
    $relativePath = str_replace('\\', '/', (string) $relativePath);
    if ($relativePath === '' || strpos($relativePath, 'assets/uploads/company_policies/') !== 0 || strpos($relativePath, '..') !== false) {
        return '';
    }
    return dirname(__DIR__) . '/' . $relativePath;
}

function policyFileExists($relativePath)
{
    $abs = policyAbsolutePath($relativePath);
    return $abs !== '' && is_file($abs) && filesize($abs) > 0;
}

/**
 * @return array{ok:bool,path?:string,original?:string,error?:string}
 */
function policyStoreUploadedPdf(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'PDF upload failed. Please try again.'];
    }

    $original = (string) ($file['name'] ?? 'Policy.pdf');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['ok' => false, 'error' => 'Only PDF files are allowed.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Empty PDF file.'];
    }
    if ($size > 10 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'PDF must be under 10 MB. Please compress or re-scan.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf' && $mime !== 'application/octet-stream') {
        return ['ok' => false, 'error' => 'Invalid PDF file.'];
    }

    policyEnsureUploadDir();
    $safeName = 'policy_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $dest = policyUploadDir() . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save PDF on server.'];
    }

    return [
        'ok' => true,
        'path' => 'assets/uploads/company_policies/' . $safeName,
        'original' => $original,
    ];
}

function policyDeleteFile($relativePath)
{
    $abs = policyAbsolutePath($relativePath);
    if ($abs !== '' && is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * Normalize selected department IDs from form
 * @return array{apply_all:bool,department_ids:int[]}
 */
function policyNormalizeDepartments($applyAll, $departmentIds)
{
    $ids = [];
    $sawAll = false;
    if (is_array($departmentIds)) {
        foreach ($departmentIds as $id) {
            if ((string) $id === 'all' || (string) $id === '0') {
                $sawAll = true;
                continue;
            }
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
    }
    $ids = array_values($ids);
    sort($ids);

    $applyAll = (int) $applyAll === 1
        || $applyAll === true
        || $applyAll === '1'
        || $applyAll === 'on'
        || $sawAll;

    if ($applyAll) {
        return ['apply_all' => true, 'department_ids' => []];
    }

    return ['apply_all' => false, 'department_ids' => $ids];
}

function syncPolicyDepartments($conn, $policyId, $applyAll, array $departmentIds)
{
    $policyId = (int) $policyId;
    $st = $conn->prepare('DELETE FROM policy_departments WHERE policy_id = ?');
    $st->bind_param('i', $policyId);
    $st->execute();
    $st->close();

    if ($applyAll || !$departmentIds) {
        return;
    }

    $st = $conn->prepare('INSERT INTO policy_departments (policy_id, department_id) VALUES (?, ?)');
    foreach ($departmentIds as $deptId) {
        $deptId = (int) $deptId;
        if ($deptId <= 0) {
            continue;
        }
        $st->bind_param('ii', $policyId, $deptId);
        $st->execute();
    }
    $st->close();
}

/**
 * @return int[]
 */
function getPolicyDepartmentIds($policyId, $conn = null)
{
    $policyId = (int) $policyId;
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        ensurePolicyTables($conn);
        $closeAfter = true;
    }

    $ids = [];
    $st = $conn->prepare('SELECT department_id FROM policy_departments WHERE policy_id = ? ORDER BY department_id ASC');
    $st->bind_param('i', $policyId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['department_id'];
    }
    $st->close();

    if ($closeAfter) {
        $conn->close();
    }
    return $ids;
}

function policyDepartmentsLabel(array $row)
{
    if (!empty($row['apply_all_departments'])) {
        return 'All Departments';
    }
    $names = trim((string) ($row['department_names'] ?? ''));
    if ($names !== '') {
        return $names;
    }
    $ids = $row['department_ids'] ?? [];
    if (is_array($ids) && $ids) {
        return count($ids) . ' department(s)';
    }
    return '—';
}

function attachPolicyDepartments(array &$rows, $conn)
{
    if (!$rows) {
        return;
    }

    $ids = [];
    foreach ($rows as $r) {
        $cid = (int) ($r['id'] ?? 0);
        if ($cid > 0 && empty($r['apply_all_departments'])) {
            $ids[] = $cid;
        }
    }
    $ids = array_values(array_unique($ids));

    $map = [];
    $nameMap = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $sql = "SELECT cd.policy_id, cd.department_id, d.department_name
                FROM policy_departments cd
                LEFT JOIN departments d ON d.id = cd.department_id
                WHERE cd.policy_id IN ({$in})
                ORDER BY d.sort_order ASC, d.department_name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cid = (int) $row['policy_id'];
                $map[$cid][] = (int) $row['department_id'];
                if (!empty($row['department_name'])) {
                    $nameMap[$cid][] = $row['department_name'];
                }
            }
        }
    }

    foreach ($rows as &$r) {
        $cid = (int) ($r['id'] ?? 0);
        if (!empty($r['apply_all_departments'])) {
            $r['department_ids'] = [];
            $r['department_names'] = 'All Departments';
            continue;
        }
        $r['department_ids'] = $map[$cid] ?? [];
        $r['department_names'] = !empty($nameMap[$cid]) ? implode(', ', $nameMap[$cid]) : '';
    }
    unset($r);
}

function fetchPolicies($year = 0, $departmentId = 0)
{
    $conn = getDBConnection();
    ensurePolicyTables($conn);

    $sql = "SELECT c.*, u.full_name AS created_by_name
            FROM policies c
            LEFT JOIN users u ON u.id = c.created_by
            WHERE c.status = 1";
    $params = [];
    $types = '';

    if ($year >= 2000 && $year <= 2100) {
        $sql .= " AND (YEAR(c.policy_date) = ? OR YEAR(c.added_date) = ?)";
        $params[] = $year;
        $params[] = $year;
        $types .= 'ii';
    }

    $departmentId = (int) $departmentId;
    if ($departmentId > 0) {
        $sql .= " AND (
            c.apply_all_departments = 1
            OR EXISTS (
                SELECT 1 FROM policy_departments cd
                WHERE cd.policy_id = c.id AND cd.department_id = ?
            )
        )";
        $params[] = $departmentId;
        $types .= 'i';
    }

    $sql .= " ORDER BY c.policy_date DESC, c.added_date DESC, c.id DESC";

    $rows = [];
    if ($types !== '') {
        $st = $conn->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $st->close();
    } else {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }

    attachPolicyDepartments($rows, $conn);
    $conn->close();
    return $rows;
}

function getPolicyById($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    $conn = getDBConnection();
    ensurePolicyTables($conn);

    $st = $conn->prepare(
        "SELECT c.*, u.full_name AS created_by_name
         FROM policies c
         LEFT JOIN users u ON u.id = c.created_by
         WHERE c.id = ? AND c.status = 1
         LIMIT 1"
    );
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    if ($row) {
        $rows = [$row];
        attachPolicyDepartments($rows, $conn);
        $row = $rows[0];
    }

    $conn->close();
    return $row;
}

/**
 * @return array{ok:bool,id?:int,error?:string}
 */
function savePolicy(array $data, $uploadedFile = null)
{
    $id = (int) ($data['id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $policyNo = trim((string) ($data['policy_no'] ?? ''));
    $policyDate = parseDateInput($data['policy_date'] ?? '');
    $addedDate = parseDateInput($data['added_date'] ?? '');
    $remarks = trim((string) ($data['remarks'] ?? ''));
    $userId = (int) ($data['created_by'] ?? ($_SESSION['user_id'] ?? 0));

    $deptNorm = policyNormalizeDepartments(
        $data['apply_all_departments'] ?? 0,
        $data['department_ids'] ?? []
    );
    $applyAll = $deptNorm['apply_all'] ? 1 : 0;
    $departmentIds = $deptNorm['department_ids'];

    if ($title === '') {
        return ['ok' => false, 'error' => 'Title is required.'];
    }
    if (!$policyDate) {
        return ['ok' => false, 'error' => 'Policy date is required (DD-MM-YYYY).'];
    }
    if (!$addedDate) {
        return ['ok' => false, 'error' => 'Added date is required (DD-MM-YYYY).'];
    }
    if (!$applyAll && !$departmentIds) {
        return ['ok' => false, 'error' => 'Select All Departments or at least one department.'];
    }

    $conn = getDBConnection();
    ensurePolicyTables($conn);

    $existing = null;
    if ($id > 0) {
        $st = $conn->prepare("SELECT * FROM policies WHERE id = ? AND status = 1 LIMIT 1");
        $st->bind_param('i', $id);
        $st->execute();
        $existing = $st->get_result()->fetch_assoc() ?: null;
        $st->close();
        if (!$existing) {
            $conn->close();
            return ['ok' => false, 'error' => 'Policy not found.'];
        }
    }

    $pdfPath = $existing['pdf_file'] ?? '';
    $original = $existing['original_filename'] ?? '';
    $oldPdf = $pdfPath;

    $hasUpload = is_array($uploadedFile)
        && (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

    if ($hasUpload) {
        $stored = policyStoreUploadedPdf($uploadedFile);
        if (!$stored['ok']) {
            $conn->close();
            return ['ok' => false, 'error' => $stored['error']];
        }
        $pdfPath = $stored['path'];
        $original = $stored['original'];
    } elseif ($id <= 0) {
        $conn->close();
        return ['ok' => false, 'error' => 'Please upload the scanned Policy PDF.'];
    }

    if ($policyNo === '') {
        $policyNo = '';
    }
    if ($remarks === '') {
        $remarks = '';
    }
    if ($userId <= 0) {
        $userId = 0;
    }

    if ($id > 0) {
        $st = $conn->prepare(
            "UPDATE policies
             SET title = ?, policy_no = ?, policy_date = ?, added_date = ?,
                 remarks = ?, pdf_file = ?, original_filename = ?, apply_all_departments = ?
             WHERE id = ? AND status = 1"
        );
        $st->bind_param(
            'sssssssii',
            $title,
            $policyNo,
            $policyDate,
            $addedDate,
            $remarks,
            $pdfPath,
            $original,
            $applyAll,
            $id
        );
        $ok = $st->execute();
        $st->close();

        if (!$ok) {
            $conn->close();
            if ($hasUpload && $pdfPath !== $oldPdf) {
                policyDeleteFile($pdfPath);
            }
            return ['ok' => false, 'error' => 'Could not update Policy.'];
        }

        syncPolicyDepartments($conn, $id, (bool) $applyAll, $departmentIds);
        // On update, clear old reads so staff get notified again about changes
        $st = $conn->prepare('DELETE FROM policy_reads WHERE policy_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $conn->close();

        if ($hasUpload && $oldPdf !== '' && $oldPdf !== $pdfPath) {
            policyDeleteFile($oldPdf);
        }

        return ['ok' => true, 'id' => $id];
    }

    $st = $conn->prepare(
        "INSERT INTO policies
            (title, policy_no, policy_date, added_date, remarks, pdf_file, original_filename, apply_all_departments, created_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $st->bind_param(
        'sssssssii',
        $title,
        $policyNo,
        $policyDate,
        $addedDate,
        $remarks,
        $pdfPath,
        $original,
        $applyAll,
        $userId
    );
    $ok = $st->execute();
    $newId = (int) $conn->insert_id;
    $st->close();

    if (!$ok || $newId <= 0) {
        $conn->close();
        policyDeleteFile($pdfPath);
        return ['ok' => false, 'error' => 'Could not save Policy.'];
    }

    syncPolicyDepartments($conn, $newId, (bool) $applyAll, $departmentIds);
    $conn->close();

    return ['ok' => true, 'id' => $newId];
}

/**
 * Soft-delete Policy and remove PDF file
 * @return array{ok:bool,error?:string}
 */
function deletePolicy($id)
{
    $id = (int) $id;
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid Policy.'];
    }

    $conn = getDBConnection();
    ensurePolicyTables($conn);

    $st = $conn->prepare("SELECT pdf_file FROM policies WHERE id = ? AND status = 1 LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: null;
    $st->close();

    if (!$row) {
        $conn->close();
        return ['ok' => false, 'error' => 'Policy not found.'];
    }

    $st = $conn->prepare("UPDATE policies SET status = 0 WHERE id = ?");
    $st->bind_param('i', $id);
    $ok = $st->execute();
    $st->close();

    if ($ok) {
        $st = $conn->prepare('DELETE FROM policy_departments WHERE policy_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $st = $conn->prepare('DELETE FROM policy_reads WHERE policy_id = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
    }

    $conn->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not delete Policy.'];
    }

    policyDeleteFile($row['pdf_file'] ?? '');
    return ['ok' => true];
}

/**
 * Unread Policy notifications for logged-in staff
 * @return array<int,array>
 */
function fetchUnreadPolicyNotifications($userId, $limit = 12)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [];
    }

    $conn = getDBConnection();
    ensurePolicyTables($conn);

    // Portal is HR/Admin only — show all unread policies in the bell.
    // Department targeting controls list filter / assignment display.
    $sql = "SELECT c.id, c.title, c.policy_no, c.policy_date, c.added_date,
                   c.apply_all_departments, c.created_at
            FROM policies c
            WHERE c.status = 1
              AND NOT EXISTS (
                  SELECT 1 FROM policy_reads cr
                  WHERE cr.policy_id = c.id AND cr.user_id = ?
              )
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT " . max(1, min(30, (int) $limit));

    $st = $conn->prepare($sql);
    $st->bind_param('i', $userId);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $st->close();
    attachPolicyDepartments($rows, $conn);
    $conn->close();
    return $rows;
}

function countUnreadPolicyNotifications($userId)
{
    return count(fetchUnreadPolicyNotifications($userId, 30));
}

function markPolicyRead($policyId, $userId)
{
    $policyId = (int) $policyId;
    $userId = (int) $userId;
    if ($policyId <= 0 || $userId <= 0) {
        return false;
    }

    $conn = getDBConnection();
    ensurePolicyTables($conn);
    $st = $conn->prepare(
        "INSERT INTO policy_reads (policy_id, user_id, read_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE read_at = NOW()"
    );
    $st->bind_param('ii', $policyId, $userId);
    $ok = $st->execute();
    $st->close();
    $conn->close();
    return (bool) $ok;
}

function markAllPoliciesRead($userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $rows = fetchUnreadPolicyNotifications($userId, 30);
    foreach ($rows as $r) {
        markPolicyRead((int) $r['id'], $userId);
    }
    return true;
}



