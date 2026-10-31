<?php
/**
 * SubNexa Pro - Automated Billing Day Engine
 * Solves Scene 3 from PDF: "It is billing day, and hundreds of subscriptions are due at once. Nobody should be typing invoices by hand. Each one should be created from its subscription, with the customer details, product lines, taxes and totals already filled in... Nothing should be billed twice or skipped."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'staff']);

$batch_results = null;

// Handle Billing Day Execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_billing'])) {
    $force_all_active = isset($_POST['force_all_active']) ? 1 : 0;
    $target_billing_date = clean_input($_POST['billing_date'] ?? date('Y-m-d'));
    
    // Select subscriptions to process
    if ($force_all_active) {
        $subs_to_bill = db_fetch_all("
            SELECT s.*, p.billing_frequency, p.auto_close_on_end, u.name as customer_name, u.email as customer_email, u.company_name
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            JOIN users u ON s.customer_id = u.id
            WHERE s.status = 'active'
        ");
    } else {
        $subs_to_bill = db_fetch_all("
            SELECT s.*, p.billing_frequency, p.auto_close_on_end, u.name as customer_name, u.email as customer_email, u.company_name
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            JOIN users u ON s.customer_id = u.id
            WHERE s.status = 'active' AND (s.next_billing_date <= ? OR s.next_billing_date IS NULL)
        ", [$target_billing_date], "s");
    }

    $created_count = 0;
    $skipped_count = 0;
    $total_billed_amount = 0;
    $created_invoices = [];

    foreach ($subs_to_bill as $sub) {
        $sub_id = $sub['id'];

        // DUPLICATE PREVENTION LOCK (Page 3 of PDF: "Nothing should be billed twice or skipped.")
        // Check if an invoice for this subscription on this billing date already exists
        $existing_inv = db_fetch_one(
            "SELECT id, invoice_number FROM invoices WHERE subscription_id = ? AND billing_date = ? LIMIT 1",
            [$sub_id, $target_billing_date],
            "is"
        );

        if ($existing_inv) {
            $skipped_count++;
            continue; // Duplicate prevented!
        }

        // Fetch lines for this subscription
        $lines = db_fetch_all("
            SELECT sl.*, p.name as product_name, pv.variant_value, tr.rate_percentage as tax_rate
            FROM subscription_lines sl
            JOIN products p ON sl.product_id = p.id
            LEFT JOIN product_variants pv ON sl.variant_id = pv.id
            LEFT JOIN tax_rules tr ON sl.tax_id = tr.id
            WHERE sl.subscription_id = ? AND (sl.is_addon = 0 OR sl.is_addon_active = 1)
        ", [$sub_id], "i");

        if (empty($lines)) {
            continue;
        }

        // Generate next Invoice Number
        $last_inv = db_fetch_one("SELECT id FROM invoices ORDER BY id DESC LIMIT 1");
        $next_inv_num = "INV-" . date('Y') . "-" . str_pad(($last_inv ? $last_inv['id'] + 1 : 1), 4, "0", STR_PAD_LEFT);

        // Due date calculation (e.g. Net 15 days)
        $due_date = date('Y-m-d', strtotime($target_billing_date . ' + 15 days'));

        // Calculate line totals
        $subtotal = 0;
        $tax_total = 0;
        $discount_total = (float)$sub['discount_total'];

        // If discount rule exists, check usage limit
        if (!empty($sub['discount_id'])) {
            $disc_rule = db_fetch_one("SELECT * FROM discount_rules WHERE id = ? AND is_active = 1", [$sub['discount_id']], "i");
            if ($disc_rule) {
                // If max uses exceeded or expired, stop applying discount
                if (($disc_rule['max_uses'] > 0 && $disc_rule['used_count'] >= $disc_rule['max_uses']) ||
                    ($disc_rule['end_date'] && strtotime($disc_rule['end_date']) < strtotime($target_billing_date))) {
                    $discount_total = 0; // Discount ceased
                } else {
                    // Increment discount used count
                    db_execute("UPDATE discount_rules SET used_count = used_count + 1 WHERE id = ?", [$disc_rule['id']], "i");
                }
            }
        }

        foreach ($lines as $ln) {
            $subtotal += ($ln['quantity'] * $ln['unit_price']);
            $tax_total += $ln['tax_amount'];
        }

        $grand_total = max(0, ($subtotal + $tax_total - $discount_total));

        // Create Invoice
        $inv_id = db_insert(
            "INSERT INTO invoices (invoice_number, subscription_id, customer_id, billing_date, due_date, status, subtotal, tax_total, discount_total, grand_total, amount_paid, amount_due, notes) 
             VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, 0.00, ?, 'Generated via Automated Billing Day Run')",
            [$next_inv_num, $sub_id, $sub['customer_id'], $target_billing_date, $due_date, $subtotal, $tax_total, $discount_total, $grand_total, $grand_total],
            "siissddddd"
        );

        // Insert invoice lines
        foreach ($lines as $ln) {
            $line_total = ($ln['quantity'] * $ln['unit_price']) + $ln['tax_amount'] - $ln['discount_amount'];
            $var_info = $ln['variant_value'] ? $ln['variant_value'] : ($ln['is_addon'] ? 'Active Add-on' : 'Standard');
            
            db_insert(
                "INSERT INTO invoice_lines (invoice_id, product_name, variant_info, quantity, unit_price, tax_rate, tax_amount, discount_amount, total_amount) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$inv_id, $ln['product_name'], $var_info, $ln['quantity'], $ln['unit_price'], ($ln['tax_rate'] ?? 0), $ln['tax_amount'], $ln['discount_amount'], $line_total],
                "issiddddd"
            );
        }

        // Calculate next billing date for subscription
        $freq = $sub['billing_frequency'];
        $next_date = match ($freq) {
            'daily' => date('Y-m-d', strtotime($target_billing_date . ' + 1 day')),
            'weekly' => date('Y-m-d', strtotime($target_billing_date . ' + 1 week')),
            'yearly' => date('Y-m-d', strtotime($target_billing_date . ' + 1 year')),
            default => date('Y-m-d', strtotime($target_billing_date . ' + 1 month'))
        };

        // Check if plan auto-closes on end (Page 3 of PDF)
        $new_status = 'active';
        if ($sub['auto_close_on_end'] && !empty($sub['expiry_date']) && strtotime($next_date) > strtotime($sub['expiry_date'])) {
            $new_status = 'closed';
        }

        db_execute(
            "UPDATE subscriptions SET next_billing_date = ?, status = ? WHERE id = ?",
            [$next_date, $new_status, $sub_id],
            "ssi"
        );

        $created_count++;
        $total_billed_amount += $grand_total;
        $created_invoices[] = [
            'id' => $inv_id,
            'number' => $next_inv_num,
            'customer' => $sub['customer_name'],
            'amount' => $grand_total
        ];
    }

    log_audit("BILLING_DAY_EXECUTED", "Executed billing run for date $target_billing_date: $created_count invoices generated, $skipped_count skipped.");

    $batch_results = [
        'created' => $created_count,
        'skipped' => $skipped_count,
        'total_amount' => $total_billed_amount,
        'date' => $target_billing_date,
        'invoices' => $created_invoices
    ];
}

// Subscriptions due for billing right now
$due_subs = db_fetch_all("
    SELECT s.*, p.name as plan_name, p.billing_frequency, u.name as customer_name, u.company_name
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    JOIN users u ON s.customer_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.next_billing_date ASC
");

$page_title = "Automated Billing Day Engine";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Automated "Billing Day" Engine</h2>
                    <p class="text-secondary small mb-0">Batch-generate recurring invoices with automatic taxes, variant pricing, discounts, and zero double-billing lock (PDF Page 3).</p>
                </div>
            </div>

            <!-- Batch Execution Results Alert -->
            <?php if ($batch_results): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border: 1px solid #A7F3D0 !important;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                                <div>
                                    <h4 class="fw-bold text-success mb-0">Billing Day Run Completed Successfully!</h4>
                                    <div class="text-secondary small">Cycle execution for <strong><?= format_date($batch_results['date']); ?></strong>.</div>
                                </div>
                            </div>
                            <a href="<?= BASE_URL; ?>/admin/invoices.php" class="btn btn-sm btn-success text-white fw-semibold">
                                <i class="fa-solid fa-file-invoice me-1"></i> View All Invoices
                            </a>
                        </div>

                        <div class="row g-3 text-center pt-2 border-top border-success-subtle">
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-dark"><?= $batch_results['created']; ?></div>
                                <div class="text-muted small">Invoices Generated</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-primary"><?= format_currency($batch_results['total_amount']); ?></div>
                                <div class="text-muted small">Total Value Billed</div>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-secondary"><?= $batch_results['skipped']; ?></div>
                                <div class="text-muted small">Skipped (Duplicate Protected)</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Billing Run Controller Card -->
            <div class="card-nordic p-4 mb-4">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge bg-primary-subtle text-primary fw-bold mb-2">Billing Automation Architecture</span>
                        <h4 class="fw-bold text-dark">Execute Real-Time Billing Run</h4>
                        <p class="text-secondary small mb-3">
                            The system will evaluate all active subscriptions, automatically calculate variant pricing (e.g., Odoo Brand +$560), compute line taxes from admin rules, apply qualifying discounts, and prepare invoices without typing by hand.
                        </p>
                        <div class="d-flex align-items-center gap-3 small text-muted">
                            <div><i class="fa-solid fa-shield-halved text-success me-1"></i> Zero Double-Billing Lock</div>
                            <div><i class="fa-solid fa-bolt text-primary me-1"></i> Instant Sub-Second Execution</div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="bg-light p-3.5 rounded-3 border">
                            <form method="POST" action="<?= BASE_URL; ?>/admin/billing_run.php" id="billingRunForm">
                                <input type="hidden" name="execute_billing" value="1">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Effective Billing Date</label>
                                    <input type="date" class="form-control" name="billing_date" value="<?= date('Y-m-d'); ?>" required>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="force_all_active" id="force_all" value="1" checked>
                                    <label class="form-check-label small" for="force_all">
                                        <strong>Include all active subscriptions</strong> (Simulate full billing month)
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                                    <i class="fa-solid fa-bolt me-2"></i> Execute Billing Run Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscriptions Queue Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Active Subscriptions in Billing Pipeline (<?= count($due_subs); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Filter queue..." data-table-search="#billingQueueTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="billingQueueTable">
                        <thead>
                            <tr>
                                <th>Subscription #</th>
                                <th>Customer</th>
                                <th>Plan</th>
                                <th>Cycle</th>
                                <th>Total Cycle Value</th>
                                <th>Next Scheduled Billing</th>
                                <th>Duplicate Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($due_subs as $s): 
                                $is_already_billed = db_fetch_one(
                                    "SELECT id, invoice_number FROM invoices WHERE subscription_id = ? AND billing_date = CURDATE() LIMIT 1",
                                    [$s['id']],
                                    "i"
                                );
                            ?>
                                <tr>
                                    <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($s['subscription_number']); ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s['customer_name']); ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($s['company_name'] ?? '—'); ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($s['plan_name']); ?></td>
                                    <td><span class="badge bg-light text-secondary border text-uppercase"><?= htmlspecialchars($s['billing_frequency']); ?></span></td>
                                    <td class="fw-bold text-dark"><?= format_currency($s['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-calendar me-1"></i> <?= format_date($s['next_billing_date']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_already_billed): ?>
                                            <span class="badge bg-secondary-subtle text-secondary" title="Invoice #<?= $is_already_billed['invoice_number']; ?> already generated">
                                                <i class="fa-solid fa-lock me-1"></i> Billed Today (Protected)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="fa-solid fa-clock me-1"></i> Ready For Billing
                                            </span>
                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
