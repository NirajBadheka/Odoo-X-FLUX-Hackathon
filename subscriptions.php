<?php
/**
 * SubNexa Pro - Subscriptions Master Manager
 * Solves Scene 3 from PDF: "Each subscription gets its own number, a customer, a plan, a start date, an expiry date and payment terms... moves from Draft to Quotation to Confirmed to Active and finally to Closed"
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin', 'staff']);

$filter_status = clean_input($_GET['status'] ?? 'all');

$sql = "
    SELECT s.*, u.name as customer_name, u.company_name, u.email as customer_email, 
           p.name as plan_name, p.billing_frequency,
           st.name as staff_name
    FROM subscriptions s
    JOIN users u ON s.customer_id = u.id
    JOIN plans p ON s.plan_id = p.id
    LEFT JOIN users st ON s.created_by_staff_id = st.id
";

$params = [];
$types = "";

if ($filter_status !== 'all' && in_array($filter_status, ['draft', 'quotation', 'confirmed', 'active', 'paused', 'closed'])) {
    $sql .= " WHERE s.status = ? ";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY s.id DESC";

$subscriptions = db_fetch_all($sql, $params, $types);

$page_title = "Subscription Lifecycle Management";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Subscriptions & Contracts Lifecycle</h2>
                    <p class="text-secondary small mb-0">Track and manage every subscription through the complete 5-stage pipeline (PDF Page 3).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL; ?>/staff/quotation_create.php" class="btn btn-nordic-primary">
                        <i class="fa-solid fa-plus me-1.5"></i> New Quotation
                    </a>
                </div>
            </div>

            <!-- Lifecycle Pipeline Status Filter Tabs -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php" class="btn btn-sm <?= ($filter_status === 'all') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    All (<?= count($subscriptions); ?>)
                </a>
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php?status=draft" class="btn btn-sm <?= ($filter_status === 'draft') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    1. Draft
                </a>
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php?status=quotation" class="btn btn-sm <?= ($filter_status === 'quotation') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    2. Quotation
                </a>
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php?status=confirmed" class="btn btn-sm <?= ($filter_status === 'confirmed') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    3. Confirmed
                </a>
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php?status=active" class="btn btn-sm <?= ($filter_status === 'active') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    4. Active
                </a>
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php?status=closed" class="btn btn-sm <?= ($filter_status === 'closed') ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    5. Closed
                </a>
            </div>

            <!-- Subscriptions Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Contracts Ledger</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Live filter subscriptions..." data-table-search="#subsTable" style="max-width: 240px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="subsTable">
                        <thead>
                            <tr>
                                <th>Subscription #</th>
                                <th>Customer / Company</th>
                                <th>Plan & Terms</th>
                                <th>Start Date</th>
                                <th>Next Billing</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No subscriptions found under this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptions as $s): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $s['id']; ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= htmlspecialchars($s['subscription_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($s['customer_name']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= htmlspecialchars($s['company_name'] ?? '—'); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($s['plan_name']); ?></div>
                                            <span class="badge bg-light text-muted border text-uppercase" style="font-size: 0.68rem;"><?= htmlspecialchars($s['payment_terms']); ?></span>
                                        </td>
                                        <td class="text-secondary small"><?= format_date($s['start_date']); ?></td>
                                        <td class="text-secondary small">
                                            <?= $s['next_billing_date'] ? '<i class="fa-solid fa-calendar me-1 text-primary"></i> ' . format_date($s['next_billing_date']) : '—'; ?>
                                        </td>
                                        <td class="fw-bold text-dark fs-6"><?= format_currency($s['total_amount']); ?></td>
                                        <td><?= get_status_badge($s['status']); ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $s['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2.5">
                                                <i class="fa-solid fa-eye me-1"></i> View / Pipeline
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
