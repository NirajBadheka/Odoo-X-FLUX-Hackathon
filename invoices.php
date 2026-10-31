<?php
/**
 * SubNexa Pro - Master Invoices Ledger
 * Solves Scene 3 & 4 from PDF: "The team can confirm, cancel, send or print any invoice, and each invoice moves from Draft to Confirmed to Paid... At any moment, the team should be able to see which invoices are paid and which are still outstanding..."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'staff']);

$filter = clean_input($_GET['filter'] ?? 'all');

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $inv_id = (int)$_POST['invoice_id'];
    $new_status = clean_input($_POST['new_status']);

    if (in_array($new_status, ['draft', 'confirmed', 'paid', 'cancelled'])) {
        db_execute("UPDATE invoices SET status = ? WHERE id = ?", [$new_status, $inv_id], "si");
        log_audit("INVOICE_STATUS_UPDATED", "Invoice #$inv_id status set to $new_status");
        $_SESSION['flash_success'] = "Invoice status changed to " . strtoupper($new_status);
        header("Location: " . BASE_URL . "/admin/invoices.php?filter=" . $filter);
        exit;
    }
}

// SQL Query
$sql = "
    SELECT i.*, u.name as customer_name, u.company_name, u.email as customer_email,
           s.subscription_number,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN users u ON i.customer_id = u.id
    JOIN subscriptions s ON i.subscription_id = s.id
";

$params = [];
$types = "";

if ($filter === 'overdue') {
    $sql .= " WHERE i.status = 'confirmed' AND i.due_date < CURDATE() ";
} elseif (in_array($filter, ['draft', 'confirmed', 'paid', 'cancelled'])) {
    $sql .= " WHERE i.status = ? ";
    $params[] = $filter;
    $types .= "s";
}

$sql .= " ORDER BY i.id DESC";

$invoices = db_fetch_all($sql, $params, $types);

$page_title = "Invoices & Receivables Ledger";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Invoices & Receivables Ledger</h2>
                    <p class="text-secondary small mb-0">Manage billing statements, transitions (Draft &rarr; Confirmed &rarr; Paid), printouts, and payment tracking (PDF Page 3 & 4).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL; ?>/admin/billing_run.php" class="btn btn-nordic-primary">
                        <i class="fa-solid fa-calendar-check me-1.5"></i> Run Billing Day
                    </a>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= BASE_URL; ?>/admin/invoices.php" class="btn btn-sm <?= ($filter === 'all') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    All Invoices (<?= count($invoices); ?>)
                </a>
                <a href="<?= BASE_URL; ?>/admin/invoices.php?filter=draft" class="btn btn-sm <?= ($filter === 'draft') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    Drafts
                </a>
                <a href="<?= BASE_URL; ?>/admin/invoices.php?filter=confirmed" class="btn btn-sm <?= ($filter === 'confirmed') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    Confirmed (Pending Payment)
                </a>
                <a href="<?= BASE_URL; ?>/admin/invoices.php?filter=paid" class="btn btn-sm <?= ($filter === 'paid') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    Paid & Settled
                </a>
                <a href="<?= BASE_URL; ?>/admin/invoices.php?filter=overdue" class="btn btn-sm <?= ($filter === 'overdue') ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Overdue Receivables
                </a>
            </div>

            <!-- Invoices Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Invoices Master</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Live search invoices..." data-table-search="#invoicesTable" style="max-width: 240px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="invoicesTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer / Company</th>
                                <th>Subscription #</th>
                                <th>Billing Date</th>
                                <th>Due Date</th>
                                <th>Grand Total</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No invoices found under this view.</td>
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
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($inv['customer_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($inv['company_name'] ?? '—'); ?></div>
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
                                        <td><?= get_status_badge($inv['status']); ?></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $inv['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Printable Invoice View">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                                <?php if ($inv['status'] === 'draft'): ?>
                                                    <form method="POST" action="<?= BASE_URL; ?>/admin/invoices.php" class="d-inline">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <input type="hidden" name="invoice_id" value="<?= $inv['id']; ?>">
                                                        <input type="hidden" name="new_status" value="confirmed">
                                                        <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Confirm Invoice">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($inv['status'] === 'confirmed'): ?>
                                                    <a href="<?= BASE_URL; ?>/admin/payments.php?invoice_id=<?= $inv['id']; ?>" class="btn btn-sm btn-success text-white py-1 px-2" title="Record Payment">
                                                        <i class="fa-solid fa-dollar-sign"></i> Pay
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
