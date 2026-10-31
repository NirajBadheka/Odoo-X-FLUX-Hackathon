<?php
/**
 * SubNexa Pro - Customer Self-Service Portal Dashboard
 * Solves Scene 1 from PDF: "...and customers want to see and manage their own subscriptions without having to email anyone."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['customer']);

$customer_id = $_SESSION['user_id'];

// Customer metrics
$active_subs_count = db_fetch_one("SELECT COUNT(*) as cnt FROM subscriptions WHERE customer_id = ? AND status = 'active'", [$customer_id], "i")['cnt'] ?? 0;
$monthly_spend = db_fetch_one("SELECT COALESCE(SUM(total_amount), 0) as total FROM subscriptions WHERE customer_id = ? AND status = 'active'", [$customer_id], "i")['total'] ?? 0;

$next_billing = db_fetch_one("SELECT next_billing_date FROM subscriptions WHERE customer_id = ? AND status = 'active' AND next_billing_date >= CURDATE() ORDER BY next_billing_date ASC LIMIT 1", [$customer_id], "i");
$next_billing_date = $next_billing ? $next_billing['next_billing_date'] : null;

$amount_due_total = db_fetch_one("SELECT COALESCE(SUM(amount_due), 0) as total FROM invoices WHERE customer_id = ? AND status = 'confirmed'", [$customer_id], "i")['total'] ?? 0;
$unpaid_invoices_count = db_fetch_one("SELECT COUNT(*) as cnt FROM invoices WHERE customer_id = ? AND status = 'confirmed'", [$customer_id], "i")['cnt'] ?? 0;

// Customer Active Subscriptions
$my_subs = db_fetch_all("
    SELECT s.*, p.name as plan_name, p.billing_frequency, p.can_pause
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.customer_id = ?
    ORDER BY s.id DESC
", [$customer_id], "i");

// Unpaid Invoices
$my_unpaid_invoices = db_fetch_all("
    SELECT i.*, s.subscription_number, DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN subscriptions s ON i.subscription_id = s.id
    WHERE i.customer_id = ? AND i.status = 'confirmed'
    ORDER BY i.due_date ASC
", [$customer_id], "i");

$page_title = "Customer Portal Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Subscriber Self-Service Portal</h2>
                    <p class="text-secondary small mb-0">Welcome back, <strong><?= htmlspecialchars($_SESSION['user_name']); ?></strong>! Manage your recurring software subscriptions and invoices online.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL; ?>/customer/my_invoices.php" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-receipt me-1"></i> My Invoices
                    </a>
                </div>
            </div>

            <!-- Unpaid Invoices Banner if any -->
            <?php if ($unpaid_invoices_count > 0): ?>
                <div class="alert alert-warning border-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 rounded-3 mb-4 shadow-sm" role="alert">
                    <div class="d-flex align-items-center gap-3 mb-2 mb-md-0">
                        <i class="fa-solid fa-bell text-warning fs-4"></i>
                        <div>
                            <div class="fw-bold text-dark">You have <?= $unpaid_invoices_count; ?> outstanding invoice(s) awaiting payment</div>
                            <div class="small text-secondary">Total balance due: <strong><?= format_currency($amount_due_total); ?></strong>. Settle online with instant receipt.</div>
                        </div>
                    </div>
                    <div>
                        <a href="<?= BASE_URL; ?>/customer/pay_invoice.php?id=<?= $my_unpaid_invoices[0]['id']; ?>" class="btn btn-sm btn-success text-white fw-semibold">
                            <i class="fa-solid fa-credit-card me-1"></i> Pay Now (<?= format_currency($my_unpaid_invoices[0]['amount_due']); ?>)
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Customer KPI Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Active Subscriptions</div>
                        <div class="stat-value text-primary"><?= (int)$active_subs_count; ?></div>
                        <div class="stat-badge bg-primary-subtle text-primary">Live SaaS Licenses</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Current Billing Rate</div>
                        <div class="stat-value text-dark"><?= format_currency($monthly_spend); ?></div>
                        <div class="stat-badge bg-light text-muted border">Recurring Value</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Next Scheduled Billing</div>
                        <div class="stat-value text-success" style="font-size: 1.35rem;"><?= format_date($next_billing_date); ?></div>
                        <div class="stat-badge bg-success-subtle text-success">Automated Renewal</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Outstanding Invoices</div>
                        <div class="stat-value text-danger"><?= format_currency($amount_due_total); ?></div>
                        <div class="stat-badge bg-danger-subtle text-danger"><?= $unpaid_invoices_count; ?> Due</div>
                    </div>
                </div>
            </div>

            <!-- Active Subscriptions with 1-Click Add-on Management -->
            <div class="card-nordic overflow-hidden mb-4">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">My Subscriptions & Contracts</h5>
                        <div class="text-muted small">Manage plan features and toggle add-ons without emailing support (PDF Page 1)</div>
                    </div>
                    <a href="<?= BASE_URL; ?>/pricing.php" class="btn btn-sm btn-outline-primary">Browse New Plans</a>
                </div>

                <div class="table-responsive">
                    <table class="table-nordic">
                        <thead>
                            <tr>
                                <th>Subscription #</th>
                                <th>Plan Details</th>
                                <th>Frequency</th>
                                <th>Total Cycle Value</th>
                                <th>Next Billing</th>
                                <th>Status</th>
                                <th class="text-end">Self-Service Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($my_subs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">You do not have any active subscriptions yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($my_subs as $s): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($s['subscription_number']); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['plan_name']); ?></div>
                                            <div class="text-muted small">Terms: <?= htmlspecialchars($s['payment_terms']); ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border text-uppercase"><?= htmlspecialchars($s['billing_frequency']); ?></span></td>
                                        <td class="fw-bold text-dark fs-6"><?= format_currency($s['total_amount']); ?></td>
                                        <td>
                                            <span class="small text-secondary">
                                                <i class="fa-solid fa-calendar me-1 text-primary"></i> <?= format_date($s['next_billing_date']); ?>
                                            </span>
                                        </td>
                                        <td><?= get_status_badge($s['status']); ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL; ?>/customer/subscription_details.php?id=<?= $s['id']; ?>" class="btn btn-sm btn-primary py-1 px-2.5 fw-semibold shadow-sm">
                                                <i class="fa-solid fa-toggle-on me-1"></i> Switch Add-Ons
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Invoices Table -->
            <?php if (!empty($my_unpaid_invoices)): ?>
                <div class="card-nordic overflow-hidden">
                    <div class="p-3 border-bottom bg-warning-subtle d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-receipt text-warning me-2"></i> Invoices Awaiting Settlement</h5>
                        <span class="badge bg-warning text-dark"><?= count($my_unpaid_invoices); ?> Invoices Due</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table-nordic">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Subscription</th>
                                    <th>Billing Date</th>
                                    <th>Due Date</th>
                                    <th>Amount Due</th>
                                    <th class="text-end">Online Settlement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_unpaid_invoices as $uinv): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($uinv['invoice_number']); ?></td>
                                        <td class="font-monospace small"><?= htmlspecialchars($uinv['subscription_number']); ?></td>
                                        <td class="small"><?= format_date($uinv['billing_date']); ?></td>
                                        <td class="small fw-semibold <?= ($uinv['days_overdue'] > 0) ? 'text-danger' : ''; ?>">
                                            <?= format_date($uinv['due_date']); ?>
                                            <?php if ($uinv['days_overdue'] > 0): ?>
                                                <span class="badge bg-danger text-white ms-1"><?= (int)$uinv['days_overdue']; ?>d late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark fs-6"><?= format_currency($uinv['amount_due']); ?></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $uinv['id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2.5">
                                                    <i class="fa-solid fa-print me-1"></i> Print
                                                </a>
                                                <a href="<?= BASE_URL; ?>/customer/pay_invoice.php?id=<?= $uinv['id']; ?>" class="btn btn-sm btn-success text-white py-1 px-2.5 fw-semibold shadow-sm">
                                                    <i class="fa-solid fa-credit-card me-1"></i> Pay Online
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
