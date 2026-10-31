<?php
/**
 * SubNexa Pro - Create Quotation Wizard (Scene 3 of PDF)
 * Pre-populates from quotation templates and computes variant adjustments dynamically
 */
require_once __DIR__ . '/../config/config.php';
require_role(['staff', 'admin']);

$preselected_template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

// Fetch Customers, Plans, Products, Variants, Taxes, Discounts, Templates
$customers = db_fetch_all("SELECT id, name, company_name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name ASC");
$plans = db_fetch_all("SELECT * FROM plans WHERE is_active = 1 ORDER BY id ASC");
$products = db_fetch_all("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC");
$variants = db_fetch_all("SELECT * FROM product_variants ORDER BY product_id ASC");
$taxes = db_fetch_all("SELECT * FROM tax_rules WHERE is_active = 1 ORDER BY rate_percentage DESC");
$discounts = db_fetch_all("SELECT * FROM discount_rules WHERE is_active = 1");
$templates = db_fetch_all("SELECT * FROM quotation_templates ORDER BY name ASC");

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quotation'])) {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $payment_terms = clean_input($_POST['payment_terms'] ?? 'Net 15');
    $validity_days = (int)($_POST['validity_days'] ?? 14);
    $discount_id = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    $notes = clean_input($_POST['notes'] ?? '');
    $selected_items = $_POST['items'] ?? [];

    if ($customer_id <= 0 || $plan_id <= 0 || empty($selected_items)) {
        $_SESSION['flash_error'] = "Customer, Plan, and at least one line item are required.";
    } else {
        // Generate Subscription Number
        $last_sub = db_fetch_one("SELECT id FROM subscriptions ORDER BY id DESC LIMIT 1");
        $sub_number = "SUB-" . date('Y') . "-" . str_pad(($last_sub ? $last_sub['id'] + 1 : 1), 4, "0", STR_PAD_LEFT);

        $start_date = date('Y-m-d');
        $expiry_date = date('Y-m-d', strtotime("+$validity_days days"));

        // Calculate Totals
        $subtotal = 0;
        $tax_total = 0;

        foreach ($selected_items as $item) {
            $unit_price = (float)($item['unit_price'] ?? 0);
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $tax_rate = (float)($item['tax_rate'] ?? 0);

            $line_subtotal = $unit_price * $qty;
            $line_tax = $line_subtotal * ($tax_rate / 100);

            $subtotal += $line_subtotal;
            $tax_total += $line_tax;
        }

        // Apply discount if selected
        $discount_total = 0;
        if ($discount_id) {
            $d_rule = db_fetch_one("SELECT * FROM discount_rules WHERE id = ?", [$discount_id], "i");
            if ($d_rule) {
                if ($d_rule['discount_type'] === 'percentage') {
                    $discount_total = $subtotal * ($d_rule['discount_value'] / 100);
                } else {
                    $discount_total = min($subtotal, (float)$d_rule['discount_value']);
                }
            }
        }

        $grand_total = max(0, ($subtotal + $tax_total - $discount_total));

        // Insert Subscription with status 'quotation'
        $sub_id = db_insert(
            "INSERT INTO subscriptions (subscription_number, customer_id, created_by_staff_id, plan_id, discount_id, status, start_date, expiry_date, payment_terms, subtotal, tax_total, discount_total, total_amount, notes) 
             VALUES (?, ?, ?, ?, ?, 'quotation', ?, ?, ?, ?, ?, ?, ?, ?)",
            [$sub_number, $customer_id, $_SESSION['user_id'], $plan_id, $discount_id, $start_date, $expiry_date, $payment_terms, $subtotal, $tax_total, $discount_total, $grand_total, $notes],
            "siiiisssdddds"
        );

        // Insert Line items
        foreach ($selected_items as $item) {
            $prod_id = (int)$item['product_id'];
            $var_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $unit_price = (float)$item['unit_price'];
            $tax_id = !empty($item['tax_id']) ? (int)$item['tax_id'] : null;
            $tax_rate = (float)($item['tax_rate'] ?? 0);

            $line_sub = $unit_price * $qty;
            $line_tax = $line_sub * ($tax_rate / 100);

            // Check if product is an add-on
            $prod_info = db_fetch_one("SELECT type FROM products WHERE id = ?", [$prod_id], "i");
            $is_addon = ($prod_info && $prod_info['type'] === 'add_on') ? 1 : 0;

            db_insert(
                "INSERT INTO subscription_lines (subscription_id, product_id, variant_id, is_addon, is_addon_active, quantity, unit_price, tax_id, tax_amount, discount_amount, subtotal) 
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, 0.00, ?)",
                [$sub_id, $prod_id, $var_id, $is_addon, $qty, $unit_price, $tax_id, $line_tax, $line_sub],
                "iiiididd"
            );
        }

        log_audit("QUOTATION_CREATED", "Prepared quotation $sub_number for customer #$customer_id");
        $_SESSION['flash_success'] = "Quotation $sub_number created successfully!";
        header("Location: " . BASE_URL . "/admin/subscription_view.php?id=" . $sub_id);
        exit;
    }
}

$page_title = "Create Quotation Proposal";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Prepare New Quotation Proposal</h2>
                    <p class="text-secondary small mb-0">Start from a template with default plan and product lines, apply variants (e.g. +$560 Odoo Brand), and calculate taxes (PDF Page 3).</p>
                </div>
                <div>
                    <a href="<?= BASE_URL; ?>/staff/quotations.php" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back to Quotes
                    </a>
                </div>
            </div>

            <form method="POST" action="<?= BASE_URL; ?>/staff/quotation_create.php" id="quoteForm">
                <input type="hidden" name="save_quotation" value="1">

                <!-- Template Selector Card -->
                <div class="card-nordic p-4 mb-4" style="background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); border: 1px solid #BAE6FD;">
                    <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold small">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>FAST-START WITH A PRE-SET TEMPLATE (Scene 3 of PDF):</span>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-8">
                            <select class="form-select" id="template_selector" onchange="loadTemplate(this.value)">
                                <option value="">Select a Quotation Template...</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?= $tpl['id']; ?>" <?= ($preselected_template_id == $tpl['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($tpl['name']); ?> (Valid: <?= $tpl['validity_days']; ?> days)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 text-muted small">
                            Automatically loads recommended plan and line items.
                        </div>
                    </div>
                </div>

                <!-- Customer & Terms Configuration -->
                <div class="card-nordic p-4 mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-tie text-primary me-2"></i> Proposal Parameters</h5>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer / Company <span class="text-danger">*</span></label>
                            <select class="form-select" name="customer_id" required>
                                <option value="">Select customer...</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id']; ?>">
                                        <?= htmlspecialchars($c['name']); ?> (<?= htmlspecialchars($c['company_name'] ?? 'Individual'); ?>) — <?= htmlspecialchars($c['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Billing Plan <span class="text-danger">*</span></label>
                            <select class="form-select" name="plan_id" id="plan_select" required>
                                <option value="">Select recurring plan...</option>
                                <?php foreach ($plans as $pl): ?>
                                    <option value="<?= $pl['id']; ?>" data-cost="<?= $pl['cost']; ?>">
                                        <?= htmlspecialchars($pl['name']); ?> (<?= ucfirst($pl['billing_frequency']); ?> - <?= format_currency($pl['cost']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payment Terms</label>
                            <select class="form-select" name="payment_terms">
                                <option value="Immediate Payment">Immediate Payment</option>
                                <option value="Net 15" selected>Net 15</option>
                                <option value="Net 30">Net 30</option>
                                <option value="Net 60">Net 60</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quotation Validity (Days)</label>
                            <input type="number" class="form-control" name="validity_days" id="validity_days_input" value="14" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Volume Discount Rule</label>
                            <select class="form-select" name="discount_id" id="discount_select" onchange="recalculateTotals()">
                                <option value="">No discount applied</option>
                                <?php foreach ($discounts as $d): ?>
                                    <option value="<?= $d['id']; ?>" data-type="<?= $d['discount_type']; ?>" data-val="<?= $d['discount_value']; ?>">
                                        <?= htmlspecialchars($d['code']); ?> — <?= htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Lines & Variants Table -->
                <div class="card-nordic overflow-hidden mb-4">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Quote Line Items & Variants</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLineItem()">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table-nordic" id="linesTable">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">Product / Module</th>
                                    <th style="width: 25%;">Variant Modifier</th>
                                    <th style="width: 12%;">Quantity</th>
                                    <th style="width: 15%;">Unit Price ($)</th>
                                    <th style="width: 15%;">Tax Rule</th>
                                    <th class="text-end" style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="linesContainer">
                                <!-- Initial Line Item -->
                                <tr class="line-row">
                                    <td>
                                        <select class="form-select form-select-sm product-select" name="items[0][product_id]" required onchange="onProductChange(this, 0)">
                                            <option value="">Select Product...</option>
                                            <?php foreach ($products as $pr): ?>
                                                <option value="<?= $pr['id']; ?>" data-price="<?= $pr['sales_price']; ?>">
                                                    <?= htmlspecialchars($pr['name']); ?> (<?= format_currency($pr['sales_price']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm variant-select" name="items[0][variant_id]" onchange="onVariantChange(this, 0)">
                                            <option value="" data-adjustment="0">Standard Edition (+0.00)</option>
                                            <?php foreach ($variants as $v): ?>
                                                <option value="<?= $v['id']; ?>" data-prod="<?= $v['product_id']; ?>" data-adjustment="<?= $v['price_adjustment']; ?>">
                                                    <?= htmlspecialchars($v['variant_value']); ?> (+<?= format_currency($v['price_adjustment']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm qty-input" name="items[0][quantity]" value="1" min="1" required oninput="recalculateTotals()">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control form-control-sm price-input" name="items[0][unit_price]" value="0.00" required oninput="recalculateTotals()">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm tax-select" name="items[0][tax_id]" onchange="onTaxChange(this, 0)">
                                            <?php foreach ($taxes as $tx): ?>
                                                <option value="<?= $tx['id']; ?>" data-rate="<?= $tx['rate_percentage']; ?>">
                                                    <?= htmlspecialchars($tx['name']); ?> (<?= (float)$tx['rate_percentage']; ?>%)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" class="tax-rate-hidden" name="items[0][tax_rate]" value="18.00">
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeLineItem(this)">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Calculation Summary Card -->
                <div class="row justify-content-end mb-4">
                    <div class="col-md-5">
                        <div class="card-nordic p-4 bg-light">
                            <h6 class="fw-bold text-dark mb-3">Live Quotation Summary</h6>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted">Lines Subtotal:</span>
                                <span class="fw-semibold text-dark" id="disp_subtotal">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted">Calculated Taxes:</span>
                                <span class="fw-semibold text-dark" id="disp_tax">+$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between small mb-2 text-success">
                                <span>Applied Discount:</span>
                                <span class="fw-semibold" id="disp_discount">-$0.00</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">Quotation Total:</span>
                                <span class="fs-4 fw-extrabold text-primary" id="disp_grand_total">$0.00</span>
                            </div>

                            <button type="submit" class="btn btn-nordic-primary w-100 py-2.5 mt-3 fw-semibold shadow-sm">
                                <i class="fa-solid fa-file-signature me-1.5"></i> Save & Dispatch Quotation
                            </button>
                        </div>
                    </div>
                </div>

            </form>

        </div>
    </main>
</div>

<script>
let lineCounter = 1;

function onProductChange(selectEl, idx) {
    const row = selectEl.closest('tr');
    const selected = selectEl.options[selectEl.selectedIndex];
    const basePrice = parseFloat(selected.getAttribute('data-price') || 0);

    const variantSelect = row.querySelector('.variant-select');
    const variantAdj = parseFloat(variantSelect.options[variantSelect.selectedIndex].getAttribute('data-adjustment') || 0);

    const priceInput = row.querySelector('.price-input');
    priceInput.value = (basePrice + variantAdj).toFixed(2);

    recalculateTotals();
}

function onVariantChange(variantSelect, idx) {
    const row = variantSelect.closest('tr');
    const prodSelect = row.querySelector('.product-select');
    const basePrice = parseFloat(prodSelect.options[prodSelect.selectedIndex]?.getAttribute('data-price') || 0);
    const variantAdj = parseFloat(variantSelect.options[variantSelect.selectedIndex].getAttribute('data-adjustment') || 0);

    const priceInput = row.querySelector('.price-input');
    priceInput.value = (basePrice + variantAdj).toFixed(2);

    recalculateTotals();
}

function onTaxChange(taxSelect, idx) {
    const row = taxSelect.closest('tr');
    const rate = taxSelect.options[taxSelect.selectedIndex].getAttribute('data-rate') || 0;
    row.querySelector('.tax-rate-hidden').value = rate;
    recalculateTotals();
}

function addLineItem() {
    const container = document.getElementById('linesContainer');
    const firstRow = container.querySelector('.line-row');
    const newRow = firstRow.cloneNode(true);

    newRow.querySelectorAll('select, input').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, `[${lineCounter}]`);
        if (el.classList.contains('qty-input')) el.value = 1;
        if (el.classList.contains('price-input')) el.value = '0.00';
    });

    container.appendChild(newRow);
    lineCounter++;
    recalculateTotals();
}

function removeLineItem(btn) {
    const container = document.getElementById('linesContainer');
    if (container.querySelectorAll('.line-row').length > 1) {
        btn.closest('tr').remove();
        recalculateTotals();
    } else {
        alert("At least one line item is required.");
    }
}

function recalculateTotals() {
    let subtotal = 0;
    let taxTotal = 0;

    document.querySelectorAll('.line-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value || 1);
        const price = parseFloat(row.querySelector('.price-input').value || 0);
        const taxRate = parseFloat(row.querySelector('.tax-rate-hidden').value || 0);

        const lineSub = qty * price;
        const lineTax = lineSub * (taxRate / 100);

        subtotal += lineSub;
        taxTotal += lineTax;
    });

    // Discount
    let discount = 0;
    const discSelect = document.getElementById('discount_select');
    if (discSelect && discSelect.value) {
        const opt = discSelect.options[discSelect.selectedIndex];
        const type = opt.getAttribute('data-type');
        const val = parseFloat(opt.getAttribute('data-val') || 0);

        if (type === 'percentage') {
            discount = subtotal * (val / 100);
        } else {
            discount = Math.min(subtotal, val);
        }
    }

    const grand = Math.max(0, subtotal + taxTotal - discount);

    document.getElementById('disp_subtotal').innerText = '₹ ' + subtotal.toFixed(2);
    document.getElementById('disp_tax').innerText = '+₹ ' + taxTotal.toFixed(2);
    document.getElementById('disp_discount').innerText = '-₹ ' + discount.toFixed(2);
    document.getElementById('disp_grand_total').innerText = '₹ ' + grand.toFixed(2);
}

// Pre-populate if template passed
function loadTemplate(tplId) {
    if (tplId) {
        window.location.href = "<?= BASE_URL; ?>/staff/quotation_create.php?template_id=" + tplId;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
