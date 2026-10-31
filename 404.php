<?php
/**
 * SubNexa Pro - 404 Custom Error Page
 */
require_once __DIR__ . '/config/config.php';

$page_title = "404 Page Not Found — SubNexa Pro";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="min-vh-100 d-flex flex-column align-items-center justify-content-center text-center p-4 bg-light">
    <div class="rounded-circle p-4 bg-white border shadow-sm mb-4" style="width: 100px; height: 100px; display: inline-flex; align-items: center; justify-content: center;">
        <i class="fa-solid fa-compass-drafting text-primary fs-1"></i>
    </div>
    <h1 class="display-4 fw-extrabold text-dark tracking-tight mb-2">404</h1>
    <h3 class="fw-bold text-dark mb-2">Page Not Found</h3>
    <p class="text-secondary max-w-md mx-auto mb-4">
        The requested billing module or page does not exist or has been moved.
    </p>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL; ?>/index.php" class="btn btn-primary fw-semibold px-4 py-2.5">
            <i class="fa-solid fa-home me-1.5"></i> Back to Home
        </a>
        <a href="<?= BASE_URL; ?>/auth/login.php" class="btn btn-outline-secondary fw-semibold px-4 py-2.5">
            <i class="fa-solid fa-arrow-right-to-bracket me-1.5"></i> Sign In
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
