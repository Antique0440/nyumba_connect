<?php
/**
 * User Dashboard for Nyumba Connect Platform
 * Role-based dashboard with personalized content
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Require authentication
requireAuth();

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    redirectWithMessage('login.php', 'warning', 'Your session has expired. Please log in again.');
}

// Update last activity
$_SESSION['last_activity'] = time();

$user_id = getCurrentUserId();
$user_role = getCurrentUserRole();
$user_name = $_SESSION['user_name'] ?? 'User';

// Get user-specific data based on role
$dashboardData = [];

try {
    $db = getDB();

    if ($user_role === ROLE_STUDENT) {
        // Get student-specific data
        $dashboardData['applications'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM applications WHERE applicant_id = ?",
            [$user_id]
        )[0]['count'] ?? 0;

        $dashboardData['mentorship_requests'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM mentorship_requests WHERE student_id = ?",
            [$user_id]
        )[0]['count'] ?? 0;

        $dashboardData['active_mentorships'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM mentorships WHERE student_id = ? AND active = TRUE",
            [$user_id]
        )[0]['count'] ?? 0;

    } elseif ($user_role === ROLE_ALUMNI) {
        // Get alumni-specific data
        $dashboardData['mentorship_requests'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM mentorship_requests WHERE alumni_id = ? AND status = 'pending'",
            [$user_id]
        )[0]['count'] ?? 0;

        $dashboardData['active_mentorships'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM mentorships WHERE alumni_id = ? AND active = TRUE",
            [$user_id]
        )[0]['count'] ?? 0;

    } elseif ($user_role === ROLE_ADMIN) {
        // Get admin-specific data
        $dashboardData['total_users'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM users WHERE is_active = TRUE"
        )[0]['count'] ?? 0;

        $dashboardData['pending_opportunities'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM opportunities WHERE status = 'pending'"
        )[0]['count'] ?? 0;

        $dashboardData['active_mentorships'] = $db->fetchAll(
            "SELECT COUNT(*) as count FROM mentorships WHERE active = TRUE"
        )[0]['count'] ?? 0;
    }

} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading dashboard data.');
}

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-1">Welcome back, <?php echo sanitizeInput($user_name); ?>!</h1>
                <p class="mb-0 opacity-75">
                    <i class="bi bi-person-badge me-2"></i>
                    <?php echo getRoleDisplayName($user_role); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-flex gap-2 justify-content-md-end">
                    <a href="profile.php" class="btn btn-outline-light">
                        <i class="bi bi-person me-1"></i>Profile
                    </a>
                    <a href="logout.php" class="btn btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <?php echo displayFlashMessages(); ?>

    <!-- Dashboard Statistics -->
    <div class="row mt-4">
        <div class="col-12">
            <h3 class="section-title">Your Dashboard</h3>
        </div>

        <?php if ($user_role === ROLE_STUDENT): ?>
            <!-- Student Dashboard -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-briefcase mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">My Applications</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['applications']; ?></h2>
                        <a href="opportunities/list.php" class="btn btn-light">View Opportunities</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <div class="card-body text-center">
                        <i class="bi bi-chat-dots mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Mentorship Requests</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['mentorship_requests']; ?></h2>
                        <a href="mentorship/requests.php" class="btn btn-light">View Requests</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <div class="card-body text-center">
                        <i class="bi bi-people mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Active Mentorships</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['active_mentorships']; ?></h2>
                        <a href="mentorship/active.php" class="btn btn-light">View Mentorships</a>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === ROLE_ALUMNI): ?>
            <!-- Alumni Dashboard -->
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Pending Requests</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['mentorship_requests']; ?></h2>
                        <a href="mentorship/requests.php" class="btn btn-light">Review Requests</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <div class="card-body text-center">
                        <i class="bi bi-people mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Active Mentorships</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['active_mentorships']; ?></h2>
                        <a href="mentorship/active.php" class="btn btn-light">Manage Mentorships</a>
                    </div>
                </div>
            </div>

        <?php elseif ($user_role === ROLE_ADMIN): ?>
            <!-- Admin Dashboard -->
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['total_users']; ?></h2>
                        <a href="admin/users.php" class="btn btn-light">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Pending Opportunities</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['pending_opportunities']; ?></h2>
                        <a href="admin/approve_opportunity.php" class="btn btn-light">Review Opportunities</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card dashboard-card h-100" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <div class="card-body text-center">
                        <i class="bi bi-heart mb-3" style="font-size: 2.5rem;"></i>
                        <h5 class="card-title">Active Mentorships</h5>
                        <h2 class="display-4 mb-3"><?php echo $dashboardData['active_mentorships']; ?></h2>
                        <a href="admin/mentorships.php" class="btn btn-light">Monitor Mentorships</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="section-title">Quick Actions</h3>
        </div>

        <?php if ($user_role === ROLE_STUDENT): ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-search text-primary mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Browse Opportunities</h6>
                        <a href="opportunities/list.php" class="btn btn-primary btn-sm">Explore</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-person-plus text-info mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Request Mentorship</h6>
                        <a href="mentorship/send_request.php" class="btn btn-info btn-sm">Connect</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-text text-success mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">View Resources</h6>
                        <a href="resources/list.php" class="btn btn-success btn-sm">Browse</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-person-gear text-secondary mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Update Profile</h6>
                        <a href="profile.php" class="btn btn-secondary btn-sm">Edit</a>
                    </div>
                </div>
            </div>

        <?php elseif ($user_role === ROLE_ALUMNI): ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-plus-circle text-primary mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Post Opportunity</h6>
                        <a href="opportunities/create.php" class="btn btn-primary btn-sm">Create</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-clock text-warning mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Review Requests</h6>
                        <a href="mentorship/requests.php" class="btn btn-warning btn-sm">Review</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-chat-dots text-info mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Messages</h6>
                        <a href="messages/inbox.php" class="btn btn-info btn-sm">View</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-person-gear text-secondary mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Update Profile</h6>
                        <a href="profile.php" class="btn btn-secondary btn-sm">Edit</a>
                    </div>
                </div>
            </div>

        <?php elseif ($user_role === ROLE_ADMIN): ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-primary mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Manage Users</h6>
                        <a href="admin/users.php" class="btn btn-primary btn-sm">Manage</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle text-warning mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Approve Opportunities</h6>
                        <a href="admin/approve_opportunity.php" class="btn btn-warning btn-sm">Review</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-heart text-success mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Monitor Mentorships</h6>
                        <a href="admin/mentorships.php" class="btn btn-success btn-sm">Monitor</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-cloud-upload text-info mb-2" style="font-size: 2rem;"></i>
                        <h6 class="card-title">Upload Resources</h6>
                        <a href="resources/upload.php" class="btn btn-info btn-sm">Upload</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>