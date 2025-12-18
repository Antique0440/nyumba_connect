<?php
/**
 * Profile Management Interface for Nyumba Connect Platform
 * Handles profile viewing and editing functionality with validation and sanitization
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Initialize authentication
init_auth();
require_auth();

$user_id = getCurrentUserId();
$current_user_role = getCurrentUserRole();
$errors = [];
$success_message = '';

// Check if viewing another user's profile (admin only)
$profile_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;

// Verify access permissions
if ($profile_user_id !== $user_id && !isAdmin()) {
    redirectWithMessage(SITE_URL . '/dashboard.php', 'error', 'You can only view your own profile.');
}

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Only allow users to update their own profile (except admins)
        if ($profile_user_id !== $user_id && !isAdmin()) {
            $errors[] = 'You can only update your own profile.';
        } else {
            // Validate and sanitize input data
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $year_left = trim($_POST['year_left'] ?? '');
            $education = trim($_POST['education'] ?? '');
            $skills = trim($_POST['skills'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $bio = trim($_POST['bio'] ?? '');

            // Validate required fields
            if (empty($name)) {
                $errors[] = 'Name is required.';
            } elseif (strlen($name) < 2 || strlen($name) > 255) {
                $errors[] = 'Name must be between 2 and 255 characters.';
            }

            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            // Validate year_left for alumni
            if (!empty($year_left)) {
                $current_year = (int) date('Y');
                if (!is_numeric($year_left) || $year_left < 1990 || $year_left > $current_year) {
                    $errors[] = 'Please enter a valid year between 1990 and ' . $current_year . '.';
                }
            }

            // Validate field lengths
            if (strlen($education) > 1000) {
                $errors[] = 'Education field cannot exceed 1000 characters.';
            }
            if (strlen($skills) > 500) {
                $errors[] = 'Skills field cannot exceed 500 characters.';
            }
            if (strlen($location) > 255) {
                $errors[] = 'Location field cannot exceed 255 characters.';
            }
            if (strlen($bio) > 1000) {
                $errors[] = 'Bio field cannot exceed 1000 characters.';
            }

            // Check for duplicate email (excluding current user)
            if (empty($errors)) {
                try {
                    $db = getDB();
                    $existing_user = $db->fetchOne(
                        "SELECT account_id FROM accounts WHERE email = ? AND account_id != ?",
                        [$email, $profile_user_id]
                    );

                    if ($existing_user) {
                        $errors[] = 'This email address is already in use by another account.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Database error occurred. Please try again.';
                    error_log("Profile update email check error: " . $e->getMessage());
                }
            }

            // Update profile if no errors
            if (empty($errors)) {
                try {
                    $db = getDB();

                    // Sanitize data for XSS prevention
                    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                    $education = htmlspecialchars($education, ENT_QUOTES, 'UTF-8');
                    $skills = htmlspecialchars($skills, ENT_QUOTES, 'UTF-8');
                    $location = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
                    $bio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');

                    $db->execute(
                        "UPDATE accounts SET name = ?, email = ?, year_left = ?, education = ?, skills = ?, location = ?, bio = ? WHERE account_id = ?",
                        [
                            $name,
                            $email,
                            $year_left ?: null,
                            $education,
                            $skills,
                            $location,
                            $bio,
                            $profile_user_id
                        ]
                    );

                    // Update session email if user updated their own profile
                    if ($profile_user_id === $user_id) {
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_name'] = $name;
                    }

                    // Log the activity
                    logActivity($user_id, 'Profile Update', "Updated profile for user ID: $profile_user_id");

                    $success_message = 'Profile updated successfully!';
                } catch (Exception $e) {
                    $errors[] = 'Failed to update profile. Please try again.';
                    error_log("Profile update error: " . $e->getMessage());
                }
            }
        }
    }
}

// Fetch user profile data
try {
    $db = getDB();
    $profile_user = $db->fetchOne(
        "SELECT account_id, name, email, role, year_left, education, skills, location, bio, cv_path, is_active, created_at FROM accounts WHERE account_id = ?",
        [$profile_user_id]
    );

    if (!$profile_user) {
        redirectWithMessage(SITE_URL . '/dashboard.php', 'error', 'User profile not found.');
    }
} catch (Exception $e) {
    redirectWithMessage(SITE_URL . '/dashboard.php', 'error', 'Failed to load profile data.');
}

// Generate CSRF token
generateCSRFToken();

$page_title = ($profile_user_id === $user_id) ? 'My Profile' : $profile_user['name'] . "'s Profile";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo htmlspecialchars($page_title); ?></h4>
                    <?php if ($profile_user_id === $user_id || isAdmin()): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="editProfileBtn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <!-- Display success message -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Display errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Profile View Mode -->
                    <div id="profileView">
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Name:</strong></div>
                            <div class="col-sm-9"><?php echo htmlspecialchars($profile_user['name']); ?></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Email:</strong></div>
                            <div class="col-sm-9"><?php echo htmlspecialchars($profile_user['email']); ?></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Role:</strong></div>
                            <div class="col-sm-9">
                                <span
                                    class="badge bg-<?php echo $profile_user['role'] === 'admin' ? 'danger' : ($profile_user['role'] === 'alumni' ? 'success' : 'primary'); ?>">
                                    <?php echo ucfirst($profile_user['role']); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($profile_user['year_left']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Year Left:</strong></div>
                                <div class="col-sm-9"><?php echo htmlspecialchars($profile_user['year_left']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile_user['education']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Education:</strong></div>
                                <div class="col-sm-9"><?php echo nl2br(htmlspecialchars($profile_user['education'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile_user['skills']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Skills:</strong></div>
                                <div class="col-sm-9"><?php echo nl2br(htmlspecialchars($profile_user['skills'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile_user['location']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Location:</strong></div>
                                <div class="col-sm-9"><?php echo htmlspecialchars($profile_user['location']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile_user['bio']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Bio:</strong></div>
                                <div class="col-sm-9"><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($profile_user['cv_path'] && file_exists($profile_user['cv_path'])): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>CV:</strong></div>
                                <div class="col-sm-9">
                                    <a href="<?php echo SITE_URL; ?>/download_cv.php?user_id=<?php echo $profile_user['account_id']; ?>"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download"></i> Download CV
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Member Since:</strong></div>
                            <div class="col-sm-9"><?php echo date('F j, Y', strtotime($profile_user['created_at'])); ?>
                            </div>
                        </div>

                        <?php if (isAdmin() && $profile_user_id !== $user_id): ?>
                            <div class="row mb-3">
                                <div class="col-sm-3"><strong>Status:</strong></div>
                                <div class="col-sm-9">
                                    <span class="badge bg-<?php echo $profile_user['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $profile_user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Profile Edit Mode -->
                    <?php if ($profile_user_id === $user_id || isAdmin()): ?>
                        <div id="profileEdit" style="display: none;">
                            <form method="POST" action="">
                                <?php echo getCSRFTokenField(); ?>

                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?php echo htmlspecialchars($profile_user['name']); ?>" required
                                        maxlength="255">
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($profile_user['email']); ?>" required
                                        maxlength="255">
                                </div>

                                <div class="mb-3">
                                    <label for="year_left" class="form-label">Year Left Nyumba ya Watoto</label>
                                    <input type="number" class="form-control" id="year_left" name="year_left"
                                        value="<?php echo htmlspecialchars($profile_user['year_left'] ?? ''); ?>" min="1990"
                                        max="<?php echo date('Y'); ?>">
                                    <div class="form-text">Leave empty if you are a current resident</div>
                                </div>

                                <div class="mb-3">
                                    <label for="education" class="form-label">Education</label>
                                    <textarea class="form-control" id="education" name="education" rows="3"
                                        maxlength="1000"><?php echo htmlspecialchars($profile_user['education'] ?? ''); ?></textarea>
                                    <div class="form-text">Describe your educational background</div>
                                </div>

                                <div class="mb-3">
                                    <label for="skills" class="form-label">Skills</label>
                                    <textarea class="form-control" id="skills" name="skills" rows="2"
                                        maxlength="500"><?php echo htmlspecialchars($profile_user['skills'] ?? ''); ?></textarea>
                                    <div class="form-text">List your key skills and competencies</div>
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location"
                                        value="<?php echo htmlspecialchars($profile_user['location'] ?? ''); ?>"
                                        maxlength="255">
                                    <div class="form-text">Your current city or region</div>
                                </div>

                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4"
                                        maxlength="1000"><?php echo htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
                                    <div class="form-text">Tell others about yourself and your goals</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancelEditBtn">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CV Upload Section -->
            <?php if ($profile_user_id === $user_id || isAdmin()): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">CV Management</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($profile_user['cv_path'] && file_exists($profile_user['cv_path'])): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-file-pdf"></i> CV uploaded on
                                <?php echo date('F j, Y', filemtime($profile_user['cv_path'])); ?>
                                <div class="mt-2">
                                    <a href="<?php echo SITE_URL; ?>/download_cv.php?user_id=<?php echo $profile_user['account_id']; ?>"
                                        class="btn btn-outline-primary btn-sm me-2">
                                        <i class="fas fa-download"></i> Download Current CV
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/upload_cv.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-upload"></i> Upload New CV
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No CV uploaded yet.
                                <div class="mt-2">
                                    <a href="<?php echo SITE_URL; ?>/upload_cv.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-upload"></i> Upload CV
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const editBtn = document.getElementById('editProfileBtn');
        const cancelBtn = document.getElementById('cancelEditBtn');
        const profileView = document.getElementById('profileView');
        const profileEdit = document.getElementById('profileEdit');

        if (editBtn) {
            editBtn.addEventListener('click', function () {
                profileView.style.display = 'none';
                profileEdit.style.display = 'block';
                editBtn.style.display = 'none';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                profileView.style.display = 'block';
                profileEdit.style.display = 'none';
                if (editBtn) editBtn.style.display = 'inline-block';
            });
        }

        // Character count for textareas
        const textareas = document.querySelectorAll('textarea[maxlength]');
        textareas.forEach(function (textarea) {
            const maxLength = textarea.getAttribute('maxlength');
            const helpText = textarea.nextElementSibling;

            function updateCount() {
                const remaining = maxLength - textarea.value.length;
                const originalText = helpText.textContent;
                const baseText = originalText.split('(')[0].trim();
                helpText.textContent = baseText + ` (${remaining} characters remaining)`;

                if (remaining < 50) {
                    helpText.className = 'form-text text-warning';
                } else {
                    helpText.className = 'form-text';
                }
            }

            textarea.addEventListener('input', updateCount);
            updateCount(); // Initial count
        });
    });
</script>

<?php include 'includes/footer.php'; ?>