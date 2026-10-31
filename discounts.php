<?php
/**
 * SubNexa Pro - Discount Rules Manager
 * Solves Scene 4 from PDF: "Only the admin can create discount rules: a fixed amount or a percentage, with a minimum purchase or quantity, start and end dates, and a limit on how many times it can be used. A discount can apply to specific products or to whole subscriptions..."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $code = strtoupper(clean_input($_POST['code'] ?? ''));
        $name = clean_input($_POST['name'] ?? '');
        $discount_type = clean_input($_POST['discount_type'] ?? 'percentage');
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $min_purchase_amount = (float)($_POST['min_purchase_amount'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 1);
        $max_uses = (int)($_POST['max_uses'] ?? 0);
        $applies_to = clean_input($_POST['applies_to'] ?? 'entire_subscription');
        $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $start_date = !empty($_POST['start_date']) ? clean_input($_POST['start_date']) : null;
        $end_date = !empty($_POST['end_date']) ? clean_input($_POST['end_date']) : null;

        if (empty($code) || empty($name) || $discount_value <= 0) {
            $_SESSION['flash_error'] = "Discount code, title, and valid value are required.";
        } else {
            $exists = db_fetch_one("SELECT id FROM discount_rules WHERE code = ? LIMIT 1", [$code], "s");
            if ($exists) {
                $_SESSION['flash_error'] = "A discount code '$code' already exists.";
            } else {
                db_insert(
                    "INSERT INTO discount_rules (code, name, discount_type, discount_value, min_purchase_amount, min_quantity, max_uses, applies_to, product_id, start_date, end_date, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                    [$code, $name, $discount_type, $discount_value, $min_purchase_amount, $min_quantity, $max_uses, $applies_to, $product_id, $start_date, $end_date],
                    "sssddiisis"
                );
                log_audit("DISCOUNT_CREATED", "Created discount rule: $code ($name)");
                $_SESSION['flash_success'] = "Discount rule '$code' created successfully!";
                header("Location: " . BASE_URL . "/admin/discounts.php");
                exit;
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM discount_rules WHERE id = ?", [$id], "i");
            log_audit("DISCOUNT_DELETED", "Deleted discount ID: $id");
            $_SESSION['flash_success'] = "Discount rule deleted.";
            header("Location: " . BASE_URL . "/admin/discounts.php");
            exit;
        }
    }
}

$discounts = db_fetch_all("
    SELECT d.*, p.name as product_name 
    FROM discount_rules d 
    LEFT JOIN products p ON d.product_id = p.id 
    ORDER BY d.id DESC
");

$products = db_fetch_all("SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC");

$page_title = "Discount Rules & Volume Incentives";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Discount Rules & Volume Incentives</h2>
                    <p class="text-secondary small mb-0">Admin-exclusive rules for fixed/₹ discounts, minimum seat quantities, caps, and auto-expiry on billing runs (PDF Page 4).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Add Discount Rule
                    </button>
                </div>
            </div>

            <!-- Discounts Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Active Rules (<?= count($discounts); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search promo codes..." data-table-search="#discountsTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="discountsTable">
                        <thead>
                            <tr>
                                <th>Code & Name</th>
                                <th>Discount</th>
                                <th>Scope</th>
                                <th>Min Purchase / Qty</th>
                                <th>Usage Limit</th>
                                <th>Validity Period</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($discounts as $d): 
                                $is_exhausted = ($d['max_uses'] > 0 && $d['used_count'] >= $d['max_uses']);
                                $is_expired = ($d['end_date'] && strtotime($d['end_date']) < time());
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($d['name']); ?></div>
                                        <span class="badge bg-light text-primary border font-monospace fw-bold"><?= htmlspecialchars($d['code']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($d['discount_type'] === 'percentage'): ?>
                                            <span class="badge bg-success-subtle text-success fs-6 fw-bold"><?= (float)$d['discount_value']; ?>% OFF</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary fs-6 fw-bold"><?= format_currency($d['discount_value']); ?> FLAT</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['applies_to'] === 'entire_subscription'): ?>
                                            <span class="badge bg-light text-dark border"><i class="fa-solid fa-layer-group me-1"></i> Entire Subscription</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-subtle text-info"><i class="fa-solid fa-box me-1"></i> <?= htmlspecialchars($d['product_name'] ?? 'Specific Product'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <div>Min Spend: <strong><?= format_currency($d['min_purchase_amount']); ?></strong></div>
                                        <div>Min Seats: <strong><?= (int)$d['min_quantity']; ?></strong></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold"><?= (int)$d['used_count']; ?> / <?= $d['max_uses'] > 0 ? (int)$d['max_uses'] : '∞'; ?> used</div>
                                        <?php if ($is_exhausted): ?>
                                            <span class="badge bg-danger text-white">Exhausted</span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="badge bg-warning text-dark">Expired</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-secondary">
                                        <div>From: <?= format_date($d['start_date']); ?></div>
                                        <div>To: <?= format_date($d['end_date']); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL; ?>/admin/discounts.php" onsubmit="return confirm('Delete this discount rule?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Add Discount Modal -->
<div class="modal fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addDiscountModalLabel"><i class="fa-solid fa-tags text-primary me-2"></i> Add Discount Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/discounts.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Discount Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase font-monospace" name="code" placeholder="SEATS25" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Discount Name / Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="25% Volume Seats Incentive" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="discount_type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="discount_value" placeholder="20.00" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Scope (Applies To) <span class="text-danger">*</span></label>
                            <select class="form-select" name="applies_to" id="applies_to" onchange="toggleProductDropdown(this.value)">
                                <option value="entire_subscription">Whole Subscription</option>
                                <option value="specific_product">Specific Product</option>
                            </select>
                        </div>
                        <div class="col-sm-6" id="product_select_div" style="display: none;">
                            <label class="form-label">Target Product</label>
                            <select class="form-select" name="product_id">
                                <option value="">Select product...</option>
                                <?php foreach ($products as $pr): ?>
                                    <option value="<?= $pr['id']; ?>"><?= htmlspecialchars($pr['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Min Purchase Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="min_purchase_amount" value="0.00">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Min Quantity (Seats)</label>
                            <input type="number" class="form-control" name="min_quantity" value="1" min="1">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Max Uses Limit (0 = Unlimited)</label>
                            <input type="number" class="form-control" name="max_uses" value="100">
                        </div>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Discount Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleProductDropdown(val) {
    const div = document.getElementById('product_select_div');
    div.style.display = (val === 'specific_product') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
