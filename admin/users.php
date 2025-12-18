<?php
/**
 * Admin User Management System
 * Allows administrators to view, manage, and control user accounts
 */

// Define page constant
define('NYUMBA_CONNECT', true);

// Include configuration and check admin access
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'User Management';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);

    try {
        $db = getDB();

        switch ($action) {
            case 'toggle_status':
                $new_status = $_POST['new_status'] === '1' ? 1 : 0;
                $db->execute(
                    "UPDATE accounts SET is_active = ? WHERE account_id = ?",
                    [$new_status, $user_id]
                );

                $status_text = $new_status ? 'activated' : 'deactivated';
                logActivity(getCurrentUserId(), 'User Status Change', "User ID $user_id $status_text");
                setFlashMessage('success', "User account has been $status_text successfully.");
                break;

            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                if (!in_array($new_role, [ROLE_STUDENT, ROLE_ALUMNI, ROLE_ADMIN])) {
                    throw new Exception('Invalid role specified.');
                }

                $db->execute(
                    "UPDATE accounts SET role = ? WHERE account_id = ?",
                    [$new_role, $user_id]
                );

                logActivity(getCurrentUserId(), 'User Role Change', "User ID $user_id role changed to $new_role");
                setFlashMessage('success', "User role has been updated successfully.");
                break;

            case 'delete_user':
                // Check if user has any dependencies
                $dependencies = $db->fetchOne(
                    "SELECT 
                        (SELECT COUNT(*) FROM opportunities WHERE posted_by = ?) as opportunities,
                        (SELECT COUNT(*) FROM applications WHERE applicant_id = ?) as applications,
                        (SELECT COUNT(*) FROM mentorship_requests WHERE student_id = ? OR alumni_id = ?) as requests,
                        (SELECT COUNT(*) FROM mentorships WHERE student_id = ? OR alumni_id = ?) as mentorships,
                        (SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?) as messages,
                        (SELECT COUNT(*) FROM resources WHERE uploaded_by = ?) as resources",
                    [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]
                );

                $total_dependencies = array_sum($dependencies);

                if ($total_dependencies > 0) {
                    // Instead of deleting, deactivate the user to preserve referential integrity
                    $db->execute(
                        "UPDATE accounts SET is_active = 0, email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE account_id = ?",
                        [$user_id]
                    );

                    logActivity(getCurrentUserId(), 'User Deactivated', "User ID $user_id deactivated due to existing data dependencies");
                    setFlashMessage('warning', 'User has existing data and has been deactivated instead of deleted to preserve data integrity.');
                } else {
                    // Safe to delete
                    $db->execute("DELETE FROM accounts WHERE account_id = ?", [$user_id]);

                    logActivity(getCurrentUserId(), 'User Deleted', "User ID $user_id permanently deleted");
                    setFlashMessage('success', 'User has been permanently deleted.');
                }
                break;

            default:
                throw new Exception('Invalid action specified.');
        }

    } catch (Exception $e) {
        error_log("Admin user management error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: users.php');
    exit;
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($role_filter) && in_array($role_filter, [ROLE_STUDENT, ROLE_ALUMNI, ROLE_ADMIN])) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $conditions[] = "is_active = ?";
    $params[] = $status_filter === '1' ? 1 : 0;
}

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $db = getDB();

    // Get total count for pagination
    $total_query = "SELECT COUNT(*) as total FROM accounts $where_clause";
    $total_result = $db->fetchOne($total_query, $params);
    $total_users = $total_result['total'];
    $total_pages = ceil($total_users / $per_page);

    // Get users for current page
    $users_query = "SELECT account_id, name, email, role, year_left, location, is_active, created_at 
                    FROM accounts $where_clause 
                    ORDER BY created_at DESC 
                    LIMIT $per_page OFFSET $offset";

    $users = $db->fetchAll($users_query, $params);

    // Get summary statistics
    $stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'alumni' THEN 1 ELSE 0 END) as alumni,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
         FROM accounts"
    );

} catch (Exception $e) {
    error_log("Database error in admin users: " . $e->getMessage());
    setFlashMessage('error', 'Unable to load user data. Please try again.');
    $users = [];
    $stats = [];
    $total_pages = 0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-people me-2"></i>User Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/index.php">Admin</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </nav>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($stats)): ?>
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['total_users']; ?></h3>
                            <p class="card-text">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['students']; ?></h3>
                            <p class="card-text">Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['alumni']; ?></h3>
                            <p class="card-text">Alumni</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['admins']; ?></h3>
                            <p class="card-text">Admins</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['active_users']; ?></h3>
                            <p class="card-text">Active</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?php echo $stats['inactive_users']; ?></h3>
                            <p class="card-text">Inactive</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select name="role" id="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="<?php echo ROLE_STUDENT; ?>" <?php echo $role_filter === ROLE_STUDENT ? 'selected' : ''; ?>>Students</option>
                            <option value="<?php echo ROLE_ALUMNI; ?>" <?php echo $role_filter === ROLE_ALUMNI ? 'selected' : ''; ?>>Alumni</option>
                            <option value="<?php echo ROLE_ADMIN; ?>" <?php echo $role_filter === ROLE_ADMIN ? 'selected' : ''; ?>>Admins</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" name="search" id="search" class="form-control"
                            placeholder="Search by name or email..." value="<?php echo sanitizeInput($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    Users (<?php echo number_format($total_users); ?> total)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Year Left</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo sanitizeInput($user['name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo sanitizeInput($user['email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($user['role']); ?>
                                        </td>
                                        <td>
                                            <?php echo $user['year_left'] ? sanitizeInput($user['year_left']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $user['location'] ? sanitizeInput($user['location']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($user['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($user['account_id'] != getCurrentUserId()): ?>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <!-- Toggle Status -->
                                                    <form method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                                        <?php echo getCSRFTokenField(); ?>
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id"
                                                            value="<?php echo $user['account_id']; ?>">
                                                        <input type="hidden" name="new_status"
                                                            value="<?php echo $user['is_active'] ? '0' : '1'; ?>">
                                                        <button type="submit"
                                                            class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>"
                                                            title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                            <i
                                                                class="bi bi-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Change Role -->
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" title="Change Role">
                                                            <i class="bi bi-person-gear"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php foreach ([ROLE_STUDENT => 'Student', ROLE_ALUMNI => 'Alumni', ROLE_ADMIN => 'Admin'] as $role => $label): ?>
                                                                <?php if ($role !== $user['role']): ?>
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <?php echo getCSRFTokenField(); ?>
                                                                            <input type="hidden" name="action" value="change_role">
                                                                            <input type="hidden" name="user_id"
                                                                                value="<?php echo $user['account_id']; ?>">
                                                                            <input type="hidden" name="new_role"
                                                                                value="<?php echo $role; ?>">
                                                                            <button type="submit" class="dropdown-item"
                                                                                onclick="return confirm('Change user role to <?php echo $label; ?>?');">
                                                                                Make <?php echo $label; ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>

                                                    <!-- Delete User -->
                                                    <form method="POST" class="d-inline"
                                                        onsubmit="return confirm('Are you sure you want to delete this user? This action may deactivate the user instead if they have existing data.');">
                                                        <?php echo getCSRFTokenField(); ?>
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id"
                                                            value="<?php echo $user['account_id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger" title="Delete User">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">Current User</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h4 class="mt-3">No Users Found</h4>
                        <p class="text-muted">No users match your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-4">
                <?php
                $query_params = array_filter([
                    'role' => $role_filter,
                    'status' => $status_filter,
                    'search' => $search
                ]);
                echo generatePagination($page, $total_pages, 'users.php', $query_params);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>