<?php
/**
 * SubNexa Pro - Customer Subscriptions List
 */
require_once __DIR__ . '/../config/config.php';
require_role(['customer']);

$customer_id = $_SESSION['user_id'];

$subscriptions = db_fetch_all("
    SELECT s.*, p.name as plan_name, p.billing_frequency, p.can_pause
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.customer_id = ?
    ORDER BY s.id DESC
", [$customer_id], "i");

$page_title = "My Subscriptions — SubNexa Pro";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">My Software Subscriptions</h2>
                    <p class="text-secondary small mb-0">Manage your active plans, switch add-on features on and off, and track renewal dates (PDF Page 1).</p>
                </div>
            </div>

            <!-- Subscriptions Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">My Contracts (<?= count($subscriptions); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Filter my contracts..." data-table-search="#mySubsTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="mySubsTable">
                        <thead>
                            <tr>
                                <th>Subscription #</th>
                                <th>Plan Details</th>
                                <th>Billing Cycle</th>
                                <th>Start Date</th>
                                <th>Next Renewal</th>
                                <th>Total Cycle Cost</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">You have no active subscriptions.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptions as $s): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($s['subscription_number']); ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['plan_name']); ?></div>
                                            <div class="text-muted small">Terms: <?= htmlspecialchars($s['payment_terms']); ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border text-uppercase"><?= htmlspecialchars($s['billing_frequency']); ?></span></td>
                                        <td class="small text-secondary"><?= format_date($s['start_date']); ?></td>
                                        <td class="small text-secondary">
                                            <i class="fa-solid fa-calendar me-1 text-primary"></i> <?= format_date($s['next_billing_date']); ?>
                                        </td>
                                        <td class="fw-bold text-dark fs-6"><?= format_currency($s['total_amount']); ?></td>
                                        <td><?= get_status_badge($s['status']); ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL; ?>/customer/subscription_details.php?id=<?= $s['id']; ?>" class="btn btn-sm btn-primary py-1 px-2.5 fw-semibold shadow-sm">
                                                <i class="fa-solid fa-gear me-1"></i> Manage & Add-Ons
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
