<?php
/**
 * Configuration File for Nyumba Connect Platform
 * Contains system constants, settings, and security configurations
 */

// Prevent direct access
if (!defined('NYUMBA_CONNECT')) {
    define('NYUMBA_CONNECT', true);
}

// System Constants
define('SITE_NAME', 'Nyumba Connect');
define('SITE_URL', 'http://localhost/nyumbaConnect/nyumba_connect');
define('ADMIN_EMAIL', 'admin@nyumbaconnect.org');

// File Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_CV_TYPES', ['application/pdf']);
define('ALLOWED_RESOURCE_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('CV_UPLOAD_PATH', __DIR__ . '/../assets/uploads/cvs/');
define('RESOURCE_UPLOAD_PATH', __DIR__ . '/../assets/uploads/resources/');

// Enhanced Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 128);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false); // Optional for now
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('FAILED_LOGIN_LOG_THRESHOLD', 3); // Log after 3 failed attempts
define('BRUTE_FORCE_THRESHOLD', 10); // Block IP after 10 failed attempts across all users

// Pagination Settings
define('ITEMS_PER_PAGE', 10);
define('MAX_ITEMS_PER_PAGE', 50);

// User Roles
define('ROLE_STUDENT', 'student');
define('ROLE_ALUMNI', 'alumni');
define('ROLE_ADMIN', 'admin');

// Opportunity Status
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_CLOSED', 'closed');

// Application Status
define('APP_STATUS_APPLIED', 'applied');
define('APP_STATUS_SHORTLISTED', 'shortlisted');
define('APP_STATUS_REJECTED', 'rejected');
define('APP_STATUS_HIRED', 'hired');

// Mentorship Request Status
define('MENTOR_STATUS_PENDING', 'pending');
define('MENTOR_STATUS_ACCEPTED', 'accepted');
define('MENTOR_STATUS_DECLINED', 'declined');

// Enhanced secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 0); // Session cookies only
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    // Use more secure session name
    session_name('NYUMBA_SESSID');

    // Set session save path to a secure location
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }
    session_save_path($sessionPath);

    session_start();

    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Include database connection
require_once __DIR__ . '/db.php';

// Include utility functions
require_once __DIR__ . '/functions.php';

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Include security functions
require_once __DIR__ . '/security.php';

// Initialize security measures
initializeSecurity();

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole()
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if current user has specific role
 */
function hasRole($role)
{
    return getCurrentUserRole() === $role;
}

/**
 * Check if current user is admin
 */
function isAdmin()
{
    return hasRole(ROLE_ADMIN);
}

/**
 * Check if current user is alumni
 */
function isAlumni()
{
    return hasRole(ROLE_ALUMNI);
}

/**
 * Check if current user is student
 */
function isStudent()
{
    return hasRole(ROLE_STUDENT);
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($role)
{
    requireAuth();
    if (!hasRole($role)) {
        header('Location: ' . SITE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin()
{
    requireRole(ROLE_ADMIN);
}