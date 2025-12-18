<?php
/**
 * Utility Functions for Nyumba Connect Platform
 * Contains shared functions for validation, sanitization, and common operations
 */

/**
 * Sanitize input to prevent XSS attacks
 */
function sanitizeInput($input)
{
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Deep sanitize input data recursively
 */
function deepSanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('deepSanitizeInput', $data);
    }

    if (is_string($data)) {
        // Remove null bytes
        $data = str_replace("\0", '', $data);

        // Trim whitespace
        $data = trim($data);

        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $data;
    }

    return $data;
}

/**
 * Sanitize for database storage (additional layer)
 */
function sanitizeForDatabase($input)
{
    if (is_array($input)) {
        return array_map('sanitizeForDatabase', $input);
    }

    // Remove null bytes and control characters except newlines and tabs
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

    return trim($input);
}

/**
 * Decode HTML entities for display (fixes double-encoding issues)
 */
function decodeHtmlEntities($input)
{
    if (is_array($input)) {
        return array_map('decodeHtmlEntities', $input);
    }

    // Decode HTML entities back to original characters
    return html_entity_decode($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize URL
 */
function sanitizeUrl($url)
{
    $url = filter_var($url, FILTER_SANITIZE_URL);

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    // Only allow http and https protocols
    $parsed = parse_url($url);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
        return false;
    }

    return $url;
}

/**
 * Sanitize filename for safe storage
 */
function sanitizeFilenameSecure($filename)
{
    // Remove path information and dots at start
    $filename = basename($filename);
    $filename = ltrim($filename, '.');

    // Remove or replace dangerous characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Remove multiple consecutive underscores/dots
    $filename = preg_replace('/[_.]{2,}/', '_', $filename);

    // Trim underscores from start and end
    $filename = trim($filename, '_');

    // Ensure filename is not empty and not too long
    if (empty($filename) || strlen($filename) > 255) {
        $filename = 'file_' . time() . '_' . uniqid();
    }

    // Prevent reserved names on Windows
    $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    if (in_array(strtoupper($name_without_ext), $reserved)) {
        $filename = 'file_' . $filename;
    }

    return $filename;
}

/**
 * Validate email address
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Enhanced password strength validation
 */
function validatePassword($password)
{
    $errors = [];

    // Check length
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    }

    if (strlen($password) > PASSWORD_MAX_LENGTH) {
        $errors[] = "Password must not exceed " . PASSWORD_MAX_LENGTH . " characters.";
    }

    // Check for uppercase letters
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }

    // Check for lowercase letters
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }

    // Check for numbers
    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }

    // Check for special characters (optional)
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }

    // Check for common weak passwords
    $weakPasswords = [
        'password',
        '123456',
        '123456789',
        'qwerty',
        'abc123',
        'password123',
        'admin',
        'letmein',
        'welcome',
        'monkey',
        '1234567890'
    ];

    if (in_array(strtolower($password), $weakPasswords)) {
        $errors[] = "Password is too common. Please choose a stronger password.";
    }

    // Check for repeated characters (more than 3 in a row)
    if (preg_match('/(.)\1{3,}/', $password)) {
        $errors[] = "Password cannot contain more than 3 repeated characters in a row.";
    }

    return empty($errors) ? true : $errors;
}

/**
 * Get password strength score (0-100)
 */
function getPasswordStrength($password)
{
    $score = 0;
    $length = strlen($password);

    // Length scoring
    if ($length >= 8)
        $score += 25;
    if ($length >= 12)
        $score += 15;
    if ($length >= 16)
        $score += 10;

    // Character variety scoring
    if (preg_match('/[a-z]/', $password))
        $score += 10;
    if (preg_match('/[A-Z]/', $password))
        $score += 10;
    if (preg_match('/\d/', $password))
        $score += 10;
    if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password))
        $score += 15;

    // Bonus for mixed case and numbers
    if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password))
        $score += 5;
    if (preg_match('/\d/', $password) && preg_match('/[a-zA-Z]/', $password))
        $score += 5;

    return min(100, $score);
}

/**
 * Hash password securely with enhanced options
 */
function hashPassword($password)
{
    // Use Argon2ID if available, otherwise use bcrypt with higher cost
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    } else {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12, // Higher cost for better security
        ]);
    }
}

/**
 * Verify password and check if rehashing is needed
 */
function verifyPasswordSecure($password, $hash)
{
    $isValid = password_verify($password, $hash);

    if ($isValid && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        // Password is valid but hash needs updating
        return ['valid' => true, 'needs_rehash' => true, 'new_hash' => hashPassword($password)];
    }

    return ['valid' => $isValid, 'needs_rehash' => false];
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate secure random token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes, $maxSize = MAX_FILE_SIZE)
{
    $errors = [];

    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = "No file was uploaded.";
        return $errors;
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error occurred.";
        return $errors;
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File size exceeds maximum allowed size of " . formatBytes($maxSize) . ".";
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowedTypes);
    }

    return $errors;
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

    return $basename . '_' . time() . '_' . uniqid() . '.' . $extension;
}

/**
 * Format file size in human readable format
 */
function formatBytes($size, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }

    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M j, Y')
{
    if (empty($date))
        return '';

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A')
{
    if (empty($datetime))
        return '';

    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Get time ago format
 */
function timeAgo($datetime)
{
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDateTime($datetime);
    }
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate pagination links
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $queryParams = [])
{
    if ($totalPages <= 1)
        return '';

    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';

    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage - 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">Previous</a></li>';
    }

    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? ' active' : '';
        $pageUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $i]));
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage + 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Next</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Display flash messages
 */
function displayFlashMessages()
{
    $types = ['success', 'error', 'warning', 'info'];
    $html = '';

    foreach ($types as $type) {
        if (isset($_SESSION['flash_' . $type])) {
            $alertClass = $type === 'error' ? 'danger' : $type;
            $html .= '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">';
            $html .= sanitizeInput($_SESSION['flash_' . $type]);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
            unset($_SESSION['flash_' . $type]);
        }
    }

    return $html;
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message)
{
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $type, $message)
{
    setFlashMessage($type, $message);
    header('Location: ' . $url);
    exit;
}

/**
 * Check CSRF token with enhanced security
 */
function checkCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Use hash_equals to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token with expiration
 */
function generateCSRFToken($regenerate = false)
{
    // Regenerate token if requested or if it's older than 1 hour
    if (
        $regenerate || !isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 3600
    ) {
        $_SESSION['csrf_token'] = generateToken(32);
        $_SESSION['csrf_token_time'] = time();
    }

    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token input field
 */
function getCSRFTokenField()
{
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Validate CSRF token and regenerate if needed
 */
function validateAndRegenerateCSRF($token)
{
    $isValid = checkCSRFToken($token);

    if ($isValid) {
        // Regenerate token after successful validation for added security
        generateCSRFToken(true);
    }

    return $isValid;
}

/**
 * Enhanced input validation for common data types
 */
function validateInput($input, $type, $options = [])
{
    $input = sanitizeInput($input);

    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;

        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false;

        case 'int':
            $min = $options['min'] ?? null;
            $max = $options['max'] ?? null;
            $flags = 0;

            if ($min !== null || $max !== null) {
                $flags = FILTER_FLAG_ALLOW_OCTAL | FILTER_FLAG_ALLOW_HEX;
                $options_filter = [];
                if ($min !== null)
                    $options_filter['min_range'] = $min;
                if ($max !== null)
                    $options_filter['max_range'] = $max;
                return filter_var($input, FILTER_VALIDATE_INT, ['options' => $options_filter, 'flags' => $flags]) !== false;
            }

            return filter_var($input, FILTER_VALIDATE_INT) !== false;

        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;

        case 'string':
            $min_length = $options['min_length'] ?? 0;
            $max_length = $options['max_length'] ?? 10000;
            $length = strlen($input);
            return $length >= $min_length && $length <= $max_length;

        case 'alphanumeric':
            return ctype_alnum(str_replace([' ', '-', '_'], '', $input));

        case 'phone':
            // Basic phone validation - adjust regex as needed
            return preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $input);

        default:
            return true;
    }
}

/**
 * Batch validate multiple inputs
 */
function validateInputs($inputs, $rules)
{
    $errors = [];

    foreach ($rules as $field => $rule) {
        $value = $inputs[$field] ?? '';
        $type = $rule['type'] ?? 'string';
        $required = $rule['required'] ?? false;
        $options = $rule['options'] ?? [];
        $label = $rule['label'] ?? $field;

        // Check if required field is empty
        if ($required && empty($value)) {
            $errors[] = "$label is required.";
            continue;
        }

        // Skip validation if field is empty and not required
        if (empty($value) && !$required) {
            continue;
        }

        // Validate the input
        if (!validateInput($value, $type, $options)) {
            $message = $rule['error_message'] ?? "$label is not valid.";
            $errors[] = $message;
        }
    }

    return $errors;
}

/**
 * Log activity with fallback mechanisms
 */
function logActivity($userId, $action, $details = '')
{
    $logEntry = date('Y-m-d H:i:s') . " - User ID: $userId - Action: $action";
    if (!empty($details)) {
        $logEntry .= " - Details: $details";
    }
    $logEntry .= PHP_EOL;

    // Try multiple logging approaches
    $logged = false;

    // Attempt 1: Try primary log file
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (is_writable($logDir) || is_writable($logFile)) {
        $result = @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        if ($result !== false) {
            $logged = true;
        }
    }

    // Attempt 2: Try system temp directory
    if (!$logged) {
        $tempLogFile = sys_get_temp_dir() . '/nyumba_activity.log';
        $result = @file_put_contents($tempLogFile, $logEntry, FILE_APPEND | LOCK_EX);
        if ($result !== false) {
            $logged = true;
        }
    }

    // Attempt 3: Use PHP error log as fallback
    if (!$logged) {
        error_log("ACTIVITY LOG: " . trim($logEntry));
    }
}

/**
 * Check if deadline has passed
 */
function isDeadlinePassed($deadline)
{
    if (empty($deadline))
        return false;
    return strtotime($deadline) < time();
}

/**
 * Get user role display name
 */
function getRoleDisplayName($role)
{
    switch ($role) {
        case ROLE_STUDENT:
            return 'Student';
        case ROLE_ALUMNI:
            return 'Alumni';
        case ROLE_ADMIN:
            return 'Administrator';
        default:
            return ucfirst($role);
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status)
{
    $badges = [
        STATUS_PENDING => 'warning',
        STATUS_APPROVED => 'success',
        STATUS_REJECTED => 'danger',
        STATUS_CLOSED => 'secondary',
        APP_STATUS_APPLIED => 'primary',
        APP_STATUS_SHORTLISTED => 'info',
        APP_STATUS_REJECTED => 'danger',
        APP_STATUS_HIRED => 'success',
        MENTOR_STATUS_PENDING => 'warning',
        MENTOR_STATUS_ACCEPTED => 'success',
        MENTOR_STATUS_DECLINED => 'danger'
    ];

    $badgeClass = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $badgeClass . '">' . ucfirst($status) . '</span>';
}

/**
 * Alias for formatBytes function for consistency
 */
function formatFileSize($size, $precision = 2)
{
    return formatBytes($size, $precision);
}

/**
 * Sanitize filename for safe storage
 */
function sanitizeFilename($filename)
{
    // Remove path information and dots
    $filename = basename($filename);

    // Replace spaces and special characters with underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Remove multiple consecutive underscores
    $filename = preg_replace('/_+/', '_', $filename);

    // Trim underscores from start and end
    $filename = trim($filename, '_');

    // Ensure filename is not empty
    if (empty($filename)) {
        $filename = 'file_' . time();
    }

    return $filename;
}
?>