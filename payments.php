<?php
/**
 * SubNexa Pro - Payment Reconciliation Ledger
 * Solves Scene 5 from PDF: "Payments arrive by different methods and on different days. Each one needs to be recorded with its method, amount, date and a note, against the right invoice."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'staff']);

$preselected_invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'bank_transfer');
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = clean_input($_POST['payment_date'] ?? date('Y-m-d'));
    $transaction_ref = clean_input($_POST['transaction_ref'] ?? '');
    $notes = clean_input($_POST['notes'] ?? '');

    if ($invoice_id <= 0 || $amount <= 0) {
        $_SESSION['flash_error'] = "Please select an invoice and enter a valid payment amount.";
    } else {
        $inv = db_fetch_one("SELECT * FROM invoices WHERE id = ?", [$invoice_id], "i");

        if (!$inv) {
            $_SESSION['flash_error'] = "Selected invoice not found.";
        } else {
            // Generate Payment Number
            $last_pay = db_fetch_one("SELECT id FROM payments ORDER BY id DESC LIMIT 1");
            $pay_num = "PAY-" . date('Y') . "-" . str_pad(($last_pay ? $last_pay['id'] + 1 : 1), 4, "0", STR_PAD_LEFT);

            // Record Payment
            $pay_id = db_insert(
                "INSERT INTO payments (payment_number, invoice_id, customer_id, recorded_by_id, payment_method, amount, payment_date, transaction_ref, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$pay_num, $invoice_id, $inv['customer_id'], $_SESSION['user_id'], $payment_method, $amount, $payment_date, $transaction_ref, $notes],
                "siiisdsss"
            );

            // Update Invoice Paid Amount and Balance
            $new_amount_paid = $inv['amount_paid'] + $amount;
            $new_amount_due = max(0, $inv['grand_total'] - $new_amount_paid);
            $new_status = ($new_amount_due <= 0.01) ? 'paid' : 'confirmed';

            db_execute(
                "UPDATE invoices SET amount_paid = ?, amount_due = ?, status = ? WHERE id = ?",
                [$new_amount_paid, $new_amount_due, $new_status, $invoice_id],
                "ddsi"
            );

            log_audit("PAYMENT_RECORDED", "Recorded payment $pay_num ($amount) for Invoice #{$inv['invoice_number']}");
            $_SESSION['flash_success'] = "Payment $pay_num of " . format_currency($amount) . " recorded successfully!";
            header("Location: " . BASE_URL . "/admin/payments.php");
            exit;
        }
    }
}

// Fetch unpaid invoices for modal dropdown
$unpaid_invoices = db_fetch_all("
    SELECT i.*, u.name as customer_name, u.company_name
    FROM invoices i
    JOIN users u ON i.customer_id = u.id
    WHERE i.status != 'paid' AND i.status != 'cancelled'
    ORDER BY i.id DESC
");

// Fetch payment history
$payments = db_fetch_all("
    SELECT p.*, i.invoice_number, i.grand_total, u.name as customer_name, u.company_name, rec.name as recorded_by_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN users u ON p.customer_id = u.id
    LEFT JOIN users rec ON p.recorded_by_id = rec.id
    ORDER BY p.id DESC
");

$page_title = "Payment Ledger & Reconciliation";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Payment Ledger & Reconciliation</h2>
                    <p class="text-secondary small mb-0">Record and match payments across credit cards, bank wires, and UPI against active invoices (PDF Page 4).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Record Incoming Payment
                    </button>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Settled Receipts (<?= count($payments); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search payments..." data-table-search="#paymentsTable" style="max-width: 240px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Invoice #</th>
                                <th>Customer / Company</th>
                                <th>Payment Method</th>
                                <th>Amount Settled</th>
                                <th>Date</th>
                                <th>Reference / Notes</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No payments recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($pay['payment_number']); ?></td>
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $pay['invoice_id']; ?>" class="font-monospace fw-bold text-primary text-decoration-none">
                                                <?= htmlspecialchars($pay['invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($pay['customer_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($pay['company_name'] ?? '—'); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.7rem;">
                                                <i class="fa-solid fa-credit-card me-1 text-primary"></i> <?= str_replace('_', ' ', $pay['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-success fs-6">+<?= format_currency($pay['amount']); ?></td>
                                        <td class="small"><?= format_date($pay['payment_date']); ?></td>
                                        <td class="small text-muted">
                                            <div><strong>Ref:</strong> <?= htmlspecialchars($pay['transaction_ref'] ?? 'N/A'); ?></div>
                                            <div style="font-size: 0.725rem;"><?= htmlspecialchars($pay['notes'] ?? '—'); ?></div>
                                        </td>
                                        <td class="small text-secondary"><?= htmlspecialchars($pay['recorded_by_name'] ?? 'System'); ?></td>
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

<!-- Record Payment Modal -->
<div class="modal fade <?= ($preselected_invoice_id > 0) ? 'show d-block' : ''; ?>" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="<?= ($preselected_invoice_id > 0) ? 'false' : 'true'; ?>" style="<?= ($preselected_invoice_id > 0) ? 'background: rgba(0,0,0,0.5);' : ''; ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="recordPaymentModalLabel"><i class="fa-solid fa-receipt text-primary me-2"></i> Record Incoming Payment</h5>
                <?php if ($preselected_invoice_id > 0): ?>
                    <a href="<?= BASE_URL; ?>/admin/payments.php" class="btn-close"></a>
                <?php else: ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php endif; ?>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/payments.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="record_payment" value="1">

                    <div class="mb-3">
                        <label class="form-label">Target Invoice <span class="text-danger">*</span></label>
                        <select class="form-select" name="invoice_id" id="payment_invoice_select" required onchange="updateDueAmount(this)">
                            <option value="">Select outstanding invoice...</option>
                            <?php foreach ($unpaid_invoices as $uinv): ?>
                                <option value="<?= $uinv['id']; ?>" data-due="<?= $uinv['amount_due']; ?>" <?= ($preselected_invoice_id == $uinv['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($uinv['invoice_number']); ?> — <?= htmlspecialchars($uinv['customer_name']); ?> (Due: <?= format_currency($uinv['amount_due']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="bank_transfer" selected>NEFT / RTGS / IMPS Bank Transfer</option>
                                <option value="credit_card">RuPay / Credit / Debit Card</option>
                                <option value="upi">UPI (GPay / PhonePe / Paytm)</option>
                                <option value="cheque">Corporate Cheque</option>
                                <option value="cash">Cash Settlement</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Amount Paid (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="amount" id="payment_amount_input" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Transaction Reference #</label>
                            <input type="text" class="form-control" name="transaction_ref" placeholder="e.g. WIRE-892018">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Reconciliation Note</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Bank statement matching note..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <?php if ($preselected_invoice_id > 0): ?>
                        <a href="<?= BASE_URL; ?>/admin/payments.php" class="btn btn-outline-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Confirm & Settle Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDueAmount(sel) {
    const selected = sel.options[sel.selectedIndex];
    const due = selected.getAttribute('data-due');
    if (due) {
        document.getElementById('payment_amount_input').value = parseFloat(due).toFixed(2);
    }
}
// Trigger on page load if preselected
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('payment_invoice_select');
    if (sel && sel.value) {
        updateDueAmount(sel);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
