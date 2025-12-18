<?php
/**
 * Resource Deletion Handler
 * Allows administrators to delete resources and clean up files
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require admin authentication
require_admin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Invalid request method');
}

// Check CSRF token
if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Invalid security token');
}

$currentUserId = getCurrentUserId();
$resourceId = intval($_POST['resource_id'] ?? 0);

// Validate resource ID
if (empty($resourceId)) {
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Invalid resource ID');
}

try {
    $db = getDB();

    // Get resource information before deletion
    $resource = $db->fetchOne(
        "SELECT * FROM resources WHERE resource_id = ?",
        [$resourceId]
    );

    if (!$resource) {
        redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Resource not found');
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Delete resource record from database
        $stmt = $db->execute(
            "DELETE FROM resources WHERE resource_id = ?",
            [$resourceId]
        );

        if ($stmt && $stmt->rowCount() > 0) {
            // Delete physical file
            $filePath = __DIR__ . '/../assets/uploads/resources/' . basename($resource['file_path']);

            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    error_log("Failed to delete resource file: " . $filePath);
                    // Continue anyway - database record is already deleted
                }
            }

            // Commit transaction
            $db->commit();

            // Log activity
            logActivity($currentUserId, 'Resource Deleted', "Resource ID: $resourceId, Title: " . $resource['title']);

            redirectWithMessage(
                SITE_URL . '/resources/list.php',
                'success',
                'Resource deleted successfully'
            );
        } else {
            $db->rollback();
            redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'Failed to delete resource');
        }

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error deleting resource: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/resources/list.php', 'error', 'An error occurred while deleting the resource');
}
?>