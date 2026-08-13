<?php
/**
 * Armor Fire company defaults (Manufacturing / Plant HRMS)
 * Change brand text here — used across app where needed.
 */

if (!defined('COMPANY_CODE')) {
    define('COMPANY_CODE', 'ARMOR_FIRE');
    define('COMPANY_DEFAULT_NAME', 'Armor Fire');
    define('COMPANY_INDUSTRY', 'Manufacturing');
    define('COMPANY_BRAND_COLOR', '#F58220');
    define('COMPANY_TAGLINE', 'Plant HRMS · Department · Workers · Staff');

    // Common worker categories for manufacturing (use in future forms)
    define('EMPLOYEE_CATEGORIES', 'Staff,Worker,Contract,Trainee,Apprentice');
}
