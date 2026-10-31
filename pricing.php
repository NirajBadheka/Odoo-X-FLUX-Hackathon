<?php
/**
 * SubNexa Pro - Interactive Pricing & Plans Catalog
 */
require_once __DIR__ . '/config/config.php';

$plans = db_fetch_all("SELECT * FROM plans WHERE is_active = 1 ORDER BY cost ASC");
$addons = db_fetch_all("SELECT * FROM products WHERE type = 'add_on' AND is_active = 1");
$variants = db_fetch_all("SELECT v.*, p.name as product_name FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.is_active = 1");

$page_title = "Plans & Pricing Calculator";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bg-white border-bottom py-5">
    <div class="container text-center max-w-2xl mx-auto">
        <span class="badge bg-primary-subtle text-primary px-3 py-1.5 fw-semibold mb-2">Transparent Subscription Tiers</span>
        <h1 class="display-6 fw-bold text-dark">Flexible Billing Built for Any Business Scale</h1>
        <p class="text-secondary">
            From daily on-demand compute burst to enterprise annual contracts. All plans include automated billing, tax compliance, and instant self-service management.
        </p>
    </div>
</div>

<div class="container py-5">
    <!-- Plans Grid -->
    <div class="row g-4 mb-5">
        <?php foreach ($plans as $plan): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border shadow-sm rounded-3 d-flex flex-column hover-shadow transition">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-primary border fw-bold text-uppercase" style="font-size: 0.7rem;">
                                <?= htmlspecialchars($plan['billing_frequency']); ?>
                            </span>
                            <?php if ($plan['billing_frequency'] === 'yearly'): ?>
                                <span class="badge bg-success-subtle text-success small">Best Value</span>
                            <?php endif; ?>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($plan['name']); ?></h4>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($plan['description'] ?? 'Recurring billing plan.'); ?></p>

                        <div class="my-3">
                            <span class="display-6 fw-extrabold text-dark"><?= format_currency($plan['cost']); ?></span>
                            <span class="text-muted small">/ <?= htmlspecialchars($plan['billing_frequency']); ?></span>
                        </div>

                        <ul class="list-unstyled small space-y-2 mb-4 text-secondary">
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid fa-check text-success"></i>
                                <span>Min Seats: <strong><?= (int)$plan['min_quantity']; ?></strong></span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid <?= $plan['can_pause'] ? 'fa-check text-success' : 'fa-xmark text-muted'; ?>"></i>
                                <span>Pause Allowed: <strong><?= $plan['can_pause'] ? 'Yes' : 'No'; ?></strong></span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid <?= $plan['can_renew'] ? 'fa-check text-success' : 'fa-xmark text-muted'; ?>"></i>
                                <span>Auto-Renew: <strong><?= $plan['can_renew'] ? 'Supported' : 'Manual'; ?></strong></span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="fa-solid <?= $plan['auto_close_on_end'] ? 'fa-circle-exclamation text-warning' : 'fa-arrows-rotate text-primary'; ?>"></i>
                                <span>End Action: <strong><?= $plan['auto_close_on_end'] ? 'Auto-Close' : 'Continuous'; ?></strong></span>
                            </li>
                        </ul>

                        <div class="mt-auto">
                            <a href="<?= BASE_URL; ?>/auth/register.php?plan=<?= $plan['id']; ?>" class="btn btn-outline-primary w-100 fw-semibold">
                                Subscribe Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Variant Pricing Highlight Box (From Page 3 of PDF) -->
    <div class="card bg-light border p-4 rounded-4 mb-5 shadow-sm">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary text-white mb-2">Challenge Showcase</span>
                <h3 class="fw-bold text-dark">Product Variant Pricing Architecture</h3>
                <p class="text-secondary small mb-3">
                    As specified in the Brightloop Software problem statement: <em>"The admin offers products in variants, for example by brand, where choosing the <strong>Odoo brand adds 560 to the price</strong>."</em> SubNexa Pro calculates variant adjustments dynamically during quotation creation and auto-billing.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($variants as $v): ?>
                        <div class="bg-white px-3 py-2 rounded-2 border shadow-sm small">
                            <span class="text-muted"><?= htmlspecialchars($v['product_name']); ?>:</span>
                            <strong><?= htmlspecialchars($v['variant_value']); ?></strong>
                            <span class="badge bg-success-subtle text-success ms-1">
                                <?= $v['price_adjustment'] > 0 ? '+' . format_currency($v['price_adjustment']) : 'Base Price'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-5 text-lg-end">
                <a href="<?= BASE_URL; ?>/auth/login.php" class="btn btn-primary fw-semibold px-4 py-2.5">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i> Test Quote Generator
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
