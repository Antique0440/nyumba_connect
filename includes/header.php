<?php
// Prevent direct access
if (!defined('NYUMBA_CONNECT')) {
    define('NYUMBA_CONNECT', true);
}

// Include configuration
require_once __DIR__ . '/config.php';

// Get current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/favicon.ico">

    <!-- JavaScript Configuration -->
    <script>
        window.SITE_URL = '<?php echo SITE_URL; ?>';
        <?php if (isLoggedIn()): ?>
            window.CURRENT_USER_ID = <?php echo getCurrentUserId(); ?>;
            window.CURRENT_USER_ROLE = '<?php echo getCurrentUserRole(); ?>';
        <?php endif; ?>
    </script>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
                <i class="bi bi-house-heart me-2"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($currentPage, 'opportunities') !== false ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/opportunities/list.php">
                                <i class="bi bi-briefcase me-1"></i>Opportunities
                            </a>
                        </li>

                        <?php if (isStudent()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($currentPage, 'mentorship') !== false ? 'active' : ''; ?>"
                                    href="<?php echo SITE_URL; ?>/mentorship/requests.php">
                                    <i class="bi bi-people me-1"></i>Mentorship
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (isAlumni()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo strpos($currentPage, 'mentorship') !== false ? 'active' : ''; ?>"
                                    href="<?php echo SITE_URL; ?>/mentorship/active.php">
                                    <i class="bi bi-people me-1"></i>Mentorship
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($currentPage, 'messages') !== false ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/messages/inbox.php">
                                <i class="bi bi-chat-dots me-1"></i>Messages
                                <?php
                                // Get unread message count
                                try {
                                    $db = getDB();
                                    $unreadCount = $db->fetchOne(
                                        "SELECT COUNT(*) as count FROM messages m
                                         JOIN mentorships ms ON m.mentorship_id = ms.mentorship_id
                                         WHERE m.receiver_id = ? AND m.is_read = FALSE AND ms.active = TRUE",
                                        [getCurrentUserId()]
                                    );
                                    if ($unreadCount && $unreadCount['count'] > 0) {
                                        echo '<span class="badge bg-danger ms-1">' . $unreadCount['count'] . '</span>';
                                    }
                                } catch (Exception $e) {
                                    // Silently fail - don't show error in navigation
                                }
                                ?>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($currentPage, 'resources') !== false ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/resources/list.php">
                                <i class="bi bi-file-earmark-text me-1"></i>Resources
                            </a>
                        </li>

                        <?php if (isAdmin()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo strpos($currentPage, 'admin') !== false ? 'active' : ''; ?>"
                                    href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-gear me-1"></i>Admin
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/index.php">Dashboard</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/users.php">Users</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?php echo SITE_URL; ?>/admin/approve_opportunity.php">Opportunities</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?php echo SITE_URL; ?>/admin/mentorships.php">Mentorships</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/resources/upload.php">Upload
                                            Resource</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <?php echo sanitizeInput($_SESSION['user_name'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php">
                                        <i class="bi bi-person me-2"></i>Profile
                                    </a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'login' ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'register' ? 'active' : ''; ?>"
                                href="<?php echo SITE_URL; ?>/register.php">
                                <i class="bi bi-person-plus me-1"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container mt-4">
        <!-- Flash Messages -->
        <?php echo displayFlashMessages(); ?>