<?php
/**
 * SubNexa Pro - Staff Operational Dashboard
 * Day-to-day sales, quotation pipeline, and billing operations
 */
require_once __DIR__ . '/../config/config.php';
require_role(['staff', 'admin']);

$staff_id = $_SESSION['user_id'];

// Metrics
$pending_quotes = db_fetch_one("SELECT COUNT(*) as cnt FROM subscriptions WHERE status IN ('draft', 'quotation')")['cnt'] ?? 0;
$active_subs = db_fetch_one("SELECT COUNT(*) as cnt FROM subscriptions WHERE status = 'active'")['cnt'] ?? 0;
$invoices_due = db_fetch_one("SELECT COUNT(*) as cnt FROM invoices WHERE status = 'confirmed'")['cnt'] ?? 0;
$overdue_count = db_fetch_one("SELECT COUNT(*) as cnt FROM invoices WHERE status = 'confirmed' AND due_date < CURDATE()")['cnt'] ?? 0;

// Recent Quotations
$quotes = db_fetch_all("
    SELECT s.*, u.name as customer_name, u.company_name, p.name as plan_name 
    FROM subscriptions s
    JOIN users u ON s.customer_id = u.id
    JOIN plans p ON s.plan_id = p.id
    WHERE s.status IN ('draft', 'quotation')
    ORDER BY s.id DESC LIMIT 5
");

$page_title = "Staff Operational Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Sales & Billing Operations Hub</h2>
                    <p class="text-secondary small mb-0">Prepare quotations from templates, manage customer subscriptions, and execute billing runs (PDF Page 2 & 3).</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL; ?>/staff/quotation_create.php" class="btn btn-nordic-primary">
                        <i class="fa-solid fa-plus me-1.5"></i> Create New Quote
                    </a>
                    <a href="<?= BASE_URL; ?>/staff/billing_run.php" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-calendar-check me-1.5"></i> Billing Day Run
                    </a>
                    <a href="<?= BASE_URL; ?>/staff/payments.php" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-receipt me-1.5"></i> Record Payment
                    </a>
                </div>
            </div>

            <!-- Operational Metrics Grid -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Pending Quotations</div>
                        <div class="stat-value text-primary"><?= (int)$pending_quotes; ?></div>
                        <div class="stat-badge bg-primary-subtle text-primary">In Sales Pipeline</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Active Subscriptions</div>
                        <div class="stat-value text-success"><?= (int)$active_subs; ?></div>
                        <div class="stat-badge bg-success-subtle text-success">Live Contracts</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Invoices Awaiting Payment</div>
                        <div class="stat-value text-dark"><?= (int)$invoices_due; ?></div>
                        <div class="stat-badge bg-warning-subtle text-dark">Confirmed Invoices</div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card">
                        <div class="stat-title">Overdue Invoices</div>
                        <div class="stat-value text-danger"><?= (int)$overdue_count; ?></div>
                        <div class="stat-badge bg-danger-subtle text-danger">Chasing Required</div>
                    </div>
                </div>
            </div>

            <!-- Active Quotations Table -->
            <div class="card-nordic overflow-hidden mb-4">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Active Quotations & Drafts</h5>
                        <div class="text-muted small">Move proposals from Draft &rarr; Quotation &rarr; Confirmed</div>
                    </div>
                    <a href="<?= BASE_URL; ?>/staff/quotations.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="table-nordic">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Customer & Company</th>
                                <th>Plan Offering</th>
                                <th>Quotation Total</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotes)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No pending quotes. Click "Create New Quote" to begin.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotes as $q): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($q['subscription_number']); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($q['customer_name']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($q['company_name'] ?? '—'); ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($q['plan_name']); ?></td>
                                        <td class="fw-bold text-dark"><?= format_currency($q['total_amount']); ?></td>
                                        <td><?= get_status_badge($q['status']); ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $q['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2.5">
                                                <i class="fa-solid fa-arrow-right me-1"></i> Open Pipeline
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
