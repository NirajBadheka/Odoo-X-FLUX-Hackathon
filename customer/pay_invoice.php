<?php
/**
 * SubNexa Pro - Online Invoice Settlement Gateway
 * Solves Scene 4 from PDF: "The money comes in: Payments arrive by different methods and on different days..."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['customer']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . BASE_URL . "/customer/my_invoices.php");
    exit;
}

$inv = db_fetch_one("
    SELECT i.*, s.subscription_number, p.name as plan_name, u.name as customer_name, u.email as customer_email
    FROM invoices i
    JOIN subscriptions s ON i.subscription_id = s.id
    JOIN plans p ON s.plan_id = p.id
    JOIN users u ON i.customer_id = u.id
    WHERE i.id = ? AND i.customer_id = ?
", [$id, $_SESSION['user_id']], "ii");

if (!$inv) {
    $_SESSION['flash_error'] = "Invoice not found or already settled.";
    header("Location: " . BASE_URL . "/customer/my_invoices.php");
    exit;
}

if ($inv['status'] === 'paid' || $inv['amount_due'] <= 0) {
    $_SESSION['flash_success'] = "This invoice is already paid in full.";
    header("Location: " . BASE_URL . "/admin/invoice_view.php?id=" . $id);
    exit;
}

$payment_successful = false;
$receipt_number = "";

// Handle Online Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $pay_method = clean_input($_POST['payment_method'] ?? 'credit_card');
    $pay_amount = (float)$inv['amount_due'];
    
    // Generate simulated reference
    $txn_ref = ($pay_method === 'credit_card') ? "CC_STRIPE_" . strtoupper(bin2hex(random_bytes(6))) : "UPI_TXN_" . strtoupper(bin2hex(random_bytes(6)));

    // Generate Payment Receipt Number
    $last_pay = db_fetch_one("SELECT id FROM payments ORDER BY id DESC LIMIT 1");
    $receipt_number = "PAY-" . date('Y') . "-" . str_pad(($last_pay ? $last_pay['id'] + 1 : 1), 4, "0", STR_PAD_LEFT);

    // Insert Payment Record
    db_insert(
        "INSERT INTO payments (payment_number, invoice_id, customer_id, recorded_by_id, payment_method, amount, payment_date, transaction_ref, notes) 
         VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'Settled via Customer Self-Service Online Portal')",
        [$receipt_number, $id, $_SESSION['user_id'], $_SESSION['user_id'], $pay_method, $pay_amount, $txn_ref],
        "siiisds"
    );

    // Update Invoice to PAID
    db_execute(
        "UPDATE invoices SET amount_paid = grand_total, amount_due = 0.00, status = 'paid' WHERE id = ?",
        [$id],
        "i"
    );

    log_audit("ONLINE_PAYMENT_SUCCESS", "Customer settled invoice #{$inv['invoice_number']} for $pay_amount via $pay_method");

    $payment_successful = true;
}

$page_title = "Pay Invoice " . $inv['invoice_number'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="min-vh-100 py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if ($payment_successful): ?>
                    <!-- Success Receipt Card -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center p-4 p-md-5 bg-white">
                        <div class="d-inline-flex p-3 rounded-circle bg-success-subtle text-success mb-3 mx-auto" style="width: 72px; height: 72px; font-size: 2rem; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Payment Successful!</h3>
                        <p class="text-muted small mb-4">Your payment of <strong><?= format_currency($inv['amount_due']); ?></strong> has been settled.</p>

                        <div class="bg-light p-3 rounded-3 border text-start small mb-4 space-y-1">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Payment Receipt #:</span>
                                <span class="fw-bold font-monospace text-primary"><?= $receipt_number; ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Invoice Settled:</span>
                                <span class="fw-bold font-monospace"><?= htmlspecialchars($inv['invoice_number']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Amount Settled:</span>
                                <span class="fw-bold text-success fs-6"><?= format_currency($inv['amount_due']); ?></span>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $id; ?>" class="btn btn-primary w-100 fw-semibold">
                                <i class="fa-solid fa-print me-1"></i> View & Print Tax Receipt
                            </a>
                            <a href="<?= BASE_URL; ?>/customer/dashboard.php" class="btn btn-outline-secondary w-100 fw-semibold">
                                Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Checkout Card -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                        <div class="card-header bg-dark text-white p-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary fw-bold text-uppercase small">Secure Checkout</span>
                                    <h4 class="fw-bold text-white mb-0 mt-1"><?= htmlspecialchars($inv['invoice_number']); ?></h4>
                                </div>
                                <div class="text-end">
                                    <div class="text-white-50 small">Amount Due:</div>
                                    <h3 class="fw-bold text-white mb-0"><?= format_currency($inv['amount_due']); ?></h3>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            <div class="bg-light p-3 rounded-3 border small mb-4">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Plan:</span>
                                    <strong><?= htmlspecialchars($inv['plan_name']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Subscription #:</span>
                                    <span class="font-monospace fw-bold"><?= htmlspecialchars($inv['subscription_number']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Billing Cycle:</span>
                                    <span><?= format_date($inv['billing_date']); ?> to <?= format_date($inv['due_date']); ?></span>
                                </div>
                            </div>

                            <form method="POST" action="<?= BASE_URL; ?>/customer/pay_invoice.php?id=<?= $id; ?>" id="paymentForm">
                                <input type="hidden" name="process_payment" value="1">

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Select Payment Method</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="radio" class="btn-check" name="payment_method" id="pay_cc" value="credit_card" checked onchange="togglePayInputs('cc')">
                                            <label class="btn btn-outline-secondary w-100 p-2.5 text-start d-flex align-items-center gap-2" for="pay_cc">
                                                <i class="fa-solid fa-credit-card text-primary fs-5"></i>
                                                <span class="small fw-semibold">Credit Card</span>
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="radio" class="btn-check" name="payment_method" id="pay_upi" value="upi" onchange="togglePayInputs('upi')">
                                            <label class="btn btn-outline-secondary w-100 p-2.5 text-start d-flex align-items-center gap-2" for="pay_upi">
                                                <i class="fa-solid fa-mobile-screen-button text-success fs-5"></i>
                                                <span class="small fw-semibold">Instant UPI</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credit Card Fields -->
                                <div id="cc_fields">
                                    <div class="mb-3">
                                        <label class="form-label small">Cardholder Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($inv['customer_name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Card Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="fa-brands fa-cc-visa"></i></span>
                                            <input type="text" class="form-control" placeholder="4242 •••• •••• 4242" value="4242 •••• •••• 4242">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-4">
                                        <div class="col-6">
                                            <label class="form-label small">Expires</label>
                                            <input type="text" class="form-control" placeholder="MM/YY" value="12/28">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">CVV</label>
                                            <input type="password" class="form-control" placeholder="123" value="789">
                                        </div>
                                    </div>
                                </div>

                                <!-- UPI Fields -->
                                <div id="upi_fields" style="display: none;" class="mb-4">
                                    <label class="form-label small">Virtual Payment Address (UPI ID)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="company@okaxis" value="<?= strtolower(str_replace(' ', '', $inv['customer_name'])); ?>@okhdfcbank">
                                        <span class="input-group-text bg-light text-success fw-bold">VERIFIED</span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 py-2.5 fw-semibold shadow-sm text-white">
                                    <i class="fa-solid fa-lock me-2"></i> Authorize Payment of <?= format_currency($inv['amount_due']); ?>
                                </button>
                            </form>

                            <div class="text-center mt-3">
                                <a href="<?= BASE_URL; ?>/customer/my_invoices.php" class="text-muted small text-decoration-none">
                                    Cancel & Return to Invoices
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function togglePayInputs(type) {
    document.getElementById('cc_fields').style.display = (type === 'cc') ? 'block' : 'none';
    document.getElementById('upi_fields').style.display = (type === 'upi') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
