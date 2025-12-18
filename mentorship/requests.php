<?php
/**
 * Mentorship Requests Management
 * View and manage mentorship requests for students
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication (both students and alumni can access)
require_auth();

$user_role = getCurrentUserRole();
$user_id = getCurrentUserId();

// Determine page title and data based on user role
if ($user_role === ROLE_STUDENT) {
    $pageTitle = 'My Mentorship Requests';

    // Get student's sent requests with mentorship info
    try {
        $db = getDB();
        $requests = $db->fetchAll(
            "SELECT mr.*, u.name as alumni_name, u.education, u.skills, u.location,
                    m.mentorship_id, m.active as mentorship_active
             FROM mentorship_requests mr
             JOIN accounts u ON mr.alumni_id = u.account_id
             LEFT JOIN mentorships m ON mr.student_id = m.student_id AND mr.alumni_id = m.alumni_id
             WHERE mr.student_id = ?
             ORDER BY mr.requested_at DESC",
            [$user_id]
        );
    } catch (Exception $e) {
        error_log("Error fetching mentorship requests: " . $e->getMessage());
        $requests = [];
    }

} elseif ($user_role === ROLE_ALUMNI) {
    $pageTitle = 'Mentorship Requests';

    // Get requests sent to this alumni with mentorship info
    try {
        $db = getDB();
        $requests = $db->fetchAll(
            "SELECT mr.*, u.name as student_name, u.email as student_email,
                    u.education as student_education, u.skills as student_skills, 
                    u.location as student_location, u.bio as student_bio,
                    m.mentorship_id, m.active as mentorship_active
             FROM mentorship_requests mr
             JOIN accounts u ON mr.student_id = u.account_id
             LEFT JOIN mentorships m ON mr.student_id = m.student_id AND mr.alumni_id = m.alumni_id
             WHERE mr.alumni_id = ?
             ORDER BY mr.requested_at DESC",
            [$user_id]
        );
    } catch (Exception $e) {
        error_log("Error fetching mentorship requests: " . $e->getMessage());
        $requests = [];
    }

} else {
    // Admin or other roles - redirect to dashboard
    redirectWithMessage(
        SITE_URL . '/dashboard.php',
        'error',
        'You do not have permission to access this page.'
    );
}

// Get active mentorships based on user role
try {
    if ($user_role === ROLE_STUDENT) {
        $activeMentorships = $db->fetchAll(
            "SELECT m.*, u.name as partner_name, u.email as partner_email
             FROM mentorships m
             JOIN accounts u ON m.alumni_id = u.account_id
             WHERE m.student_id = ? AND m.active = TRUE
             ORDER BY m.started_at DESC",
            [$user_id]
        );
    } else {
        $activeMentorships = $db->fetchAll(
            "SELECT m.*, u.name as partner_name, u.email as partner_email
             FROM mentorships m
             JOIN accounts u ON m.student_id = u.account_id
             WHERE m.alumni_id = ? AND m.active = TRUE
             ORDER BY m.started_at DESC",
            [$user_id]
        );
    }
} catch (Exception $e) {
    error_log("Error fetching active mentorships: " . $e->getMessage());
    $activeMentorships = [];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i><?php echo $pageTitle; ?></h2>
            <?php if ($user_role === ROLE_STUDENT): ?>
                <a href="<?php echo SITE_URL; ?>/mentorship/send_request.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Send New Request
                </a>
            <?php endif; ?>
        </div>

        <!-- Active Mentorships -->
        <?php if (!empty($activeMentorships)): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-check-circle me-2"></i>Active Mentorships
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($activeMentorships as $mentorship): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">
                                            <i class="bi bi-person-check me-1"></i>
                                            <?php echo sanitizeInput($mentorship['partner_name']); ?>
                                        </h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Started: <?php echo formatDateTime($mentorship['started_at']); ?>
                                            </small>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $mentorship['mentorship_id']; ?>"
                                                class="btn btn-sm btn-primary">
                                                <i class="bi bi-chat-dots me-1"></i>Message
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mentorship Requests -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>Request History
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <?php if ($user_role === ROLE_STUDENT): ?>
                            <h4 class="mt-3 text-muted">No Mentorship Requests</h4>
                            <p class="text-muted">You haven't sent any mentorship requests yet.</p>
                            <a href="<?php echo SITE_URL; ?>/mentorship/send_request.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Send Your First Request
                            </a>
                        <?php else: ?>
                            <h4 class="mt-3 text-muted">No Mentorship Requests</h4>
                            <p class="text-muted">You haven't received any mentorship requests yet. Students will find you
                                through your profile.</p>
                            <a href="<?php echo SITE_URL; ?>/profile.php" class="btn btn-primary">
                                <i class="bi bi-person-gear me-1"></i>Update Your Profile
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo $user_role === ROLE_STUDENT ? 'Alumni Mentor' : 'Student'; ?></th>
                                    <th>Message Preview</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Response</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <?php if ($user_role === ROLE_STUDENT): ?>
                                                    <strong><?php echo sanitizeInput($request['alumni_name']); ?></strong>
                                                    <?php if (!empty($request['education'])): ?>
                                                        <br><small class="text-muted">
                                                            <?php echo sanitizeInput(truncateText($request['education'], 50)); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <strong><?php echo sanitizeInput($request['student_name']); ?></strong>
                                                    <?php if (!empty($request['student_education'])): ?>
                                                        <br><small class="text-muted">
                                                            <?php echo sanitizeInput(truncateText($request['student_education'], 50)); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;"
                                                title="<?php echo sanitizeInput($request['message']); ?>">
                                                <?php echo sanitizeInput(truncateText($request['message'], 80)); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($request['status']); ?>
                                        </td>
                                        <td>
                                            <small><?php echo timeAgo($request['requested_at']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($request['responded_at']): ?>
                                                <small><?php echo timeAgo($request['responded_at']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#requestModal<?php echo $request['request_id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if ($user_role === ROLE_ALUMNI && $request['status'] === MENTOR_STATUS_PENDING): ?>
                                                    <a href="<?php echo SITE_URL; ?>/mentorship/respond_request.php?id=<?php echo $request['request_id']; ?>"
                                                        class="btn btn-warning btn-sm">
                                                        <i class="bi bi-reply"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($request['status'] === MENTOR_STATUS_ACCEPTED && !empty($request['mentorship_id']) && $request['mentorship_active']): ?>
                                                    <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $request['mentorship_id']; ?>"
                                                        class="btn btn-success btn-sm">
                                                        <i class="bi bi-chat-dots"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Request Details Modal -->
                                    <div class="modal fade" id="requestModal<?php echo $request['request_id']; ?>"
                                        tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <?php if ($user_role === ROLE_STUDENT): ?>
                                                            Mentorship Request to
                                                            <?php echo sanitizeInput($request['alumni_name']); ?>
                                                        <?php else: ?>
                                                            Mentorship Request from
                                                            <?php echo sanitizeInput($request['student_name']); ?>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <?php if ($user_role === ROLE_STUDENT): ?>
                                                                <h6>Alumni Information</h6>
                                                                <p><strong>Name:</strong>
                                                                    <?php echo sanitizeInput($request['alumni_name']); ?></p>

                                                                <?php if (!empty($request['education'])): ?>
                                                                    <p><strong>Education:</strong><br>
                                                                        <?php echo sanitizeInput($request['education']); ?></p>
                                                                <?php endif; ?>

                                                                <?php if (!empty($request['skills'])): ?>
                                                                    <p><strong>Skills:</strong><br>
                                                                        <?php echo sanitizeInput($request['skills']); ?></p>
                                                                <?php endif; ?>

                                                                <?php if (!empty($request['location'])): ?>
                                                                    <p><strong>Location:</strong>
                                                                        <?php echo sanitizeInput($request['location']); ?></p>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <h6>Student Information</h6>
                                                                <p><strong>Name:</strong>
                                                                    <?php echo sanitizeInput($request['student_name']); ?></p>
                                                                <p><strong>Email:</strong>
                                                                    <?php echo sanitizeInput($request['student_email']); ?></p>

                                                                <?php if (!empty($request['student_education'])): ?>
                                                                    <p><strong>Education:</strong><br>
                                                                        <?php echo sanitizeInput($request['student_education']); ?></p>
                                                                <?php endif; ?>

                                                                <?php if (!empty($request['student_skills'])): ?>
                                                                    <p><strong>Skills:</strong><br>
                                                                        <?php echo sanitizeInput($request['student_skills']); ?></p>
                                                                <?php endif; ?>

                                                                <?php if (!empty($request['student_location'])): ?>
                                                                    <p><strong>Location:</strong>
                                                                        <?php echo sanitizeInput($request['student_location']); ?></p>
                                                                <?php endif; ?>

                                                                <?php if (!empty($request['student_bio'])): ?>
                                                                    <p><strong>About:</strong><br>
                                                                        <?php echo nl2br(sanitizeInput($request['student_bio'])); ?></p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Request Details</h6>
                                                            <p><strong>Status:</strong>
                                                                <?php echo getStatusBadge($request['status']); ?></p>
                                                            <p><strong>Requested:</strong>
                                                                <?php echo formatDateTime($request['requested_at']); ?></p>

                                                            <?php if ($request['responded_at']): ?>
                                                                <p><strong>Responded:</strong>
                                                                    <?php echo formatDateTime($request['responded_at']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <hr>

                                                    <h6>Your Message</h6>
                                                    <div class="bg-light p-3 rounded">
                                                        <?php echo nl2br(sanitizeInput($request['message'])); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <?php if ($user_role === ROLE_ALUMNI && $request['status'] === MENTOR_STATUS_PENDING): ?>
                                                        <a href="<?php echo SITE_URL; ?>/mentorship/respond_request.php?id=<?php echo $request['request_id']; ?>"
                                                            class="btn btn-warning">
                                                            <i class="bi bi-reply me-1"></i>Respond to Request
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($request['status'] === MENTOR_STATUS_ACCEPTED && !empty($request['mentorship_id']) && $request['mentorship_active']): ?>
                                                        <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $request['mentorship_id']; ?>"
                                                            class="btn btn-success">
                                                            <i class="bi bi-chat-dots me-1"></i>Start Messaging
                                                        </a>
                                                    <?php endif; ?>
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-question-circle me-2"></i>How Mentorship Works
                </h5>
            </div>
            <div class="card-body">
                <?php if ($user_role === ROLE_STUDENT): ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-1-circle-fill text-primary display-4"></i>
                                            <h6 class="mt-2">Send Request</h6>
                                            <p class="text-muted">Browse available alumni and send a personalized mentorship request.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-2-circle-fill text-warning display-4"></i>
                                            <h6 class="mt-2">Wait for Response</h6>
                                            <p class="text-muted">Alumni will review your request and respond with acceptance or decline.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-3-circle-fill text-success display-4"></i>
                                            <h6 class="mt-2">Start Mentoring</h6>
                                            <p class="text-muted">Once accepted, you can start messaging and building your mentorship relationship.</p>
                                        </div>
                                    </div>
                                </div>
                <?php else: ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-1-circle-fill text-info display-4"></i>
                                            <h6 class="mt-2">Receive Requests</h6>
                                            <p class="text-muted">Students will send you mentorship requests based on your profile and expertise.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-2-circle-fill text-warning display-4"></i>
                                            <h6 class="mt-2">Review & Respond</h6>
                                            <p class="text-muted">Review student profiles and requests, then accept or decline based on your availability.</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="bi bi-3-circle-fill text-success display-4"></i>
                                            <h6 class="mt-2">Mentor Students</h6>
                                            <p class="text-muted">Guide students through messaging, share your experience, and help them grow professionally.</p>
                                        </div>
                                    </div>
                                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>