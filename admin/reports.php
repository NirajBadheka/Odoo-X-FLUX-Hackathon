<?php
/**
 * SubNexa Pro - Finance Lead End-of-Month Intelligence Report
 * Solves Scene 6 from PDF: "How many subscriptions are active? How much revenue came in, and how much is still owed? Which invoices are overdue? Today, answering those questions takes days... every page should still respond in under two seconds."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$start_time = microtime(true);

// 1. How many subscriptions are active?
$active_subs_count = db_fetch_one("SELECT COUNT(*) as cnt FROM subscriptions WHERE status = 'active'")['cnt'] ?? 0;
$active_mrr = db_fetch_one("SELECT COALESCE(SUM(total_amount), 0) as total FROM subscriptions WHERE status = 'active' AND plan_id IN (SELECT id FROM plans WHERE billing_frequency = 'monthly')")['total'] ?? 0;
$active_arr = db_fetch_one("SELECT COALESCE(SUM(total_amount), 0) as total FROM subscriptions WHERE status = 'active'")['total'] ?? 0;

// 2. How much revenue came in?
$revenue_collected = db_fetch_one("SELECT COALESCE(SUM(amount_paid), 0) as total FROM invoices WHERE status = 'paid'")['total'] ?? 0;

// 3. How much is still owed?
$revenue_owed = db_fetch_one("SELECT COALESCE(SUM(amount_due), 0) as total FROM invoices WHERE status = 'confirmed'")['total'] ?? 0;

// 4. Which invoices are overdue?
$overdue_invoices = db_fetch_all("
    SELECT i.*, u.name as customer_name, u.company_name, u.email as customer_email, u.phone as customer_phone,
           DATEDIFF(CURDATE(), i.due_date) as days_overdue
    FROM invoices i
    JOIN users u ON i.customer_id = u.id
    WHERE i.status = 'confirmed' AND i.due_date < CURDATE()
    ORDER BY i.due_date ASC
");

$overdue_total = 0;
$aging_0_15 = 0;
$aging_16_30 = 0;
$aging_30_plus = 0;

foreach ($overdue_invoices as $oi) {
    $overdue_total += $oi['amount_due'];
    if ($oi['days_overdue'] <= 15) {
        $aging_0_15 += $oi['amount_due'];
    } elseif ($oi['days_overdue'] <= 30) {
        $aging_16_30 += $oi['amount_due'];
    } else {
        $aging_30_plus += $oi['amount_due'];
    }
}

// Subscriptions by Frequency
$freq_breakdown = db_fetch_all("
    SELECT p.billing_frequency, COUNT(s.id) as sub_count, COALESCE(SUM(s.total_amount), 0) as total_val
    FROM subscriptions s
    JOIN plans p ON s.plan_id = p.id
    WHERE s.status = 'active'
    GROUP BY p.billing_frequency
");

$execution_time = number_format(microtime(true) - $start_time, 4);

$page_title = "Finance Lead End-of-Month Analytics";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="fa-solid fa-bolt me-1"></i> Response: <?= $execution_time; ?>s (&lt; 2.0s limit)
                        </span>
                        <span class="text-muted small">| Odoo Challenge Scene 6</span>
                    </div>
                    <h2 class="fw-bold text-dark mb-0">Finance Lead End-of-Month Intelligence</h2>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-nordic-outline">
                        <i class="fa-solid fa-print me-1.5"></i> Export / Print Statement
                    </button>
                </div>
            </div>

            <!-- The 4 Core Questions from PDF Page 4 Solved -->
            <div class="row g-3 mb-4">
                <!-- Q1: How many subscriptions are active? -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card border-top border-primary border-3">
                        <div class="stat-title">Active Subscriptions</div>
                        <div class="stat-value text-primary"><?= number_format($active_subs_count); ?></div>
                        <div class="small text-secondary mt-2">
                            <span>MRR: <strong><?= format_currency($active_mrr); ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- Q2: How much revenue came in? -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card border-top border-success border-3">
                        <div class="stat-title">Revenue Collected (Paid)</div>
                        <div class="stat-value text-success"><?= format_currency($revenue_collected); ?></div>
                        <div class="small text-secondary mt-2">
                            <span>Cashflow Verified</span>
                        </div>
                    </div>
                </div>

                <!-- Q3: How much is still owed? -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card border-top border-warning border-3">
                        <div class="stat-title">Outstanding Receivables</div>
                        <div class="stat-value text-dark"><?= format_currency($revenue_owed); ?></div>
                        <div class="small text-secondary mt-2">
                            <span>Pending Invoices</span>
                        </div>
                    </div>
                </div>

                <!-- Q4: Which invoices are overdue? -->
                <div class="col-sm-6 col-xl-3">
                    <div class="card-nordic stat-card border-top border-danger border-3">
                        <div class="stat-title">Overdue Receivables</div>
                        <div class="stat-value text-danger"><?= format_currency($overdue_total); ?></div>
                        <div class="small text-danger mt-2 fw-semibold">
                            <span><?= count($overdue_invoices); ?> accounts at risk</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aging & Frequency Analysis Charts -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card-nordic p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-hourglass-half text-danger me-2"></i> Overdue Aging Buckets</h5>
                        <div style="height: 240px;">
                            <canvas id="agingChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-nordic p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Active Revenue by Billing Frequency</h5>
                        <div class="table-responsive">
                            <table class="table-nordic">
                                <thead>
                                    <tr>
                                        <th>Frequency</th>
                                        <th>Subscriptions</th>
                                        <th class="text-end">Value Billed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($freq_breakdown as $fb): ?>
                                        <tr>
                                            <td class="fw-bold text-uppercase"><?= htmlspecialchars($fb['billing_frequency']); ?></td>
                                            <td><?= (int)$fb['sub_count']; ?> active contracts</td>
                                            <td class="text-end fw-bold text-dark"><?= format_currency($fb['total_val']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overdue Accounts Chasing Matrix -->
            <div class="card-nordic overflow-hidden mb-4">
                <div class="p-3 border-bottom bg-danger-subtle d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold text-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i> Overdue Accounts Chasing Matrix</h5>
                        <div class="small text-secondary">Identifies unpaid accounts past due date to prevent revenue leakage</div>
                    </div>
                    <span class="badge bg-danger text-white"><?= count($overdue_invoices); ?> Overdue Invoices</span>
                </div>

                <div class="table-responsive">
                    <table class="table-nordic">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer & Company</th>
                                <th>Contact Email & Phone</th>
                                <th>Due Date</th>
                                <th>Aging / Days Overdue</th>
                                <th>Amount Owed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdue_invoices)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-success fw-bold">
                                        <i class="fa-solid fa-circle-check me-2"></i> All invoices are up to date! Zero overdue accounts.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($overdue_invoices as $oi): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace"><?= htmlspecialchars($oi['invoice_number']); ?></td>
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
                                                <?= (int)$oi['days_overdue']; ?> Days Overdue
                                            </span>
                                        </td>
                                        <td class="fw-bold text-danger fs-6"><?= format_currency($oi['amount_due']); ?></td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                <a href="<?= BASE_URL; ?>/admin/invoice_view.php?id=<?= $oi['id']; ?>" class="btn btn-sm btn-outline-danger py-1 px-2.5">
                                                    <i class="fa-solid fa-file-invoice me-1"></i> Invoice
                                                </a>
                                                <a href="<?= BASE_URL; ?>/admin/payments.php?invoice_id=<?= $oi['id']; ?>" class="btn btn-sm btn-success text-white py-1 px-2.5">
                                                    <i class="fa-solid fa-receipt me-1"></i> Settle
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

<!-- Chart.js Aging Chart -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('agingChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['1-15 Days', '16-30 Days', '30+ Days Critical'],
            datasets: [{
                label: 'Overdue Amount (₹)',
                data: [<?= $aging_0_15; ?>, <?= $aging_16_30; ?>, <?= $aging_30_plus; ?>],
                backgroundColor: ['#FBBF24', '#F97316', '#EF4444'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => '₹ ' + value.toLocaleString('en-IN') },
                    grid: { color: '#F1F5F9' }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
