<?php
/**
 * Opportunity Application Page
 * Allows students to apply to opportunities with CV and cover letter
 */

// Define page constants
define('NYUMBA_CONNECT', true);

// Include required files
require_once __DIR__ . '/../includes/config.php';

// Initialize authentication
init_auth();
require_auth();

// Only students can apply to opportunities
if (!isStudent()) {
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Only students can apply to opportunities.');
}

// Get opportunity ID
$opportunityId = intval($_GET['id'] ?? 0);

if (!$opportunityId) {
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Invalid opportunity ID.');
}

try {
    $db = getDB();
    
    // Get opportunity details
    $sql = "SELECT o.*, u.name as posted_by_name 
            FROM opportunities o 
            JOIN users u ON o.posted_by = u.user_id 
            WHERE o.opportunity_id = ? AND o.status = ?";
    
    $opportunity = $db->fetchOne($sql, [$opportunityId, STATUS_APPROVED]);
    
    if (!$opportunity) {
        redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Opportunity not found or not available.');
    }
    
    // Check if deadline has passed
    if (!empty($opportunity['deadline']) && isDeadlinePassed($opportunity['deadline'])) {
        redirectWithMessage(
            SITE_URL . '/opportunities/view.php?id=' . $opportunityId, 
            'error', 
            'The application deadline for this opportunity has passed.'
        );
    }
    
    // Check if user has already applied
    $applicationSql = "SELECT application_id FROM applications WHERE opportunity_id = ? AND applicant_id = ?";
    $existingApplication = $db->fetchOne($applicationSql, [$opportunityId, getCurrentUserId()]);
    
    if ($existingApplication) {
        redirectWithMessage(
            SITE_URL . '/opportunities/view.php?id=' . $opportunityId, 
            'warning', 
            'You have already applied to this opportunity.'
        );
    }
    
    // Get user's current CV path
    $userSql = "SELECT cv_path FROM users WHERE user_id = ?";
    $user = $db->fetchOne($userSql, [getCurrentUserId()]);
    $hasCV = !empty($user['cv_path']) && file_exists($user['cv_path']);
    
    // Set page title
    $pageTitle = 'Apply to ' . $opportunity['title'];
    
} catch (Exception $e) {
    error_log("Error loading application page: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Unable to load application page.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        // Get and sanitize form data
        $coverLetter = sanitizeInput($_POST['cover_letter'] ?? '');
        $useExistingCV = isset($_POST['use_existing_cv']) && $_POST['use_existing_cv'] === '1';
        
        // Validation
        $errors = [];
        
        if (empty($coverLetter)) {
            $errors[] = 'Cover letter is required.';
        } elseif (strlen($coverLetter) < 100) {
            $errors[] = 'Cover letter must be at least 100 characters long.';
        }
        
        $cvPath = null;
        
        // Handle CV upload or use existing
        if ($useExistingCV && $hasCV) {
            $cvPath = $user['cv_path'];
        } elseif (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            // Validate uploaded CV
            $cvErrors = validateFileUpload($_FILES['cv'], ALLOWED_CV_TYPES, MAX_FILE_SIZE);
            if (!empty($cvErrors)) {
                $errors = array_merge($errors, $cvErrors);
            } else {
                // Generate unique filename and move file
                $originalName = $_FILES['cv']['name'];
                $uniqueFilename = generateUniqueFilename($originalName);
                $uploadPath = CV_UPLOAD_PATH . $uniqueFilename;
                
                // Ensure upload directory exists
                if (!is_dir(CV_UPLOAD_PATH)) {
                    mkdir(CV_UPLOAD_PATH, 0755, true);
                }
                
                if (move_uploaded_file($_FILES['cv']['tmp_name'], $uploadPath)) {
                    $cvPath = $uploadPath;
                } else {
                    $errors[] = 'Failed to upload CV file. Please try again.';
                }
            }
        } else {
            $errors[] = 'Please upload a CV or use your existing CV from your profile.';
        }
        
        // If no errors, submit the application
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Insert application
                $applicationSql = "INSERT INTO applications (opportunity_id, applicant_id, cv_path, cover_letter, status, applied_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())";
                
                $db->execute($applicationSql, [
                    $opportunityId,
                    getCurrentUserId(),
                    $cvPath,
                    $coverLetter,
                    APP_STATUS_APPLIED
                ]);
                
                $applicationId = $db->lastInsertId();
                
                $db->commit();
                
                // Log the activity
                logActivity(getCurrentUserId(), 'Application Submitted', "Opportunity ID: {$opportunityId}, Application ID: {$applicationId}");
                
                redirectWithMessage(
                    SITE_URL . '/opportunities/view.php?id=' . $opportunityId, 
                    'success', 
                    'Your application has been submitted successfully!'
                );
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Error submitting application: " . $e->getMessage());
                
                // Clean up uploaded file if it exists
                if (isset($uploadPath) && file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                
                setFlashMessage('error', 'Unable to submit application. Please try again.');
            }
        } else {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
    }
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/dashboard.php">Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/opportunities/list.php">Opportunities</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opportunityId; ?>">
                        <?php echo sanitizeInput($opportunity['title']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Apply</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h1 class="h3 mb-1">
                    <i class="bi bi-send me-2"></i>Apply to Opportunity
                </h1>
                <p class="text-muted mb-0">
                    Submit your application for: <strong><?php echo sanitizeInput($opportunity['title']); ?></strong>
                </p>
            </div>
            
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <?php echo getCSRFTokenField(); ?>
                    
                    <!-- CV Section -->
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Curriculum Vitae (CV)
                        </h5>
                        
                        <?php if ($hasCV): ?>
                            <div class="alert alert-info">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="use_existing_cv" 
                                           name="use_existing_cv" 
                                           value="1" 
                                           checked>
                                    <label class="form-check-label" for="use_existing_cv">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <strong>Use my existing CV from profile</strong>
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    You have a CV uploaded to your profile. You can use it or upload a new one below.
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div id="cv-upload-section" <?php echo $hasCV ? 'style="display: none;"' : ''; ?>>
                            <label for="cv" class="form-label">
                                Upload CV <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   class="form-control" 
                                   id="cv" 
                                   name="cv" 
                                   accept=".pdf"
                                   <?php echo !$hasCV ? 'required' : ''; ?>>
                            <div class="form-text">
                                Upload your CV in PDF format (max <?php echo formatFileSize(MAX_FILE_SIZE); ?>)
                            </div>
                            <div class="invalid-feedback">
                                Please upload a valid CV file.
                            </div>
                        </div>
                        
                        <?php if (!$hasCV): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>No CV in profile:</strong> You don't have a CV uploaded to your profile. 
                                <a href="<?php echo SITE_URL; ?>/profile.php" class="alert-link">Upload one to your profile</a> 
                                for future applications.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Cover Letter Section -->
                    <div class="mb-4">
                        <label for="cover_letter" class="form-label">
                            <i class="bi bi-file-text me-2"></i>Cover Letter <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="cover_letter" 
                                  name="cover_letter" 
                                  rows="10" 
                                  minlength="100"
                                  required><?php echo sanitizeInput($_POST['cover_letter'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Write a compelling cover letter explaining why you're interested in this opportunity and what makes you a good fit (minimum 100 characters)
                        </div>
                        <div class="invalid-feedback">
                            Please provide a cover letter (at least 100 characters).
                        </div>
                    </div>
                    
                    <!-- Application Guidelines -->
                    <div class="alert alert-light border">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2"></i>Application Guidelines
                        </h6>
                        <ul class="mb-0">
                            <li>Ensure your CV is up-to-date and relevant to the opportunity</li>
                            <li>Write a personalized cover letter for this specific opportunity</li>
                            <li>Review the opportunity details carefully before submitting</li>
                            <li>You can only submit one application per opportunity</li>
                            <?php if (!empty($opportunity['deadline'])): ?>
                                <li>Application deadline: <strong><?php echo formatDateTime($opportunity['deadline']); ?></strong></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                Your application will be sent to the opportunity poster
                            </small>
                        </div>
                        
                        <div>
                            <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opportunityId; ?>" 
                               class="btn btn-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i>Back to Opportunity
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>Submit Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Opportunity Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-briefcase me-1"></i>Opportunity Summary
                </h6>
            </div>
            <div class="card-body">
                <h6 class="card-title"><?php echo sanitizeInput($opportunity['title']); ?></h6>
                <p class="card-text">
                    <span class="badge bg-primary mb-2"><?php echo sanitizeInput(ucfirst($opportunity['category'])); ?></span><br>
                    <small class="text-muted">
                        Posted by <?php echo sanitizeInput($opportunity['posted_by_name']); ?>
                    </small>
                </p>
                
                <?php if (!empty($opportunity['deadline'])): ?>
                    <div class="alert alert-warning py-2">
                        <small>
                            <i class="bi bi-clock me-1"></i>
                            <strong>Deadline:</strong> <?php echo formatDateTime($opportunity['deadline']); ?>
                        </small>
                    </div>
                <?php endif; ?>
                
                <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opportunityId; ?>" 
                   class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-eye me-1"></i>View Full Details
                </a>
            </div>
        </div>
        
        <!-- Application Tips -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-lightbulb me-1"></i>Application Tips
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <small>Tailor your cover letter to the specific opportunity</small>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <small>Highlight relevant skills and experiences</small>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <small>Proofread your application before submitting</small>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <small>Follow up appropriately if contact information is provided</small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle CV upload section based on existing CV checkbox
document.getElementById('use_existing_cv')?.addEventListener('change', function() {
    const uploadSection = document.getElementById('cv-upload-section');
    const cvInput = document.getElementById('cv');
    
    if (this.checked) {
        uploadSection.style.display = 'none';
        cvInput.required = false;
    } else {
        uploadSection.style.display = 'block';
        cvInput.required = true;
    }
});

// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Character counter for cover letter
document.getElementById('cover_letter').addEventListener('input', function() {
    const currentLength = this.value.length;
    const minLength = 100;
    
    let counterElement = document.getElementById('cover-letter-counter');
    if (!counterElement) {
        counterElement = document.createElement('small');
        counterElement.id = 'cover-letter-counter';
        counterElement.className = 'form-text';
        this.parentNode.appendChild(counterElement);
    }
    
    if (currentLength < minLength) {
        counterElement.textContent = `${currentLength}/${minLength} characters (${minLength - currentLength} more needed)`;
        counterElement.className = 'form-text text-warning';
    } else {
        counterElement.textContent = `${currentLength} characters`;
        counterElement.className = 'form-text text-success';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>