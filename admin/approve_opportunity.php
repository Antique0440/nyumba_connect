<?php
/**
 * Admin Opportunity Approval Workflow
 * Allows administrators to approve, reject, and manage opportunity postings
 */

// Define page constant
define('NYUMBA_CONNECT', true);

// Include configuration and check admin access
require_once __DIR__ . '/../includes/config.php';
require_admin();

$pageTitle = 'Opportunity Management';

// Handle opportunity actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: approve_opportunity.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $opportunity_id = (int) ($_POST['opportunity_id'] ?? 0);

    try {
        $db = getDB();

        switch ($action) {
            case 'approve':
                $db->execute(
                    "UPDATE opportunities SET status = ? WHERE opportunity_id = ?",
                    [STATUS_APPROVED, $opportunity_id]
                );
                
                logActivity(getCurrentUserId(), 'Opportunity Approved', "Opportunity ID $opportunity_id approved");
                setFlashMessage('success', 'Opportunity has been approved and is now visible to users.');
                break;

            case 'reject':
                $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                
                $db->execute(
                    "UPDATE opportunities SET status = ? WHERE opportunity_id = ?",
                    [STATUS_REJECTED, $opportunity_id]
                );
                
                $log_details = "Opportunity ID $opportunity_id rejected";
                if (!empty($rejection_reason)) {
                    $log_details .= " - Reason: $rejection_reason";
                }
                
                logActivity(getCurrentUserId(), 'Opportunity Rejected', $log_details);
                setFlashMessage('warning', 'Opportunity has been rejected.');
                break;

            case 'close':
                $db->execute(
                    "UPDATE opportunities SET status = ? WHERE opportunity_id = ?",
                    [STATUS_CLOSED, $opportunity_id]
                );
                
                logActivity(getCurrentUserId(), 'Opportunity Closed', "Opportunity ID $opportunity_id closed");
                setFlashMessage('info', 'Opportunity has been closed.');
                break;

            case 'reopen':
                $db->execute(
                    "UPDATE opportunities SET status = ? WHERE opportunity_id = ?",
                    [STATUS_APPROVED, $opportunity_id]
                );
                
                logActivity(getCurrentUserId(), 'Opportunity Reopened', "Opportunity ID $opportunity_id reopened");
                setFlashMessage('success', 'Opportunity has been reopened.');
                break;

            default:
                throw new Exception('Invalid action specified.');
        }

    } catch (Exception $e) {
        error_log("Admin opportunity management error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: approve_opportunity.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? STATUS_PENDING;
$category_filter = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($status_filter) && in_array($status_filter, [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED, STATUS_CLOSED])) {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $conditions[] = "o.category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $conditions[] = "(o.title LIKE ? OR o.description LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try {
    $db = getDB();

    // Get total count for pagination
    $total_query = "SELECT COUNT(*) as total 
                    FROM opportunities o 
                    JOIN accounts u ON o.posted_by = u.account_id 
                    $where_clause";
    $total_result = $db->fetchOne($total_query, $params);
    $total_opportunities = $total_result['total'];
    $total_pages = ceil($total_opportunities / $per_page);

    // Get opportunities for current page
    $opportunities_query = "SELECT o.opportunity_id, o.title, o.description, o.category, o.deadline, 
                                   o.link, o.status, o.created_at,
                                   u.name as poster_name, u.email as poster_email, u.role as poster_role,
                                   (SELECT COUNT(*) FROM applications a WHERE a.opportunity_id = o.opportunity_id) as application_count
                            FROM opportunities o 
                            JOIN accounts u ON o.posted_by = u.account_id 
                            $where_clause 
                            ORDER BY 
                                CASE o.status 
                                    WHEN 'pending' THEN 1 
                                    WHEN 'approved' THEN 2 
                                    WHEN 'closed' THEN 3 
                                    WHEN 'rejected' THEN 4 
                                END,
                                o.created_at DESC 
                            LIMIT $per_page OFFSET $offset";
    
    $opportunities = $db->fetchAll($opportunities_query, $params);

    // Get summary statistics
    $stats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_opportunities,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
         FROM opportunities"
    );

    // Get categories for filter
    $categories = $db->fetchAll("SELECT DISTINCT category FROM opportunities ORDER BY category");

} catch (Exception $e) {
    error_log("Database error in admin opportunities: " . $e->getMessage());
    setFlashMessage('error', 'Unable to load opportunity data. Please try again.');
    $opportunities = [];
    $stats = [];
    $categories = [];
    $total_pages = 0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-briefcase me-2"></i>Opportunity Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/index.php">Admin</a></li>
                    <li class="breadcrumb-item active">Opportunities</li>
                </ol>
            </nav>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($stats)): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['total_opportunities']; ?></h3>
                        <p class="card-text">Total Opportunities</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['pending']; ?></h3>
                        <p class="card-text">Pending Review</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['approved']; ?></h3>
                        <p class="card-text">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['rejected']; ?></h3>
                        <p class="card-text">Rejected</p>
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
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="<?php echo STATUS_PENDING; ?>" <?php echo $status_filter === STATUS_PENDING ? 'selected' : ''; ?>>Pending Review</option>
                            <option value="<?php echo STATUS_APPROVED; ?>" <?php echo $status_filter === STATUS_APPROVED ? 'selected' : ''; ?>>Approved</option>
                            <option value="<?php echo STATUS_REJECTED; ?>" <?php echo $status_filter === STATUS_REJECTED ? 'selected' : ''; ?>>Rejected</option>
                            <option value="<?php echo STATUS_CLOSED; ?>" <?php echo $status_filter === STATUS_CLOSED ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo sanitizeInput($cat['category']); ?>" 
                                    <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo sanitizeInput($cat['category']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Search by title, description, or poster..." value="<?php echo sanitizeInput($search); ?>">
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

        <!-- Opportunities List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    Opportunities (<?php echo number_format($total_opportunities); ?> total)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($opportunities)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Opportunity</th>
                                <th>Category</th>
                                <th>Posted By</th>
                                <th>Applications</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($opportunities as $opp): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo sanitizeInput($opp['title']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo truncateText(sanitizeInput($opp['description']), 100); ?>
                                        </small>
                                        <?php if (!empty($opp['link'])): ?>
                                        <br>
                                        <small>
                                            <a href="<?php echo sanitizeInput($opp['link']); ?>" target="_blank" class="text-decoration-none">
                                                <i class="bi bi-link-45deg"></i> View Details
                                            </a>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo sanitizeInput($opp['category']); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo sanitizeInput($opp['poster_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo sanitizeInput($opp['poster_email']); ?></small>
                                        <br>
                                        <small><?php echo getStatusBadge($opp['poster_role']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $opp['application_count']; ?></span>
                                </td>
                                <td>
                                    <?php if ($opp['deadline']): ?>
                                        <small>
                                            <?php echo formatDate($opp['deadline']); ?>
                                            <?php if (isDeadlinePassed($opp['deadline'])): ?>
                                                <br><span class="badge bg-danger">Expired</span>
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">No deadline</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($opp['status']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo formatDate($opp['created_at']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($opp['status'] === STATUS_PENDING): ?>
                                            <!-- Approve -->
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Approve this opportunity?');">
                                                <?php echo getCSRFTokenField(); ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="opportunity_id" value="<?php echo $opp['opportunity_id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Approve">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>

                                            <!-- Reject -->
                                            <button type="button" class="btn btn-danger" title="Reject" 
                                                    data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $opp['opportunity_id']; ?>">
                                                <i class="bi bi-x-lg"></i>
                                            </button>

                                            <!-- Reject Modal -->
                                            <div class="modal fade" id="rejectModal<?php echo $opp['opportunity_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject Opportunity</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to reject this opportunity?</p>
                                                                <div class="mb-3">
                                                                    <label for="rejection_reason<?php echo $opp['opportunity_id']; ?>" class="form-label">
                                                                        Reason (Optional)
                                                                    </label>
                                                                    <textarea name="rejection_reason" id="rejection_reason<?php echo $opp['opportunity_id']; ?>" 
                                                                              class="form-control" rows="3" 
                                                                              placeholder="Enter reason for rejection..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <?php echo getCSRFTokenField(); ?>
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="opportunity_id" value="<?php echo $opp['opportunity_id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Reject</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php elseif ($opp['status'] === STATUS_APPROVED): ?>
                                            <!-- Close -->
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Close this opportunity?');">
                                                <?php echo getCSRFTokenField(); ?>
                                                <input type="hidden" name="action" value="close">
                                                <input type="hidden" name="opportunity_id" value="<?php echo $opp['opportunity_id']; ?>">
                                                <button type="submit" class="btn btn-warning" title="Close">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            </form>

                                        <?php elseif ($opp['status'] === STATUS_CLOSED): ?>
                                            <!-- Reopen -->
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Reopen this opportunity?');">
                                                <?php echo getCSRFTokenField(); ?>
                                                <input type="hidden" name="action" value="reopen">
                                                <input type="hidden" name="opportunity_id" value="<?php echo $opp['opportunity_id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Reopen">
                                                    <i class="bi bi-unlock"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- View Details -->
                                        <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opp['opportunity_id']; ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
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
                    <i class="bi bi-briefcase display-1 text-muted"></i>
                    <h4 class="mt-3">No Opportunities Found</h4>
                    <p class="text-muted">No opportunities match your current filters.</p>
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
                'category' => $category_filter,
                'search' => $search
            ]);
            echo generatePagination($page, $total_pages, 'approve_opportunity.php', $query_params);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>