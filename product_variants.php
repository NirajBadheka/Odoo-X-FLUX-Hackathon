<?php
/**
 * SubNexa Pro - Product Variants Manager
 * Solves Scene 2 from PDF: "...offers it in variants, for example by brand, where choosing the Odoo brand adds 560 to the price."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Handle Variant Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $p_id = (int)($_POST['product_id'] ?? 0);
        $attr = clean_input($_POST['attribute_name'] ?? 'Brand');
        $val = clean_input($_POST['variant_value'] ?? '');
        $adj = (float)($_POST['price_adjustment'] ?? 0);

        if ($p_id <= 0 || empty($val)) {
            $_SESSION['flash_error'] = "Product and variant value are required.";
        } else {
            db_insert(
                "INSERT INTO product_variants (product_id, attribute_name, variant_value, price_adjustment) VALUES (?, ?, ?, ?)",
                [$p_id, $attr, $val, $adj],
                "issd"
            );
            log_audit("VARIANT_CREATED", "Added variant: $attr: $val (+$adj) for product #$p_id");
            $_SESSION['flash_success'] = "Variant '$val' added successfully!";
            header("Location: " . BASE_URL . "/admin/product_variants.php" . ($product_id ? "?product_id=$product_id" : ""));
            exit;
        }
    } elseif ($action === 'delete') {
        $vid = (int)($_POST['id'] ?? 0);
        if ($vid > 0) {
            db_execute("DELETE FROM product_variants WHERE id = ?", [$vid], "i");
            log_audit("VARIANT_DELETED", "Deleted variant ID: $vid");
            $_SESSION['flash_success'] = "Variant removed.";
            header("Location: " . BASE_URL . "/admin/product_variants.php" . ($product_id ? "?product_id=$product_id" : ""));
            exit;
        }
    }
}

// Fetch products for dropdown
$products_list = db_fetch_all("SELECT id, name, code, sales_price FROM products WHERE is_active = 1 ORDER BY name ASC");

// Fetch variants query
$sql = "SELECT v.*, p.name as product_name, p.code as product_code, p.sales_price 
        FROM product_variants v 
        JOIN products p ON v.product_id = p.id ";
$params = [];
$types = "";

if ($product_id > 0) {
    $sql .= " WHERE v.product_id = ? ";
    $params[] = $product_id;
    $types .= "i";
}
$sql .= " ORDER BY p.name ASC, v.id ASC";

$variants = db_fetch_all($sql, $params, $types);

$page_title = "Product Variants & Price Modifiers";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Product Variants & Price Adjustments</h2>
                    <p class="text-secondary small mb-0">Configure variant options such as Brand, Edition, or Architecture with custom price modifiers (PDF Page 3).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL; ?>/admin/products.php" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-arrow-left me-1"></i> Products Catalog
                    </a>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Add Variant
                    </button>
                </div>
            </div>

            <!-- Challenge Callout Alert -->
            <div class="card border-0 shadow-sm rounded-3 mb-4" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%); border: 1px solid #BBF7D0 !important;">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <i class="fa-solid fa-award fs-3 text-success"></i>
                    <div>
                        <div class="fw-bold text-success small">Odoo Hackathon Challenge Scene 2:</div>
                        <div class="text-dark small">
                            <em>"Brightloop offers products in variants, for example by brand, where choosing the <strong>Odoo brand adds 560 to the price</strong>."</em> Notice the entry below for Brightloop ERP Edition.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variants Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Active Variants (<?= count($variants); ?> Configured)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Filter variants..." data-table-search="#variantsTable" style="max-width: 240px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="variantsTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Attribute Type</th>
                                <th>Variant Value</th>
                                <th>Base Product Price</th>
                                <th>Price Adjustment</th>
                                <th>Effective Total</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($variants)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No variants configured for this selection.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($variants as $v): 
                                    $eff_total = $v['sales_price'] + $v['price_adjustment'];
                                    $is_odoo = (stripos($v['variant_value'], 'Odoo') !== false);
                                ?>
                                    <tr class="<?= $is_odoo ? 'table-warning-subtle' : ''; ?>">
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($v['product_name']); ?></div>
                                            <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($v['product_code']); ?></span>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($v['attribute_name']); ?></span></td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($v['variant_value']); ?></strong>
                                            <?php if ($is_odoo): ?>
                                                <span class="badge bg-primary text-white ms-1"><i class="fa-solid fa-star me-1"></i> Challenge Feature</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-secondary"><?= format_currency($v['sales_price']); ?></td>
                                        <td>
                                            <?php if ($v['price_adjustment'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success fw-bold">+<?= format_currency($v['price_adjustment']); ?></span>
                                            <?php elseif ($v['price_adjustment'] < 0): ?>
                                                <span class="badge bg-danger-subtle text-danger fw-bold">-<?= format_currency(abs($v['price_adjustment'])); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">+0.00 (Standard)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-primary"><?= format_currency($eff_total); ?></td>
                                        <td class="text-end">
                                            <form method="POST" action="<?= BASE_URL; ?>/admin/product_variants.php" onsubmit="return confirm('Delete this variant modifier?');" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $v['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Add Variant Modal -->
<div class="modal fade" id="addVariantModal" tabindex="-1" aria-labelledby="addVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addVariantModalLabel"><i class="fa-solid fa-code-branch text-primary me-2"></i> Add Variant Modifier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/product_variants.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Target Product <span class="text-danger">*</span></label>
                        <select class="form-select" name="product_id" required>
                            <option value="">Select a product...</option>
                            <?php foreach ($products_list as $pl): ?>
                                <option value="<?= $pl['id']; ?>" <?= ($product_id == $pl['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pl['name']); ?> (Base: <?= format_currency($pl['sales_price']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attribute Name (e.g. Brand, Hosting, Edition)</label>
                        <input type="text" class="form-control" name="attribute_name" placeholder="Brand" value="Brand" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Variant Value <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="variant_value" placeholder="e.g. Odoo Brand Edition" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Price Adjustment ($) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">+ / -</span>
                            <input type="number" step="0.01" class="form-control" name="price_adjustment" placeholder="560.00" required>
                        </div>
                        <div class="form-text" style="font-size: 0.725rem;">Enter positive value (e.g. 560.00) to add to product base price.</div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Variant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
