<?php
/**
 * Mentorship Response System
 * Allows alumni to respond to mentorship requests (accept/decline)
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require alumni authentication
require_alumni();

$pageTitle = 'Respond to Mentorship Request';

// Get request ID from URL
$request_id = (int)($_GET['id'] ?? 0);

if (empty($request_id)) {
    redirectWithMessage(
        SITE_URL . '/mentorship/active.php',
        'error',
        'Invalid mentorship request.'
    );
}

// Get the mentorship request details
try {
    $db = getDB();
    $request = $db->fetchOne(
        "SELECT mr.*, s.name as student_name, s.email as student_email, 
                s.education as student_education, s.skills as student_skills, 
                s.location as student_location, s.bio as student_bio
         FROM mentorship_requests mr
         JOIN accounts s ON mr.student_id = s.account_id
         WHERE mr.request_id = ? AND mr.alumni_id = ? AND mr.status = ?",
        [$request_id, getCurrentUserId(), MENTOR_STATUS_PENDING]
    );
    
    if (!$request) {
        redirectWithMessage(
            SITE_URL . '/mentorship/active.php',
            'error',
            'Mentorship request not found or already responded to.'
        );
    }
} catch (Exception $e) {
    error_log("Error fetching mentorship request: " . $e->getMessage());
    redirectWithMessage(
        SITE_URL . '/mentorship/active.php',
        'error',
        'Error loading mentorship request.'
    );
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    // Get response
    $response = $_POST['response'] ?? '';
    
    if (!in_array($response, [MENTOR_STATUS_ACCEPTED, MENTOR_STATUS_DECLINED])) {
        $errors[] = "Please select a valid response.";
    }
    
    // Process the response if no errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update the request status and response timestamp
            $db->execute(
                "UPDATE mentorship_requests 
                 SET status = ?, responded_at = NOW() 
                 WHERE request_id = ? AND alumni_id = ? AND status = ?",
                [$response, $request_id, getCurrentUserId(), MENTOR_STATUS_PENDING]
            );
            
            // If accepted, create mentorship relationship
            if ($response === MENTOR_STATUS_ACCEPTED) {
                // Check if mentorship already exists (shouldn't happen, but safety check)
                $existingMentorship = $db->fetchOne(
                    "SELECT mentorship_id FROM mentorships 
                     WHERE student_id = ? AND alumni_id = ? AND active = TRUE",
                    [$request['student_id'], getCurrentUserId()]
                );
                
                if (!$existingMentorship) {
                    $db->execute(
                        "INSERT INTO mentorships (student_id, alumni_id, started_at, active) 
                         VALUES (?, ?, NOW(), TRUE)",
                        [$request['student_id'], getCurrentUserId()]
                    );
                }
            }
            
            $db->commit();
            
            // Log the activity
            $action = $response === MENTOR_STATUS_ACCEPTED ? 'Mentorship Request Accepted' : 'Mentorship Request Declined';
            logActivity(getCurrentUserId(), $action, "Request ID: $request_id, Student: {$request['student_name']}");
            
            // Redirect with success message
            $message = $response === MENTOR_STATUS_ACCEPTED 
                ? "Mentorship request accepted! You can now start messaging with {$request['student_name']}."
                : "Mentorship request declined.";
                
            redirectWithMessage(
                SITE_URL . '/mentorship/active.php',
                'success',
                $message
            );
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error responding to mentorship request: " . $e->getMessage());
            $errors[] = "Failed to process response. Please try again.";
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
                    <i class="bi bi-person-check me-2"></i>Mentorship Request Response
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

                <!-- Student Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-person me-2"></i>Student Information
                                </h5>
                                
                                <p><strong>Name:</strong> <?php echo sanitizeInput($request['student_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo sanitizeInput($request['student_email']); ?></p>
                                
                                <?php if (!empty($request['student_education'])): ?>
                                    <p><strong>Education:</strong><br>
                                    <?php echo nl2br(sanitizeInput($request['student_education'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($request['student_skills'])): ?>
                                    <p><strong>Skills:</strong><br>
                                    <?php echo nl2br(sanitizeInput($request['student_skills'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($request['student_location'])): ?>
                                    <p><strong>Location:</strong> <?php echo sanitizeInput($request['student_location']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($request['student_bio'])): ?>
                                    <p><strong>About:</strong><br>
                                    <?php echo nl2br(sanitizeInput($request['student_bio'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-clock-history me-2"></i>Request Details
                                </h5>
                                
                                <p><strong>Requested:</strong> <?php echo formatDateTime($request['requested_at']); ?></p>
                                <p><strong>Status:</strong> <?php echo getStatusBadge($request['status']); ?></p>
                                
                                <div class="mt-3">
                                    <h6>Student's Message:</h6>
                                    <div class="bg-white p-3 rounded border">
                                        <?php echo nl2br(sanitizeInput($request['message'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Response Form -->
                <form method="POST" action="">
                    <?php echo getCSRFTokenField(); ?>
                    
                    <div class="mb-4">
                        <h5>Your Response</h5>
                        <p class="text-muted">Please choose how you would like to respond to this mentorship request:</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-success h-100">
                                    <div class="card-body text-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="response" 
                                                   id="accept" value="<?php echo MENTOR_STATUS_ACCEPTED; ?>" required>
                                            <label class="form-check-label w-100" for="accept">
                                                <i class="bi bi-check-circle text-success display-4 d-block mb-2"></i>
                                                <h5 class="text-success">Accept Request</h5>
                                                <p class="text-muted">
                                                    Start a mentorship relationship with <?php echo sanitizeInput($request['student_name']); ?>. 
                                                    You'll be able to message each other and provide guidance.
                                                </p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-danger h-100">
                                    <div class="card-body text-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="response" 
                                                   id="decline" value="<?php echo MENTOR_STATUS_DECLINED; ?>" required>
                                            <label class="form-check-label w-100" for="decline">
                                                <i class="bi bi-x-circle text-danger display-4 d-block mb-2"></i>
                                                <h5 class="text-danger">Decline Request</h5>
                                                <p class="text-muted">
                                                    Politely decline this mentorship request. The student will be notified 
                                                    and can send requests to other alumni.
                                                </p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Once you respond, this decision cannot be changed. If you accept, 
                        you'll be able to communicate with the student through the messaging system.
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="<?php echo SITE_URL; ?>/mentorship/active.php" class="btn btn-secondary me-md-2">
                            <i class="bi bi-arrow-left me-1"></i>Back to Requests
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Submit Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add visual feedback when radio buttons are selected
    const radioButtons = document.querySelectorAll('input[name="response"]');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove previous selections
            document.querySelectorAll('.card').forEach(card => {
                card.classList.remove('border-primary', 'bg-light');
            });
            
            // Highlight selected option
            const selectedCard = this.closest('.card');
            selectedCard.classList.add('border-primary', 'bg-light');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>