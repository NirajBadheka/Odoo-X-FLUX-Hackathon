<?php
/**
 * SubNexa Pro - Quotations Management (Sales Team)
 */
require_once __DIR__ . '/../config/config.php';
require_role(['staff', 'admin']);

$quotes = db_fetch_all("
    SELECT s.*, u.name as customer_name, u.company_name, u.email as customer_email,
           p.name as plan_name, p.billing_frequency,
           st.name as staff_name
    FROM subscriptions s
    JOIN users u ON s.customer_id = u.id
    JOIN plans p ON s.plan_id = p.id
    LEFT JOIN users st ON s.created_by_staff_id = st.id
    WHERE s.status IN ('draft', 'quotation')
    ORDER BY s.id DESC
");

$page_title = "Quotations & Proposals";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Sales Quotations & Proposals</h2>
                    <p class="text-secondary small mb-0">Active draft offers and customer quotations prepared from pre-set templates (PDF Page 3).</p>
                </div>
                <div>
                    <a href="<?= BASE_URL; ?>/staff/quotation_create.php" class="btn btn-nordic-primary">
                        <i class="fa-solid fa-plus me-1.5"></i> Create New Quotation
                    </a>
                </div>
            </div>

            <!-- Quotations Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Active Quotations (<?= count($quotes); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search quotes..." data-table-search="#quotesTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="quotesTable">
                        <thead>
                            <tr>
                                <th>Quote #</th>
                                <th>Customer / Company</th>
                                <th>Plan Offering</th>
                                <th>Created On</th>
                                <th>Valid Until</th>
                                <th>Quotation Total</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quotes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No pending quotations found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quotes as $q): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL; ?>/admin/subscription_view.php?id=<?= $q['id']; ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                <?= htmlspecialchars($q['subscription_number']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($q['customer_name']); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($q['company_name'] ?? '—'); ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($q['plan_name']); ?></td>
                                        <td class="small text-secondary"><?= format_date($q['start_date']); ?></td>
                                        <td class="small text-danger fw-semibold"><?= format_date($q['expiry_date']); ?></td>
                                        <td class="fw-bold text-dark fs-6"><?= format_currency($q['total_amount']); ?></td>
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
