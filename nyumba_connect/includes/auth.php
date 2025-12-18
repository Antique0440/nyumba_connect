<?php
/**
 * Authentication Middleware for Nyumba Connect Platform
 * Provides role-based access control and authentication functions
 */

// Prevent direct access
if (!defined('NYUMBA_CONNECT')) {
    die('Direct access not permitted');
}

/**
 * Enhanced authentication check with session validation
 */
function authenticate_user($email, $password)
{
    try {
        $db = getDB();
        $user = $db->fetchOne(
            "SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            return false;
        }

        $passwordCheck = verifyPasswordSecure($password, $user['password_hash']);

        if (!$passwordCheck['valid']) {
            return false;
        }

        if (!$user['is_active']) {
            return ['error' => 'Account is deactivated'];
        }

        // Update password hash if needed
        if ($passwordCheck['needs_rehash']) {
            try {
                $db->execute(
                    "UPDATE users SET password_hash = ? WHERE user_id = ?",
                    [$passwordCheck['new_hash'], $user['user_id']]
                );
            } catch (Exception $e) {
                error_log("Password rehash error: " . $e->getMessage());
            }
        }

        return $user;
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is authenticated and session is valid
 */
function check_auth()
{
    // Check if user is logged in
    if (!isLoggedIn()) {
        return false;
    }

    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        if ($inactive_time > SESSION_TIMEOUT) {
            logout_user();
            return false;
        }
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    // Verify user still exists and is active
    try {
        $db = getDB();
        $user = $db->fetchOne(
            "SELECT is_active FROM users WHERE user_id = ?",
            [getCurrentUserId()]
        );

        if (!$user || !$user['is_active']) {
            logout_user();
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has required role
 */
function check_role($required_role)
{
    if (!check_auth()) {
        return false;
    }

    $user_role = getCurrentUserRole();

    // Admin has access to everything
    if ($user_role === ROLE_ADMIN) {
        return true;
    }

    // Check specific role
    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    }

    return $user_role === $required_role;
}

/**
 * Logout user and destroy session
 */
function logout_user()
{
    // Log the logout
    if (isLoggedIn()) {
        logActivity(getCurrentUserId(), 'Logout', "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    // Destroy session
    session_unset();
    session_destroy();

    // Start new session for flash messages
    session_start();
    session_regenerate_id(true);
}

/**
 * Redirect to login with intended URL
 */
function redirect_to_login($message = null)
{
    // Store intended URL for redirect after login
    if (!isset($_SESSION['intended_url']) && isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
    }

    if ($message) {
        setFlashMessage('warning', $message);
    }

    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

/**
 * Require authentication middleware
 */
function require_auth()
{
    if (!check_auth()) {
        redirect_to_login('Please log in to access this page.');
    }
}

/**
 * Require specific role middleware
 */
function require_role($required_role, $redirect_url = null)
{
    if (!check_role($required_role)) {
        if (!isLoggedIn()) {
            redirect_to_login('Please log in to access this page.');
        } else {
            $redirect_url = $redirect_url ?: SITE_URL . '/dashboard.php';
            redirectWithMessage($redirect_url, 'error', 'You do not have permission to access this page.');
        }
    }
}

/**
 * Require admin access middleware
 */
function require_admin($redirect_url = null)
{
    require_role(ROLE_ADMIN, $redirect_url);
}

/**
 * Require alumni access middleware
 */
function require_alumni($redirect_url = null)
{
    require_role(ROLE_ALUMNI, $redirect_url);
}

/**
 * Require student access middleware
 */
function require_student($redirect_url = null)
{
    require_role(ROLE_STUDENT, $redirect_url);
}

/**
 * Check if user can access specific resource
 */
function can_access_resource($resource_type, $resource_id = null, $owner_id = null)
{
    if (!check_auth()) {
        return false;
    }

    $user_id = getCurrentUserId();
    $user_role = getCurrentUserRole();

    // Admin can access everything
    if ($user_role === ROLE_ADMIN) {
        return true;
    }

    switch ($resource_type) {
        case 'profile':
            // Users can only access their own profile
            return $user_id == $resource_id;

        case 'messages':
            // Users can only access messages in their mentorship relationships
            if (!$resource_id)
                return false;

            try {
                $db = getDB();
                $mentorship = $db->fetchOne(
                    "SELECT mentorship_id FROM mentorships WHERE mentorship_id = ? AND (student_id = ? OR alumni_id = ?) AND active = TRUE",
                    [$resource_id, $user_id, $user_id]
                );
                return $mentorship !== false;
            } catch (Exception $e) {
                return false;
            }

        case 'opportunity_application':
            // Users can only access their own applications
            return $user_id == $owner_id;

        case 'mentorship_request':
            // Users can access requests they sent or received
            if (!$resource_id)
                return false;

            try {
                $db = getDB();
                $request = $db->fetchOne(
                    "SELECT request_id FROM mentorship_requests WHERE request_id = ? AND (student_id = ? OR alumni_id = ?)",
                    [$resource_id, $user_id, $user_id]
                );
                return $request !== false;
            } catch (Exception $e) {
                return false;
            }

        default:
            return false;
    }
}

/**
 * Check if user owns resource
 */
function owns_resource($table, $id_field, $resource_id, $owner_field = 'user_id')
{
    if (!check_auth()) {
        return false;
    }

    $user_id = getCurrentUserId();
    $user_role = getCurrentUserRole();

    // Admin owns everything
    if ($user_role === ROLE_ADMIN) {
        return true;
    }

    try {
        $db = getDB();
        $resource = $db->fetchOne(
            "SELECT {$owner_field} FROM {$table} WHERE {$id_field} = ?",
            [$resource_id]
        );

        return $resource && $resource[$owner_field] == $user_id;
    } catch (Exception $e) {
        error_log("Resource ownership check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rate limiting for sensitive operations
 */
function check_rate_limit($action, $limit = 5, $window = 300)
{
    $key = 'rate_limit_' . $action . '_' . (getCurrentUserId() ?: session_id());

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $now = time();
    $attempts = $_SESSION[$key];

    // Remove old attempts outside the window
    $attempts = array_filter($attempts, function ($timestamp) use ($now, $window) {
        return ($now - $timestamp) < $window;
    });

    // Check if limit exceeded
    if (count($attempts) >= $limit) {
        return false;
    }

    // Add current attempt
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;

    return true;
}

/**
 * Enhanced session security validation
 */
function validate_session_security()
{
    // Check for session hijacking - User Agent
    if (isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            logSecurityEvent('Session Hijacking Attempt', 'User agent mismatch', 'HIGH');
            logout_user();
            redirect_to_login('Session security violation detected.');
        }
    } else {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    // Check for IP changes with subnet tolerance
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($_SESSION['ip_address'])) {
        if ($_SESSION['ip_address'] !== $current_ip) {
            // Check if IPs are in same subnet (more lenient for mobile users)
            $session_ip_parts = explode('.', $_SESSION['ip_address']);
            $current_ip_parts = explode('.', $current_ip);

            // If first 3 octets are different, it's suspicious
            if (count($session_ip_parts) === 4 && count($current_ip_parts) === 4) {
                $subnet_match = ($session_ip_parts[0] === $current_ip_parts[0] &&
                    $session_ip_parts[1] === $current_ip_parts[1] &&
                    $session_ip_parts[2] === $current_ip_parts[2]);

                if (!$subnet_match) {
                    logSecurityEvent('Suspicious IP Change', "From: {$_SESSION['ip_address']} To: {$current_ip}", 'MEDIUM');
                    // Don't logout immediately, but require re-authentication for sensitive operations
                    $_SESSION['ip_changed'] = true;
                    $_SESSION['ip_change_time'] = time();
                }
            }

            logActivity(getCurrentUserId(), 'IP Change', "From: {$_SESSION['ip_address']} To: {$current_ip}");
            $_SESSION['ip_address'] = $current_ip;
        }
    } else {
        $_SESSION['ip_address'] = $current_ip;
    }

    // Check session age and regenerate if needed
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    }

    // Force re-authentication if session is too old (24 hours)
    if (time() - $_SESSION['created_at'] > 86400) {
        logSecurityEvent('Session Expired', 'Session older than 24 hours', 'INFO');
        logout_user();
        redirect_to_login('Your session has expired. Please log in again.');
    }

    // Check for concurrent sessions (basic check)
    if (isset($_SESSION['last_request_time'])) {
        $time_diff = time() - $_SESSION['last_request_time'];
        if ($time_diff < 1) { // Requests less than 1 second apart might indicate session sharing
            logSecurityEvent('Rapid Requests', "Requests {$time_diff} seconds apart", 'LOW');
        }
    }
    $_SESSION['last_request_time'] = time();
}

/**
 * Check if user can message another user (must have active mentorship)
 */
function check_messaging_permission($user1_id, $user2_id)
{
    if (!check_auth()) {
        return false;
    }

    try {
        $db = getDB();
        $mentorship = $db->fetchOne(
            "SELECT mentorship_id FROM mentorships 
             WHERE ((student_id = ? AND alumni_id = ?) OR (student_id = ? AND alumni_id = ?)) 
             AND active = TRUE",
            [$user1_id, $user2_id, $user2_id, $user1_id]
        );
        return $mentorship !== false;
    } catch (Exception $e) {
        error_log("Messaging permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get mentorship ID between two users
 */
function get_mentorship_id($user1_id, $user2_id)
{
    try {
        $db = getDB();
        $mentorship = $db->fetchOne(
            "SELECT mentorship_id FROM mentorships 
             WHERE ((student_id = ? AND alumni_id = ?) OR (student_id = ? AND alumni_id = ?)) 
             AND active = TRUE",
            [$user1_id, $user2_id, $user2_id, $user1_id]
        );
        return $mentorship ? $mentorship['mentorship_id'] : false;
    } catch (Exception $e) {
        error_log("Get mentorship ID error: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify user has access to specific mentorship
 */
function verify_mentorship_access($mentorship_id, $user_id)
{
    try {
        $db = getDB();
        $mentorship = $db->fetchOne(
            "SELECT mentorship_id FROM mentorships 
             WHERE mentorship_id = ? AND (student_id = ? OR alumni_id = ?) AND active = TRUE",
            [$mentorship_id, $user_id, $user_id]
        );
        return $mentorship !== false;
    } catch (Exception $e) {
        error_log("Mentorship access verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize authentication for protected pages
 */
function init_auth()
{
    if (isLoggedIn()) {
        validate_session_security();
    }
}