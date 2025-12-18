<?php
/**
 * Opportunities Listing Page
 * Displays approved opportunities with filtering and pagination
 */

// Define page constants
define('NYUMBA_CONNECT', true);
$pageTitle = 'Opportunities';

// Include required files
require_once __DIR__ . '/../includes/config.php';

// Initialize authentication
init_auth();
require_auth();

// Get filter parameters
$category = sanitizeInput($_GET['category'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = ITEMS_PER_PAGE;

try {
    $db = getDB();
    
    // Build WHERE clause for filtering
    $whereConditions = ["o.status = ?"];
    $params = [STATUS_APPROVED];
    
    if (!empty($category)) {
        $whereConditions[] = "o.category = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(o.title LIKE ? OR o.description LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM opportunities o WHERE {$whereClause}";
    $totalResult = $db->fetchOne($countSql, $params);
    $totalItems = $totalResult['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Get opportunities with pagination
    $offset = ($page - 1) * $itemsPerPage;
    $sql = "SELECT o.*, u.name as posted_by_name 
            FROM opportunities o 
            JOIN accounts u ON o.posted_by = u.account_id 
            WHERE {$whereClause}
            ORDER BY o.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $itemsPerPage;
    $params[] = $offset;
    
    $opportunities = $db->fetchAll($sql, $params);
    
    // Get available categories for filter
    $categorySql = "SELECT DISTINCT category FROM opportunities WHERE status = ? ORDER BY category";
    $categories = $db->fetchAll($categorySql, [STATUS_APPROVED]);
    
} catch (Exception $e) {
    error_log("Error fetching opportunities: " . $e->getMessage());
    setFlashMessage('error', 'Unable to load opportunities. Please try again.');
    $opportunities = [];
    $categories = [];
    $totalPages = 0;
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-briefcase me-2"></i>Opportunities</h1>
            <?php if (isAlumni() || isAdmin()): ?>
                <a href="<?php echo SITE_URL; ?>/opportunities/create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Post Opportunity
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo sanitizeInput($search); ?>" 
                               placeholder="Search opportunities...">
                    </div>
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo sanitizeInput($cat['category']); ?>" 
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput(ucfirst($cat['category'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                        <a href="<?php echo SITE_URL; ?>/opportunities/list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Results Summary -->
<div class="row mb-3">
    <div class="col-12">
        <p class="text-muted">
            Showing <?php echo count($opportunities); ?> of <?php echo $totalItems; ?> opportunities
            <?php if (!empty($search) || !empty($category)): ?>
                <span class="badge bg-info ms-2">Filtered</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Opportunities List -->
<div class="row">
    <?php if (empty($opportunities)): ?>
        <div class="col-12">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-briefcase display-1 text-muted mb-3"></i>
                    <h3 class="text-muted">No Opportunities Found</h3>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($category)): ?>
                            Try adjusting your search criteria or clearing the filters.
                        <?php else: ?>
                            There are currently no approved opportunities available.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <a href="<?php echo SITE_URL; ?>/opportunities/list.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-1"></i>View All Opportunities
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($opportunities as $opportunity): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 opportunity-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary"><?php echo sanitizeInput(ucfirst($opportunity['category'])); ?></span>
                            <?php if (!empty($opportunity['deadline']) && !isDeadlinePassed($opportunity['deadline'])): ?>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    Due: <?php echo formatDate($opportunity['deadline']); ?>
                                </small>
                            <?php elseif (!empty($opportunity['deadline']) && isDeadlinePassed($opportunity['deadline'])): ?>
                                <small class="text-danger">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Expired
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="card-title">
                            <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opportunity['opportunity_id']; ?>" 
                               class="text-decoration-none">
                                <?php echo sanitizeInput($opportunity['title']); ?>
                            </a>
                        </h5>
                        
                        <p class="card-text flex-grow-1">
                            <?php echo truncateText(sanitizeInput($opportunity['description']), 120); ?>
                        </p>
                        
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Posted by <?php echo sanitizeInput($opportunity['posted_by_name']); ?>
                                </small>
                                <small class="text-muted">
                                    <?php echo timeAgo($opportunity['created_at']); ?>
                                </small>
                            </div>
                            
                            <div class="mt-2">
                                <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $opportunity['opportunity_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>View Details
                                </a>
                                
                                <?php if (!empty($opportunity['deadline']) && !isDeadlinePassed($opportunity['deadline'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/opportunities/apply.php?id=<?php echo $opportunity['opportunity_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="bi bi-send me-1"></i>Apply
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="row mt-4">
        <div class="col-12">
            <?php 
            $queryParams = [];
            if (!empty($search)) $queryParams['search'] = $search;
            if (!empty($category)) $queryParams['category'] = $category;
            echo generatePagination($page, $totalPages, SITE_URL . '/opportunities/list.php', $queryParams);
            ?>
        </div>
    </div>
<?php endif; ?>

<style>
.opportunity-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.opportunity-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.opportunity-card .card-title a {
    color: inherit;
}

.opportunity-card .card-title a:hover {
    color: var(--bs-primary);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>