<?php
/**
 * ==============================================================================
 * 404 Not Found Custom Error Screen (404.php)
 * ------------------------------------------------------------------------------
 * Clean, branded error page with intuitive navigation back to the portal.
 * ==============================================================================
 */

http_response_code(404);
$page_title = 'Page Not Found';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-12 col-md-8 col-lg-6 text-center">
        <div class="card shadow-sm border-0 p-5">
            <div class="empty-state-icon mx-auto mb-4" style="width: 84px; height: 84px; font-size: 2.5rem; background: #eef2ff; color: #4f46e5;">
                <i class="bi bi-search"></i>
            </div>
            <h1 class="font-heading fw-bold text-dark display-6 mb-2">404</h1>
            <h4 class="fw-bold text-dark mb-2">Resource Not Found</h4>
            <p class="text-muted mb-4">
                The academic record, course catalog entry, or page you were looking for could not be located or may have been removed.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= e(get_app_url('dashboard.php')) ?>" class="btn btn-primary px-4">
                    <i class="bi bi-house-door-fill me-1"></i> Return to Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-light border px-4">
                    <i class="bi bi-arrow-left me-1"></i> Go Back
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
