<?php
/**
 * Admin Mentorship Monitoring System
 * Allows administrators to monitor and manage mentorship relationships
 */

// Define page constant
define('NYUMBA_CONNECT', true);

// Include configuration and check admin access
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Mentorship Management';

// Handle mentorship actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: mentorships.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $mentorship_id = (int) ($_POST['mentorship_id'] ?? 0);

    try {
        $db = getDB();

        switch ($action) {
            case 'deactivate':
                $db->execute(
                    "UPDATE mentorships SET active = FALSE WHERE mentorship_id = ?",
                    [$mentorship_id]
                );

                logActivity(getCurrentUserId(), 'Mentorship Deactivated', "Mentorship ID $mentorship_id deactivated by admin");
                setFlashMessage('warning', 'Mentorship relationship has been deactivated.');
                break;

            case 'reactivate':
                $db->execute(
                    "UPDATE mentorships SET active = TRUE WHERE mentorship_id = ?",
                    [$mentorship_id]
                );

                logActivity(getCurrentUserId(), 'Mentorship Reactivated', "Mentorship ID $mentorship_id reactivated by admin");
                setFlashMessage('success', 'Mentorship relationship has been reactivated.');
                break;

            default:
                throw new Exception('Invalid action specified.');
        }

    } catch (Exception $e) {
        error_log("Admin mentorship management error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: mentorships.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if ($status_filter === 'active') {
    $conditions[] = "m.active = TRUE";
} elseif ($status_filter === 'inactive') {
    $conditions[] = "m.active = FALSE";
}

if (!empty($search)) {
    $conditions[] = "(s.name LIKE ? OR s.email LIKE ? OR a.name LIKE ? OR a.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $db = getDB();

    // Get total count for pagination
    $total_query = "SELECT COUNT(*) as total 
                    FROM mentorships m
                    JOIN accounts s ON m.student_id = s.account_id
                    JOIN accounts a ON m.alumni_id = a.account_id
                    $where_clause";
    $total_result = $db->fetchOne($total_query, $params);
    $total_mentorships = $total_result['total'];
    $total_pages = ceil($total_mentorships / $per_page);

    // Get mentorships for current page
    $mentorships_query = "SELECT m.mentorship_id, m.started_at, m.active,
                                 s.user_id as student_id, s.name as student_name, s.email as student_email,
                                 s.year_left as student_year, s.location as student_location,
                                 a.user_id as alumni_id, a.name as alumni_name, a.email as alumni_email,
                                 a.year_left as alumni_year, a.location as alumni_location,
                                 (SELECT COUNT(*) FROM messages msg WHERE msg.mentorship_id = m.mentorship_id) as message_count,
                                 (SELECT MAX(msg.sent_at) FROM messages msg WHERE msg.mentorship_id = m.mentorship_id) as last_message_date
                          FROM mentorships m
                          JOIN accounts s ON m.student_id = s.account_id
                          JOIN accounts a ON m.alumni_id = a.account_id
                          $where_clause 
                          ORDER BY m.started_at DESC 
                          LIMIT $per_page OFFSET $offset";

    $mentorships = $db->fetchAll($mentorships_query, $params);

    // Get summary statistics
    $stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_mentorships,
            SUM(CASE WHEN active = TRUE THEN 1 ELSE 0 END) as active_mentorships,
            SUM(CASE WHEN active = FALSE THEN 1 ELSE 0 END) as inactive_mentorships,
            AVG(CASE WHEN active = TRUE THEN DATEDIFF(NOW(), started_at) ELSE NULL END) as avg_duration_days
         FROM mentorships"
    );

    // Get mentorship requests statistics
    $request_stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_requests,
            SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined_requests
         FROM mentorship_requests"
    );

    // Get recent activity
    $recent_activity = $db->fetchAll(
        "SELECT 'request' as type, mr.requested_at as date, 
                s.name as student_name, a.name as alumni_name, mr.status
         FROM mentorship_requests mr
         JOIN accounts s ON mr.student_id = s.account_id
         JOIN accounts a ON mr.alumni_id = a.account_id
         WHERE mr.requested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         UNION ALL
         SELECT 'mentorship' as type, m.started_at as date,
                s.name as student_name, a.name as alumni_name, 
                CASE WHEN m.active THEN 'active' ELSE 'inactive' END as status
         FROM mentorships m
         JOIN accounts s ON m.student_id = s.account_id
         JOIN accounts a ON m.alumni_id = a.account_id
         WHERE m.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         ORDER BY date DESC
         LIMIT 10"
    );

} catch (Exception $e) {
    error_log("Database error in admin mentorships: " . $e->getMessage());
    setFlashMessage('error', 'Unable to load mentorship data. Please try again.');
    $mentorships = [];
    $stats = [];
    $request_stats = [];
    $recent_activity = [];
    $total_pages = 0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-people me-2"></i>Mentorship Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/index.php">Admin</a></li>
                    <li class="breadcrumb-item active">Mentorships</li>
                </ol>
            </nav>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['total_mentorships'] ?? 0; ?></h3>
                        <p class="card-text">Total Mentorships</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['active_mentorships'] ?? 0; ?></h3>
                        <p class="card-text">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $request_stats['pending_requests'] ?? 0; ?></h3>
                        <p class="card-text">Pending Requests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title">
                            <?php
                            $avg_days = $stats['avg_duration_days'] ?? 0;
                            echo $avg_days ? round($avg_days) : 0;
                            ?>
                        </h3>
                        <p class="card-text">Avg Duration (Days)</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                                        Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control"
                                    placeholder="Search by student or alumni name/email..."
                                    value="<?php echo sanitizeInput($search); ?>">
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

                <!-- Mentorships List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Mentorship Relationships (<?php echo number_format($total_mentorships); ?> total)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($mentorships)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Alumni</th>
                                            <th>Started</th>
                                            <th>Messages</th>
                                            <th>Last Activity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mentorships as $mentorship): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo sanitizeInput($mentorship['student_name']); ?></strong>
                                                        <br>
                                                        <small
                                                            class="text-muted"><?php echo sanitizeInput($mentorship['student_email']); ?></small>
                                                        <?php if ($mentorship['student_year']): ?>
                                                            <br>
                                                            <small>Class of
                                                                <?php echo sanitizeInput($mentorship['student_year']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo sanitizeInput($mentorship['alumni_name']); ?></strong>
                                                        <br>
                                                        <small
                                                            class="text-muted"><?php echo sanitizeInput($mentorship['alumni_email']); ?></small>
                                                        <?php if ($mentorship['alumni_year']): ?>
                                                            <br>
                                                            <small>Class of
                                                                <?php echo sanitizeInput($mentorship['alumni_year']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDate($mentorship['started_at']); ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo timeAgo($mentorship['started_at']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-info"><?php echo $mentorship['message_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($mentorship['last_message_date']): ?>
                                                        <small><?php echo timeAgo($mentorship['last_message_date']); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">No messages</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($mentorship['active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if ($mentorship['active']): ?>
                                                            <!-- Deactivate -->
                                                            <form method="POST" class="d-inline"
                                                                onsubmit="return confirm('Deactivate this mentorship relationship?');">
                                                                <?php echo getCSRFTokenField(); ?>
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="mentorship_id"
                                                                    value="<?php echo $mentorship['mentorship_id']; ?>">
                                                                <button type="submit" class="btn btn-warning" title="Deactivate">
                                                                    <i class="bi bi-pause"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <!-- Reactivate -->
                                                            <form method="POST" class="d-inline"
                                                                onsubmit="return confirm('Reactivate this mentorship relationship?');">
                                                                <?php echo getCSRFTokenField(); ?>
                                                                <input type="hidden" name="action" value="reactivate">
                                                                <input type="hidden" name="mentorship_id"
                                                                    value="<?php echo $mentorship['mentorship_id']; ?>">
                                                                <button type="submit" class="btn btn-success" title="Reactivate">
                                                                    <i class="bi bi-play"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <!-- View Messages -->
                                                        <a href="<?php echo SITE_URL; ?>/messages/thread.php?mentorship_id=<?php echo $mentorship['mentorship_id']; ?>"
                                                            class="btn btn-outline-primary" title="View Messages">
                                                            <i class="bi bi-chat-dots"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <h4 class="mt-3">No Mentorships Found</h4>
                                <p class="text-muted">No mentorship relationships match your current filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-4">
                        <?php
                        $query_params = array_filter([
                            'status' => $status_filter,
                            'search' => $search
                        ]);
                        echo generatePagination($page, $total_pages, 'mentorships.php', $query_params);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Request Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Request Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary"><?php echo $request_stats['total_requests'] ?? 0; ?></h4>
                                <small>Total Requests</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success"><?php echo $request_stats['accepted_requests'] ?? 0; ?></h4>
                                <small>Accepted</small>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-warning"><?php echo $request_stats['pending_requests'] ?? 0; ?></h4>
                                <small>Pending</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-danger"><?php echo $request_stats['declined_requests'] ?? 0; ?></h4>
                                <small>Declined</small>
                            </div>
                        </div>
                        <?php if ($request_stats['total_requests'] > 0): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-muted">
                                    Success Rate:
                                    <?php
                                    $success_rate = ($request_stats['accepted_requests'] / $request_stats['total_requests']) * 100;
                                    echo round($success_rate, 1);
                                    ?>%
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Activity (30 days)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activity)): ?>
                            <div class="timeline">
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <?php if ($activity['type'] === 'request'): ?>
                                                    <i class="bi bi-envelope text-primary"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-people text-success"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <small>
                                                    <strong><?php echo sanitizeInput($activity['student_name']); ?></strong>
                                                    <?php if ($activity['type'] === 'request'): ?>
                                                        sent request to
                                                    <?php else: ?>
                                                        started mentorship with
                                                    <?php endif; ?>
                                                    <strong><?php echo sanitizeInput($activity['alumni_name']); ?></strong>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo timeAgo($activity['date']); ?>
                                                    - <?php echo getStatusBadge($activity['status']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clock-history text-muted"></i>
                                <p class="text-muted mb-0">No recent activity</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>