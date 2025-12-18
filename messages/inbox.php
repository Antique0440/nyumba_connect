<?php
/**
 * Messages Inbox - View all active mentorship conversations
 * Displays list of active mentorship relationships with messaging access
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

$pageTitle = 'Messages';
$currentUserId = getCurrentUserId();
$currentUserRole = getCurrentUserRole();

// Get active mentorships for current user
try {
    $db = getDB();

    if ($currentUserRole === ROLE_STUDENT) {
        // Students see their mentorships with alumni
        $mentorships = $db->fetchAll(
            "SELECT m.mentorship_id, m.started_at, 
                    a.name as partner_name, a.account_id as partner_id,
                    (SELECT COUNT(*) FROM messages WHERE mentorship_id = m.mentorship_id AND is_read = FALSE AND receiver_id = ?) as unread_count,
                    (SELECT message_text FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_message,
                    (SELECT sent_at FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_message_at,
                    (SELECT sender_id FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_sender_id
             FROM mentorships m
             JOIN accounts a ON m.alumni_id = a.account_id
             WHERE m.student_id = ? AND m.active = TRUE
             ORDER BY last_message_at DESC, m.started_at DESC",
            [$currentUserId, $currentUserId]
        );
    } else {
        // Alumni see their mentorships with students
        $mentorships = $db->fetchAll(
            "SELECT m.mentorship_id, m.started_at,
                    s.name as partner_name, s.account_id as partner_id,
                    (SELECT COUNT(*) FROM messages WHERE mentorship_id = m.mentorship_id AND is_read = FALSE AND receiver_id = ?) as unread_count,
                    (SELECT message_text FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_message,
                    (SELECT sent_at FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_message_at,
                    (SELECT sender_id FROM messages WHERE mentorship_id = m.mentorship_id ORDER BY sent_at DESC LIMIT 1) as last_sender_id
             FROM mentorships m
             JOIN accounts s ON m.student_id = s.account_id
             WHERE m.alumni_id = ? AND m.active = TRUE
             ORDER BY last_message_at DESC, m.started_at DESC",
            [$currentUserId, $currentUserId]
        );
    }
} catch (Exception $e) {
    error_log("Error fetching mentorships: " . $e->getMessage());
    $mentorships = [];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-chat-dots me-2"></i>Messages</h2>
            <div class="d-flex gap-2">
                <span class="badge bg-primary fs-6">
                    <?php echo count($mentorships); ?> Active
                    Conversation<?php echo count($mentorships) !== 1 ? 's' : ''; ?>
                </span>
                <?php
                $totalUnread = array_sum(array_column($mentorships, 'unread_count'));
                if ($totalUnread > 0):
                    ?>
                    <span class="badge bg-danger fs-6">
                        <?php echo $totalUnread; ?> Unread
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($mentorships)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-chat-dots display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Active Conversations</h4>
                    <p class="text-muted mb-4">
                        <?php if ($currentUserRole === ROLE_STUDENT): ?>
                            You can start messaging once you have an active mentorship relationship.<br>
                            <a href="<?php echo SITE_URL; ?>/mentorship/send_request.php" class="btn btn-primary mt-2">
                                <i class="bi bi-person-plus me-1"></i>Request Mentorship
                            </a>
                        <?php else: ?>
                            You can start messaging once you accept mentorship requests from students.<br>
                            <a href="<?php echo SITE_URL; ?>/mentorship/active.php" class="btn btn-primary mt-2">
                                <i class="bi bi-people me-1"></i>View Mentorship Requests
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Your Conversations
                    </h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($mentorships as $mentorship): ?>
                        <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $mentorship['mentorship_id']; ?>"
                            class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0 me-2">
                                            <i class="bi bi-person-circle me-1"></i>
                                            <?php echo sanitizeInput($mentorship['partner_name']); ?>
                                        </h6>
                                        <?php if ($mentorship['unread_count'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $mentorship['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($mentorship['last_message'])): ?>
                                        <p class="mb-1 text-muted">
                                            <?php if ($mentorship['last_sender_id'] == $currentUserId): ?>
                                                <i class="bi bi-arrow-right me-1"></i>You:
                                            <?php else: ?>
                                                <i class="bi bi-arrow-left me-1"></i>
                                            <?php endif; ?>
                                            <?php echo sanitizeInput(truncateText($mentorship['last_message'], 80)); ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo timeAgo($mentorship['last_message_at']); ?>
                                        </small>
                                    <?php else: ?>
                                        <p class="mb-1 text-muted fst-italic">
                                            No messages yet - start the conversation!
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            Mentorship started: <?php echo formatDate($mentorship['started_at']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="text-end">
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-info-circle display-6 text-primary"></i>
                            <h6 class="mt-2">Message Guidelines</h6>
                            <p class="small text-muted mb-0">
                                Keep conversations professional and respectful.
                                Messages are only available within active mentorship relationships.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-check display-6 text-success"></i>
                            <h6 class="mt-2">Privacy & Security</h6>
                            <p class="small text-muted mb-0">
                                Your messages are private and secure. Only you and your mentorship partner can see them.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>