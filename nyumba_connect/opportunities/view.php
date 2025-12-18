<?php
/**
 * Opportunity Details Page
 * Displays detailed information about a specific opportunity
 */

// Define page constants
define('NYUMBA_CONNECT', true);

// Include required files
require_once __DIR__ . '/../includes/config.php';

// Initialize authentication
init_auth();
require_auth();

// Get opportunity ID
$opportunityId = intval($_GET['id'] ?? 0);

if (!$opportunityId) {
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Invalid opportunity ID.');
}

try {
    $db = getDB();

    // Get opportunity details with poster information
    $sql = "SELECT o.*, u.name as posted_by_name, u.role as posted_by_role 
            FROM opportunities o 
            JOIN users u ON o.posted_by = u.user_id 
            WHERE o.opportunity_id = ? AND o.status = ?";

    $opportunity = $db->fetchOne($sql, [$opportunityId, STATUS_APPROVED]);

    if (!$opportunity) {
        redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Opportunity not found or not available.');
    }

    // Check if current user has already applied
    $hasApplied = false;
    if (isStudent()) {
        $applicationSql = "SELECT application_id FROM applications WHERE opportunity_id = ? AND applicant_id = ?";
        $existingApplication = $db->fetchOne($applicationSql, [$opportunityId, getCurrentUserId()]);
        $hasApplied = $existingApplication !== false;
    }

    // Check if deadline has passed
    $deadlinePassed = !empty($opportunity['deadline']) && isDeadlinePassed($opportunity['deadline']);

    // Set page title
    $pageTitle = $opportunity['title'] . ' - Opportunity Details';

} catch (Exception $e) {
    error_log("Error fetching opportunity details: " . $e->getMessage());
    redirectWithMessage(SITE_URL . '/opportunities/list.php', 'error', 'Unable to load opportunity details.');
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/dashboard.php">Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo SITE_URL; ?>/opportunities/list.php">Opportunities</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo sanitizeInput($opportunity['title']); ?>
                </li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="h3 mb-1"><?php echo sanitizeInput($opportunity['title']); ?></h1>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span
                                class="badge bg-primary"><?php echo sanitizeInput(ucfirst($opportunity['category'])); ?></span>
                            <?php echo getStatusBadge($opportunity['status']); ?>
                            <?php if ($deadlinePassed): ?>
                                <span class="badge bg-danger">Expired</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (isStudent() && !$deadlinePassed): ?>
                        <div>
                            <?php if ($hasApplied): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="bi bi-check-circle me-1"></i>Applied
                                </button>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/opportunities/apply.php?id=<?php echo $opportunity['opportunity_id']; ?>"
                                    class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Apply Now
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Posted By</h6>
                        <p class="mb-0">
                            <i class="bi bi-person me-1"></i>
                            <?php echo sanitizeInput($opportunity['posted_by_name']); ?>
                            <span class="badge bg-light text-dark ms-1">
                                <?php echo getRoleDisplayName($opportunity['posted_by_role']); ?>
                            </span>
                        </p>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Posted On</h6>
                        <p class="mb-0">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo formatDateTime($opportunity['created_at']); ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($opportunity['deadline'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-1">Application Deadline</h6>
                            <p class="mb-0 <?php echo $deadlinePassed ? 'text-danger' : 'text-success'; ?>">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?php echo formatDateTime($opportunity['deadline']); ?>
                                <?php if ($deadlinePassed): ?>
                                    <span class="ms-2 badge bg-danger">Expired</span>
                                <?php else: ?>
                                    <small class="text-muted ms-2">
                                        (<?php echo timeAgo($opportunity['deadline']); ?>)
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <hr>

                <h5 class="mb-3">Description</h5>
                <div class="opportunity-description">
                    <?php echo nl2br(sanitizeInput($opportunity['description'])); ?>
                </div>

                <?php if (!empty($opportunity['link'])): ?>
                    <hr>
                    <h6 class="mb-2">External Link</h6>
                    <p>
                        <a href="<?php echo sanitizeInput($opportunity['link']); ?>" target="_blank"
                            rel="noopener noreferrer" class="btn btn-outline-primary">
                            <i class="bi bi-link-45deg me-1"></i>
                            Visit Opportunity Page
                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Application Status Card (for students) -->
        <?php if (isStudent()): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-1"></i>Application Status
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($hasApplied): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Application Submitted</strong><br>
                            <small>You have already applied to this opportunity.</small>
                        </div>
                    <?php elseif ($deadlinePassed): ?>
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Application Closed</strong><br>
                            <small>The deadline for this opportunity has passed.</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-clock me-2"></i>
                            <strong>Ready to Apply</strong><br>
                            <small>You can submit your application for this opportunity.</small>
                        </div>
                        <a href="<?php echo SITE_URL; ?>/opportunities/apply.php?id=<?php echo $opportunity['opportunity_id']; ?>"
                            class="btn btn-primary w-100">
                            <i class="bi bi-send me-1"></i>Apply Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-lightning me-1"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/opportunities/list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Opportunities
                    </a>

                    <?php if (!empty($opportunity['link'])): ?>
                        <a href="<?php echo sanitizeInput($opportunity['link']); ?>" target="_blank"
                            rel="noopener noreferrer" class="btn btn-outline-primary">
                            <i class="bi bi-link-45deg me-1"></i>External Link
                        </a>
                    <?php endif; ?>

                    <button class="btn btn-outline-info" onclick="shareOpportunity()">
                        <i class="bi bi-share me-1"></i>Share
                    </button>
                </div>
            </div>
        </div>

        <!-- Related Opportunities -->
        <?php
        try {
            // Get related opportunities in the same category
            $relatedSql = "SELECT opportunity_id, title, category, deadline 
                          FROM opportunities 
                          WHERE category = ? AND opportunity_id != ? AND status = ? 
                          ORDER BY created_at DESC 
                          LIMIT 3";
            $relatedOpportunities = $db->fetchAll($relatedSql, [
                $opportunity['category'],
                $opportunity['opportunity_id'],
                STATUS_APPROVED
            ]);

            if (!empty($relatedOpportunities)):
                ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-collection me-1"></i>Related Opportunities
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($relatedOpportunities as $related): ?>
                            <div class="mb-3 pb-3 <?php echo $related !== end($relatedOpportunities) ? 'border-bottom' : ''; ?>">
                                <h6 class="mb-1">
                                    <a href="<?php echo SITE_URL; ?>/opportunities/view.php?id=<?php echo $related['opportunity_id']; ?>"
                                        class="text-decoration-none">
                                        <?php echo sanitizeInput($related['title']); ?>
                                    </a>
                                </h6>
                                <small class="text-muted">
                                    <?php echo sanitizeInput(ucfirst($related['category'])); ?>
                                    <?php if (!empty($related['deadline'])): ?>
                                        • Due: <?php echo formatDate($related['deadline']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php
            endif;
        } catch (Exception $e) {
            // Silently fail for related opportunities
        }
        ?>
    </div>
</div>

<script>
    function shareOpportunity() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo addslashes($opportunity['title']); ?>',
                text: 'Check out this opportunity on Nyumba Connect',
                url: window.location.href
            });
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href).then(function () {
                alert('Link copied to clipboard!');
            });
        }
    }
</script>

<style>
    .opportunity-description {
        line-height: 1.6;
        font-size: 1.1rem;
    }

    .card-header h6 {
        color: var(--bs-primary);
    }

    .alert {
        border: none;
        border-radius: 8px;
    }

    .btn {
        border-radius: 6px;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>