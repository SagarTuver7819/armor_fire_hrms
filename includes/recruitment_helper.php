<?php
/**
 * Recruitment — applications, uploads, QR apply URL
 */

if (!function_exists('ensureRecruitmentTables')) {

    function ensureRecruitmentTables($conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_no VARCHAR(40) NOT NULL,
                department_id INT DEFAULT NULL,
                designation_id INT DEFAULT NULL,
                department_name VARCHAR(150) NOT NULL DEFAULT '',
                position_name VARCHAR(150) NOT NULL DEFAULT '',
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL DEFAULT '',
                mobile VARCHAR(20) NOT NULL,
                alt_mobile VARCHAR(20) DEFAULT NULL,
                gender VARCHAR(20) DEFAULT NULL,
                dob DATE DEFAULT NULL,
                marital_status VARCHAR(30) DEFAULT NULL,
                address TEXT,
                city VARCHAR(100) DEFAULT NULL,
                state_name VARCHAR(100) DEFAULT NULL,
                pincode VARCHAR(12) DEFAULT NULL,
                current_salary DECIMAL(12,2) DEFAULT NULL,
                expected_salary DECIMAL(12,2) DEFAULT NULL,
                notice_period VARCHAR(80) DEFAULT NULL,
                total_experience VARCHAR(40) DEFAULT NULL,
                bank_statement_file VARCHAR(255) DEFAULT NULL,
                salary_slip_file VARCHAR(255) DEFAULT NULL,
                resume_file VARCHAR(255) DEFAULT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'new',
                hr_remarks TEXT,
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_recruitment_app_no (application_no),
                INDEX idx_recruitment_status (status),
                INDEX idx_recruitment_dept (department_id),
                INDEX idx_recruitment_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_education (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                degree VARCHAR(150) NOT NULL DEFAULT '',
                institution VARCHAR(200) NOT NULL DEFAULT '',
                year_of_passing VARCHAR(20) DEFAULT NULL,
                percentage VARCHAR(20) DEFAULT NULL,
                specialization VARCHAR(150) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                INDEX idx_rec_edu_app (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_experience (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                company_name VARCHAR(200) NOT NULL DEFAULT '',
                designation VARCHAR(150) NOT NULL DEFAULT '',
                from_date VARCHAR(40) DEFAULT NULL,
                to_date VARCHAR(40) DEFAULT NULL,
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                last_salary DECIMAL(12,2) DEFAULT NULL,
                responsibilities TEXT,
                sort_order INT NOT NULL DEFAULT 0,
                INDEX idx_rec_exp_app (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS recruitment_followups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                call_no INT NOT NULL DEFAULT 1,
                call_at DATETIME NOT NULL,
                next_followup_at DATE DEFAULT NULL,
                notes TEXT,
                outcome VARCHAR(80) DEFAULT NULL,
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rec_fu_app (application_id),
                INDEX idx_rec_fu_next (next_followup_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $extraCols = [
            'age_years' => "INT DEFAULT NULL AFTER dob",
            'aadhaar_no' => "VARCHAR(20) DEFAULT NULL AFTER pincode",
            'pan_no' => "VARCHAR(20) DEFAULT NULL AFTER aadhaar_no",
            'bank_name' => "VARCHAR(150) DEFAULT NULL AFTER pan_no",
            'bank_account' => "VARCHAR(40) DEFAULT NULL AFTER bank_name",
            'bank_ifsc' => "VARCHAR(20) DEFAULT NULL AFTER bank_account",
            'interview_mode' => "VARCHAR(40) DEFAULT NULL AFTER status",
            'interview_date' => "DATE DEFAULT NULL AFTER interview_mode",
            'interview_notes' => "TEXT AFTER interview_date",
            'awaited_with' => "VARCHAR(40) DEFAULT NULL AFTER interview_notes",
            'not_selected_reason' => "TEXT AFTER awaited_with",
            'offer_generated_at' => "DATETIME DEFAULT NULL AFTER not_selected_reason",
            'selected_at' => "DATETIME DEFAULT NULL AFTER offer_generated_at",
            'next_followup_at' => "DATE DEFAULT NULL AFTER selected_at",
            'last_call_at' => "DATETIME DEFAULT NULL AFTER next_followup_at",
            'call_count' => "INT NOT NULL DEFAULT 0 AFTER last_call_at",
            'employee_id' => "INT DEFAULT NULL AFTER call_count",
        ];
        foreach ($extraCols as $col => $def) {
            $chk = $conn->query("SHOW COLUMNS FROM recruitment_applications LIKE '" . $conn->real_escape_string($col) . "'");
            if ($chk && $chk->num_rows === 0) {
                $conn->query("ALTER TABLE recruitment_applications ADD COLUMN {$col} {$def}");
            }
        }

        // Normalize legacy statuses toward interview flow
        $conn->query("UPDATE recruitment_applications SET status = 'interview' WHERE status IN ('review','shortlisted')");
        $conn->query("UPDATE recruitment_applications SET status = 'not_selected' WHERE status = 'rejected'");
        $conn->query("UPDATE recruitment_applications SET status = 'selected' WHERE status = 'hired'");

        if ($close) {
            $conn->close();
        }
    }

    function recruitmentSqlStr($conn, $value)
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $conn->real_escape_string((string) $value) . "'";
    }

    function recruitmentSqlNum($value)
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        return (string) (float) $value;
    }

    function recruitmentSqlIntOrNull($value)
    {
        $value = (int) $value;
        return $value > 0 ? (string) $value : 'NULL';
    }

    function recruitmentApplyUrl()
    {
        $path = 'recruitment/apply.php';

        // Optional override in .env: RECRUITMENT_PUBLIC_URL=https://armor-hrms.oceanhub.co.in/recruitment/apply.php
        $override = trim((string) (function_exists('env') ? env('RECRUITMENT_PUBLIC_URL', '') : ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $liveBase = 'https://armor-hrms.oceanhub.co.in';
        $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $isLocalHost = (
            strpos($host, 'localhost') !== false
            || strpos($host, '127.0.0.1') !== false
            || $appUrl === ''
            || stripos($appUrl, 'localhost') !== false
            || stripos($appUrl, '127.0.0.1') !== false
        );

        // Fixed QR for phones must use live public URL (not localhost)
        if ($isLocalHost) {
            return $liveBase . '/' . $path;
        }

        if ($appUrl !== '') {
            return $appUrl . '/' . $path;
        }

        return app_full_url($path);
    }

    function recruitmentUploadDir()
    {
        return dirname(__DIR__) . '/assets/uploads/recruitment';
    }

    function recruitmentPublicPath($relative)
    {
        $relative = trim((string) $relative);
        if ($relative === '' || strpos($relative, 'assets/uploads/recruitment/') !== 0 || strpos($relative, '..') !== false) {
            return '';
        }
        return app_url($relative);
    }

    function recruitmentGenerateAppNo($conn)
    {
        $prefix = 'REC-' . date('Ymd') . '-';
        $like = $prefix . '%';
        $st = $conn->prepare(
            'SELECT application_no FROM recruitment_applications
             WHERE application_no LIKE ?
             ORDER BY id DESC LIMIT 1'
        );
        $st->bind_param('s', $like);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $next = 1;
        if ($row && preg_match('/-(\d+)$/', (string) $row['application_no'], $m)) {
            $next = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    function recruitmentUploadFile($fileKey, $prefix = 'doc')
    {
        if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
            return ['ok' => true, 'path' => ''];
        }
        $file = $_FILES[$fileKey];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => ''];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Each file must be under 5 MB.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['ok' => false, 'error' => 'Allowed file types: PDF, JPG, PNG, WEBP.'];
        }
        $dir = recruitmentUploadDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create upload folder.'];
        }
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix)
            . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $safe;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'Could not save uploaded file.'];
        }
        return ['ok' => true, 'path' => 'assets/uploads/recruitment/' . $safe];
    }

    function recruitmentFormatExperienceMonths($months)
    {
        $months = max(0, (int) $months);
        if ($months <= 0) {
            return '';
        }
        $y = intdiv($months, 12);
        $m = $months % 12;
        $parts = [];
        if ($y > 0) {
            $parts[] = $y . ($y === 1 ? ' year' : ' years');
        }
        if ($m > 0) {
            $parts[] = $m . ($m === 1 ? ' month' : ' months');
        }
        return implode(' ', $parts);
    }

    /**
     * Sum company-wise From/To (YYYY-MM). Current job uses today's month.
     */
    function recruitmentCalculateTotalExperience(array $experience)
    {
        $total = 0;
        $nowYm = date('Y-m');
        foreach ($experience as $ex) {
            $from = trim((string) ($ex['from_date'] ?? ''));
            $to = trim((string) ($ex['to_date'] ?? ''));
            if (!empty($ex['is_current'])) {
                $to = $nowYm;
            }
            if (!preg_match('/^\d{4}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}$/', $to)) {
                continue;
            }
            [$fy, $fm] = array_map('intval', explode('-', $from));
            [$ty, $tm] = array_map('intval', explode('-', $to));
            $diff = ($ty - $fy) * 12 + ($tm - $fm);
            if ($diff < 0) {
                continue;
            }
            $total += ($diff === 0 ? 1 : $diff);
        }
        return recruitmentFormatExperienceMonths($total);
    }

    function recruitmentStatusLabels()
    {
        return [
            'new' => 'New Application',
            'interview' => 'Interview',
            'awaited' => 'Awaited',
            'selected' => 'Selected',
            'not_selected' => 'Not Selected',
            // legacy aliases (display only if old rows remain)
            'review' => 'Interview',
            'shortlisted' => 'Awaited',
            'rejected' => 'Not Selected',
            'hired' => 'Selected',
        ];
    }

    function recruitmentInterviewModes()
    {
        return [
            'in_house' => 'In-house',
            'in_person' => 'In-person',
            'virtual' => 'Virtual',
        ];
    }

    function recruitmentAwaitedSides()
    {
        return [
            'hr' => 'HR',
            'management' => 'Management',
            'employee' => 'Employee side',
        ];
    }

    function recruitmentCalcAgeYears($dob)
    {
        $dob = trim((string) $dob);
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            return null;
        }
        try {
            $birth = new DateTime($dob);
            $now = new DateTime('today');
            if ($birth > $now) {
                return null;
            }
            return (int) $birth->diff($now)->y;
        } catch (Throwable $e) {
            return null;
        }
    }

    function recruitmentFollowups($applicationId, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $rows = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_followups WHERE application_id = ? ORDER BY call_no ASC, id ASC'
        );
        $st->bind_param('i', $applicationId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $rows;
    }

    function recruitmentIsFollowupDue(array $row)
    {
        if (($row['status'] ?? '') !== 'awaited') {
            return false;
        }
        $next = trim((string) ($row['next_followup_at'] ?? ''));
        if ($next === '') {
            return false;
        }
        return $next <= date('Y-m-d');
    }

    function saveRecruitmentInterviewUpdate($id, array $data, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $status = trim((string) ($data['status'] ?? 'interview'));
        $allowed = ['new', 'interview', 'awaited', 'selected', 'not_selected'];
        if (!in_array($status, $allowed, true)) {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Invalid status'];
        }

        $mode = trim((string) ($data['interview_mode'] ?? ''));
        $interviewDate = trim((string) ($data['interview_date'] ?? ''));
        $notes = trim((string) ($data['interview_notes'] ?? ''));
        $awaitedWith = trim((string) ($data['awaited_with'] ?? ''));
        $notReason = trim((string) ($data['not_selected_reason'] ?? ''));
        $hrRemarks = trim((string) ($data['hr_remarks'] ?? ''));

        if ($status === 'not_selected' && $notReason === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please enter reason for Not Selected.'];
        }
        if ($status === 'awaited' && $awaitedWith === '') {
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => 'Please select Awaited with (HR / Management / Employee).'];
        }

        $sets = [
            'status = ' . recruitmentSqlStr($conn, $status),
            'interview_mode = ' . recruitmentSqlStr($conn, $mode),
            'interview_date = ' . ($interviewDate !== '' ? recruitmentSqlStr($conn, $interviewDate) : 'NULL'),
            'interview_notes = ' . recruitmentSqlStr($conn, $notes),
            'awaited_with = ' . recruitmentSqlStr($conn, $awaitedWith),
            'not_selected_reason = ' . recruitmentSqlStr($conn, $notReason),
            'hr_remarks = ' . recruitmentSqlStr($conn, $hrRemarks),
        ];

        if ($status === 'awaited') {
            // Default 6-day reminder from today if not already set / refresh on status change
            $sets[] = 'next_followup_at = DATE_ADD(CURDATE(), INTERVAL 6 DAY)';
        }
        if ($status === 'selected') {
            $sets[] = 'selected_at = IFNULL(selected_at, NOW())';
            $sets[] = 'awaited_with = NULL';
            $sets[] = 'not_selected_reason = ' . recruitmentSqlStr($conn, '');
        }
        if ($status === 'not_selected') {
            $sets[] = 'awaited_with = NULL';
            $sets[] = 'next_followup_at = NULL';
        }

        $ok = $conn->query(
            'UPDATE recruitment_applications SET ' . implode(', ', $sets) . ' WHERE id = ' . $id
        );
        $err = $ok ? '' : $conn->error;
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err ?: 'Update failed'];
    }

    function saveRecruitmentFollowup($applicationId, array $data, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $applicationId = (int) $applicationId;
        $notes = trim((string) ($data['notes'] ?? ''));
        $outcome = trim((string) ($data['outcome'] ?? ''));
        $callAt = trim((string) ($data['call_at'] ?? ''));
        if ($callAt === '') {
            $callAt = date('Y-m-d H:i:s');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $callAt)) {
            $callAt = str_replace('T', ' ', substr($callAt, 0, 16)) . ':00';
        }
        $next = trim((string) ($data['next_followup_at'] ?? ''));
        if ($next === '') {
            $next = date('Y-m-d', strtotime('+6 days'));
        }

        $st = $conn->prepare('SELECT COALESCE(MAX(call_no), 0) AS m FROM recruitment_followups WHERE application_id = ?');
        $st->bind_param('i', $applicationId);
        $st->execute();
        $callNo = (int) ($st->get_result()->fetch_assoc()['m'] ?? 0) + 1;
        $st->close();

        $userId = (int) $userId;
        $sql = 'INSERT INTO recruitment_followups
            (application_id, call_no, call_at, next_followup_at, notes, outcome, created_by)
            VALUES (' .
            $applicationId . ',' .
            $callNo . ',' .
            recruitmentSqlStr($conn, $callAt) . ',' .
            recruitmentSqlStr($conn, $next) . ',' .
            recruitmentSqlStr($conn, $notes) . ',' .
            recruitmentSqlStr($conn, $outcome) . ',' .
            ($userId > 0 ? $userId : 'NULL') .
        ')';
        if (!$conn->query($sql)) {
            $err = $conn->error;
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => $err ?: 'Could not save follow-up'];
        }

        $conn->query(
            'UPDATE recruitment_applications SET
                last_call_at = ' . recruitmentSqlStr($conn, $callAt) . ',
                next_followup_at = ' . recruitmentSqlStr($conn, $next) . ',
                call_count = ' . (int) $callNo . '
             WHERE id = ' . $applicationId
        );

        if ($close) {
            $conn->close();
        }
        return ['ok' => true, 'call_no' => $callNo];
    }

    function getRecruitmentApplication($id, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $st = $conn->prepare('SELECT * FROM recruitment_applications WHERE id = ? LIMIT 1');
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            if ($close) {
                $conn->close();
            }
            return null;
        }

        $edu = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_education WHERE application_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $edu[] = $r;
        }
        $st->close();

        $exp = [];
        $st = $conn->prepare(
            'SELECT * FROM recruitment_experience WHERE application_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) {
            $exp[] = $r;
        }
        $st->close();

        $row['education'] = $edu;
        $row['experience'] = $exp;
        $row['followups'] = recruitmentFollowups($id, $conn);
        if (empty($row['age_years']) && !empty($row['dob'])) {
            $row['age_years'] = recruitmentCalcAgeYears($row['dob']);
        }
        if ($close) {
            $conn->close();
        }
        return $row;
    }

    function saveRecruitmentApplication(array $data, array $education, array $experience, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $appNo = recruitmentGenerateAppNo($conn);

        $sql = 'INSERT INTO recruitment_applications (
            application_no, department_id, designation_id, department_name, position_name,
            full_name, email, mobile, alt_mobile, gender, dob, age_years, marital_status,
            address, city, state_name, pincode,
            aadhaar_no, pan_no, bank_name, bank_account, bank_ifsc,
            current_salary, expected_salary, notice_period, total_experience,
            bank_statement_file, salary_slip_file, resume_file,
            status, ip_address, user_agent
        ) VALUES (' .
            recruitmentSqlStr($conn, $appNo) . ',' .
            recruitmentSqlIntOrNull($data['department_id'] ?? 0) . ',' .
            recruitmentSqlIntOrNull($data['designation_id'] ?? 0) . ',' .
            recruitmentSqlStr($conn, $data['department_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['position_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['full_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['email'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['mobile'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['alt_mobile'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['gender'] ?? '') . ',' .
            (!empty($data['dob']) ? recruitmentSqlStr($conn, $data['dob']) : 'NULL') . ',' .
            (isset($data['age_years']) && $data['age_years'] !== null && $data['age_years'] !== ''
                ? (string) (int) $data['age_years'] : 'NULL') . ',' .
            recruitmentSqlStr($conn, $data['marital_status'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['address'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['city'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['state_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['pincode'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['aadhaar_no'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['pan_no'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_name'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_account'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_ifsc'] ?? '') . ',' .
            recruitmentSqlNum($data['current_salary'] ?? null) . ',' .
            recruitmentSqlNum($data['expected_salary'] ?? null) . ',' .
            recruitmentSqlStr($conn, $data['notice_period'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['total_experience'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['bank_statement_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['salary_slip_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, $data['resume_file'] ?? '') . ',' .
            recruitmentSqlStr($conn, 'new') . ',' .
            recruitmentSqlStr($conn, $data['ip_address'] ?? '') . ',' .
            recruitmentSqlStr($conn, substr((string) ($data['user_agent'] ?? ''), 0, 255)) .
        ')';

        if (!$conn->query($sql)) {
            $err = $conn->error;
            if ($close) {
                $conn->close();
            }
            return ['ok' => false, 'error' => $err ?: 'Could not save application.'];
        }

        $appId = (int) $conn->insert_id;
        $i = 0;
        foreach ($education as $ed) {
            $degree = trim((string) ($ed['degree'] ?? ''));
            $inst = trim((string) ($ed['institution'] ?? ''));
            if ($degree === '' && $inst === '') {
                continue;
            }
            $conn->query(
                'INSERT INTO recruitment_education
                 (application_id, degree, institution, year_of_passing, percentage, specialization, sort_order)
                 VALUES (' .
                    (int) $appId . ',' .
                    recruitmentSqlStr($conn, $degree) . ',' .
                    recruitmentSqlStr($conn, $inst) . ',' .
                    recruitmentSqlStr($conn, $ed['year_of_passing'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ed['percentage'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ed['specialization'] ?? '') . ',' .
                    (int) $i .
                ')'
            );
            $i++;
        }

        $j = 0;
        foreach ($experience as $ex) {
            $cname = trim((string) ($ex['company_name'] ?? ''));
            $desig = trim((string) ($ex['designation'] ?? ''));
            if ($cname === '' && $desig === '') {
                continue;
            }
            $conn->query(
                'INSERT INTO recruitment_experience
                 (application_id, company_name, designation, from_date, to_date, is_current, last_salary, responsibilities, sort_order)
                 VALUES (' .
                    (int) $appId . ',' .
                    recruitmentSqlStr($conn, $cname) . ',' .
                    recruitmentSqlStr($conn, $desig) . ',' .
                    recruitmentSqlStr($conn, $ex['from_date'] ?? '') . ',' .
                    recruitmentSqlStr($conn, $ex['to_date'] ?? '') . ',' .
                    (!empty($ex['is_current']) ? 1 : 0) . ',' .
                    recruitmentSqlNum($ex['last_salary'] ?? null) . ',' .
                    recruitmentSqlStr($conn, $ex['responsibilities'] ?? '') . ',' .
                    (int) $j .
                ')'
            );
            $j++;
        }

        if ($close) {
            $conn->close();
        }
        return ['ok' => true, 'id' => $appId, 'application_no' => $appNo];
    }

    function updateRecruitmentStatus($id, $status, $remarks = '', $conn = null)
    {
        $labels = recruitmentStatusLabels();
        if (!isset($labels[$status])) {
            return ['ok' => false, 'error' => 'Invalid status'];
        }
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureRecruitmentTables($conn);
        $id = (int) $id;
        $st = $conn->prepare(
            'UPDATE recruitment_applications SET status = ?, hr_remarks = ? WHERE id = ?'
        );
        $st->bind_param('ssi', $status, $remarks, $id);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err];
    }
}
