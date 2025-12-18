</main>

<!-- Footer -->
<footer class="bg-dark text-light mt-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-house-heart me-2"></i><?php echo SITE_NAME; ?></h5>
                <p class="mb-2">Connecting current and former residents of Nyumba ya Watoto for career development,
                    mentorship, and professional growth.</p>
                <p class="text-muted small mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
                </p>
            </div>

            <div class="col-md-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo SITE_URL; ?>/dashboard.php"
                                class="text-light text-decoration-none">Dashboard</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/opportunities/list.php"
                                class="text-light text-decoration-none">Opportunities</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/resources/list.php"
                                class="text-light text-decoration-none">Resources</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/profile.php"
                                class="text-light text-decoration-none">Profile</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-light text-decoration-none">Login</a>
                        </li>
                        <li><a href="<?php echo SITE_URL; ?>/register.php"
                                class="text-light text-decoration-none">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-md-3">
                <h6>Support</h6>
                <ul class="list-unstyled">
                    <li><a href="mailto:<?php echo ADMIN_EMAIL; ?>" class="text-light text-decoration-none">
                            <i class="bi bi-envelope me-1"></i>Contact Support
                        </a></li>
                    <li><small class="text-muted">
                            For technical issues or account problems
                        </small></li>
                </ul>
            </div>
        </div>

        <hr class="my-3">

        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    Built with <i class="bi bi-heart-fill text-danger"></i> for the Nyumba ya Watoto community
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small class="text-muted">
                    Version 1.0 | Last updated: <?php echo date('M Y'); ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

<!-- Messaging JavaScript (for authenticated users) -->
<?php if (isLoggedIn()): ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/messaging.js"></script>
<?php endif; ?>

<!-- Page-specific JavaScript -->
<?php if (isset($pageScript)): ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $pageScript; ?>"></script>
<?php endif; ?>

<!-- Inline JavaScript -->
<?php if (isset($inlineScript)): ?>
    <script><?php echo $inlineScript; ?></script>
<?php endif; ?>
</body>

</html>