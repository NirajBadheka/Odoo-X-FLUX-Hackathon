<?php
/**
 * SubNexa Pro - Customer Invoices & Statements
 * Solves Scene 1 & 2 from PDF: "and customers see only their own subscriptions and invoices through a portal."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['customer']);

$customer_id = $_SESSION['user_id'];

$invoices = db_fetch_all("
    SELECT i.*, s.subscription_number, DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN subscriptions s ON i.subscription_id = s.id
    WHERE i.customer_id = ?
    ORDER BY i.id DESC
", [$customer_id], "i");

$page_title = "My Invoices & Statements — SubNexa Pro";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">My Invoices & Payment Receipts</h2>
                    <p class="text-secondary small mb-0">View tax invoices, download printable receipts, and settle outstanding balances online (PDF Page 2 & 4).</p>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Billing History (<?= count($invoices); ?> Statements)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search statements..." data-table-search="#myInvoicesTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="myInvoicesTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Subscription #</th>
                                <th>Billing Date</th>
                                <th>Due Date</th>
                                <th>Invoice Total</th>
                                <th>Paid</th>
                                <th>Balance Due</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No invoices generated for your account yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): 
                                    $is_overdue = ($inv['status'] === 'confirmed' && $inv['days_overdue'] > 0);
                                ?>
                                    <tr class="<?= $is_overdue ? 'table-danger-subtle' : ''; ?>">
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $inv['id']; ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= htmlspecialchars($inv['invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td class="font-monospace small"><?= htmlspecialchars($inv['subscription_number']); ?></td>
                                        <td class="small"><?= format_date($inv['billing_date']); ?></td>
                                        <td class="small">
                                            <?= format_date($inv['due_date']); ?>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger text-white ms-1"><?= (int)$inv['days_overdue']; ?>d late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark"><?= format_currency($inv['grand_total']); ?></td>
                                        <td class="text-success fw-semibold"><?= format_currency($inv['amount_paid']); ?></td>
                                        <td class="fw-bold <?= ($inv['amount_due'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                            <?= format_currency($inv['amount_due']); ?>
                                        </td>
                                        <td><?= get_status_badge($inv['status']); ?></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $inv['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2.5" title="View / Print Statement">
                                                    <i class="fa-solid fa-print me-1"></i> Print
                                                </a>
                                                <?php if ($inv['status'] !== 'paid' && $inv['amount_due'] > 0): ?>
                                                    <a href="<?= BASE_URL; ?>/customer/pay_invoice.php?id=<?= $inv['id']; ?>" class="btn btn-sm btn-success text-white py-1 px-2.5 fw-semibold shadow-sm" title="Pay Online">
                                                        <i class="fa-solid fa-credit-card me-1"></i> Pay
                                                    </a>
                                                <?php endif; ?>
                                            </div>
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
