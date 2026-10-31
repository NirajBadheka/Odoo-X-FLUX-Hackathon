<?php
/**
 * SubNexa Pro - Product Catalog & Variant Manager
 * Solves Scene 2 from PDF: "The admin adds it with its name, type, sales price, cost price and notes, and offers it in variants..."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$error = "";
$success = "";

// Handle Product Add/Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $type = clean_input($_POST['type'] ?? 'software');
        $sales_price = (float)($_POST['sales_price'] ?? 0);
        $cost_price = (float)($_POST['cost_price'] ?? 0);
        $notes = clean_input($_POST['notes'] ?? '');

        if (empty($name) || empty($code)) {
            $_SESSION['flash_error'] = "Product name and unique code are required.";
        } else {
            // Check code uniqueness
            $exists = db_fetch_one("SELECT id FROM products WHERE code = ? LIMIT 1", [$code], "s");
            if ($exists) {
                $_SESSION['flash_error'] = "A product with code '$code' already exists.";
            } else {
                $pid = db_insert(
                    "INSERT INTO products (name, code, type, sales_price, cost_price, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$name, $code, $type, $sales_price, $cost_price, $notes],
                    "sssdds"
                );
                log_audit("PRODUCT_CREATED", "Created product: $name ($code)");
                $_SESSION['flash_success'] = "Product '$name' created successfully!";
                header("Location: " . BASE_URL . "/admin/products.php");
                exit;
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM products WHERE id = ?", [$id], "i");
            log_audit("PRODUCT_DELETED", "Deleted product ID: $id");
            $_SESSION['flash_success'] = "Product removed from catalog.";
            header("Location: " . BASE_URL . "/admin/products.php");
            exit;
        }
    }
}

// Fetch all products with their variant count
$products = db_fetch_all("
    SELECT p.*, COUNT(v.id) as variant_count 
    FROM products p 
    LEFT JOIN product_variants v ON p.id = v.product_id 
    GROUP BY p.id 
    ORDER BY p.id ASC
");

$page_title = "Product Catalog & Pricing";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Products & Pricing Catalog</h2>
                    <p class="text-secondary small mb-0">Manage software packages, add-ons, cost/sales pricing, and variant modifiers (Page 3 of PDF).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Add New Product
                    </button>
                </div>
            </div>

            <!-- Products Table Card -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Product Inventory (<?= count($products); ?> Items)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search products..." data-table-search="#productsTable" style="max-width: 240px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="productsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Product Name</th>
                                <th>Type</th>
                                <th>Sales Price</th>
                                <th>Cost Price</th>
                                <th>Margin</th>
                                <th>Variants</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): 
                                $margin = $p['sales_price'] - $p['cost_price'];
                            ?>
                                <tr>
                                    <td class="font-monospace fw-bold text-muted"><?= htmlspecialchars($p['code']); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']); ?></div>
                                        <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($p['notes'] ?? 'No notes'); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($p['type'] === 'software'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Software Core</span>
                                        <?php elseif ($p['type'] === 'add_on'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">Add-On Feature</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border">Service</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-dark"><?= format_currency($p['sales_price']); ?></td>
                                    <td class="text-secondary"><?= format_currency($p['cost_price']); ?></td>
                                    <td class="text-success fw-semibold">+<?= format_currency($margin); ?></td>
                                    <td>
                                        <a href="<?= BASE_URL; ?>/admin/product_variants.php?product_id=<?= $p['id']; ?>" class="badge bg-light text-dark border text-decoration-none">
                                            <i class="fa-solid fa-code-branch me-1 text-primary"></i> <?= $p['variant_count']; ?> Variants
                                        </a>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="<?= BASE_URL; ?>/admin/product_variants.php?product_id=<?= $p['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Manage Variants">
                                                <i class="fa-solid fa-code-branch"></i> Variants
                                            </a>
                                            <form method="POST" action="<?= BASE_URL; ?>/admin/products.php" onsubmit="return confirm('Delete this product?');" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Product">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        </div>
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addProductModalLabel"><i class="fa-solid fa-box-open text-primary me-2"></i> Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/products.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Brightloop Payroll Module" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" placeholder="PROD-PAYROLL" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="type" required>
                                <option value="software">Software Package</option>
                                <option value="add_on">Add-On (Toggleable)</option>
                                <option value="service">Professional Service</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Sales Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="sales_price" placeholder="199.00" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Cost Price ($)</label>
                            <input type="number" step="0.01" class="form-control" name="cost_price" placeholder="50.00" value="0.00">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Notes & Specifications</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Features, licensing notes, limits..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
