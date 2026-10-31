<?php
/**
 * SubNexa Pro - Top Navbar Partial
 */
$user = current_user();
?>
<header class="app-navbar d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
            <button class="btn btn-sm btn-light d-lg-none" id="sidebar-toggle" type="button" aria-label="Toggle Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
        <?php endif; ?>
        
        <a href="<?= BASE_URL; ?>/index.php" class="brand-logo text-decoration-none">
            <i class="fa-solid fa-arrows-spin"></i>
            <span>SubNexa<span class="accent">Pro</span></span>
        </a>

        
    </div>

    <!-- Right Side Actions -->
    <div class="d-flex align-items-center gap-3">
        <?php if ($user): ?>
            <!-- User Role Badge -->
            <div class="d-none d-md-flex flex-column text-end">
                <span class="fw-bold text-dark small leading-tight"><?= htmlspecialchars($user['name']); ?></span>
                <span class="text-muted" style="font-size: 0.725rem;">
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="text-primary fw-bold"><i class="fa-solid fa-shield-halved me-1"></i> Admin / Finance Lead</span>
                    <?php elseif ($user['role'] === 'staff'): ?>
                        <span class="text-success fw-bold"><i class="fa-solid fa-briefcase me-1"></i> Billing & Sales Staff</span>
                    <?php else: ?>
                        <span class="text-info fw-bold"><i class="fa-solid fa-user me-1"></i> Customer Portal</span>
                    <?php endif; ?>
                </span>
            </div>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px; background-color: #E0F2FE; color: #0284C7; font-weight: 700;">
                    <?= strtoupper(substr($user['name'], 0, 2)); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2" style="min-width: 200px;">
                    <li class="px-3 py-1">
                        <div class="fw-bold text-dark small"><?= htmlspecialchars($user['name']); ?></div>
                        <div class="text-muted text-truncate" style="font-size: 0.75rem;"><?= htmlspecialchars($user['email']); ?></div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/admin/dashboard.php"><i class="fa-solid fa-chart-pie me-2 text-primary"></i> Admin Dashboard</a></li>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/admin/reports.php"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i> Finance Reports</a></li>
                    <?php elseif ($user['role'] === 'staff'): ?>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/staff/dashboard.php"><i class="fa-solid fa-chart-line me-2 text-success"></i> Staff Dashboard</a></li>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/staff/quotations.php"><i class="fa-solid fa-file-lines me-2 text-primary"></i> Quotations</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/customer/dashboard.php"><i class="fa-solid fa-gauge me-2 text-info"></i> Customer Portal</a></li>
                        <li><a class="dropdown-item py-1 text-sm" href="<?= BASE_URL; ?>/customer/profile.php"><i class="fa-solid fa-user-gear me-2 text-secondary"></i> My Profile</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-1 text-sm text-danger" href="<?= BASE_URL; ?>/auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out</a></li>
                </ul>
            </div>
        <?php else: ?>
            <!-- Guest Links -->
            <a href="<?= BASE_URL; ?>/index.php#features" class="btn btn-sm btn-link text-secondary text-decoration-none d-none d-md-inline-block">Features</a>
            <a href="<?= BASE_URL; ?>/pricing.php" class="btn btn-sm btn-link text-secondary text-decoration-none d-none d-md-inline-block">Pricing</a>
            <a href="<?= BASE_URL; ?>/auth/login.php" class="btn btn-sm btn-nordic-outline">
                <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Log In
            </a>
            <a href="<?= BASE_URL; ?>/auth/register.php" class="btn btn-sm btn-nordic-primary">
                <i class="fa-solid fa-user-plus me-1"></i> Register
            </a>
        <?php endif; ?>
    </div>
</header>
