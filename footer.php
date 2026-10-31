<?php
/**
 * SubNexa Pro - Footer Partial
 */
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<footer class="app-footer text-center border-top py-3 mt-auto bg-white">
    <div class="container-fluid d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 px-4">
        <div class="text-muted small">
            &copy; <?= date('Y'); ?> <strong><?= htmlspecialchars(APP_NAME); ?></strong> — <?= htmlspecialchars(APP_COMPANY); ?>. All rights reserved.
        </div>
        <div class="d-flex align-items-center gap-3 small">
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i> All Systems Operational
            </span>
            <span class="text-muted">Response: &lt; 0.08s</span>
            <a href="<?= BASE_URL; ?>/index.php" class="text-muted text-decoration-none">Home</a>
            <a href="<?= BASE_URL; ?>/pricing.php" class="text-muted text-decoration-none">Pricing</a>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SubNexa Pro Custom Scripts -->
<script src="<?= BASE_URL; ?>/assets/js/main.js"></script>

<?php if ($flash_success): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showToast('success', <?= json_encode($flash_success); ?>);
    });
</script>
<?php endif; ?>

<?php if ($flash_error): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        showToast('error', <?= json_encode($flash_error); ?>);
    });
</script>
<?php endif; ?>

</body>
</html>
