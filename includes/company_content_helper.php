<?php
/**
 * Company History + Vision / Mission / Core Values content
 */

if (!function_exists('ensureCompanyContentTables')) {

    function ensureCompanyContentTables($conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS company_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_key VARCHAR(40) NOT NULL,
                title VARCHAR(200) NOT NULL DEFAULT '',
                body_json LONGTEXT,
                status TINYINT(1) NOT NULL DEFAULT 1,
                updated_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_company_content_key (content_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        seedCompanyContentDefaults($conn);

        if ($close) {
            $conn->close();
        }
    }

    function companyContentDefaultHistory()
    {
        return [
            'company' => 'Armor Steel Industries Pvt. Ltd.',
            'heading' => 'Company History',
            'paragraphs' => [
                'Armor Steel Industries Pvt. Ltd. is one of India\'s emerging manufacturers of high-quality firefighting equipment and fire protection solutions, headquartered in Rajkot, Gujarat. The company operates under the trusted brand ARMOR FIRE and is committed to manufacturing products that protect lives and property through innovation, quality, and compliance with national and international standards.',
                'The company\'s journey began as Mahadev Casting, where it established expertise in precision metal casting and manufacturing. Building on years of engineering experience and customer trust, the organization expanded into the fire safety industry, developing a comprehensive portfolio of firefighting products under the ARMOR FIRE brand. The business emphasizes complete in-house manufacturing to ensure consistent quality and reliability.',
                'During the year 2021, the company undertook a significant expansion of its manufacturing facility, increasing the total production area to approximately 26,100 sq. ft. This expansion was aligned with the Make in India and Startup India initiatives, enabling the company to become India\'s first manufacturer of several fire protection products that were previously imported from overseas.',
                'This strategic expansion substantially enhanced the company\'s manufacturing capacity while strengthening its advanced machinery, production capabilities, and quality control systems. As a result, the company has significantly contributed to India\'s self-reliance in the fire protection industry by reducing dependence on imports and manufacturing these world-class products domestically.',
                'In 2024, the organization undertook another phase of expansion by strengthening its technical workforce and enhancing manufacturing capabilities to support future growth. During the same period, Armor Steel Industries Private Limited was formally incorporated as a private limited company, reflecting its vision for long-term growth, corporate governance, and national market expansion.',
                'Today, Armor Steel Industries manufactures an extensive range of fire protection products. Its products are manufactured in accordance with BIS/ISI standards, supported by ISO-certified quality systems and stringent quality control processes.',
            ],
            'products_2021' => [
                'Sprinkler Accessories',
                'Fire Sprinklers (UL Listed)',
                'Flexible Hoses',
                'Alarm Valves',
                'Deluge Valves',
            ],
            'products_today' => [
                'Fire Hydrant Valves',
                'RRL Fire Hoses',
                'Hose Reel Drums',
                'Branch Pipes',
                'Landing Valves',
                'Fire Fighting Accessories',
                'Other Fire Protection Equipment',
            ],
        ];
    }

    function companyContentDefaultVision()
    {
        return [
            'heading' => 'Vision, Mission & Core Values',
            'leadership' => 'The company is led by Founder Mr. Ankur Babariya and Managing Director Mr. Pritesh Babariya, whose vision is to position ARMOR FIRE as a trusted name in fire safety by combining engineering excellence, continuous innovation, and customer-centric service.',
            'vision' => 'To become one of India\'s most trusted and globally recognized manufacturers of fire protection equipment through innovation, quality, and customer satisfaction.',
            'mission' => [
                'Deliver reliable and certified fire safety products.',
                'Maintain the highest standards of quality and manufacturing excellence.',
                'Continuously invest in technology, infrastructure, and people.',
                'Protect lives and property with dependable fire protection solutions.',
            ],
            'core_values' => [
                'Safety First',
                'Quality Without Compromise',
                'Integrity',
                'Innovation',
                'Customer Focus',
                'Continuous Improvement',
                'Teamwork',
            ],
        ];
    }

    function seedCompanyContentDefaults($conn)
    {
        $defaults = [
            'history' => [
                'title' => 'Company History',
                'body' => companyContentDefaultHistory(),
            ],
            'vision' => [
                'title' => 'Vision, Mission & Core Values',
                'body' => companyContentDefaultVision(),
            ],
        ];

        foreach ($defaults as $key => $meta) {
            $st = $conn->prepare('SELECT id FROM company_content WHERE content_key = ? LIMIT 1');
            $st->bind_param('s', $key);
            $st->execute();
            $exists = $st->get_result()->fetch_assoc();
            $st->close();
            if ($exists) {
                continue;
            }
            $title = $meta['title'];
            $json = json_encode($meta['body'], JSON_UNESCAPED_UNICODE);
            $ins = $conn->prepare(
                'INSERT INTO company_content (content_key, title, body_json, status) VALUES (?, ?, ?, 1)'
            );
            $ins->bind_param('sss', $key, $title, $json);
            $ins->execute();
            $ins->close();
        }
    }

    function getCompanyContent($key, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureCompanyContentTables($conn);
        $key = trim((string) $key);
        $st = $conn->prepare(
            'SELECT * FROM company_content WHERE content_key = ? AND status = 1 LIMIT 1'
        );
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($close) {
            $conn->close();
        }
        if (!$row) {
            return null;
        }
        $body = json_decode((string) ($row['body_json'] ?? ''), true);
        if (!is_array($body)) {
            $body = ($key === 'history') ? companyContentDefaultHistory() : companyContentDefaultVision();
        }
        $row['body'] = $body;
        return $row;
    }

    function saveCompanyContent($key, $title, array $body, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureCompanyContentTables($conn);
        $key = trim((string) $key);
        $title = trim((string) $title);
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $userId = (int) $userId;

        $st = $conn->prepare(
            'INSERT INTO company_content (content_key, title, body_json, status, updated_by)
             VALUES (?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), body_json = VALUES(body_json),
               status = 1, updated_by = VALUES(updated_by)'
        );
        $st->bind_param('sssi', $key, $title, $json, $userId);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err];
    }

    function companyContentLinesToArray($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, "•\t-–—* ");
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /**
     * Escape text and wrap key company facts for highlight display.
     */
    function companyContentHighlightHtml($text)
    {
        $html = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $phrases = [
            'Armor Steel Industries Private Limited',
            'Armor Steel Industries Pvt. Ltd.',
            'Armor Steel Industries',
            'Managing Director Mr. Pritesh Babariya',
            'Founder Mr. Ankur Babariya',
            'Mr. Pritesh Babariya',
            'Mr. Ankur Babariya',
            'Mahadev Casting',
            'ARMOR FIRE',
            'Make in India',
            'Startup India',
            '26,100 sq. ft.',
            'UL Listed',
            'BIS/ISI',
            'ISO-certified',
            '2021',
            '2024',
            'Rajkot, Gujarat',
        ];
        usort($phrases, static function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $tokens = [];
        $i = 0;
        foreach ($phrases as $phrase) {
            $escaped = preg_quote(htmlspecialchars($phrase, ENT_QUOTES, 'UTF-8'), '/');
            $html = preg_replace_callback(
                '/' . $escaped . '/i',
                static function ($m) use (&$tokens, &$i) {
                    $key = "\x00HL" . $i++ . "\x00";
                    $tokens[$key] = '<mark class="co-hl">' . $m[0] . '</mark>';
                    return $key;
                },
                $html,
                1
            );
        }
        if ($tokens) {
            $html = strtr($html, $tokens);
        }
        return nl2br($html);
    }
}
