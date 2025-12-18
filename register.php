<?php
/**
 * User Registration Page for Nyumba Connect Platform
 * Handles user registration with role-based account creation
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Sanitize input data
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? '');
        $year_left = sanitizeInput($_POST['year_left'] ?? '');

        // Validate required fields
        if (empty($name)) {
            $errors[] = "Name is required.";
        }

        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!validateEmail($email)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($password)) {
            $errors[] = "Password is required.";
        } else {
            $passwordValidation = validatePassword($password);
            if ($passwordValidation !== true) {
                $errors = array_merge($errors, $passwordValidation);
            }
        }

        if ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($role) || !in_array($role, [ROLE_STUDENT, ROLE_ALUMNI])) {
            $errors[] = "Please select a valid role.";
        }

        // Validate year_left for alumni
        if ($role === ROLE_ALUMNI) {
            if (empty($year_left)) {
                $errors[] = "Year left is required for alumni.";
            } elseif (!is_numeric($year_left) || $year_left < 1990 || $year_left > date('Y')) {
                $errors[] = "Please enter a valid year between 1990 and " . date('Y') . ".";
            }
        }

        // Check for duplicate email
        if (empty($errors)) {
            try {
                $db = getDB();
                $existingUser = $db->fetchOne(
                    "SELECT account_id FROM accounts WHERE email = ?",
                    [$email]
                );

                if ($existingUser) {
                    $errors[] = "An account with this email address already exists.";
                }
            } catch (Exception $e) {
                $errors[] = "Database error occurred. Please try again.";
                error_log("Registration email check error: " . $e->getMessage());
            }
        }

        // Create user account if no errors
        if (empty($errors)) {
            try {
                $db = getDB();
                $passwordHash = hashPassword($password);

                $sql = "INSERT INTO accounts (name, email, password_hash, role, year_left, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, TRUE, NOW())";
                
                $params = [
                    $name,
                    $email,
                    $passwordHash,
                    $role,
                    $role === ROLE_ALUMNI ? $year_left : null
                ];

                $db->execute($sql, $params);
                
                // Log the registration
                $userId = $db->lastInsertId();
                logActivity($userId, 'User Registration', "Role: $role, Email: $email");

                $success = true;
                setFlashMessage('success', 'Registration successful! You can now log in with your credentials.');
                
                // Redirect to login page
                header('Location: login.php');
                exit;

            } catch (Exception $e) {
                $errors[] = "Registration failed. Please try again.";
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center mb-0">Join Nyumba Connect</h3>
                </div>
                <div class="card-body">
                    <?php echo displayFlashMessages(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitizeInput($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php">
                        <?php echo getCSRFTokenField(); ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo sanitizeInput($_POST['name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo sanitizeInput($_POST['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">
                                Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long and contain at least one uppercase letter, one lowercase letter, and one number.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">I am a *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select your role</option>
                                <option value="<?php echo ROLE_STUDENT; ?>" 
                                        <?php echo (($_POST['role'] ?? '') === ROLE_STUDENT) ? 'selected' : ''; ?>>
                                    Current Student/Recent Graduate
                                </option>
                                <option value="<?php echo ROLE_ALUMNI; ?>" 
                                        <?php echo (($_POST['role'] ?? '') === ROLE_ALUMNI) ? 'selected' : ''; ?>>
                                    Alumni (Former Resident)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3" id="year_left_group" style="display: none;">
                            <label for="year_left" class="form-label">Year You Left Nyumba ya Watoto *</label>
                            <input type="number" class="form-control" id="year_left" name="year_left" 
                                   min="1990" max="<?php echo date('Y'); ?>" 
                                   value="<?php echo sanitizeInput($_POST['year_left'] ?? ''); ?>">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Sign in here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const yearLeftGroup = document.getElementById('year_left_group');
    const yearLeftInput = document.getElementById('year_left');

    function toggleYearLeft() {
        if (roleSelect.value === '<?php echo ROLE_ALUMNI; ?>') {
            yearLeftGroup.style.display = 'block';
            yearLeftInput.required = true;
        } else {
            yearLeftGroup.style.display = 'none';
            yearLeftInput.required = false;
            yearLeftInput.value = '';
        }
    }

    roleSelect.addEventListener('change', toggleYearLeft);
    
    // Initialize on page load
    toggleYearLeft();
});
</script>

<?php require_once 'includes/footer.php'; ?>