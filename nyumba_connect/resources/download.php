<?php
/**
 * Resource Download Handler
 * Provides secure file serving with authentication and download tracking
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

$currentUserId = getCurrentUserId();
$resourceId = intval($_GET['id'] ?? 0);

// Validate resource ID
if (empty($resourceId)) {
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Invalid resource ID');
}

try {
    $db = getDB();

    // Get resource information
    $resource = $db->fetchOne(
        "SELECT r.*, u.name as uploader_name
         FROM resources r
         JOIN users u ON r.uploaded_by = u.user_id
         WHERE r.resource_id = ?",
        [$resourceId]
    );

    if (!$resource) {
        redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Resource not found');
    }

    // Build full file path
    $filePath = __DIR__ . '/../assets/uploads/resources/' . basename($resource['file_path']);

    // Check if file exists
    if (!file_exists($filePath)) {
        error_log("Resource file not found: " . $filePath);
        redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Resource file not found on server');
    }

    // Check if file is readable
    if (!is_readable($filePath)) {
        error_log("Resource file not readable: " . $filePath);
        redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Unable to access resource file');
    }

    // Update download count
    $db->execute(
        "UPDATE resources SET download_count = download_count + 1 WHERE resource_id = ?",
        [$resourceId]
    );

    // Log download activity
    logActivity($currentUserId, 'Resource Downloaded', "Resource ID: $resourceId, Title: " . $resource['title']);

    // Get file information
    $fileSize = filesize($filePath);
    $fileName = basename($resource['file_path']);
    $mimeType = mime_content_type($filePath);

    // If mime type detection fails, use default based on extension
    if (!$mimeType) {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'pdf':
                $mimeType = 'application/pdf';
                break;
            case 'doc':
                $mimeType = 'application/msword';
                break;
            case 'docx':
                $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
            case 'xls':
                $mimeType = 'application/vnd.ms-excel';
                break;
            case 'xlsx':
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'ppt':
                $mimeType = 'application/vnd.ms-powerpoint';
                break;
            case 'pptx':
                $mimeType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                break;
            case 'txt':
                $mimeType = 'text/plain';
                break;
            default:
                $mimeType = 'application/octet-stream';
        }
    }

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');

    // Prevent caching
    header('Cache-Control: no-cache, no-store, must-revalidate');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Read and output file in chunks to handle large files
    $chunkSize = 8192; // 8KB chunks
    $handle = fopen($filePath, 'rb');

    if ($handle === false) {
        error_log("Failed to open resource file: " . $filePath);
        redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Unable to open resource file');
    }

    // Output file in chunks
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) {
            break;
        }
        echo $chunk;

        // Flush output to browser
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    fclose($handle);
    exit;

} catch (Exception $e) {
    error_log("Error downloading resource: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'An error occurred while downloading the resource');
}
?>