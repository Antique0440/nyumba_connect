<?php
/**
 * Secure CV Upload System for Nyumba Connect Platform
 * Handles PDF file uploads with comprehensive validation and security measures
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Initialize authentication
init_auth();
require_auth();

$user_id = getCurrentUserId();
$errors = [];
$success_message = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_cv'])) {
    // Verify CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Check if file was uploaded
        if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select a CV file to upload.';
        } else {
            $file = $_FILES['cv_file'];

            // Check for upload errors
            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'File is too large. Maximum size allowed is ' . formatFileSize(MAX_FILE_SIZE) . '.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'File upload was interrupted. Please try again.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:
                case UPLOAD_ERR_EXTENSION:
                    $errors[] = 'Server error occurred during upload. Please try again.';
                    error_log("CV upload error: " . $file['error']);
                    break;
                default:
                    $errors[] = 'Unknown upload error occurred.';
                    break;
            }

            if (empty($errors)) {
                // Validate file size
                if ($file['size'] > MAX_FILE_SIZE) {
                    $errors[] = 'File is too large. Maximum size allowed is ' . formatFileSize(MAX_FILE_SIZE) . '.';
                }

                // Validate file type using multiple methods
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $detected_type = finfo_file($file_info, $file['tmp_name']);
                finfo_close($file_info);

                $allowed_types = ALLOWED_CV_TYPES;
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($detected_type, $allowed_types) || $file_extension !== 'pdf') {
                    $errors[] = 'Only PDF files are allowed for CV uploads.';
                }

                // Additional PDF validation
                if (empty($errors)) {
                    $file_content = file_get_contents($file['tmp_name']);
                    if (substr($file_content, 0, 4) !== '%PDF') {
                        $errors[] = 'Invalid PDF file format.';
                    }
                }

                // Validate filename
                if (strlen($file['name']) > 255) {
                    $errors[] = 'Filename is too long.';
                }

                // Check for malicious content in filename
                if (preg_match('/[<>:"|?*]/', $file['name'])) {
                    $errors[] = 'Filename contains invalid characters.';
                }
            }

            // Process upload if no errors
            if (empty($errors)) {
                try {
                    $db = getDB();

                    // Get current user info
                    $user = $db->fetchOne(
                        "SELECT name, cv_path FROM accounts WHERE account_id = ?",
                        [$user_id]
                    );

                    if (!$user) {
                        $errors[] = 'User account not found.';
                    } else {
                        // Create upload directory if it doesn't exist
                        if (!is_dir(CV_UPLOAD_PATH)) {
                            if (!mkdir(CV_UPLOAD_PATH, 0755, true)) {
                                $errors[] = 'Failed to create upload directory.';
                                error_log("Failed to create CV upload directory: " . CV_UPLOAD_PATH);
                            }
                        }

                        if (empty($errors)) {
                            // Generate unique filename
                            $file_extension = 'pdf';
                            $base_filename = 'cv_' . $user_id . '_' . time();
                            $filename = $base_filename . '.' . $file_extension;
                            $upload_path = CV_UPLOAD_PATH . $filename;

                            // Ensure filename is unique
                            $counter = 1;
                            while (file_exists($upload_path)) {
                                $filename = $base_filename . '_' . $counter . '.' . $file_extension;
                                $upload_path = CV_UPLOAD_PATH . $filename;
                                $counter++;
                            }

                            // Move uploaded file
                            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                                // Set proper file permissions
                                chmod($upload_path, 0644);

                                // Delete old CV file if exists
                                if (!empty($user['cv_path']) && file_exists($user['cv_path'])) {
                                    unlink($user['cv_path']);
                                }

                                // Update database with new CV path
                                $db->execute(
                                    "UPDATE accounts SET cv_path = ? WHERE account_id = ?",
                                    [$upload_path, $user_id]
                                );

                                // Log the activity
                                logActivity($user_id, 'CV Upload', "Uploaded new CV: $filename");

                                $success_message = 'CV uploaded successfully!';
                            } else {
                                $errors[] = 'Failed to save uploaded file. Please try again.';
                                error_log("Failed to move uploaded CV file to: $upload_path");
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Database error occurred. Please try again.';
                    error_log("CV upload database error: " . $e->getMessage());
                }
            }
        }
    }
}

// Generate CSRF token
generateCSRFToken();

// Get current CV info
$current_cv = null;
try {
    $db = getDB();
    $user = $db->fetchOne(
        "SELECT name, cv_path FROM accounts WHERE account_id = ?",
        [$user_id]
    );

    if ($user && !empty($user['cv_path']) && file_exists($user['cv_path'])) {
        $current_cv = [
            'path' => $user['cv_path'],
            'size' => filesize($user['cv_path']),
            'uploaded' => filemtime($user['cv_path'])
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching current CV info: " . $e->getMessage());
}

$page_title = 'Upload CV';
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">CV Upload</h4>
                    <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>

                <div class="card-body">
                    <!-- Display success message -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Display errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Current CV Info -->
                    <?php if ($current_cv): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-file-pdf"></i> Current CV</h6>
                            <p class="mb-2">
                                <strong>Uploaded:</strong>
                                <?php echo date('F j, Y \a\t g:i A', $current_cv['uploaded']); ?><br>
                                <strong>Size:</strong> <?php echo formatFileSize($current_cv['size']); ?>
                            </p>
                            <a href="<?php echo SITE_URL; ?>/download_cv.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download"></i> Download Current CV
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" id="cvUploadForm">
                        <?php echo getCSRFTokenField(); ?>

                        <div class="mb-4">
                            <label for="cv_file" class="form-label">
                                <?php echo $current_cv ? 'Upload New CV' : 'Select CV File'; ?>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control" id="cv_file" name="cv_file"
                                accept=".pdf,application/pdf" required>
                            <div class="form-text">
                                <strong>Requirements:</strong>
                                <ul class="mb-0 mt-1">
                                    <li>File must be in PDF format</li>
                                    <li>Maximum file size: <?php echo formatFileSize(MAX_FILE_SIZE); ?></li>
                                    <li>File should contain your complete CV/resume</li>
                                </ul>
                            </div>
                        </div>

                        <!-- File Preview -->
                        <div id="filePreview" class="mb-3" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Selected File:</h6>
                                    <div id="fileInfo"></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="upload_cv" class="btn btn-primary" id="uploadBtn">
                                <i class="fas fa-upload"></i>
                                <?php echo $current_cv ? 'Replace CV' : 'Upload CV'; ?>
                            </button>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>

                    <!-- Upload Guidelines -->
                    <div class="mt-4">
                        <h6>CV Upload Guidelines:</h6>
                        <div class="small text-muted">
                            <ul>
                                <li><strong>Format:</strong> Only PDF files are accepted for security and compatibility
                                </li>
                                <li><strong>Content:</strong> Include your education, work experience, skills, and
                                    contact information</li>
                                <li><strong>Privacy:</strong> Your CV is only accessible to you and system
                                    administrators</li>
                                <li><strong>Updates:</strong> You can replace your CV at any time by uploading a new
                                    file</li>
                                <li><strong>Applications:</strong> Your current CV will be used for opportunity
                                    applications</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('cv_file');
        const filePreview = document.getElementById('filePreview');
        const fileInfo = document.getElementById('fileInfo');
        const uploadBtn = document.getElementById('uploadBtn');
        const form = document.getElementById('cvUploadForm');

        // File selection handler
        fileInput.addEventListener('change', function () {
            const file = this.files[0];

            if (file) {
                // Validate file type
                if (file.type !== 'application/pdf') {
                    alert('Please select a PDF file.');
                    this.value = '';
                    filePreview.style.display = 'none';
                    return;
                }

                // Validate file size
                const maxSize = <?php echo MAX_FILE_SIZE; ?>;
                if (file.size > maxSize) {
                    alert('File is too large. Maximum size allowed is <?php echo formatFileSize(MAX_FILE_SIZE); ?>.');
                    this.value = '';
                    filePreview.style.display = 'none';
                    return;
                }

                // Show file info
                const fileSize = formatFileSize(file.size);
                const fileName = file.name;

                fileInfo.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    <div>
                        <div><strong>${fileName}</strong></div>
                        <div class="small text-muted">Size: ${fileSize}</div>
                    </div>
                </div>
            `;

                filePreview.style.display = 'block';
            } else {
                filePreview.style.display = 'none';
            }
        });

        // Form submission handler
        form.addEventListener('submit', function (e) {
            const file = fileInput.files[0];

            if (!file) {
                e.preventDefault();
                alert('Please select a CV file to upload.');
                return;
            }

            // Disable submit button to prevent double submission
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        });

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    });
</script>

<?php include 'includes/footer.php'; ?>