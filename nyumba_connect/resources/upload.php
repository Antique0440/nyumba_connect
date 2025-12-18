<?php
/**
 * Admin Resource Upload System
 * Allows administrators to upload and manage platform resources
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require admin authentication
require_admin();

$pageTitle = 'Upload Resource';
$currentUserId = getCurrentUserId();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $uploadedFile = $_FILES['resource_file'] ?? null;

        $errors = [];

        // Validate title
        if (empty($title)) {
            $errors[] = 'Resource title is required';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Resource title is too long (maximum 255 characters)';
        }

        // Validate file upload
        if (!$uploadedFile || $uploadedFile['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select a file to upload';
        } elseif ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error occurred';
        } else {
            // Define allowed file types and maximum size
            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain'
            ];

            $maxFileSize = 10 * 1024 * 1024; // 10MB

            // Validate file
            $fileErrors = validateFileUpload($uploadedFile, $allowedTypes, $maxFileSize);
            $errors = array_merge($errors, $fileErrors);
        }

        // If no errors, process upload
        if (empty($errors)) {
            try {
                $db = getDB();

                // Create upload directory if it doesn't exist
                $uploadDir = __DIR__ . '/../assets/uploads/resources/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generate unique filename
                $originalName = $uploadedFile['name'];
                $uniqueFilename = generateUniqueFilename($originalName);
                $filePath = $uploadDir . $uniqueFilename;

                // Move uploaded file
                if (move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                    // Insert resource record
                    $stmt = $db->execute(
                        "INSERT INTO resources (title, file_path, uploaded_by, created_at) 
                         VALUES (?, ?, ?, NOW())",
                        [$title, $uniqueFilename, $currentUserId]
                    );

                    if ($stmt) {
                        $resourceId = $db->lastInsertId();

                        // Log activity
                        logActivity($currentUserId, 'Resource Uploaded', "Resource ID: $resourceId, Title: $title");

                        redirectWithMessage(
                            SITE_URL . '/resources/list.php',
                            'success',
                            'Resource uploaded successfully!'
                        );
                    } else {
                        // Delete uploaded file if database insert failed
                        unlink($filePath);
                        $errors[] = 'Failed to save resource information';
                    }
                } else {
                    $errors[] = 'Failed to save uploaded file';
                }

            } catch (Exception $e) {
                error_log("Error uploading resource: " . $e->getMessage());
                $errors[] = 'An error occurred while uploading the resource';
            }
        }

        // Display errors
        if (!empty($errors)) {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
    }
}

// Get existing resources for management
try {
    $db = getDB();
    $existingResources = $db->fetchAll(
        "SELECT r.*, u.name as uploader_name
         FROM resources r
         JOIN users u ON r.uploaded_by = u.user_id
         ORDER BY r.created_at DESC
         LIMIT 20"
    );
} catch (Exception $e) {
    error_log("Error fetching existing resources: " . $e->getMessage());
    $existingResources = [];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Upload Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-cloud-upload me-2"></i>Upload New Resource
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?php echo getCSRFTokenField(); ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">Resource Title *</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="255" required
                            value="<?php echo sanitizeInput($_POST['title'] ?? ''); ?>"
                            placeholder="Enter a descriptive title for the resource">
                        <div class="form-text">
                            Choose a clear, descriptive title that helps users understand what this resource contains.
                        </div>
                        <div class="invalid-feedback">
                            Please provide a resource title.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="resource_file" class="form-label">Resource File *</label>
                        <input type="file" class="form-control" id="resource_file" name="resource_file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
                        <div class="form-text">
                            <strong>Allowed file types:</strong> PDF, Word documents, Excel spreadsheets, PowerPoint
                            presentations, Text files<br>
                            <strong>Maximum file size:</strong> 10MB
                        </div>
                        <div class="invalid-feedback">
                            Please select a file to upload.
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-1"></i>Upload Guidelines</h6>
                        <ul class="mb-0 small">
                            <li>Only upload resources that are relevant for career development</li>
                            <li>Ensure you have the right to distribute the content</li>
                            <li>Use clear, descriptive titles to help users find resources</li>
                            <li>Files should be in good quality and properly formatted</li>
                            <li>Avoid uploading duplicate or outdated resources</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-upload me-1"></i>Upload Resource
                        </button>
                        <a href="<?php echo SITE_URL; ?>/resources/list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Resources
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Upload Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-bar-chart me-1"></i>Upload Statistics
                </h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stats = $db->fetchOne(
                        "SELECT 
                            COUNT(*) as total_resources,
                            SUM(download_count) as total_downloads,
                            AVG(download_count) as avg_downloads
                         FROM resources"
                    );

                    $recentUploads = $db->fetchOne(
                        "SELECT COUNT(*) as count 
                         FROM resources 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
                    );
                    ?>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary mb-0"><?php echo $stats['total_resources'] ?? 0; ?></h4>
                                <small class="text-muted">Total Resources</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success mb-0"><?php echo $stats['total_downloads'] ?? 0; ?></h4>
                            <small class="text-muted">Total Downloads</small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <p class="mb-1">
                            <strong><?php echo $recentUploads['count'] ?? 0; ?></strong> resources uploaded this month
                        </p>
                        <p class="mb-0 small text-muted">
                            Average: <?php echo number_format($stats['avg_downloads'] ?? 0, 1); ?> downloads per resource
                        </p>
                    </div>
                    <?php
                } catch (Exception $e) {
                    echo '<p class="text-muted">Statistics unavailable</p>';
                }
                ?>
            </div>
        </div>

        <!-- File Type Guide -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-file-earmark me-1"></i>Supported File Types
                </h6>
            </div>
            <div class="card-body">
                <div class="row small">
                    <div class="col-6">
                        <ul class="list-unstyled mb-0">
                            <li><i class="bi bi-file-earmark-pdf text-danger me-1"></i>PDF</li>
                            <li><i class="bi bi-file-earmark-word text-primary me-1"></i>Word (.doc, .docx)</li>
                            <li><i class="bi bi-file-earmark-excel text-success me-1"></i>Excel (.xls, .xlsx)</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <ul class="list-unstyled mb-0">
                            <li><i class="bi bi-file-earmark-ppt text-warning me-1"></i>PowerPoint (.ppt, .pptx)</li>
                            <li><i class="bi bi-file-earmark-text text-secondary me-1"></i>Text (.txt)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Resources Management -->
<?php if (!empty($existingResources)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list me-2"></i>Recent Resources
                    </h5>
                    <a href="<?php echo SITE_URL; ?>/resources/list.php" class="btn btn-sm btn-outline-primary">
                        View All Resources
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingResources as $resource): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                                        <?php echo sanitizeInput($resource['title']); ?>
                                    </td>
                                    <td><?php echo sanitizeInput($resource['uploader_name']); ?></td>
                                    <td>
                                        <small><?php echo formatDate($resource['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $resource['download_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo SITE_URL; ?>/resources/download.php?id=<?php echo $resource['resource_id']; ?>"
                                                class="btn btn-outline-primary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger"
                                                onclick="deleteResource(<?php echo $resource['resource_id']; ?>, '<?php echo addslashes($resource['title']); ?>')"
                                                title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // Form validation
    (function () {
        'use strict';
        window.addEventListener('load', function () {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function (form) {
                form.addEventListener('submit', function (event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();

    // File upload preview
    document.getElementById('resource_file').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const maxSize = 10;

            if (fileSize > maxSize) {
                alert(`File size (${fileSize}MB) exceeds the maximum allowed size of ${maxSize}MB`);
                this.value = '';
                return;
            }

            console.log(`Selected file: ${file.name} (${fileSize}MB)`);
        }
    });

    // Delete resource function
    function deleteResource(resourceId, title) {
        if (confirm(`Are you sure you want to delete the resource "${title}"?\n\nThis action cannot be undone and will remove the file from the server.`)) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo SITE_URL; ?>/resources/delete.php';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'resource_id';
            idInput.value = resourceId;

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = 'csrf_token';
            tokenInput.value = '<?php echo generateCSRFToken(); ?>';

            form.appendChild(idInput);
            form.appendChild(tokenInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>