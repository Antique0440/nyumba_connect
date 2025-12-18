<?php
/**
 * Active Mentorship Management
 * View and manage active mentorship relationships and pending requests
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require alumni authentication
require_alumni();

$pageTitle = 'Mentorship Management';

// Get pending mentorship requests for this alumni
try {
    $db = getDB();
    $pendingRequests = $db->fetchAll(
        "SELECT mr.*, s.name as student_name, s.email as student_email, 
                s.education, s.skills, s.location, s.bio
         FROM mentorship_requests mr
         JOIN users s ON mr.student_id = s.user_id
         WHERE mr.alumni_id = ? AND mr.status = ?
         ORDER BY mr.requested_at DESC",
        [getCurrentUserId(), MENTOR_STATUS_PENDING]
    );
} catch (Exception $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
    $pendingRequests = [];
}

// Get active mentorships for this alumni
try {
    $activeMentorships = $db->fetchAll(
        "SELECT m.*, s.name as student_name, s.email as student_email,
                s.education, s.skills, s.location,
                (SELECT COUNT(*) FROM messages WHERE mentorship_id = m.mentorship_id AND is_read = FALSE AND receiver_id = ?) as unread_count,
                (SELECT MAX(sent_at) FROM messages WHERE mentorship_id = m.mentorship_id) as last_message_at
         FROM mentorships m
         JOIN users s ON m.student_id = s.user_id
         WHERE m.alumni_id = ? AND m.active = TRUE
         ORDER BY last_message_at DESC, m.started_at DESC",
        [getCurrentUserId(), getCurrentUserId()]
    );
} catch (Exception $e) {
    error_log("Error fetching active mentorships: " . $e->getMessage());
    $activeMentorships = [];
}

// Get mentorship history (responded requests)
try {
    $mentorshipHistory = $db->fetchAll(
        "SELECT mr.*, s.name as student_name, s.education
         FROM mentorship_requests mr
         JOIN users s ON mr.student_id = s.user_id
         WHERE mr.alumni_id = ? AND mr.status IN (?, ?)
         ORDER BY mr.responded_at DESC
         LIMIT 10",
        [getCurrentUserId(), MENTOR_STATUS_ACCEPTED, MENTOR_STATUS_DECLINED]
    );
} catch (Exception $e) {
    error_log("Error fetching mentorship history: " . $e->getMessage());
    $mentorshipHistory = [];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i>Mentorship Management</h2>
            <div class="d-flex gap-2">
                <span class="badge bg-warning fs-6">
                    <?php echo count($pendingRequests); ?> Pending
                    Request<?php echo count($pendingRequests) !== 1 ? 's' : ''; ?>
                </span>
                <span class="badge bg-success fs-6">
                    <?php echo count($activeMentorships); ?> Active
                    Mentorship<?php echo count($activeMentorships) !== 1 ? 's' : ''; ?>
                </span>
            </div>
        </div>

        <!-- Pending Requests -->
        <?php if (!empty($pendingRequests)): ?>
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Pending Mentorship Requests
                        <span class="badge bg-dark ms-2"><?php echo count($pendingRequests); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($pendingRequests as $request): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo sanitizeInput($request['student_name']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo timeAgo($request['requested_at']); ?>
                                            </small>
                                        </div>

                                        <?php if (!empty($request['education'])): ?>
                                            <p class="card-text">
                                                <strong>Education:</strong><br>
                                                <small><?php echo sanitizeInput(truncateText($request['education'], 100)); ?></small>
                                            </p>
                                        <?php endif; ?>

                                        <p class="card-text">
                                            <strong>Message:</strong><br>
                                            <small><?php echo sanitizeInput(truncateText($request['message'], 120)); ?></small>
                                        </p>

                                        <div class="d-flex gap-2">
                                            <a href="<?php echo SITE_URL; ?>/mentorship/respond_request.php?id=<?php echo $request['request_id']; ?>"
                                                class="btn btn-primary btn-sm">
                                                <i class="bi bi-reply me-1"></i>Respond
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#requestModal<?php echo $request['request_id']; ?>">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Request Details Modal -->
                            <div class="modal fade" id="requestModal<?php echo $request['request_id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Mentorship Request from <?php echo sanitizeInput($request['student_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Student Information</h6>
                                                    <p><strong>Name:</strong>
                                                        <?php echo sanitizeInput($request['student_name']); ?></p>
                                                    <p><strong>Email:</strong>
                                                        <?php echo sanitizeInput($request['student_email']); ?></p>

                                                    <?php if (!empty($request['education'])): ?>
                                                        <p><strong>Education:</strong><br>
                                                            <?php echo nl2br(sanitizeInput($request['education'])); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($request['skills'])): ?>
                                                        <p><strong>Skills:</strong><br>
                                                            <?php echo nl2br(sanitizeInput($request['skills'])); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($request['location'])): ?>
                                                        <p><strong>Location:</strong>
                                                            <?php echo sanitizeInput($request['location']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Request Details</h6>
                                                    <p><strong>Requested:</strong>
                                                        <?php echo formatDateTime($request['requested_at']); ?></p>

                                                    <?php if (!empty($request['bio'])): ?>
                                                        <p><strong>About Student:</strong><br>
                                                            <?php echo nl2br(sanitizeInput($request['bio'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <hr>

                                            <h6>Student's Message</h6>
                                            <div class="bg-light p-3 rounded">
                                                <?php echo nl2br(sanitizeInput($request['message'])); ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?php echo SITE_URL; ?>/mentorship/respond_request.php?id=<?php echo $request['request_id']; ?>"
                                                class="btn btn-primary">
                                                <i class="bi bi-reply me-1"></i>Respond to Request
                                            </a>
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active Mentorships -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="bi bi-check-circle me-2"></i>Active Mentorships
                    <span class="badge bg-light text-dark ms-2"><?php echo count($activeMentorships); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($activeMentorships)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No Active Mentorships</h5>
                        <p class="text-muted">
                            <?php if (empty($pendingRequests)): ?>
                                You don't have any active mentorships or pending requests at the moment.
                            <?php else: ?>
                                Review and respond to pending requests above to start new mentorships.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($activeMentorships as $mentorship): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <i class="bi bi-person-check me-1"></i>
                                                <?php echo sanitizeInput($mentorship['student_name']); ?>
                                                <?php if ($mentorship['unread_count'] > 0): ?>
                                                    <span
                                                        class="badge bg-danger ms-1"><?php echo $mentorship['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                Started: <?php echo formatDate($mentorship['started_at']); ?>
                                            </small>
                                        </div>

                                        <?php if (!empty($mentorship['education'])): ?>
                                            <p class="card-text">
                                                <strong>Education:</strong><br>
                                                <small><?php echo sanitizeInput(truncateText($mentorship['education'], 100)); ?></small>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($mentorship['last_message_at']): ?>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <i class="bi bi-chat-dots me-1"></i>
                                                    Last message: <?php echo timeAgo($mentorship['last_message_at']); ?>
                                                </small>
                                            </p>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2">
                                            <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $mentorship['mentorship_id']; ?>"
                                                class="btn btn-primary btn-sm">
                                                <i class="bi bi-chat-dots me-1"></i>Message
                                                <?php if ($mentorship['unread_count'] > 0): ?>
                                                    <span
                                                        class="badge bg-light text-dark ms-1"><?php echo $mentorship['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#mentorshipModal<?php echo $mentorship['mentorship_id']; ?>">
                                                <i class="bi bi-info-circle me-1"></i>Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mentorship Details Modal -->
                            <div class="modal fade" id="mentorshipModal<?php echo $mentorship['mentorship_id']; ?>"
                                tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                Mentorship with <?php echo sanitizeInput($mentorship['student_name']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>Student Information</h6>
                                                    <p><strong>Name:</strong>
                                                        <?php echo sanitizeInput($mentorship['student_name']); ?></p>
                                                    <p><strong>Email:</strong>
                                                        <?php echo sanitizeInput($mentorship['student_email']); ?></p>

                                                    <?php if (!empty($mentorship['education'])): ?>
                                                        <p><strong>Education:</strong><br>
                                                            <?php echo nl2br(sanitizeInput($mentorship['education'])); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($mentorship['skills'])): ?>
                                                        <p><strong>Skills:</strong><br>
                                                            <?php echo nl2br(sanitizeInput($mentorship['skills'])); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($mentorship['location'])): ?>
                                                        <p><strong>Location:</strong>
                                                            <?php echo sanitizeInput($mentorship['location']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Mentorship Details</h6>
                                                    <p><strong>Started:</strong>
                                                        <?php echo formatDateTime($mentorship['started_at']); ?></p>
                                                    <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>

                                                    <?php if ($mentorship['last_message_at']): ?>
                                                        <p><strong>Last Message:</strong>
                                                            <?php echo formatDateTime($mentorship['last_message_at']); ?></p>
                                                    <?php endif; ?>

                                                    <?php if ($mentorship['unread_count'] > 0): ?>
                                                        <p><strong>Unread Messages:</strong>
                                                            <span
                                                                class="badge bg-danger"><?php echo $mentorship['unread_count']; ?></span>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $mentorship['mentorship_id']; ?>"
                                                class="btn btn-primary">
                                                <i class="bi bi-chat-dots me-1"></i>Open Messages
                                            </a>
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mentorship History -->
        <?php if (!empty($mentorshipHistory)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Response History
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Education</th>
                                    <th>Response</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mentorshipHistory as $history): ?>
                                    <tr>
                                        <td><?php echo sanitizeInput($history['student_name']); ?></td>
                                        <td>
                                            <?php if (!empty($history['education'])): ?>
                                                <?php echo sanitizeInput(truncateText($history['education'], 60)); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($history['status']); ?></td>
                                        <td><small><?php echo formatDate($history['responded_at']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>