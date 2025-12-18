<?php
/**
 * Error Page for Nyumba Connect Platform
 * User-friendly error display with navigation
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Get error details from URL parameters or session
$error_code = $_GET['code'] ?? '404';
$error_message = $_GET['message'] ?? 'Page not found';

// Sanitize inputs
$error_code = sanitizeInput($error_code);
$error_message = sanitizeInput($error_message);

// Define error details
$error_details = [
    '404' => [
        'title' => 'Page Not Found',
        'message' => 'The page you are looking for could not be found.',
        'icon' => 'bi-exclamation-triangle'
    ],
    '403' => [
        'title' => 'Access Denied',
        'message' => 'You do not have permission to access this resource.',
        'icon' => 'bi-shield-exclamation'
    ],
    '500' => [
        'title' => 'Server Error',
        'message' => 'An internal server error occurred. Please try again later.',
        'icon' => 'bi-exclamation-octagon'
    ],
    'default' => [
        'title' => 'Error',
        'message' => $error_message,
        'icon' => 'bi-exclamation-circle'
    ]
];

$current_error = $error_details[$error_code] ?? $error_details['default'];

// Include header
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="text-center">
                <i class="<?php echo $current_error['icon']; ?> text-danger mb-4" style="font-size: 5rem;"></i>

                <h1 class="display-4 mb-3"><?php echo $current_error['title']; ?></h1>

                <p class="lead text-muted mb-4">
                    <?php echo $current_error['message']; ?>
                </p>

                <?php if ($error_code === '404'): ?>
                    <p class="text-muted mb-4">
                        The page you requested might have been moved, deleted, or you entered the wrong URL.
                    </p>
                <?php elseif ($error_code === '403'): ?>
                    <p class="text-muted mb-4">
                        Please log in with appropriate credentials or contact an administrator if you believe this is an
                        error.
                    </p>
                <?php elseif ($error_code === '500'): ?>
                    <p class="text-muted mb-4">
                        Our team has been notified and is working to resolve this issue.
                    </p>
                <?php endif; ?>

                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="javascript:history.back()" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Go Back
                    </a>

                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="bi bi-house me-2"></i>Dashboard
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-house me-2"></i>Home
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Helpful links -->
                <div class="mt-5">
                    <h5 class="mb-3">Need Help?</h5>
                    <div class="row">
                        <?php if (isLoggedIn()): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-briefcase text-primary mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title">Opportunities</h6>
                                        <a href="opportunities/list.php" class="btn btn-sm btn-outline-primary">Browse</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-people text-success mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title">Mentorship</h6>
                                        <a href="mentorship/requests.php" class="btn btn-sm btn-outline-success">View</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-file-earmark-text text-info mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title">Resources</h6>
                                        <a href="resources/list.php" class="btn btn-sm btn-outline-info">Browse</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-person-plus text-primary mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title">Join Us</h6>
                                        <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="bi bi-box-arrow-in-right text-success mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title">Sign In</h6>
                                        <a href="login.php" class="btn btn-sm btn-outline-success">Login</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>