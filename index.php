<?php
/**
 * Main Landing Page for Nyumba Connect Platform
 * Redirects authenticated users to dashboard, shows welcome page for guests
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Redirect authenticated users to dashboard based on their role
if (isLoggedIn()) {
    $userRole = getCurrentUserRole();

    // Role-based dashboard routing
    switch ($userRole) {
        case ROLE_ADMIN:
            header('Location: admin/index.php');
            break;
        case ROLE_ALUMNI:
        case ROLE_STUDENT:
        default:
            header('Location: dashboard.php');
            break;
    }
    exit;
}

// Get some basic statistics for the landing page
$stats = [];
try {
    $db = getDB();

    // Get total active users (excluding admins for public display)
    $stats['users'] = $db->fetchAll(
        "SELECT COUNT(*) as count FROM accounts WHERE is_active = TRUE AND role != ?",
        [ROLE_ADMIN]
    )[0]['count'] ?? 0;

    // Get total approved opportunities
    $stats['opportunities'] = $db->fetchAll(
        "SELECT COUNT(*) as count FROM opportunities WHERE status = 'approved'"
    )[0]['count'] ?? 0;

    // Get total active mentorships
    $stats['mentorships'] = $db->fetchAll(
        "SELECT COUNT(*) as count FROM mentorships WHERE active = TRUE"
    )[0]['count'] ?? 0;

} catch (Exception $e) {
    error_log("Landing page stats error: " . $e->getMessage());
    // Set default values if database query fails
    $stats = ['users' => 0, 'opportunities' => 0, 'mentorships' => 0];
}

// Include header
require_once 'includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <div class="row align-items-center min-vh-75">
        <div class="col-lg-6">
            <h1 class="display-4 fw-bold text-primary mb-4">
                Welcome to Nyumba Connect
            </h1>
            <p class="lead mb-4">
                A platform designed specifically for current and former residents of Nyumba ya Watoto.
                Connect with opportunities, find mentorship, and build your professional network within our community.
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <a href="register.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-person-plus me-2"></i>Join Our Community
                </a>
                <a href="login.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </a>
            </div>
        </div>
        <div class="col-lg-6 text-center">
            <i class="bi bi-house-heart text-primary" style="font-size: 12rem;"></i>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-5">What You Can Do</h2>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-briefcase text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="card-title">Find Opportunities</h5>
                    <p class="card-text">
                        Discover job openings, internships, scholarships, and educational opportunities
                        shared by our alumni network.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-people text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="card-title">Get Mentorship</h5>
                    <p class="card-text">
                        Connect with alumni who can provide guidance, career advice, and support
                        as you navigate your professional journey.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <i class="bi bi-file-earmark-text text-primary mb-3" style="font-size: 3rem;"></i>
                    <h5 class="card-title">Access Resources</h5>
                    <p class="card-text">
                        Download CV templates, career guides, and other valuable resources
                        to help you succeed in your professional development.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-4">Our Community Impact</h2>
        </div>

        <div class="col-md-4 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <h3 class="text-primary display-6"><?php echo number_format($stats['users']); ?></h3>
                    <p class="text-muted">Active Members</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <h3 class="text-success display-6"><?php echo number_format($stats['opportunities']); ?></h3>
                    <p class="text-muted">Opportunities Shared</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 text-center">
            <div class="card border-0">
                <div class="card-body">
                    <h3 class="text-info display-6"><?php echo number_format($stats['mentorships']); ?></h3>
                    <p class="text-muted">Active Mentorships</p>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="text-center mb-5">How It Works</h2>
        </div>

        <div class="col-md-3 text-center mb-4">
            <div class="mb-3">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                    style="width: 60px; height: 60px;">
                    <span class="fw-bold">1</span>
                </div>
            </div>
            <h5>Create Your Profile</h5>
            <p class="text-muted">Sign up and complete your profile with your education, skills, and career goals.</p>
        </div>

        <div class="col-md-3 text-center mb-4">
            <div class="mb-3">
                <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center"
                    style="width: 60px; height: 60px;">
                    <span class="fw-bold">2</span>
                </div>
            </div>
            <h5>Connect & Apply</h5>
            <p class="text-muted">Browse opportunities and connect with alumni mentors in your field of interest.</p>
        </div>

        <div class="col-md-3 text-center mb-4">
            <div class="mb-3">
                <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center"
                    style="width: 60px; height: 60px;">
                    <span class="fw-bold">3</span>
                </div>
            </div>
            <h5>Get Guidance</h5>
            <p class="text-muted">Receive mentorship, career advice, and access to valuable resources.</p>
        </div>

        <div class="col-md-3 text-center mb-4">
            <div class="mb-3">
                <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center"
                    style="width: 60px; height: 60px;">
                    <span class="fw-bold">4</span>
                </div>
            </div>
            <h5>Achieve Success</h5>
            <p class="text-muted">Land opportunities and grow your career with community support.</p>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="row mt-5 mb-5">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-5">
                    <h3 class="mb-3">Ready to Get Started?</h3>
                    <p class="mb-4">
                        Join our community today and start connecting with opportunities and mentors
                        who can help you achieve your goals.
                    </p>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="register.php" class="btn btn-light btn-lg">
                            <i class="bi bi-person-plus me-2"></i>Create Your Account
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Already a Member?
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .min-vh-75 {
        min-height: 75vh;
    }

    .display-6 {
        font-size: 2.5rem;
        font-weight: 600;
    }
</style>

<?php require_once 'includes/footer.php'; ?>