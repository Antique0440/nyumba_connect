<?php
/**
 * Logout Page for Nyumba Connect Platform
 * Handles secure session destruction and user logout
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (checkCSRFToken($_POST['csrf_token'] ?? '')) {
        // Perform logout
        logout_user();

        // Redirect to login with success message
        redirectWithMessage('login.php', 'success', 'You have been successfully logged out.');
    } else {
        redirectWithMessage('dashboard.php', 'error', 'Invalid security token.');
    }
}

// Auto-logout if requested via GET (for navigation links)
if (isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
    logout_user();
    redirectWithMessage('login.php', 'success', 'You have been successfully logged out.');
}

// Include header
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center mb-0">Confirm Logout</h3>
                </div>
                <div class="card-body text-center">
                    <p>Are you sure you want to log out of your account?</p>

                    <form method="POST" action="logout.php" class="d-inline">
                        <?php echo getCSRFTokenField(); ?>
                        <button type="submit" class="btn btn-danger me-2">Yes, Log Out</button>
                    </form>

                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>