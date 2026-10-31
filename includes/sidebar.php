<?php
/**
 * SubNexa Pro - Dynamic Role-Based Sidebar Partial
 */
$user = current_user();
if (!$user) return;

$current_page = basename($_SERVER['PHP_SELF']);
$role = $user['role'];
?>
<aside class="app-sidebar">
    <div class="sidebar-heading">Navigation Menu</div>
    <ul class="sidebar-menu">

        <?php if ($role === 'admin'): ?>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/dashboard.php" class="sidebar-link <?= ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <div class="sidebar-heading">Billing Operations</div>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/subscriptions.php" class="sidebar-link <?= ($current_page === 'subscriptions.php' || $current_page === 'subscription_view.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Subscriptions</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/billing_run.php" class="sidebar-link <?= ($current_page === 'billing_run.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Billing Day Run</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/invoices.php" class="sidebar-link <?= ($current_page === 'invoices.php' || $current_page === 'invoice_view.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/payments.php" class="sidebar-link <?= ($current_page === 'payments.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Payments Ledger</span>
                </a>
            </li>

            <div class="sidebar-heading">Catalog & Rules</div>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/products.php" class="sidebar-link <?= ($current_page === 'products.php' || $current_page === 'product_variants.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Products & Variants</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/plans.php" class="sidebar-link <?= ($current_page === 'plans.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-layer-group"></i>
                    <span>Recurring Plans</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/discounts.php" class="sidebar-link <?= ($current_page === 'discounts.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-tags"></i>
                    <span>Discount Rules</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/taxes.php" class="sidebar-link <?= ($current_page === 'taxes.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-percent"></i>
                    <span>Tax Rules</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/templates.php" class="sidebar-link <?= ($current_page === 'templates.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-signature"></i>
                    <span>Quote Templates</span>
                </a>
            </li>

            <div class="sidebar-heading">Administration</div>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/staff.php" class="sidebar-link <?= ($current_page === 'staff.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Staff Management</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/admin/reports.php" class="sidebar-link <?= ($current_page === 'reports.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Finance Reports</span>
                </a>
            </li>

        <?php elseif ($role === 'staff'): ?>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/dashboard.php" class="sidebar-link <?= ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <div class="sidebar-heading">Sales & Quotations</div>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/quotations.php" class="sidebar-link <?= ($current_page === 'quotations.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>Quotations</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/quotation_create.php" class="sidebar-link <?= ($current_page === 'quotation_create.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>Create New Quote</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/subscriptions.php" class="sidebar-link <?= ($current_page === 'subscriptions.php' || $current_page === 'subscription_view.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-arrows-rotate"></i>
                    <span>Subscriptions</span>
                </a>
            </li>

            <div class="sidebar-heading">Billing Day & Collections</div>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/billing_run.php" class="sidebar-link <?= ($current_page === 'billing_run.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Execute Billing Run</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/invoices.php" class="sidebar-link <?= ($current_page === 'invoices.php' || $current_page === 'invoice_view.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/payments.php" class="sidebar-link <?= ($current_page === 'payments.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Record Payment</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/staff/overdue.php" class="sidebar-link <?= ($current_page === 'overdue.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Overdue Accounts</span>
                </a>
            </li>

        <?php else: /* customer */ ?>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/customer/dashboard.php" class="sidebar-link <?= ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Portal Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/customer/my_subscriptions.php" class="sidebar-link <?= ($current_page === 'my_subscriptions.php' || $current_page === 'subscription_details.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-repeat"></i>
                    <span>My Subscriptions</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/customer/my_invoices.php" class="sidebar-link <?= ($current_page === 'my_invoices.php' || $current_page === 'invoice_view.php' || $current_page === 'pay_invoice.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Invoices & Billing</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL; ?>/customer/profile.php" class="sidebar-link <?= ($current_page === 'profile.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Account Security</span>
                </a>
            </li>
        <?php endif; ?>

        <div class="sidebar-heading mt-3">Session</div>
        <li class="sidebar-item">
            <a href="<?= BASE_URL; ?>/auth/logout.php" class="sidebar-link text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket text-danger"></i>
                <span>Sign Out</span>
            </a>
        </li>
    </ul>
</aside>
