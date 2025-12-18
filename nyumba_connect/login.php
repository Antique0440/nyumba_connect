<?php
/**
 * User Login Page for Nyumba Connect Platform
 * Handles user authentication with secure session management
 */

define('NYUMBA_CONNECT', true);
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttemptTime = $_SESSION['last_attempt_time'] ?? 0;

// Check if user is locked out
$isLockedOut = false;
if ($loginAttempts >= MAX_LOGIN_ATTEMPTS) {
    $timeSinceLastAttempt = time() - $lastAttemptTime;
    if ($timeSinceLastAttempt < LOGIN_LOCKOUT_TIME) {
        $isLockedOut = true;
        $remainingTime = LOGIN_LOCKOUT_TIME - $timeSinceLastAttempt;
        $errors[] = "Too many failed login attempts. Please try again in " . ceil($remainingTime / 60) . " minutes.";
    } else {
        // Reset attempts after lockout period
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt_time']);
        $loginAttempts = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    // Validate CSRF token
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Sanitize input data
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        // Validate required fields
        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!validateEmail($email)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($password)) {
            $errors[] = "Password is required.";
        }

        // Authenticate user if no validation errors
        if (empty($errors)) {
            try {
                $db = getDB();
                $user = $db->fetchOne(
                    "SELECT user_id, name, email, password_hash, role, is_active FROM users WHERE email = ?",
                    [$email]
                );

                if ($user) {
                    $passwordCheck = verifyPasswordSecure($password, $user['password_hash']);

                    if ($passwordCheck['valid']) {
                        // Check if account is active
                        if (!$user['is_active']) {
                            $errors[] = "Your account has been deactivated. Please contact the administrator.";
                        } else {
                            // Update password hash if needed
                            if ($passwordCheck['needs_rehash']) {
                                try {
                                    $db->execute(
                                        "UPDATE users SET password_hash = ? WHERE user_id = ?",
                                        [$passwordCheck['new_hash'], $user['user_id']]
                                    );
                                } catch (Exception $e) {
                                    error_log("Password rehash error: " . $e->getMessage());
                                }
                            }

                            // Successful login - create session
                            session_regenerate_id(true);

                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();

                            // Set remember me cookie if requested
                            if ($remember_me) {
                                $token = generateToken();
                                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true); // 30 days

                                // Store token in database (you might want to create a remember_tokens table)
                                // For now, we'll just extend session lifetime
                                ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
                            }

                            // Reset login attempts
                            unset($_SESSION['login_attempts']);
                            unset($_SESSION['last_attempt_time']);

                            // Log successful login
                            logActivity($user['user_id'], 'Login', "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                            // Redirect to dashboard or intended page
                            $redirectUrl = $_SESSION['intended_url'] ?? 'dashboard.php';
                            unset($_SESSION['intended_url']);

                            header('Location: ' . $redirectUrl);
                            exit;
                        }
                    } else {
                        // Invalid password
                        $errors[] = "Invalid email or password.";
                    }
                } else {
                    // Invalid credentials
                    $errors[] = "Invalid email or password.";

                    // Increment login attempts
                    $_SESSION['login_attempts'] = $loginAttempts + 1;
                    $_SESSION['last_attempt_time'] = time();

                    // Log failed login attempt
                    if ($user) {
                        logActivity($user['user_id'], 'Failed Login', "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Login failed. Please try again.";
                error_log("Login error: " . $e->getMessage());
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
                    <h3 class="text-center mb-0">Welcome Back</h3>
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

                    <?php if ($loginAttempts > 0 && $loginAttempts < MAX_LOGIN_ATTEMPTS && !$isLockedOut): ?>
                        <div class="alert alert-warning">
                            <small>Failed attempts: <?php echo $loginAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?></small>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" <?php echo $isLockedOut ? 'style="display:none;"' : ''; ?>>
                        <?php echo getCSRFTokenField(); ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?php echo sanitizeInput($_POST['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">
                                Remember me for 30 days
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Sign In</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="register.php">Create one here</a></p>
                        <p><a href="forgot-password.php">Forgot your password?</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>