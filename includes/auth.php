<?php
/**
 * Authentication Helper Functions
 * Handles session checks for Admin and HR roles.
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
 * Admin or HR (staff portal users)
 */
function isStaffUser()
{
    return isAdmin() || isHR();
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
 * Force Admin or HR
 */
function requireStaff()
{
    requireLogin();
    if (!isStaffUser()) {
        require_once __DIR__ . '/../config/app.php';
        header('Location: ' . app_url('index.php'));
        exit;
    }
}

/**
 * Legacy helper — employee self-login removed from portal
 */
function isEmployee()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
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
    return strtoupper((string) $_SESSION['role']);
}
