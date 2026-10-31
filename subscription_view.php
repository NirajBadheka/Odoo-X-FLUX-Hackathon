<?php
/**
 * SubNexa Pro - Subscription Details & Interactive Lifecycle Pipeline
 * Solves Scene 3 from PDF: "It moves from Draft to Quotation to Confirmed to Active and finally to Closed, and anyone on the team should be able to see where it stands."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'staff']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . BASE_URL . "/admin/subscriptions.php");
    exit;
}

// Handle Status Transition POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transition_status'])) {
    $new_status = clean_input($_POST['new_status']);
    if (in_array($new_status, ['draft', 'quotation', 'confirmed', 'active', 'paused', 'closed'])) {
        $update_sql = "UPDATE subscriptions SET status = ? ";
        $params = [$new_status];
        $types = "s";

        // If activating, set next billing date if empty
        if ($new_status === 'active') {
            $update_sql .= ", next_billing_date = COALESCE(next_billing_date, CURDATE()) ";
        }

        $update_sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        db_execute($update_sql, $params, $types);
        log_audit("SUBSCRIPTION_STATUS_UPDATED", "Updated subscription #$id to $new_status");
        $_SESSION['flash_success'] = "Subscription status moved to " . strtoupper($new_status) . "!";
        header("Location: " . BASE_URL . "/admin/subscription_view.php?id=" . $id);
        exit;
    }
}

// Fetch subscription details
$sub = db_fetch_one("
    SELECT s.*, u.name as customer_name, u.email as customer_email, u.company_name, u.phone as customer_phone, u.address as customer_address,
           p.name as plan_name, p.billing_frequency, p.can_pause, p.can_renew, p.auto_close_on_end,
           d.name as discount_name, d.code as discount_code, d.discount_type, d.discount_value,
           st.name as staff_name
    FROM subscriptions s
    JOIN users u ON s.customer_id = u.id
    JOIN plans p ON s.plan_id = p.id
    LEFT JOIN discount_rules d ON s.discount_id = d.id
    LEFT JOIN users st ON s.created_by_staff_id = st.id
    WHERE s.id = ?
", [$id], "i");

if (!$sub) {
    $_SESSION['flash_error'] = "Subscription not found.";
    header("Location: " . BASE_URL . "/admin/subscriptions.php");
    exit;
}

// Fetch lines
$lines = db_fetch_all("
    SELECT sl.*, p.name as product_name, p.code as product_code, p.type as product_type,
           pv.attribute_name, pv.variant_value, pv.price_adjustment,
           tr.name as tax_name, tr.rate_percentage as tax_rate
    FROM subscription_lines sl
    JOIN products p ON sl.product_id = p.id
    LEFT JOIN product_variants pv ON sl.variant_id = pv.id
    LEFT JOIN tax_rules tr ON sl.tax_id = tr.id
    WHERE sl.subscription_id = ?
", [$id], "i");

// Fetch invoices for this sub
$invoices = db_fetch_all("
    SELECT * FROM invoices WHERE subscription_id = ? ORDER BY id DESC
", [$id], "i");

// Pipeline state array
$steps = [
    'draft' => ['label' => '1. Draft', 'desc' => 'Initial quote creation'],
    'quotation' => ['label' => '2. Quotation', 'desc' => 'Offer dispatched to customer'],
    'confirmed' => ['label' => '3. Confirmed', 'desc' => 'Terms accepted'],
    'active' => ['label' => '4. Active', 'desc' => 'Live recurring billing'],
    'closed' => ['label' => '5. Closed', 'desc' => 'Contract completed / closed']
];

$current_status = $sub['status'];

$page_title = "Subscription " . $sub['subscription_number'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <!-- Top Header & Breadcrumb -->
            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <a href="<?= BASE_URL; ?>/admin/subscriptions.php" class="text-secondary small text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i> Subscriptions
                        </a>
                        <span class="text-muted">/</span>
                        <span class="fw-bold font-monospace text-primary"><?= htmlspecialchars($sub['subscription_number']); ?></span>
                    </div>
                    <h2 class="fw-bold text-dark mb-0"><?= htmlspecialchars($sub['customer_name']); ?> — <?= htmlspecialchars($sub['plan_name']); ?></h2>
                </div>

                <!-- Status Transition Action Buttons -->
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($current_status === 'draft'): ?>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="quotation">
                            <button type="submit" class="btn btn-primary fw-semibold"><i class="fa-solid fa-paper-plane me-1"></i> Send Quotation</button>
                        </form>
                    <?php elseif ($current_status === 'quotation'): ?>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="confirmed">
                            <button type="submit" class="btn btn-success text-white fw-semibold"><i class="fa-solid fa-check me-1"></i> Confirm Subscription</button>
                        </form>
                    <?php elseif ($current_status === 'confirmed'): ?>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="active">
                            <button type="submit" class="btn btn-primary fw-semibold"><i class="fa-solid fa-play me-1"></i> Activate & Schedule Billing</button>
                        </form>
                    <?php elseif ($current_status === 'active'): ?>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="paused">
                            <button type="submit" class="btn btn-warning text-dark fw-semibold"><i class="fa-solid fa-pause me-1"></i> Pause</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline" onsubmit="return confirm('Close this subscription early?');">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="closed">
                            <button type="submit" class="btn btn-outline-danger fw-semibold"><i class="fa-solid fa-ban me-1"></i> Close Contract</button>
                        </form>
                    <?php elseif ($current_status === 'paused'): ?>
                        <form method="POST" action="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $id; ?>" class="d-inline">
                            <input type="hidden" name="transition_status" value="1">
                            <input type="hidden" name="new_status" value="active">
                            <button type="submit" class="btn btn-success text-white fw-semibold"><i class="fa-solid fa-play me-1"></i> Resume Subscription</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 5-Stage Lifecycle Stepper (Scene 3 of PDF) -->
            <div class="card-nordic p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">
                        <i class="fa-solid fa-diagram-project text-primary me-1.5"></i> Subscription Lifecycle Pipeline (Scene 3 of PDF)
                    </span>
                    <?= get_status_badge($current_status); ?>
                </div>

                <div class="pipeline-stepper">
                    <?php 
                    $order = ['draft', 'quotation', 'confirmed', 'active', 'closed'];
                    $curr_idx = array_search($current_status, $order);
                    if ($curr_idx === false) $curr_idx = 3; // default for paused

                    foreach ($order as $idx => $st_key): 
                        $step_info = $steps[$st_key];
                        $is_completed = ($idx < $curr_idx);
                        $is_active = ($idx === $curr_idx);
                    ?>
                        <div class="pipeline-step <?= $is_completed ? 'completed' : ($is_active ? 'active' : 'inactive'); ?>">
                            <?php if ($is_completed): ?>
                                <i class="fa-solid fa-circle-check"></i>
                            <?php elseif ($is_active): ?>
                                <i class="fa-solid fa-circle-dot"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-circle"></i>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($step_info['label']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Details Overview Grid -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card-nordic p-4 h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-building text-primary me-2"></i> Subscriber Info</h5>
                        <div class="small space-y-2 text-secondary">
                            <div><strong>Name:</strong> <?= htmlspecialchars($sub['customer_name']); ?></div>
                            <div><strong>Company:</strong> <?= htmlspecialchars($sub['company_name'] ?? '—'); ?></div>
                            <div><strong>Email:</strong> <?= htmlspecialchars($sub['customer_email']); ?></div>
                            <div><strong>Phone:</strong> <?= htmlspecialchars($sub['customer_phone'] ?? '—'); ?></div>
                            <div><strong>Address:</strong> <?= htmlspecialchars($sub['customer_address'] ?? '—'); ?></div>
                            <div><strong>Assigned Rep:</strong> <?= htmlspecialchars($sub['staff_name'] ?? 'Eleanor Vance (Admin)'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-nordic p-4 h-100">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-contract text-primary me-2"></i> Terms & Schedule</h5>
                        <div class="small space-y-2 text-secondary">
                            <div><strong>Plan:</strong> <?= htmlspecialchars($sub['plan_name']); ?></div>
                            <div><strong>Frequency:</strong> <span class="badge bg-light text-primary border text-uppercase"><?= htmlspecialchars($sub['billing_frequency']); ?></span></div>
                            <div><strong>Payment Terms:</strong> <?= htmlspecialchars($sub['payment_terms']); ?></div>
                            <div><strong>Start Date:</strong> <?= format_date($sub['start_date']); ?></div>
                            <div><strong>Expiry Date:</strong> <?= format_date($sub['expiry_date']); ?></div>
                            <div><strong>Next Billing:</strong> <span class="fw-bold text-dark"><?= format_date($sub['next_billing_date']); ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card-nordic p-4 h-100 bg-light">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-calculator text-primary me-2"></i> Financial Summary</h5>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Lines Subtotal:</span>
                            <span class="fw-semibold text-dark"><?= format_currency($sub['subtotal']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span class="text-muted">Calculated Taxes:</span>
                            <span class="fw-semibold text-dark">+<?= format_currency($sub['tax_total']); ?></span>
                        </div>
                        <?php if ($sub['discount_total'] > 0): ?>
                            <div class="d-flex justify-content-between small mb-2 text-success">
                                <span>Discount (<?= htmlspecialchars($sub['discount_code'] ?? 'Rule'); ?>):</span>
                                <span class="fw-semibold">-<?= format_currency($sub['discount_total']); ?></span>
                            </div>
                        <?php endif; ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Grand Cycle Total:</span>
                            <span class="fs-4 fw-extrabold text-primary"><?= format_currency($sub['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product & Add-On Lines Table -->
            <div class="card-nordic overflow-hidden mb-4">
                <div class="p-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">Subscription Line Items & Variants</h5>
                </div>
                <div class="table-responsive">
                    <table class="table-nordic">
                        <thead>
                            <tr>
                                <th>Item / Module</th>
                                <th>Type</th>
                                <th>Variant Applied</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Tax Rate</th>
                                <th>Tax ($)</th>
                                <th>Discount</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $ln): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($ln['product_name']); ?></div>
                                        <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($ln['product_code']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($ln['is_addon']): ?>
                                            <span class="badge bg-info-subtle text-info">Add-On</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary">Core Software</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ln['variant_value']): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <?= htmlspecialchars($ln['variant_value']); ?> (+<?= format_currency($ln['price_adjustment']); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Standard Base</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= (int)$ln['quantity']; ?></strong></td>
                                    <td><?= format_currency($ln['unit_price']); ?></td>
                                    <td><?= number_format((float)($ln['tax_rate'] ?? 0), 1); ?>%</td>
                                    <td><?= format_currency($ln['tax_amount']); ?></td>
                                    <td class="text-success"><?= $ln['discount_amount'] > 0 ? '-' . format_currency($ln['discount_amount']) : '—'; ?></td>
                                    <td class="text-end fw-bold text-dark"><?= format_currency($ln['subtotal']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoices Ledger for this Subscription -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-primary me-2"></i> Invoices Generated Under This Subscription</h5>
                    <a href="<?= BASE_URL; ?>/admin/billing_run.php" class="btn btn-sm btn-outline-primary">Trigger Billing Run</a>
                </div>
                <div class="table-responsive">
                    <table class="table-nordic">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Billing Date</th>
                                <th>Due Date</th>
                                <th>Subtotal</th>
                                <th>Tax</th>
                                <th>Grand Total</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No invoices generated yet. Will be generated on billing day.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $inv['id']; ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= htmlspecialchars($inv['invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td><?= format_date($inv['billing_date']); ?></td>
                                        <td><?= format_date($inv['due_date']); ?></td>
                                        <td><?= format_currency($inv['subtotal']); ?></td>
                                        <td><?= format_currency($inv['tax_total']); ?></td>
                                        <td class="fw-bold text-dark"><?= format_currency($inv['grand_total']); ?></td>
                                        <td class="text-success fw-semibold"><?= format_currency($inv['amount_paid']); ?></td>
                                        <td><?= get_status_badge($inv['status']); ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $inv['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2.5">
                                                <i class="fa-solid fa-print me-1"></i> Print / Pay
                                            </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
