<?php
/**
 * Resource Library - List and browse available resources
 * Displays career development materials and resources for users
 */

define('NYUMBA_CONNECT', true);
require_once __DIR__ . '/../includes/config.php';

// Require authentication
require_auth();

$pageTitle = 'Resource Library';
$currentUserId = getCurrentUserId();

// Pagination settings
$itemsPerPage = 12;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $itemsPerPage;

// Search and filter parameters
$searchQuery = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Validate sort parameters
$allowedSortFields = ['title', 'created_at', 'download_count'];
$allowedSortOrders = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'created_at';
}
if (!in_array($sortOrder, $allowedSortOrders)) {
    $sortOrder = 'DESC';
}

try {
    $db = getDB();

    // Build WHERE clause for search
    $whereClause = "WHERE 1=1";
    $params = [];

    if (!empty($searchQuery)) {
        $whereClause .= " AND (r.title LIKE ? OR r.title LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    // Get total count for pagination
    $totalCountQuery = "SELECT COUNT(*) as total FROM resources r $whereClause";
    $totalResult = $db->fetchOne($totalCountQuery, $params);
    $totalResources = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalResources / $itemsPerPage);

    // Get resources with uploader information
    $resourcesQuery = "
        SELECT r.*, u.name as uploader_name
        FROM resources r
        JOIN accounts u ON r.uploaded_by = u.account_id
        $whereClause
        ORDER BY r.$sortBy $sortOrder
        LIMIT $itemsPerPage OFFSET $offset
    ";

    $resources = $db->fetchAll($resourcesQuery, $params);

    // Get popular resources (top 5 by download count)
    $popularResources = $db->fetchAll(
        "SELECT r.*, u.name as uploader_name
         FROM resources r
         JOIN accounts u ON r.uploaded_by = u.account_id
         WHERE r.download_count > 0
         ORDER BY r.download_count DESC, r.created_at DESC
         LIMIT 5"
    );

    // Get recent resources (last 5 uploaded)
    $recentResources = $db->fetchAll(
        "SELECT r.*, u.name as uploader_name
         FROM resources r
         JOIN accounts u ON r.uploaded_by = u.account_id
         ORDER BY r.created_at DESC
         LIMIT 5"
    );

} catch (Exception $e) {
    error_log("Error fetching resources: " . $e->getMessage());
    $resources = [];
    $popularResources = [];
    $recentResources = [];
    $totalResources = 0;
    $totalPages = 0;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-lg-9">
        <!-- Header and Search -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-file-earmark-text me-2"></i>Resource Library</h2>
                <p class="text-muted mb-0">
                    Career development materials and resources to help you succeed
                </p>
            </div>
            <?php if (isAdmin()): ?>
                <a href="<?php echo SITE_URL; ?>/resources/upload.php" class="btn btn-primary">
                    <i class="bi bi-cloud-upload me-1"></i>Upload Resource
                </a>
            <?php endif; ?>
        </div>

        <!-- Search and Filter Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search resources..."
                                value="<?php echo sanitizeInput($searchQuery); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="sort" class="form-select">
                            <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>
                                Sort by Date
                            </option>
                            <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>
                                Sort by Title
                            </option>
                            <option value="download_count" <?php echo $sortBy === 'download_count' ? 'selected' : ''; ?>>
                                Sort by Popularity
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="order" class="form-select">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>
                                Descending
                            </option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>
                                Ascending
                            </option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Summary -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="text-muted">
                    Showing <?php echo count($resources); ?> of <?php echo $totalResources; ?> resources
                    <?php if (!empty($searchQuery)): ?>
                        for "<?php echo sanitizeInput($searchQuery); ?>"
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($searchQuery)): ?>
                <a href="<?php echo SITE_URL; ?>/resources/list.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Clear Search
                </a>
            <?php endif; ?>
        </div>

        <!-- Resources Grid -->
        <?php if (empty($resources)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">
                        <?php if (!empty($searchQuery)): ?>
                            No resources found
                        <?php else: ?>
                            No resources available
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted">
                        <?php if (!empty($searchQuery)): ?>
                            Try adjusting your search terms or browse all resources.
                        <?php else: ?>
                            Resources will appear here once they are uploaded by administrators.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($searchQuery)): ?>
                        <a href="<?php echo SITE_URL; ?>/resources/list.php" class="btn btn-primary">
                            Browse All Resources
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($resources as $resource): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-file-earmark-pdf me-1 text-danger"></i>
                                        <?php echo sanitizeInput($resource['title']); ?>
                                    </h6>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $resource['download_count']; ?> downloads
                                    </span>
                                </div>

                                <p class="card-text small text-muted mb-2">
                                    <i class="bi bi-person me-1"></i>
                                    Uploaded by <?php echo sanitizeInput($resource['uploader_name']); ?>
                                </p>

                                <p class="card-text small text-muted mb-3">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php echo formatDate($resource['created_at']); ?>
                                </p>

                                <div class="d-grid">
                                    <a href="<?php echo SITE_URL; ?>/resources/download.php?id=<?php echo $resource['resource_id']; ?>"
                                        class="btn btn-primary btn-sm">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Resource pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Previous button -->
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next button -->
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link"
                                    href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-3">
        <!-- Popular Resources -->
        <?php if (!empty($popularResources)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-star me-1"></i>Popular Resources
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($popularResources as $resource): ?>
                        <a href="<?php echo SITE_URL; ?>/resources/download.php?id=<?php echo $resource['resource_id']; ?>"
                            class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 small">
                                        <?php echo sanitizeInput(truncateText($resource['title'], 40)); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo $resource['download_count']; ?> downloads
                                    </small>
                                </div>
                                <i class="bi bi-download text-muted"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Resources -->
        <?php if (!empty($recentResources)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-clock me-1"></i>Recently Added
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentResources as $resource): ?>
                        <a href="<?php echo SITE_URL; ?>/resources/download.php?id=<?php echo $resource['resource_id']; ?>"
                            class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 small">
                                        <?php echo sanitizeInput(truncateText($resource['title'], 40)); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo timeAgo($resource['created_at']); ?>
                                    </small>
                                </div>
                                <i class="bi bi-download text-muted"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Resource Guidelines -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-info-circle me-1"></i>Resource Guidelines
                </h6>
            </div>
            <div class="card-body">
                <ul class="small mb-0">
                    <li>Resources are provided for career development</li>
                    <li>All downloads are tracked for statistics</li>
                    <li>Report any issues with resources to administrators</li>
                    <li>Use resources responsibly and ethically</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>