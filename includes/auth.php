<?php
/**
 * Authentication Helper Functions
 * Admin, HR, and Employee portal logins.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Force login - redirect if not logged in
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        require_once __DIR__ . '/../config/app.php';
        header('Location: ' . app_url('index.php'));
        exit;
    }
}

/**
 * Check if current user is Admin
 */
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is HR
 */
function isHR()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

/**
 * Employee portal user (custom role assigned)
 */
function isEmployee()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

/**
 * Admin or HR (staff portal users — not employee)
 */
function isStaffUser()
{
    return isAdmin() || isHR();
}

/**
 * Any portal user: Admin, HR, or Employee with login
 */
function isPortalUser()
{
    return isAdmin() || isHR() || isEmployee();
}

/**
 * Force Admin role
 */
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        require_once __DIR__ . '/../config/app.php';
        header('Location: ' . app_url('dashboard.php'));
        exit;
    }
}

/**
 * Force Admin / HR / Employee portal (module pages use requireAccess after this)
 */
function requireStaff()
{
    requireLogin();
    if (!isPortalUser()) {
        require_once __DIR__ . '/../config/app.php';
        header('Location: ' . app_url('index.php'));
        exit;
    }
}

/**
 * Get logged-in user display name
 */
function getUserName()
{
    return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
}

/**
 * Get logged-in user role label
 */
function getUserRoleLabel()
{
    if (!isset($_SESSION['role'])) {
        return '';
    }
    if ($_SESSION['role'] === 'admin') {
        return 'ADMINISTRATOR';
    }
    if ($_SESSION['role'] === 'hr') {
        return 'HR MANAGER';
    }
    if ($_SESSION['role'] === 'employee') {
        $custom = trim((string) ($_SESSION['custom_role_name'] ?? ''));
        return $custom !== '' ? strtoupper($custom) : 'EMPLOYEE';
    }
    return strtoupper((string) $_SESSION['role']);
}

/**
 * Linked employee id for employee portal users
 */
function getSessionEmployeeId()
{
    return (int) ($_SESSION['employee_id'] ?? 0);
}
