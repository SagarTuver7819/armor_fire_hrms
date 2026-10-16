<?php
/**
 * Company Settings Helper
 * Separate logos for Login page and Dashboard header.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Ensure company_settings table has login + dashboard logo columns
 */
function ensureCompanySettingsSchema($conn)
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS company_settings (
            id INT PRIMARY KEY DEFAULT 1,
            company_name VARCHAR(150) NOT NULL DEFAULT 'Armor Fire',
            company_logo VARCHAR(255) DEFAULT NULL,
            login_logo VARCHAR(255) DEFAULT NULL,
            dashboard_logo VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    // Add new columns if upgrading from older schema
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM company_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }

    if (!in_array('login_logo', $cols, true)) {
        $conn->query("ALTER TABLE company_settings ADD COLUMN login_logo VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('dashboard_logo', $cols, true)) {
        $conn->query("ALTER TABLE company_settings ADD COLUMN dashboard_logo VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('header_details', $cols, true)) {
        $conn->query("ALTER TABLE company_settings ADD COLUMN header_details TEXT DEFAULT NULL");
    }
    if (!in_array('footer_details', $cols, true)) {
        $conn->query("ALTER TABLE company_settings ADD COLUMN footer_details TEXT DEFAULT NULL");
    }
    if (!in_array('letterhead_image', $cols, true)) {
        $conn->query("ALTER TABLE company_settings ADD COLUMN letterhead_image VARCHAR(255) DEFAULT NULL");
    }

    $conn->query(
        "INSERT INTO company_settings (id, company_name)
         VALUES (1, 'Armor Fire')
         ON DUPLICATE KEY UPDATE id = id"
    );

    // Seed approved letterhead if empty and file exists
    $defaultLh = 'assets/uploads/letterhead/Letterhead_Approved_Final.png';
    if (is_file(__DIR__ . '/../' . $defaultLh)) {
        $conn->query(
            "UPDATE company_settings
             SET letterhead_image = '" . $conn->real_escape_string($defaultLh) . "'
             WHERE id = 1
               AND (letterhead_image IS NULL OR letterhead_image = '')"
        );
    }

    // Migrate old single logo into both slots if empty
    $conn->query(
        "UPDATE company_settings
         SET login_logo = company_logo
         WHERE id = 1
           AND (login_logo IS NULL OR login_logo = '')
           AND company_logo IS NOT NULL
           AND company_logo <> ''"
    );
    $conn->query(
        "UPDATE company_settings
         SET dashboard_logo = company_logo
         WHERE id = 1
           AND (dashboard_logo IS NULL OR dashboard_logo = '')
           AND company_logo IS NOT NULL
           AND company_logo <> ''"
    );
}

/**
 * Get company settings
 */
function getCompanySettings()
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    $defaultLogo = 'assets/images/logo-placeholder.svg';
    $settings = [
        'company_name'      => 'Armor Fire',
        'login_logo'        => $defaultLogo,
        'dashboard_logo'    => $defaultLogo,
        'company_logo'      => $defaultLogo, // legacy
        'header_details'    => '',
        'footer_details'    => '',
        'letterhead_image'  => '',
    ];

    $conn = @getDBConnection();
    if (!$conn) {
        return $settings;
    }

    ensureCompanySettingsSchema($conn);

    $result = $conn->query(
        "SELECT company_name, company_logo, login_logo, dashboard_logo, header_details, footer_details, letterhead_image
         FROM company_settings WHERE id = 1 LIMIT 1"
    );

    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['company_name'])) {
            $settings['company_name'] = $row['company_name'];
        }

        $login = !empty($row['login_logo']) ? $row['login_logo'] : $row['company_logo'];
        $dash  = !empty($row['dashboard_logo']) ? $row['dashboard_logo'] : $row['company_logo'];

        if (!empty($login) && file_exists(__DIR__ . '/../' . $login)) {
            $settings['login_logo'] = $login;
            $settings['company_logo'] = $login;
        }
        if (!empty($dash) && file_exists(__DIR__ . '/../' . $dash)) {
            $settings['dashboard_logo'] = $dash;
        }
        $settings['header_details'] = trim((string) ($row['header_details'] ?? ''));
        $settings['footer_details'] = trim((string) ($row['footer_details'] ?? ''));
        $lh = trim((string) ($row['letterhead_image'] ?? ''));
        if ($lh !== '' && file_exists(__DIR__ . '/../' . $lh)) {
            $settings['letterhead_image'] = $lh;
        }
    }

    $conn->close();
    return $settings;
}

/**
 * Login page logo
 */
function getLoginLogo()
{
    $settings = getCompanySettings();
    return $settings['login_logo'];
}

/**
 * Dashboard / header logo
 */
function getDashboardLogo()
{
    $settings = getCompanySettings();
    return $settings['dashboard_logo'];
}

/**
 * Legacy helper (returns dashboard logo)
 */
function getCompanyLogo()
{
    return getDashboardLogo();
}

/**
 * Company display name
 */
function getCompanyName()
{
    $settings = getCompanySettings();
    return $settings['company_name'];
}

/**
 * Document / PDF header details (address, phone, GST etc.)
 */
function getCompanyHeaderDetails()
{
    $settings = getCompanySettings();
    return (string) ($settings['header_details'] ?? '');
}

/**
 * Document / PDF footer details
 */
function getCompanyFooterDetails()
{
    $settings = getCompanySettings();
    return (string) ($settings['footer_details'] ?? '');
}

/**
 * Approved letterhead image path (relative), used on all print / PDF documents
 */
function getCompanyLetterheadImage()
{
    $settings = getCompanySettings();
    return (string) ($settings['letterhead_image'] ?? '');
}

/**
 * Check if path is a custom uploaded logo (not default placeholder)
 */
function isCustomLogo($path)
{
    return !empty($path)
        && $path !== 'assets/images/logo-placeholder.svg'
        && file_exists(__DIR__ . '/../' . $path);
}
