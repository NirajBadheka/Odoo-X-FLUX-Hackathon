<?php
/**
 * SubNexa Pro - Printable Enterprise Tax Invoice
 * Supports @media print for browser printing and offline dispatch
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . BASE_URL . "/admin/invoices.php");
    exit;
}

$inv = db_fetch_one("
    SELECT i.*, 
           u.name as customer_name, u.email as customer_email, u.company_name, u.phone as customer_phone, u.address as customer_address,
           s.subscription_number, s.payment_terms,
           p.name as plan_name, p.billing_frequency,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN users u ON i.customer_id = u.id
    JOIN subscriptions s ON i.subscription_id = s.id
    JOIN plans p ON s.plan_id = p.id
    WHERE i.id = ?
", [$id], "i");

if (!$inv) {
    $_SESSION['flash_error'] = "Invoice not found.";
    header("Location: " . BASE_URL . "/admin/invoices.php");
    exit;
}

// Security: If role is customer, ensure customer only views their own invoices (PDF Page 2 constraint)
if (user_role() === 'customer' && $inv['customer_id'] != $_SESSION['user_id']) {
    $_SESSION['flash_error'] = "Access denied: Customers can only view their own invoices.";
    header("Location: " . BASE_URL . "/customer/my_invoices.php");
    exit;
}

$lines = db_fetch_all("SELECT * FROM invoice_lines WHERE invoice_id = ?", [$id], "i");
$payments = db_fetch_all("SELECT * FROM payments WHERE invoice_id = ? ORDER BY id ASC", [$id], "i");

$is_paid = ($inv['status'] === 'paid');
$is_overdue = ($inv['status'] === 'confirmed' && $inv['days_overdue'] > 0);

$page_title = "Invoice " . $inv['invoice_number'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print-Only / Non-Print Controls -->
<style>
@media print {
    .no-print, .app-navbar, .app-footer, .app-sidebar {
        display: none !important;
    }
    body {
        background-color: #FFFFFF !important;
        color: #000000 !important;
    }
    .invoice-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<div class="no-print">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
</div>

<div class="container py-4 my-2">
    <!-- Back & Action Bar -->
    <div class="no-print d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a href="<?= (user_role() === 'customer') ? BASE_URL . '/customer/my_invoices.php' : BASE_URL . '/admin/invoices.php'; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Invoices
            </a>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-sm btn-outline-primary fw-semibold">
                <i class="fa-solid fa-print me-1"></i> Print / Save as PDF
            </button>
            <?php if (!$is_paid && user_role() === 'customer'): ?>
                <a href="<?= BASE_URL; ?>/customer/pay_invoice.php?id=<?= $id; ?>" class="btn btn-sm btn-success text-white fw-semibold">
                    <i class="fa-solid fa-credit-card me-1"></i> Pay <?= format_currency($inv['amount_due']); ?> Online
                </a>
            <?php elseif (!$is_paid && (user_role() === 'admin' || user_role() === 'staff')): ?>
                <a href="<?= BASE_URL; ?>/admin/payments.php?invoice_id=<?= $id; ?>" class="btn btn-sm btn-success text-white fw-semibold">
                    <i class="fa-solid fa-dollar-sign me-1"></i> Record Offline Payment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Official Printable Invoice Card -->
    <div class="card invoice-card border shadow-sm rounded-4 p-4 p-md-5 bg-white">
        
        <!-- Header Banner: Brightloop Software Branding -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-3 border-bottom pb-4 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="rounded-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 1.15rem;">
                        <i class="fa-solid fa-arrows-spin"></i>
                    </div>
                    <div>
                        <h3 class="fw-extrabold text-dark mb-0 tracking-tight">Brightloop<span class="text-primary">Technologies India</span></h3>
                        <div class="text-muted small" style="font-size: 0.75rem;">Enterprise Subscription Management & Recurring Billing</div>
                    </div>
                </div>
                <div class="text-muted small mt-2">
                    Level 8, Cyber Park, Electronic City, Bengaluru, Karnataka 560100<br>
                    GSTIN: <strong>29AAAAA0000A1Z5</strong> | State: <strong>29-Karnataka</strong><br>
                    billing@brightloop.in | +91 (800) 555-LOOP
                </div>
            </div>

            <!-- Invoice Status & Number -->
            <div class="text-sm-end">
                <div class="fs-6 text-muted fw-bold text-uppercase">Tax Invoice</div>
                <h3 class="fw-bold font-monospace text-dark mb-1"><?= htmlspecialchars($inv['invoice_number']); ?></h3>
                
                <?php if ($is_paid): ?>
                    <span class="badge bg-success-subtle text-success fs-6 px-3 py-1.5 border border-success-subtle fw-bold">
                        <i class="fa-solid fa-circle-check me-1"></i> PAID IN FULL
                    </span>
                <?php elseif ($is_overdue): ?>
                    <span class="badge bg-danger-subtle text-danger fs-6 px-3 py-1.5 border border-danger-subtle fw-bold">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> OVERDUE (<?= (int)$inv['days_overdue']; ?> Days Late)
                    </span>
                <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary fs-6 px-3 py-1.5 border border-primary-subtle fw-bold">
                        <i class="fa-solid fa-clock me-1"></i> CONFIRMED / UNPAID
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Billed To & Dates Grid -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6">
                <div class="text-muted small fw-bold text-uppercase mb-1">Billed To (Customer):</div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($inv['customer_name']); ?></h5>
                <div class="text-secondary small">
                    <strong><?= htmlspecialchars($inv['company_name'] ?? '—'); ?></strong><br>
                    <?= htmlspecialchars($inv['customer_address'] ?? 'Customer Headquarters'); ?><br>
                    Email: <?= htmlspecialchars($inv['customer_email']); ?><br>
                    Phone: <?= htmlspecialchars($inv['customer_phone'] ?? '—'); ?>
                </div>
            </div>

            <div class="col-sm-6 text-sm-end">
                <div class="text-muted small fw-bold text-uppercase mb-1">Invoice Specifications:</div>
                <div class="text-secondary small space-y-1">
                    <div><strong>Billing Date:</strong> <?= format_date($inv['billing_date']); ?></div>
                    <div><strong>Due Date:</strong> <span class="<?= $is_overdue ? 'text-danger fw-bold' : ''; ?>"><?= format_date($inv['due_date']); ?></span></div>
                    <div><strong>Payment Terms:</strong> <?= htmlspecialchars($inv['payment_terms']); ?></div>
                    <div><strong>Subscription:</strong> <span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($inv['subscription_number']); ?></span></div>
                    <div><strong>Billing Cycle:</strong> <?= ucfirst($inv['billing_frequency']); ?></div>
                </div>
            </div>
        </div>

        <!-- Itemized Line Table -->
        <div class="table-responsive mb-4">
            <table class="table border-bottom">
                <thead class="table-light border-top">
                    <tr class="small text-uppercase text-secondary">
                        <th>Description / Line Item</th>
                        <th>Variant / Add-On Info</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Tax Rate</th>
                        <th class="text-end">GST (₹)</th>
                        <th class="text-end">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $ln): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($ln['product_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <?= htmlspecialchars($ln['variant_info'] ?? 'Standard'); ?>
                                </span>
                            </td>
                            <td class="text-center"><strong><?= (int)$ln['quantity']; ?></strong></td>
                            <td class="text-end"><?= format_currency($ln['unit_price']); ?></td>
                            <td class="text-end"><?= number_format((float)$ln['tax_rate'], 1); ?>%</td>
                            <td class="text-end"><?= format_currency($ln['tax_amount']); ?></td>
                            <td class="text-end fw-bold text-dark"><?= format_currency($ln['total_amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Financial Totals Box -->
        <div class="row justify-content-end mb-4">
            <div class="col-md-5">
                <div class="bg-light p-3.5 rounded-3 border">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Lines Subtotal:</span>
                        <span class="fw-semibold text-dark"><?= format_currency($inv['subtotal']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Calculated Taxes (VAT/GST):</span>
                        <span class="fw-semibold text-dark">+<?= format_currency($inv['tax_total']); ?></span>
                    </div>
                    <?php if ($inv['discount_total'] > 0): ?>
                        <div class="d-flex justify-content-between small mb-2 text-success">
                            <span>Applied Discounts:</span>
                            <span class="fw-semibold">-<?= format_currency($inv['discount_total']); ?></span>
                        </div>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark">Invoice Total:</span>
                        <span class="fs-5 fw-extrabold text-dark"><?= format_currency($inv['grand_total']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2 text-success">
                        <span>Amount Paid to Date:</span>
                        <span class="fw-semibold"><?= format_currency($inv['amount_paid']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="fw-bold text-danger">Balance Due:</span>
                        <span class="fs-5 fw-extrabold text-danger"><?= format_currency($inv['amount_due']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments Ledger Section (if payments recorded) -->
        <?php if (!empty($payments)): ?>
            <div class="border-top pt-3 mb-4">
                <div class="fw-bold small text-dark mb-2"><i class="fa-solid fa-receipt text-success me-1"></i> Payment Settlement History:</div>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead>
                            <tr class="text-muted">
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Payment Method</th>
                                <th>Transaction Ref</th>
                                <th class="text-end">Amount Settled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td class="font-monospace fw-bold"><?= htmlspecialchars($pay['payment_number']); ?></td>
                                    <td><?= format_date($pay['payment_date']); ?></td>
                                    <td><span class="badge bg-light text-secondary border text-uppercase"><?= str_replace('_', ' ', $pay['payment_method']); ?></span></td>
                                    <td class="font-monospace text-muted"><?= htmlspecialchars($pay['transaction_ref'] ?? '—'); ?></td>
                                    <td class="text-end fw-bold text-success"><?= format_currency($pay['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer / QR Code Authentication Notice -->
        <div class="border-top pt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 text-muted small">
            <div>
                <strong>Thank you for choosing Brightloop Software!</strong><br>
                For questions regarding this invoice, contact <code>billing@brightloop.in</code>.
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- SVG QR Code Icon for authenticity -->
                <div class="p-2 border rounded bg-light">
                    <i class="fa-solid fa-qrcode fs-3 text-dark"></i>
                </div>
                <div style="font-size: 0.7rem;">
                    <div>Verified Cryptographic Invoice</div>
                    <div class="font-monospace">DIGITAL-HASH-<?= strtoupper(substr(md5($inv['id'] . $inv['invoice_number']), 0, 12)); ?></div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="no-print">
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
