<?php
/**
 * Security Functions for Nyumba Connect Platform
 * Additional security measures and input protection
 */

// Prevent direct access
if (!defined('NYUMBA_CONNECT')) {
    die('Direct access not permitted');
}

/**
 * Set security headers
 */
function setSecurityHeaders()
{
    // Prevent clickjacking
    header('X-Frame-Options: DENY');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (basic)
    $csp = "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "img-src 'self' data: https:; " .
        "font-src 'self' https://cdn.jsdelivr.net; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none';";

    header("Content-Security-Policy: $csp");

    // Only set HSTS if using HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Validate request method
 */
function validateRequestMethod($allowedMethods)
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($method, $allowedMethods)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));
        die('Method Not Allowed');
    }

    return $method;
}

/**
 * Validate request origin (basic CSRF protection)
 */
function validateRequestOrigin()
{
    // Skip validation for GET requests
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return true;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    // Check if origin matches our host
    if (!empty($origin)) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost === $host;
    }

    // Fallback to referer check
    if (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $host;
    }

    // If neither origin nor referer is present, it might be suspicious
    return false;
}

/**
 * Enhanced rate limiting with different limits for different actions
 */
function checkAdvancedRateLimit($action, $identifier = null)
{
    $limits = [
        'login' => ['limit' => 5, 'window' => 900],      // 5 attempts per 15 minutes
        'register' => ['limit' => 3, 'window' => 3600],  // 3 attempts per hour
        'upload' => ['limit' => 10, 'window' => 3600],   // 10 uploads per hour
        'message' => ['limit' => 50, 'window' => 3600],  // 50 messages per hour
        'api' => ['limit' => 100, 'window' => 3600],     // 100 API calls per hour
        'default' => ['limit' => 20, 'window' => 3600]   // Default limit
    ];

    $config = $limits[$action] ?? $limits['default'];
    $identifier = $identifier ?: (getCurrentUserId() ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return check_rate_limit($action . '_' . $identifier, $config['limit'], $config['window']);
}

/**
 * Validate file upload security
 */
function validateFileUploadSecurity($file, $allowedTypes, $maxSize = MAX_FILE_SIZE)
{
    $errors = [];

    // Basic upload validation
    $basicErrors = validateFileUpload($file, $allowedTypes, $maxSize);
    if (!empty($basicErrors)) {
        return $basicErrors;
    }

    // Additional security checks
    $tmpName = $file['tmp_name'];
    $originalName = $file['name'];

    // Check for executable file extensions in filename
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'phar', 'js', 'html', 'htm', 'exe', 'bat', 'cmd', 'scr', 'vbs', 'jar'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (in_array($extension, $dangerousExtensions)) {
        $errors[] = "File type '$extension' is not allowed for security reasons.";
    }

    // Check file content for embedded scripts (basic check)
    $fileContent = file_get_contents($tmpName);
    if ($fileContent !== false) {
        // Look for common script patterns
        $scriptPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i'
        ];

        foreach ($scriptPatterns as $pattern) {
            if (preg_match($pattern, $fileContent)) {
                $errors[] = "File contains potentially malicious content.";
                break;
            }
        }
    }

    // Validate MIME type more strictly
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!in_array($detectedMimeType, $allowedTypes)) {
        $errors[] = "File MIME type '$detectedMimeType' does not match allowed types.";
    }

    return $errors;
}

/**
 * Secure file storage with additional protections
 */
function secureFileStorage($file, $uploadPath, $allowedTypes)
{
    // Validate file security
    $errors = validateFileUploadSecurity($file, $allowedTypes);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Generate secure filename
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $secureFilename = sanitizeFilenameSecure($originalName);

    // Add timestamp and random component to prevent conflicts
    $finalFilename = pathinfo($secureFilename, PATHINFO_FILENAME) . '_' . time() . '_' . uniqid() . '.' . $extension;

    // Ensure upload directory exists and is secure
    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            return ['success' => false, 'errors' => ['Failed to create upload directory.']];
        }
    }

    // Create .htaccess file to prevent direct execution
    $htaccessPath = $uploadPath . '.htaccess';
    if (!file_exists($htaccessPath)) {
        $htaccessContent = "# Prevent direct access to uploaded files\n";
        $htaccessContent .= "Options -ExecCGI\n";
        $htaccessContent .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
        $htaccessContent .= "<Files *.php>\n";
        $htaccessContent .= "    Deny from all\n";
        $htaccessContent .= "</Files>\n";

        file_put_contents($htaccessPath, $htaccessContent);
    }

    $fullPath = $uploadPath . $finalFilename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        // Set secure file permissions
        chmod($fullPath, 0644);

        return [
            'success' => true,
            'filename' => $finalFilename,
            'path' => $fullPath,
            'size' => $file['size']
        ];
    } else {
        return ['success' => false, 'errors' => ['Failed to save uploaded file.']];
    }
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '', $severity = 'INFO')
{
    $logEntry = date('Y-m-d H:i:s') . " [$severity] Security Event: $event";

    if (!empty($details)) {
        $logEntry .= " - Details: $details";
    }

    // Add request information
    $logEntry .= " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logEntry .= " - User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    if (isLoggedIn()) {
        $logEntry .= " - User ID: " . getCurrentUserId();
    }

    $logEntry .= PHP_EOL;

    $logFile = __DIR__ . '/../logs/security.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Detect and prevent common attack patterns
 */
function detectAttackPatterns($input)
{
    if (is_array($input)) {
        foreach ($input as $value) {
            if (detectAttackPatterns($value)) {
                return true;
            }
        }
        return false;
    }

    if (!is_string($input)) {
        return false;
    }

    // Common SQL injection patterns
    $sqlPatterns = [
        '/(\bunion\b.*\bselect\b)/i',
        '/(\bselect\b.*\bfrom\b)/i',
        '/(\binsert\b.*\binto\b)/i',
        '/(\bdelete\b.*\bfrom\b)/i',
        '/(\bdrop\b.*\btable\b)/i',
        '/(\bupdate\b.*\bset\b)/i',
        '/(\'|\")(\s*)(or|and)(\s*)(\'|\")/i',
        '/(\bor\b|\band\b)(\s*)(\'|\")(\s*)(\d+|true|false)(\s*)(\'|\")/i'
    ];

    // XSS patterns
    $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload\s*=/i',
        '/onerror\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i'
    ];

    // Path traversal patterns
    $pathPatterns = [
        '/\.\.\//i',
        '/\.\.\\\/i',
        '/%2e%2e%2f/i',
        '/%2e%2e%5c/i'
    ];

    $allPatterns = array_merge($sqlPatterns, $xssPatterns, $pathPatterns);

    foreach ($allPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }

    return false;
}

/**
 * Initialize security measures for each request
 */
function initializeSecurity()
{
    // Set security headers
    setSecurityHeaders();

    // Validate request origin for POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !validateRequestOrigin()) {
        logSecurityEvent('Invalid Request Origin', 'Origin/Referer validation failed', 'WARNING');
        // Don't block immediately, just log for now
    }

    // Check for attack patterns in all input
    $allInput = array_merge($_GET, $_POST, $_COOKIE);
    if (detectAttackPatterns($allInput)) {
        logSecurityEvent('Attack Pattern Detected', 'Suspicious input detected', 'HIGH');
        // Log but don't block - could be false positive
    }

    // Initialize authentication security if user is logged in
    if (isLoggedIn()) {
        init_auth();
    }
}