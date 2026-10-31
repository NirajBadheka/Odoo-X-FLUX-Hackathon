<?php
/**
 * SubNexa Pro - Staff Overdue Accounts Chaser
 * Solves Scene 5 from PDF: "...so overdue ones can be chased before they turn into lost revenue."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['staff', 'admin']);

// Handle Simulated Reminder POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    $inv_id = (int)$_POST['invoice_id'];
    $cust_email = clean_input($_POST['customer_email']);
    log_audit("CHASE_REMINDER_SENT", "Staff dispatched overdue invoice reminder to $cust_email for invoice ID #$inv_id");
    $_SESSION['flash_success'] = "Payment reminder dispatched to " . htmlspecialchars($cust_email) . "!";
    header("Location: " . BASE_URL . "/staff/overdue.php");
    exit;
}

$overdue_invoices = db_fetch_all("
    SELECT i.*, u.name as customer_name, u.company_name, u.email as customer_email, u.phone as customer_phone,
           s.subscription_number,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN users u ON i.customer_id = u.id
    JOIN subscriptions s ON i.subscription_id = s.id
    WHERE i.status = 'confirmed' AND i.due_date < CURDATE()
    ORDER BY i.due_date ASC
");

$page_title = "Overdue Accounts Chaser";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Overdue Receivables Chasing Board</h2>
                    <p class="text-secondary small mb-0">Follow up on delinquent accounts to prevent lost subscription revenue (PDF Page 4).</p>
                </div>
            </div>

            <!-- Overdue Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom bg-danger-subtle d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-danger small text-uppercase tracking-wider">
                        <i class="fa-solid fa-triangle-exclamation me-1.5"></i> <?= count($overdue_invoices); ?> Overdue Invoices Requiring Follow-Up
                    </span>
                    <input type="text" class="form-control form-control-sm" placeholder="Filter overdue..." data-table-search="#overdueTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="overdueTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer & Company</th>
                                <th>Contact Information</th>
                                <th>Due Date</th>
                                <th>Overdue Days</th>
                                <th>Amount Owed</th>
                                <th class="text-end">Chase Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdue_invoices)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-success fw-bold">
                                        <i class="fa-solid fa-circle-check me-2"></i> All customer accounts are currently in good standing!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($overdue_invoices as $oi): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $oi['id']; ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= htmlspecialchars($oi['invoice_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($oi['customer_name']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($oi['company_name'] ?? '—'); ?></div>
                                        </td>
                                        <td class="small">
                                            <div><i class="fa-solid fa-envelope text-muted me-1"></i> <?= htmlspecialchars($oi['customer_email']); ?></div>
                                            <div><i class="fa-solid fa-phone text-muted me-1"></i> <?= htmlspecialchars($oi['customer_phone'] ?? '—'); ?></div>
                                        </td>
                                        <td class="text-danger fw-semibold small"><?= format_date($oi['due_date']); ?></td>
                                        <td>
                                            <span class="badge bg-danger text-white fw-bold">
                                                <?= (int)$oi['days_overdue']; ?> Days Late
                                            </span>
                                        </td>
                                        <td class="fw-bold text-danger fs-6"><?= format_currency($oi['amount_due']); ?></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="<?= BASE_URL; ?>/staff/overdue.php" class="d-inline">
                                                    <input type="hidden" name="send_reminder" value="1">
                                                    <input type="hidden" name="invoice_id" value="<?= $oi['id']; ?>">
                                                    <input type="hidden" name="customer_email" value="<?= htmlspecialchars($oi['customer_email']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning py-1 px-2.5 fw-semibold" title="Send Email Reminder">
                                                        <i class="fa-solid fa-paper-plane me-1"></i> Remind
                                                    </button>
                                                </form>
                                                <a href="<?= BASE_URL; ?>/admin/payments.php?invoice_id=<?= $oi['id']; ?>" class="btn btn-sm btn-success text-white py-1 px-2.5 fw-semibold" title="Record Settlement">
                                                    <i class="fa-solid fa-dollar-sign me-1"></i> Record Pay
                                                </a>
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
