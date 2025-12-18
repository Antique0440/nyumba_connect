<?php
/**
 * Mentorship Request System - Send Request
 * Allows students to send mentorship requests to alumni
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require student authentication
require_student();

$pageTitle = 'Send Mentorship Request';

// Get alumni list for selection
try {
    $db = getDB();
    $alumni = $db->fetchAll(
        "SELECT user_id, name, education, skills, location, bio 
         FROM users 
         WHERE role = ? AND is_active = TRUE 
         ORDER BY name",
        [ROLE_ALUMNI]
    );
} catch (Exception $e) {
    error_log("Error fetching alumni: " . $e->getMessage());
    $alumni = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }

    // Get and validate input
    $alumni_id = (int) ($_POST['alumni_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    // Validation
    if (empty($alumni_id)) {
        $errors[] = "Please select an alumni mentor.";
    }

    if (empty($message)) {
        $errors[] = "Please provide a message explaining why you want mentorship.";
    } elseif (strlen($message) < 50) {
        $errors[] = "Message must be at least 50 characters long.";
    } elseif (strlen($message) > 1000) {
        $errors[] = "Message must not exceed 1000 characters.";
    }

    // Check if alumni exists and is active
    if (!empty($alumni_id)) {
        try {
            $selectedAlumni = $db->fetchOne(
                "SELECT user_id, name FROM users WHERE user_id = ? AND role = ? AND is_active = TRUE",
                [$alumni_id, ROLE_ALUMNI]
            );

            if (!$selectedAlumni) {
                $errors[] = "Selected alumni is not available for mentorship.";
            }
        } catch (Exception $e) {
            error_log("Error validating alumni: " . $e->getMessage());
            $errors[] = "Error validating alumni selection.";
        }
    }

    // Check for duplicate request
    if (empty($errors)) {
        try {
            $existingRequest = $db->fetchOne(
                "SELECT request_id FROM mentorship_requests 
                 WHERE student_id = ? AND alumni_id = ? AND status IN (?, ?)",
                [getCurrentUserId(), $alumni_id, MENTOR_STATUS_PENDING, MENTOR_STATUS_ACCEPTED]
            );

            if ($existingRequest) {
                $errors[] = "You already have a pending or accepted mentorship request with this alumni.";
            }
        } catch (Exception $e) {
            error_log("Error checking duplicate request: " . $e->getMessage());
            $errors[] = "Error checking existing requests.";
        }
    }

    // Rate limiting check
    if (empty($errors) && !check_rate_limit('mentorship_request', 3, 3600)) {
        $errors[] = "You can only send 3 mentorship requests per hour. Please try again later.";
    }

    // Create mentorship request if no errors
    if (empty($errors)) {
        try {
            $db->execute(
                "INSERT INTO mentorship_requests (student_id, alumni_id, message, status, requested_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [getCurrentUserId(), $alumni_id, $message, MENTOR_STATUS_PENDING]
            );

            // Log the activity
            logActivity(getCurrentUserId(), 'Mentorship Request Sent', "To Alumni ID: $alumni_id");

            redirectWithMessage(
                SITE_URL . '/mentorship/requests.php',
                'success',
                'Mentorship request sent successfully to ' . sanitizeInput($selectedAlumni['name']) . '!'
            );

        } catch (Exception $e) {
            error_log("Error creating mentorship request: " . $e->getMessage());
            $errors[] = "Failed to send mentorship request. Please try again.";
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="bi bi-person-plus me-2"></i>Send Mentorship Request
                </h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitizeInput($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo getCSRFTokenField(); ?>

                    <div class="mb-3">
                        <label for="alumni_id" class="form-label">Select Alumni Mentor *</label>
                        <select class="form-select" id="alumni_id" name="alumni_id" required>
                            <option value="">Choose an alumni mentor...</option>
                            <?php foreach ($alumni as $alum): ?>
                                <option value="<?php echo $alum['user_id']; ?>" <?php echo (isset($_POST['alumni_id']) && $_POST['alumni_id'] == $alum['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($alum['name']); ?>
                                    <?php if (!empty($alum['education'])): ?>
                                        - <?php echo sanitizeInput(truncateText($alum['education'], 50)); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the alumni you would like to request mentorship from.</div>
                    </div>

                    <!-- Alumni Details Preview -->
                    <div id="alumni-details" class="mb-3" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Alumni Details</h6>
                                <div id="alumni-info"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Your Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="6"
                            placeholder="Explain why you would like this person as your mentor, what you hope to learn, and how you plan to make the most of the mentorship opportunity..."
                            required minlength="50"
                            maxlength="1000"><?php echo sanitizeInput($_POST['message'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Minimum 50 characters, maximum 1000 characters.
                            <span id="char-count">0</span> characters entered.
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL; ?>/mentorship/requests.php" class="btn btn-secondary me-md-2">
                            <i class="bi bi-arrow-left me-1"></i>Back to Requests
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Send Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($alumni)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Available Alumni Mentors</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($alumni as $alum): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo sanitizeInput($alum['name']); ?></h6>

                                        <?php if (!empty($alum['education'])): ?>
                                            <p class="card-text">
                                                <strong>Education:</strong><br>
                                                <?php echo sanitizeInput($alum['education']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($alum['skills'])): ?>
                                            <p class="card-text">
                                                <strong>Skills:</strong><br>
                                                <?php echo sanitizeInput($alum['skills']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($alum['location'])): ?>
                                            <p class="card-text">
                                                <strong>Location:</strong> <?php echo sanitizeInput($alum['location']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($alum['bio'])): ?>
                                            <p class="card-text">
                                                <?php echo sanitizeInput(truncateText($alum['bio'], 150)); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const alumniSelect = document.getElementById('alumni_id');
        const alumniDetails = document.getElementById('alumni-details');
        const alumniInfo = document.getElementById('alumni-info');
        const messageTextarea = document.getElementById('message');
        const charCount = document.getElementById('char-count');

        // Alumni data for preview
        const alumniData = <?php echo json_encode($alumni); ?>;

        // Handle alumni selection change
        alumniSelect.addEventListener('change', function () {
            const selectedId = parseInt(this.value);

            if (selectedId) {
                const selectedAlumni = alumniData.find(alum => alum.user_id === selectedId);

                if (selectedAlumni) {
                    let html = '<p><strong>' + selectedAlumni.name + '</strong></p>';

                    if (selectedAlumni.education) {
                        html += '<p><strong>Education:</strong> ' + selectedAlumni.education + '</p>';
                    }

                    if (selectedAlumni.skills) {
                        html += '<p><strong>Skills:</strong> ' + selectedAlumni.skills + '</p>';
                    }

                    if (selectedAlumni.location) {
                        html += '<p><strong>Location:</strong> ' + selectedAlumni.location + '</p>';
                    }

                    if (selectedAlumni.bio) {
                        html += '<p><strong>About:</strong> ' + selectedAlumni.bio + '</p>';
                    }

                    alumniInfo.innerHTML = html;
                    alumniDetails.style.display = 'block';
                }
            } else {
                alumniDetails.style.display = 'none';
            }
        });

        // Character count for message
        function updateCharCount() {
            const count = messageTextarea.value.length;
            charCount.textContent = count;

            if (count < 50) {
                charCount.className = 'text-warning';
            } else if (count > 1000) {
                charCount.className = 'text-danger';
            } else {
                charCount.className = 'text-success';
            }
        }

        messageTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>