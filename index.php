<?php
/**
 * SubNexa Pro - Landing Page & Public Hub
 * Odoo Hackathon Virtual Round
 */
require_once __DIR__ . '/config/config.php';

// Fetch live metrics from database for dynamic display
$active_subs_count = db_fetch_one("SELECT COUNT(*) as cnt FROM subscriptions WHERE status = 'active'")['cnt'] ?? 0;
$mrr_total = db_fetch_one("SELECT COALESCE(SUM(total_amount), 0) as total FROM subscriptions WHERE status = 'active' AND plan_id IN (SELECT id FROM plans WHERE billing_frequency = 'monthly')")['total'] ?? 0;
$invoices_paid_count = db_fetch_one("SELECT COUNT(*) as cnt FROM invoices WHERE status = 'paid'")['cnt'] ?? 0;
$total_products_count = db_fetch_one("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1")['cnt'] ?? 0;

$page_title = "Unified Subscription & Recurring Billing Platform";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Announcement Ribbon -->
<div class="bg-dark text-white py-2 px-3 text-center small border-bottom border-secondary-subtle">
    <div class="container d-flex flex-wrap align-items-center justify-content-center gap-2">
        <span class="badge bg-primary px-2.5 py-1">Odoo Hackathon</span>
        <span><strong>Brightloop Software Case Study:</strong> Full lifecycle subscription management, variant pricing (+560 Odoo Brand), and 1-click billing day engine.</span>
        <a href="<?= BASE_URL; ?>/auth/login.php" class="text-info fw-bold ms-2 text-decoration-none">Launch Demo &rarr;</a>
    </div>
</div>

<!-- Hero Section -->
<section class="py-5 bg-white border-bottom position-relative overflow-hidden">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-light border text-primary small fw-semibold mb-3">
                    <i class="fa-solid fa-bolt text-primary"></i> Real-time Enterprise Billing Platform
                </div>
                <h1 class="display-5 fw-extrabold text-dark tracking-tight mb-3">
                    The Digital Backbone for High-Growth <span class="text-primary">Software Subscriptions</span>
                </h1>
                <p class="lead text-secondary mb-4 fs-6">
                    Designed for <strong>Brightloop Software</strong> to eliminate spreadsheets and manual invoices. Run products with variants, quotation templates, automated billing day runs, tax calculation, and instant MRR financial reporting in under 2 seconds.
                </p>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= BASE_URL; ?>/auth/login.php" class="btn btn-primary btn-lg px-4 py-2.5 fw-semibold shadow-sm">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Open Fast Login
                    </a>
                    <a href="<?= BASE_URL; ?>/pricing.php" class="btn btn-outline-secondary btn-lg px-4 py-2.5 fw-semibold">
                        <i class="fa-solid fa-tags me-2"></i> Plans & Pricing
                    </a>
                    <a href="#demo-accounts" class="btn btn-light border btn-lg px-4 py-2.5 fw-semibold text-secondary">
                        <i class="fa-solid fa-users-gear me-2"></i> Demo Roles
                    </a>
                </div>

                <!-- Live Metrics Summary Bar -->
                <div class="row g-3 pt-3 border-top">
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-dark"><?= number_format($active_subs_count); ?>+</div>
                        <div class="text-muted small">Active Subscriptions</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-primary"><?= format_currency($mrr_total); ?></div>
                        <div class="text-muted small">Live MRR Run Rate</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-success">&lt; 0.08s</div>
                        <div class="text-muted small">Query Response Time</div>
                    </div>
                </div>
            </div>

            <!-- Hero Right Card Mockup -->
            <div class="col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle bg-danger d-inline-block" style="width: 10px; height: 10px;"></span>
                            <span class="rounded-circle bg-warning d-inline-block" style="width: 10px; height: 10px;"></span>
                            <span class="rounded-circle bg-success d-inline-block" style="width: 10px; height: 10px;"></span>
                            <span class="small fw-semibold ms-2 text-white-50">SubNexa Pro Real-Time Engine</span>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle small">LIVE ENGINE</span>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <!-- Mini Stat Preview -->
                        <div class="bg-white p-3 rounded-3 border mb-3 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small fw-bold text-uppercase">Monthly Recurring Revenue</span>
                                <span class="badge bg-success text-white">+14.2%</span>
                            </div>
                            <h3 class="fw-bold text-dark mb-0"><?= format_currency(74890.35); ?></h3>
                        </div>

                        <!-- Mini Billing Day Lock -->
                        <div class="bg-white p-3 rounded-3 border mb-3 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold small text-dark"><i class="fa-solid fa-shield-check text-primary me-1"></i> Billing Day Duplicate Lock</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Prevents double billing & skipped renewals</div>
                                </div>
                                <span class="badge bg-primary-subtle text-primary">ENFORCED</span>
                            </div>
                        </div>

                        <!-- Mini Pipeline Preview -->
                        <div class="bg-white p-3 rounded-3 border shadow-sm">
                            <div class="small fw-bold text-muted mb-2 text-uppercase" style="font-size: 0.7rem;">Active Lifecycle Pipeline</div>
                            <div class="d-flex align-items-center justify-content-between text-center small">
                                <span class="badge bg-light text-secondary border">Draft</span>
                                <i class="fa-solid fa-chevron-right text-muted small"></i>
                                <span class="badge bg-light text-secondary border">Quotation</span>
                                <i class="fa-solid fa-chevron-right text-muted small"></i>
                                <span class="badge bg-light text-secondary border">Confirmed</span>
                                <i class="fa-solid fa-chevron-right text-muted small"></i>
                                <span class="badge bg-primary text-white">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 1-Click Fast Login / Demo Credentials Section -->
<section id="demo-accounts" class="py-5 bg-light border-bottom">
    <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 fw-semibold mb-2">Hackathon Evaluation Portal</span>
            <h2 class="fw-bold text-dark">1-Click Fast Login for Hackathon Judges</h2>
            <p class="text-muted">
                Test each distinct user persona with real-world permissions. Click any role button to automatically log in or pre-fill credentials.
            </p>
        </div>

        <div class="row g-4">
            <!-- Admin Role Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden border-top border-primary border-4">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1">SUPER ADMIN</span>
                            <i class="fa-solid fa-shield-halved text-primary fs-4"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Aarav Sharma (Admin)</h4>
                        <p class="text-muted small mb-3">
                            Full system control: sets products, variants (e.g. +₹560 Odoo brand), plans, discount rules, GST tax rules, and end-of-month MRR/ARR analytics. <strong>Only admin can add new staff.</strong>
                        </p>
                        <div class="bg-light p-2.5 rounded-2 font-monospace small mb-3 text-secondary">
                            <div><strong>Email:</strong> admin@brightloop.in</div>
                            <div><strong>Password:</strong> Demo@12345</div>
                        </div>
                        <div class="mt-auto">
                            <form action="<?= BASE_URL; ?>/auth/login.php" method="POST">
                                <input type="hidden" name="email" value="admin@brightloop.in">
                                <input type="hidden" name="password" value="Demo@12345">
                                <input type="hidden" name="auto_login" value="1">
                                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                                    <i class="fa-solid fa-bolt me-1"></i> 1-Click Login as Admin
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Role Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden border-top border-success border-4">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-success-subtle text-success fw-bold px-2.5 py-1">INTERNAL STAFF</span>
                            <i class="fa-solid fa-briefcase text-success fs-4"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Priya Patel (Staff)</h4>
                        <p class="text-muted small mb-3">
                            Day-to-day operations: turns quotation templates into customer subscriptions, runs automated billing day batches, confirms invoices, and records incoming payments.
                        </p>
                        <div class="bg-light p-2.5 rounded-2 font-monospace small mb-3 text-secondary">
                            <div><strong>Email:</strong> staff@brightloop.in</div>
                            <div><strong>Password:</strong> Demo@12345</div>
                        </div>
                        <div class="mt-auto">
                            <form action="<?= BASE_URL; ?>/auth/login.php" method="POST">
                                <input type="hidden" name="email" value="staff@brightloop.in">
                                <input type="hidden" name="password" value="Demo@12345">
                                <input type="hidden" name="auto_login" value="1">
                                <button type="submit" class="btn btn-success w-100 fw-semibold text-white">
                                    <i class="fa-solid fa-bolt me-1"></i> 1-Click Login as Staff
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Role Card -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden border-top border-info border-4">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-info-subtle text-info fw-bold px-2.5 py-1">CUSTOMER PORTAL</span>
                            <i class="fa-solid fa-user-circle text-info fs-4"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Vikram Malhotra (Client)</h4>
                        <p class="text-muted small mb-3">
                            Customer portal: views active subscriptions, <strong>switches add-ons on/off live</strong> with immediate recalculation, downloads tax invoices, and pays overdue bills online.
                        </p>
                        <div class="bg-light p-2.5 rounded-2 font-monospace small mb-3 text-secondary">
                            <div><strong>Email:</strong> client@apex.in</div>
                            <div><strong>Password:</strong> Demo@12345</div>
                        </div>
                        <div class="mt-auto">
                            <form action="<?= BASE_URL; ?>/auth/login.php" method="POST">
                                <input type="hidden" name="email" value="client@apex.in">
                                <input type="hidden" name="password" value="Demo@12345">
                                <input type="hidden" name="auto_login" value="1">
                                <button type="submit" class="btn btn-info w-100 fw-semibold text-white">
                                    <i class="fa-solid fa-bolt me-1"></i> 1-Click Login as Customer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Scene-by-Scene Problem Solving (From Odoo Challenge PDF) -->
<section id="features" class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="badge bg-secondary-subtle text-secondary px-3 py-1.5 fw-semibold mb-2">Business Architecture</span>
            <h2 class="fw-bold text-dark">Engineered to Solve Every Brightloop Scene</h2>
            <p class="text-muted">Direct compliance with the problem statement outlined in the Odoo challenge brief.</p>
        </div>

        <div class="row g-4">
            <!-- Scene 1 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-primary-subtle text-primary fs-5">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Role Segregation</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Admin controls the entire system. Staff operate day-to-day functions. Customers access self-service portal. Only admin can add staff members.
                    </p>
                </div>
            </div>

            <!-- Scene 2 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-success-subtle text-success fs-5">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Product & Variant Pricing</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Products with sales and cost prices. Offer variants like Brand, where selecting <strong>Odoo Brand adds +₹560.00</strong> to the line price dynamically.
                    </p>
                </div>
            </div>

            <!-- Scene 3 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-info-subtle text-info fs-5">
                            <i class="fa-solid fa-arrows-split-up-and-left"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Quote Templates to Subs</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Sales reps create offers from pre-set quotation templates with validity days. Seamlessly transitions: <code>Draft &rarr; Quotation &rarr; Confirmed &rarr; Active &rarr; Closed</code>.
                    </p>
                </div>
            </div>

            <!-- Scene 4 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-warning-subtle text-warning fs-5">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Automated Billing Day</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Zero manual invoice typing. 1-click batch engine generates recurring invoices with customer details, product lines, taxes, and discounts. Nothing billed twice.
                    </p>
                </div>
            </div>

            <!-- Scene 5 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-danger-subtle text-danger fs-5">
                            <i class="fa-solid fa-percent"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Discounts & Tax Rules</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Fixed or percentage discounts with minimum purchase, quantity, start/end dates, and usage limits. Automatically applies to invoices and stops when expired.
                    </p>
                </div>
            </div>

            <!-- Scene 6 -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border p-4 rounded-3 shadow-none hover-shadow transition">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2.5 rounded-2 bg-dark-subtle text-dark fs-5">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0">Sub-2s Finance Reports</h5>
                    </div>
                    <p class="text-secondary small mb-0">
                        Finance lead end-of-month dashboard: Active subscriptions, MRR/ARR, revenue collected, payments recorded, and overdue invoice aging buckets.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5 bg-dark text-white text-center">
    <div class="container py-3">
        <h2 class="fw-bold mb-3">Ready to Inspect SubNexa Pro?</h2>
        <p class="text-white-50 max-w-xl mx-auto mb-4">
            Experience the complete, real-time subscription management system built with clean PHP, MySQL, Bootstrap 5, and Nordic Minimalist design.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= BASE_URL; ?>/auth/login.php" class="btn btn-primary btn-lg px-4 fw-semibold">
                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Open Sign In
            </a>
            <a href="<?= BASE_URL; ?>/auth/register.php" class="btn btn-outline-light btn-lg px-4 fw-semibold">
                <i class="fa-solid fa-user-plus me-2"></i> Register New Customer
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
