<?php
/**
 * Secure CV Download Handler for Nyumba Connect Platform
 * Provides authenticated access to CV files with proper security checks
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Initialize authentication
init_auth();
require_auth();

$user_id = getCurrentUserId();
$requested_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;

// Verify access permissions
// Users can download their own CV, admins can download any CV
if ($requested_user_id !== $user_id && !isAdmin()) {
    http_response_code(403);
    die('Access denied. You can only download your own CV.');
}

try {
    $db = getDB();

    // Get user CV information
    $user = $db->fetchOne(
        "SELECT name, cv_path FROM accounts WHERE account_id = ? AND is_active = TRUE",
        [$requested_user_id]
    );

    if (!$user) {
        http_response_code(404);
        die('User not found or account is inactive.');
    }

    if (empty($user['cv_path'])) {
        http_response_code(404);
        die('No CV found for this user.');
    }

    $cv_path = $user['cv_path'];

    // Verify file exists and is within allowed directory
    if (!file_exists($cv_path) || !is_readable($cv_path)) {
        http_response_code(404);
        die('CV file not found or not accessible.');
    }

    // Security check: ensure file is within CV upload directory
    $real_cv_path = realpath($cv_path);
    $real_upload_path = realpath(CV_UPLOAD_PATH);

    if (!$real_cv_path || !$real_upload_path || strpos($real_cv_path, $real_upload_path) !== 0) {
        http_response_code(403);
        error_log("Security violation: Attempted access to file outside CV directory: $cv_path");
        die('Access denied. Invalid file path.');
    }

    // Get file information
    $file_size = filesize($cv_path);
    $file_name = sanitizeFilename($user['name'] . '_CV.pdf');

    // Log the download
    logActivity($user_id, 'CV Download', "Downloaded CV for user ID: $requested_user_id");

    // Set headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');

    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Output file content
    readfile($cv_path);
    exit;

} catch (Exception $e) {
    error_log("CV download error: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred while downloading the CV. Please try again.');
}
?>