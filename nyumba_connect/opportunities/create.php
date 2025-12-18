<?php
/**
 * Opportunity Creation Page
 * Allows alumni and admins to post new opportunities
 */

// Define page constants
define('NYUMBA_CONNECT', true);
$pageTitle = 'Post New Opportunity';

// Include required files
require_once __DIR__ . '/../includes/config.php';

// Initialize authentication
init_auth();
require_auth();

// Check if user can post opportunities (alumni or admin)
if (!isAlumni() && !isAdmin()) {
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'You do not have permission to post opportunities.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
    } else {
        // Get and sanitize form data
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        $deadline = sanitizeInput($_POST['deadline'] ?? '');
        $link = sanitizeInput($_POST['link'] ?? '');

        // Validation
        $errors = [];

        if (empty($title)) {
            $errors[] = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title must be less than 255 characters.';
        }

        if (empty($description)) {
            $errors[] = 'Description is required.';
        } elseif (strlen($description) < 50) {
            $errors[] = 'Description must be at least 50 characters long.';
        }

        if (empty($category)) {
            $errors[] = 'Category is required.';
        }

        if (!empty($deadline)) {
            $deadlineTimestamp = strtotime($deadline);
            if (!$deadlineTimestamp || $deadlineTimestamp <= time()) {
                $errors[] = 'Deadline must be a future date.';
            }
        }

        if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
            $errors[] = 'Please enter a valid URL for the external link.';
        }

        // If no errors, save the opportunity
        if (empty($errors)) {
            try {
                $db = getDB();

                // Prepare deadline for database (convert to MySQL date format or NULL)
                $deadlineForDb = !empty($deadline) ? date('Y-m-d', strtotime($deadline)) : null;

                $sql = "INSERT INTO opportunities (title, description, category, deadline, link, posted_by, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                $params = [
                    $title,
                    $description,
                    $category,
                    $deadlineForDb,
                    $link ?: null,
                    getCurrentUserId(),
                    isAdmin() ? STATUS_APPROVED : STATUS_PENDING // Admins auto-approve their posts
                ];

                $db->execute($sql, $params);
                $opportunityId = $db->lastInsertId();

                // Log the activity
                logActivity(getCurrentUserId(), 'Opportunity Created', "ID: {$opportunityId}, Title: {$title}");

                // Set success message based on user role
                if (isAdmin()) {
                    $message = 'Opportunity posted successfully and is now live!';
                    $redirectUrl = SITE_URL . '/opportunities/view.php?id=' . $opportunityId;
                } else {
                    $message = 'Opportunity submitted successfully! It will be reviewed by an administrator before being published.';
                    $redirectUrl = SITE_URL . '/opportunities/list.php';
                }

                redirectWithMessage($redirectUrl, 'success', $message);

            } catch (Exception $e) {
                error_log("Error creating opportunity: " . $e->getMessage());
                setFlashMessage('error', 'Unable to create opportunity. Please try again.');
            }
        } else {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
    }
}

// Predefined categories
$categories = [
    'job' => 'Job Opportunity',
    'internship' => 'Internship',
    'scholarship' => 'Scholarship',
    'course' => 'Course/Training',
    'volunteer' => 'Volunteer Opportunity',
    'mentorship' => 'Mentorship Program',
    'other' => 'Other'
];

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
                <li class="breadcrumb-item active" aria-current="page">Post New Opportunity</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h1 class="h3 mb-0">
                    <i class="bi bi-plus-circle me-2"></i>Post New Opportunity
                </h1>
                <p class="text-muted mb-0 mt-2">
                    Share job opportunities, internships, scholarships, and other career development resources with the
                    Nyumba Connect community.
                </p>
            </div>

            <div class="card-body">
                <?php if (!isAdmin()): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Review Process:</strong> Your opportunity will be reviewed by an administrator before being
                        published to ensure quality and appropriateness.
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <?php echo getCSRFTokenField(); ?>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">
                                Opportunity Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo sanitizeInput($_POST['title'] ?? ''); ?>" maxlength="255" required>
                            <div class="form-text">
                                Enter a clear, descriptive title for the opportunity
                            </div>
                            <div class="invalid-feedback">
                                Please provide a valid opportunity title.
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="category" class="form-label">
                                Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($_POST['category'] ?? '') === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a category.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">
                            Description <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="8" minlength="50"
                            required><?php echo sanitizeInput($_POST['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Provide detailed information about the opportunity, requirements, and application process
                            (minimum 50 characters)
                        </div>
                        <div class="invalid-feedback">
                            Please provide a detailed description (at least 50 characters).
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="deadline" class="form-label">
                                Application Deadline
                            </label>
                            <input type="date" class="form-control" id="deadline" name="deadline"
                                value="<?php echo sanitizeInput($_POST['deadline'] ?? ''); ?>"
                                min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            <div class="form-text">
                                Optional: Set a deadline for applications
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="link" class="form-label">
                                External Link
                            </label>
                            <input type="url" class="form-control" id="link" name="link"
                                value="<?php echo sanitizeInput($_POST['link'] ?? ''); ?>"
                                placeholder="https://example.com/opportunity">
                            <div class="form-text">
                                Optional: Link to external application page or more details
                            </div>
                            <div class="invalid-feedback">
                                Please enter a valid URL.
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                All opportunities are subject to community guidelines
                            </small>
                        </div>

                        <div>
                            <a href="<?php echo SITE_URL; ?>/opportunities/list.php" class="btn btn-secondary me-2">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>
                                <?php echo isAdmin() ? 'Post Opportunity' : 'Submit for Review'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Bootstrap form validation
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

    // Character counter for description
    document.getElementById('description').addEventListener('input', function () {
        const maxLength = 2000;
        const currentLength = this.value.length;
        const remaining = maxLength - currentLength;

        let counterElement = document.getElementById('description-counter');
        if (!counterElement) {
            counterElement = document.createElement('small');
            counterElement.id = 'description-counter';
            counterElement.className = 'form-text';
            this.parentNode.appendChild(counterElement);
        }

        counterElement.textContent = `${currentLength} characters`;
        if (remaining < 100) {
            counterElement.className = 'form-text text-warning';
        } else {
            counterElement.className = 'form-text text-muted';
        }
    });

    // Set minimum date for deadline
    document.getElementById('deadline').min = new Date(Date.now() + 86400000).toISOString().split('T')[0];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>