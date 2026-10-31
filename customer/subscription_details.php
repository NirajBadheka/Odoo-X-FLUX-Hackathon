<?php
/**
 * SubNexa Pro - Customer Subscription Details & Live Add-On Switchboard
 * Solves Scene 1 from PDF: "add-ons customers switch on and off... without having to email anyone"
 */
require_once __DIR__ . '/../config/config.php';
require_role(['customer', 'admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . BASE_URL . "/customer/my_subscriptions.php");
    exit;
}

$sub = db_fetch_one("
    SELECT s.*, p.name as plan_name, p.billing_frequency, p.can_pause, p.can_renew,
           u.name as customer_name, u.email as customer_email, u.company_name
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    JOIN users u ON s.customer_id = u.id
    WHERE s.id = ?
", [$id], "i");

if (!$sub) {
    $_SESSION['flash_error'] = "Subscription not found.";
    header("Location: " . BASE_URL . "/customer/my_subscriptions.php");
    exit;
}

// Ownership check
if (user_role() === 'customer' && $sub['customer_id'] != $_SESSION['user_id']) {
    $_SESSION['flash_error'] = "Access denied: You do not own this subscription.";
    header("Location: " . BASE_URL . "/customer/my_subscriptions.php");
    exit;
}

// Fetch Core Software Lines
$core_lines = db_fetch_all("
    SELECT sl.*, p.name as product_name, p.code as product_code, pv.variant_value, pv.price_adjustment
    FROM subscription_lines sl
    JOIN products p ON sl.product_id = p.id
    LEFT JOIN product_variants pv ON sl.variant_id = pv.id
    WHERE sl.subscription_id = ? AND sl.is_addon = 0
", [$id], "i");

// Fetch All Add-Ons from Catalog and cross-reference with this subscription
$all_addons = db_fetch_all("SELECT * FROM products WHERE type = 'add_on' AND is_active = 1 ORDER BY sales_price ASC");

$sub_addon_lines = db_fetch_all("
    SELECT * FROM subscription_lines WHERE subscription_id = ? AND is_addon = 1
", [$id], "i");

$active_addon_map = [];
foreach ($sub_addon_lines as $sal) {
    $active_addon_map[$sal['product_id']] = (bool)$sal['is_addon_active'];
}

$page_title = "Subscription " . $sub['subscription_number'] . " — Self-Service";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <a href="<?= BASE_URL; ?>/customer/my_subscriptions.php" class="text-secondary small text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i> My Subscriptions
                        </a>
                        <span class="text-muted">/</span>
                        <span class="fw-bold font-monospace text-primary"><?= htmlspecialchars($sub['subscription_number']); ?></span>
                    </div>
                    <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($sub['plan_name']); ?> Management</h2>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?= get_status_badge($sub['status']); ?>
                </div>
            </div>

            <!-- Recurring Cycle Card -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <!-- Core Software Inclusions -->
                    <div class="card-nordic p-4 mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-cube text-primary me-2"></i> Base Software & Variants</h5>
                        <div class="table-responsive">
                            <table class="table-nordic">
                                <thead>
                                    <tr>
                                        <th>Product Core</th>
                                        <th>Variant Selected</th>
                                        <th>Qty</th>
                                        <th class="text-end">Base Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($core_lines as $cl): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($cl['product_name']); ?></div>
                                                <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($cl['product_code']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($cl['variant_value']): ?>
                                                    <span class="badge bg-success-subtle text-success">
                                                        <i class="fa-solid fa-check me-1"></i> <?= htmlspecialchars($cl['variant_value']); ?> (+<?= format_currency($cl['price_adjustment']); ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Standard Package</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= (int)$cl['quantity']; ?></strong></td>
                                            <td class="text-end fw-bold text-dark"><?= format_currency($cl['unit_price']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Flagship Feature: Live Add-On Switchboard (PDF Page 1) -->
                    <div class="card-nordic p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-toggle-on text-primary me-2"></i> Live Add-On Switchboard (Self-Service)
                                </h5>
                                <div class="text-muted small">Switch optional services on and off live. Costs update immediately (Scene 1 from PDF).</div>
                            </div>
                            <span class="badge bg-info-subtle text-info fw-bold">Live Real-Time Engine</span>
                        </div>

                        <div class="row g-3">
                            <?php foreach ($all_addons as $addon): 
                                $is_active = !empty($active_addon_map[$addon['id']]);
                            ?>
                                <div class="col-12">
                                    <div class="p-3 rounded-3 border d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 bg-white hover-shadow transition">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-2 p-2.5 <?= $is_active ? 'bg-primary text-white' : 'bg-light text-muted'; ?>" style="font-size: 1.25rem;">
                                                <i class="fa-solid <?= ($addon['code'] === 'ADDON-SLA') ? 'fa-headset' : (($addon['code'] === 'ADDON-BI') ? 'fa-chart-pie' : 'fa-user-tie'); ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($addon['name']); ?></h6>
                                                <p class="text-muted small mb-0"><?= htmlspecialchars($addon['notes'] ?? 'Optional feature'); ?></p>
                                                <div class="fw-bold text-primary small mt-1">+<?= format_currency($addon['sales_price']); ?> / month</div>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch ps-sm-4" style="font-size: 1.35rem;">
                                            <input class="form-check-input" type="checkbox" role="switch" id="addon_switch_<?= $addon['id']; ?>" 
                                                   <?= $is_active ? 'checked' : ''; ?> 
                                                   onchange="handleToggleAddon(<?= $sub['id']; ?>, <?= $addon['id']; ?>, this)">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Summary Card -->
                <div class="col-lg-4">
                    <div class="card-nordic p-4 bg-light position-sticky" style="top: 80px;">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-receipt text-primary me-2"></i> Current Billing Summary</h5>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Lines Subtotal:</span>
                            <span class="fw-semibold text-dark" id="side_subtotal"><?= format_currency($sub['subtotal']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Taxes (GST/VAT):</span>
                            <span class="fw-semibold text-dark" id="side_tax">+<?= format_currency($sub['tax_total']); ?></span>
                        </div>
                        <?php if ($sub['discount_total'] > 0): ?>
                            <div class="d-flex justify-content-between small mb-2 text-success">
                                <span>Applied Discount:</span>
                                <span class="fw-semibold">-<?= format_currency($sub['discount_total']); ?></span>
                            </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold text-dark">Recurring Cycle Total:</span>
                            <span class="fs-4 fw-extrabold text-primary" id="side_total"><?= format_currency($sub['total_amount']); ?></span>
                        </div>

                        <div class="small text-secondary mb-3 space-y-1">
                            <div><i class="fa-solid fa-repeat me-1 text-primary"></i> <strong>Frequency:</strong> <?= ucfirst($sub['billing_frequency']); ?></div>
                            <div><i class="fa-solid fa-calendar me-1 text-primary"></i> <strong>Next Renewal:</strong> <?= format_date($sub['next_billing_date']); ?></div>
                        </div>

                        <a href="<?= BASE_URL; ?>/customer/my_invoices.php" class="btn btn-outline-primary w-100 fw-semibold">
                            <i class="fa-solid fa-receipt me-1"></i> View Invoices History
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- AJAX Addon Toggle Script -->
<script>
function handleToggleAddon(subId, addonId, checkbox) {
    const isChecked = checkbox.checked ? 1 : 0;

    // Visual feedback
    checkbox.disabled = true;

    fetch('<?= BASE_URL; ?>/ajax/toggle_addon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            subscription_id: subId,
            addon_id: addonId,
            is_active: isChecked
        })
    })
    .then(response => response.json())
    .then(data => {
        checkbox.disabled = false;
        if (data.success) {
            // Update financial displays
            document.getElementById('side_subtotal').innerText = '₹ ' + data.subtotal;
            document.getElementById('side_tax').innerText = '+₹ ' + data.tax_total;
            document.getElementById('side_total').innerText = '₹ ' + data.grand_total;

            // Highlight update animation
            const totalEl = document.getElementById('side_total');
            totalEl.style.color = '#10B981';
            setTimeout(() => { totalEl.style.color = ''; }, 1000);

            showToast('success', data.message);
        } else {
            checkbox.checked = !isChecked; // Revert
            showToast('error', data.error || 'Failed to update add-on.');
        }
    })
    .catch(err => {
        checkbox.disabled = false;
        checkbox.checked = !isChecked;
        showToast('error', 'Network error while updating add-on.');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
