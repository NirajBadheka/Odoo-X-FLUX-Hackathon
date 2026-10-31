<?php
/**
 * SubNexa Pro - Quotation Templates Manager
 * Solves Scene 3 from PDF: "A sales rep is preparing an offer for a large customer. Instead of building it from scratch, they start from a quotation template that already has the right plan and product lines, and stays valid for a set number of days."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $validity_days = (int)($_POST['validity_days'] ?? 30);
        $notes = clean_input($_POST['notes'] ?? '');
        $selected_products = $_POST['products'] ?? [];

        if (empty($name) || $plan_id <= 0) {
            $_SESSION['flash_error'] = "Template name and plan are required.";
        } else {
            $tpl_id = db_insert(
                "INSERT INTO quotation_templates (name, plan_id, validity_days, notes) VALUES (?, ?, ?, ?)",
                [$name, $plan_id, $validity_days, $notes],
                "siis"
            );

            if ($tpl_id && !empty($selected_products)) {
                foreach ($selected_products as $p_id) {
                    $p_id = (int)$p_id;
                    if ($p_id > 0) {
                        db_insert(
                            "INSERT INTO quotation_template_lines (template_id, product_id, default_quantity) VALUES (?, ?, 1)",
                            [$tpl_id, $p_id],
                            "ii"
                        );
                    }
                }
            }

            log_audit("TEMPLATE_CREATED", "Created quotation template: $name (ID: $tpl_id)");
            $_SESSION['flash_success'] = "Quotation template '$name' created successfully!";
            header("Location: " . BASE_URL . "/admin/templates.php");
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM quotation_templates WHERE id = ?", [$id], "i");
            log_audit("TEMPLATE_DELETED", "Deleted quotation template ID: $id");
            $_SESSION['flash_success'] = "Template removed.";
            header("Location: " . BASE_URL . "/admin/templates.php");
            exit;
        }
    }
}

$templates = db_fetch_all("
    SELECT t.*, p.name as plan_name, p.billing_frequency, p.cost as plan_cost
    FROM quotation_templates t
    JOIN plans p ON t.plan_id = p.id
    ORDER BY t.id DESC
");

$plans = db_fetch_all("SELECT id, name, billing_frequency, cost FROM plans WHERE is_active = 1");
$products = db_fetch_all("SELECT id, name, type, sales_price FROM products WHERE is_active = 1");

$page_title = "Quotation Templates Builder";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Quotation Templates Builder</h2>
                    <p class="text-secondary small mb-0">Pre-configure quotes with target plans, standard line items, and expiration windows for sales reps (PDF Page 3).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Build Template
                    </button>
                </div>
            </div>

            <!-- Templates Grid / Cards -->
            <div class="row g-4">
                <?php foreach ($templates as $tpl): 
                    $lines = db_fetch_all("
                        SELECT tl.*, p.name as product_name, p.type, p.sales_price 
                        FROM quotation_template_lines tl 
                        JOIN products p ON tl.product_id = p.id 
                        WHERE tl.template_id = ?
                    ", [$tpl['id']], "i");
                ?>
                    <div class="col-md-6">
                        <div class="card-nordic h-100 p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($tpl['name']); ?></h4>
                                    <span class="badge bg-light text-primary border">
                                        <i class="fa-solid fa-layer-group me-1"></i> <?= htmlspecialchars($tpl['plan_name']); ?> (<?= ucfirst($tpl['billing_frequency']); ?>)
                                    </span>
                                </div>
                                <span class="badge bg-info-subtle text-info fw-bold">
                                    <i class="fa-solid fa-hourglass-half me-1"></i> Valid: <?= (int)$tpl['validity_days']; ?> Days
                                </span>
                            </div>

                            <p class="text-muted small mb-3"><?= htmlspecialchars($tpl['notes'] ?? 'Standard sales offering.'); ?></p>

                            <div class="bg-light p-3 rounded-3 border mb-3">
                                <div class="fw-bold text-dark small mb-2"><i class="fa-solid fa-boxes-stacked text-secondary me-1"></i> Pre-bundled Line Items:</div>
                                <?php if (empty($lines)): ?>
                                    <span class="text-muted small">No default line items attached.</span>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0 small space-y-1">
                                        <?php foreach ($lines as $ln): ?>
                                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                                <span>
                                                    <i class="fa-solid fa-angle-right text-muted me-1"></i>
                                                    <?= htmlspecialchars($ln['product_name']); ?>
                                                    <span class="badge bg-white text-muted border ms-1"><?= ucfirst($ln['type']); ?></span>
                                                </span>
                                                <span class="fw-semibold text-dark"><?= format_currency($ln['sales_price']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="mt-auto d-flex justify-content-between align-items-center pt-2">
                                <a href="<?= BASE_URL; ?>/staff/quotation_create.php?template_id=<?= $tpl['id']; ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                    <i class="fa-solid fa-file-invoice me-1"></i> Test Quote From This
                                </a>
                                <form method="POST" action="<?= BASE_URL; ?>/admin/templates.php" onsubmit="return confirm('Delete template?');" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $tpl['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </main>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addTemplateModalLabel"><i class="fa-solid fa-file-signature text-primary me-2"></i> Build Quotation Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/templates.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-8">
                            <label class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Standard Enterprise Software Suite" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Validity (Days) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="validity_days" value="30" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Linked Plan <span class="text-danger">*</span></label>
                        <select class="form-select" name="plan_id" required>
                            <option value="">Select Plan...</option>
                            <?php foreach ($plans as $pl): ?>
                                <option value="<?= $pl['id']; ?>">
                                    <?= htmlspecialchars($pl['name']); ?> (<?= ucfirst($pl['billing_frequency']); ?> - <?= format_currency($pl['cost']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pre-select Products & Add-Ons:</label>
                        <div class="p-3 bg-light rounded-3 border" style="max-height: 180px; overflow-y: auto;">
                            <?php foreach ($products as $pr): ?>
                                <div class="form-check py-1">
                                    <input class="form-check-input" type="checkbox" name="products[]" value="<?= $pr['id']; ?>" id="tpl_prod_<?= $pr['id']; ?>">
                                    <label class="form-check-label small" for="tpl_prod_<?= $pr['id']; ?>">
                                        <strong><?= htmlspecialchars($pr['name']); ?></strong> (<?= ucfirst($pr['type']); ?> - <?= format_currency($pr['sales_price']); ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Notes for Sales Team</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Standard terms, discount boundaries..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
