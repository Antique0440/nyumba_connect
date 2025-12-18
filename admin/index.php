<?php
/**
 * Admin Dashboard with System Statistics
 * Provides comprehensive overview of platform metrics and activity
 */

// Define page constant
define('NYUMBA_CONNECT', true);

// Include configuration and check admin access
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Admin Dashboard';

try {
    $db = getDB();

    // User Statistics
    $user_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'alumni' THEN 1 ELSE 0 END) as alumni,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_7d
         FROM accounts"
    );

    // Opportunity Statistics
    $opportunity_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_opportunities,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_opportunities_30d
         FROM opportunities"
    );

    // Application Statistics
    $application_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied,
            SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired,
            SUM(CASE WHEN applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_applications_30d
         FROM applications"
    );

    // Mentorship Statistics
    $mentorship_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_mentorships,
            SUM(CASE WHEN active = TRUE THEN 1 ELSE 0 END) as active_mentorships,
            SUM(CASE WHEN active = FALSE THEN 1 ELSE 0 END) as inactive_mentorships,
            SUM(CASE WHEN started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_mentorships_30d
         FROM mentorships"
    );

    // Mentorship Request Statistics
    $request_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_requests,
            SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined_requests
         FROM mentorship_requests"
    );

    // Message Statistics
    $message_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_messages,
            SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread_messages,
            SUM(CASE WHEN sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as messages_30d,
            SUM(CASE WHEN sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as messages_7d
         FROM messages"
    );

    // Resource Statistics
    $resource_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_resources,
            SUM(download_count) as total_downloads,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_resources_30d
         FROM resources"
    );

    // Popular Resources
    $popular_resources = $db->fetchAll(
        "SELECT r.resource_id, r.title, r.download_count, r.created_at,
                u.name as uploader_name
         FROM resources r
         JOIN accounts u ON r.uploaded_by = u.account_id
         ORDER BY r.download_count DESC
         LIMIT 5"
    );

    // Recent Opportunities
    $recent_opportunities = $db->fetchAll(
        "SELECT o.opportunity_id, o.title, o.status, o.created_at,
                u.name as poster_name,
                (SELECT COUNT(*) FROM applications a WHERE a.opportunity_id = o.opportunity_id) as application_count
         FROM opportunities o
         JOIN accounts u ON o.posted_by = u.account_id
         ORDER BY o.created_at DESC
         LIMIT 5"
    );

    // Recent Users
    $recent_users = $db->fetchAll(
        "SELECT account_id, name, email, role, is_active, created_at
         FROM accounts
         ORDER BY created_at DESC
         LIMIT 5"
    );

    // Active Mentorships with Most Messages
    $active_mentorships = $db->fetchAll(
        "SELECT m.mentorship_id, 
                s.name as student_name, a.name as alumni_name,
                COUNT(msg.message_id) as message_count,
                MAX(msg.sent_at) as last_message
         FROM mentorships m
         JOIN accounts s ON m.student_id = s.account_id
         JOIN accounts a ON m.alumni_id = a.account_id
         LEFT JOIN messages msg ON m.mentorship_id = msg.mentorship_id
         WHERE m.active = TRUE
         GROUP BY m.mentorship_id, s.name, a.name
         ORDER BY message_count DESC
         LIMIT 5"
    );

    // System Health Metrics
    $health_metrics = [
        'pending_opportunities' => $opportunity_stats['pending'],
        'pending_requests' => $request_stats['pending_requests'],
        'inactive_users' => $user_stats['inactive_users'],
        'unread_messages' => $message_stats['unread_messages']
    ];

} catch (Exception $e) {
    error_log("Database error in admin dashboard: " . $e->getMessage());
    setFlashMessage('error', 'Unable to load dashboard data. Please try again.');

    // Initialize empty arrays to prevent errors
    $user_stats = $opportunity_stats = $application_stats = $mentorship_stats =
        $request_stats = $message_stats = $resource_stats = [];
    $popular_resources = $recent_opportunities = $recent_users = $active_mentorships = [];
    $health_metrics = [];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h1>
            <div>
                <small class="text-muted">Last updated: <?php echo date('M j, Y g:i A'); ?></small>
            </div>
        </div>

        <!-- System Health Alerts -->
        <?php if (!empty($health_metrics)): ?>
            <?php if ($health_metrics['pending_opportunities'] > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong><?php echo $health_metrics['pending_opportunities']; ?></strong>
                    opportunity<?php echo $health_metrics['pending_opportunities'] > 1 ? 'ies' : 'y'; ?>
                    pending approval.
                    <a href="<?php echo SITE_URL; ?>/admin/approve_opportunity.php" class="alert-link">Review now</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($health_metrics['pending_requests'] > 0): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong><?php echo $health_metrics['pending_requests']; ?></strong>
                    mentorship request<?php echo $health_metrics['pending_requests'] > 1 ? 's' : ''; ?>
                    awaiting response.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Total Users</h6>
                                <h2 class="card-title mb-0"><?php echo $user_stats['total_users'] ?? 0; ?></h2>
                                <small>+<?php echo $user_stats['new_users_30d'] ?? 0; ?> this month</small>
                            </div>
                            <div>
                                <i class="bi bi-people display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-primary bg-opacity-75">
                        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="text-white text-decoration-none">
                            View all <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Opportunities</h6>
                                <h2 class="card-title mb-0">
                                    <?php echo $opportunity_stats['total_opportunities'] ?? 0; ?>
                                </h2>
                                <small><?php echo $opportunity_stats['approved'] ?? 0; ?> approved</small>
                            </div>
                            <div>
                                <i class="bi bi-briefcase display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-success bg-opacity-75">
                        <a href="<?php echo SITE_URL; ?>/admin/approve_opportunity.php"
                            class="text-white text-decoration-none">
                            Manage <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Mentorships</h6>
                                <h2 class="card-title mb-0"><?php echo $mentorship_stats['active_mentorships'] ?? 0; ?>
                                </h2>
                                <small>Active relationships</small>
                            </div>
                            <div>
                                <i class="bi bi-people-fill display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-info bg-opacity-75">
                        <a href="<?php echo SITE_URL; ?>/admin/mentorships.php" class="text-white text-decoration-none">
                            Monitor <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2">Resources</h6>
                                <h2 class="card-title mb-0"><?php echo $resource_stats['total_resources'] ?? 0; ?></h2>
                                <small><?php echo $resource_stats['total_downloads'] ?? 0; ?> downloads</small>
                            </div>
                            <div>
                                <i class="bi bi-file-earmark-text display-4"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-warning bg-opacity-75">
                        <a href="<?php echo SITE_URL; ?>/resources/list.php" class="text-white text-decoration-none">
                            View all <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row mb-4">
            <!-- User Breakdown -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">User Breakdown</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Students</span>
                                <strong><?php echo $user_stats['students'] ?? 0; ?></strong>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info"
                                    style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['students'] / $user_stats['total_users'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Alumni</span>
                                <strong><?php echo $user_stats['alumni'] ?? 0; ?></strong>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success"
                                    style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['alumni'] / $user_stats['total_users'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Admins</span>
                                <strong><?php echo $user_stats['admins'] ?? 0; ?></strong>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning"
                                    style="width: <?php echo $user_stats['total_users'] > 0 ? ($user_stats['admins'] / $user_stats['total_users'] * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="text-success">Active</span>
                            <strong><?php echo $user_stats['active_users'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger">Inactive</span>
                            <strong><?php echo $user_stats['inactive_users'] ?? 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Statistics -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Application Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <h3 class="text-primary"><?php echo $application_stats['total_applications'] ?? 0; ?>
                                </h3>
                                <small>Total Applications</small>
                            </div>
                            <div class="col-6">
                                <h3 class="text-success"><?php echo $application_stats['hired'] ?? 0; ?></h3>
                                <small>Hired</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Applied</span>
                            <span class="badge bg-primary"><?php echo $application_stats['applied'] ?? 0; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shortlisted</span>
                            <span class="badge bg-info"><?php echo $application_stats['shortlisted'] ?? 0; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Rejected</span>
                            <span class="badge bg-danger"><?php echo $application_stats['rejected'] ?? 0; ?></span>
                        </div>
                        <hr>
                        <small class="text-muted">
                            <?php echo $application_stats['new_applications_30d'] ?? 0; ?> new applications this month
                        </small>
                    </div>
                </div>
            </div>

            <!-- Message Activity -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Message Activity</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <h3 class="text-primary"><?php echo $message_stats['total_messages'] ?? 0; ?></h3>
                                <small>Total Messages</small>
                            </div>
                            <div class="col-6">
                                <h3 class="text-warning"><?php echo $message_stats['unread_messages'] ?? 0; ?></h3>
                                <small>Unread</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Last 7 days</span>
                            <strong><?php echo $message_stats['messages_7d'] ?? 0; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Last 30 days</span>
                            <strong><?php echo $message_stats['messages_30d'] ?? 0; ?></strong>
                        </div>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                Avg: <?php
                                $avg_per_day = $message_stats['messages_30d'] > 0 ? round($message_stats['messages_30d'] / 30, 1) : 0;
                                echo $avg_per_day;
                                ?> messages/day
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <!-- Recent Opportunities -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Opportunities</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_opportunities)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_opportunities as $opp): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo sanitizeInput($opp['title']); ?></h6>
                                                <small class="text-muted">
                                                    Posted by <?php echo sanitizeInput($opp['poster_name']); ?>
                                                    - <?php echo timeAgo($opp['created_at']); ?>
                                                </small>
                                                <br>
                                                <small>
                                                    <span class="badge bg-info"><?php echo $opp['application_count']; ?>
                                                        applications</span>
                                                </small>
                                            </div>
                                            <div>
                                                <?php echo getStatusBadge($opp['status']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <small class="text-muted">No recent opportunities</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Users</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_users)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo sanitizeInput($user['name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo sanitizeInput($user['email']); ?>
                                                    - <?php echo timeAgo($user['created_at']); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <?php echo getStatusBadge($user['role']); ?>
                                                <?php if (!$user['is_active']): ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <small class="text-muted">No recent users</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Popular Resources -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Popular Resources</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($popular_resources)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($popular_resources as $resource): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo sanitizeInput($resource['title']); ?></h6>
                                                <small class="text-muted">
                                                    Uploaded by <?php echo sanitizeInput($resource['uploader_name']); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge bg-primary">
                                                    <i
                                                        class="bi bi-download me-1"></i><?php echo $resource['download_count']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <small class="text-muted">No resources available</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Mentorships -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Most Active Mentorships</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($active_mentorships)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($active_mentorships as $mentorship): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php echo sanitizeInput($mentorship['student_name']); ?>
                                                    <i class="bi bi-arrow-left-right small"></i>
                                                    <?php echo sanitizeInput($mentorship['alumni_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Last message:
                                                    <?php echo $mentorship['last_message'] ? timeAgo($mentorship['last_message']) : 'No messages'; ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="badge bg-info">
                                                    <i
                                                        class="bi bi-chat-dots me-1"></i><?php echo $mentorship['message_count']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <small class="text-muted">No active mentorships</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>