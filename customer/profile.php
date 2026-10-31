<?php
/**
 * SubNexa Pro - Customer Profile & Security
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$user_id], "i");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name = clean_input($_POST['name'] ?? '');
        $company_name = clean_input($_POST['company_name'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $address = clean_input($_POST['address'] ?? '');

        if (!empty($name)) {
            db_execute(
                "UPDATE users SET name = ?, company_name = ?, phone = ?, address = ? WHERE id = ?",
                [$name, $company_name, $phone, $address, $user_id],
                "ssssi"
            );
            $_SESSION['user_name'] = $name;
            $_SESSION['user_company'] = $company_name;
            log_audit("PROFILE_UPDATED", "Updated profile info");
            $_SESSION['flash_success'] = "Profile details updated successfully!";
            header("Location: " . BASE_URL . "/customer/profile.php");
            exit;
        }
    } elseif ($action === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_pass, $user['password_hash'])) {
            $_SESSION['flash_error'] = "Current password is incorrect.";
        } elseif ($new_pass !== $confirm_pass) {
            $_SESSION['flash_error'] = "New password confirmation does not match.";
        } else {
            $validation = validate_password_strength($new_pass);
            if ($validation !== true) {
                $_SESSION['flash_error'] = $validation;
            } else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                db_execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $user_id], "si");
                log_audit("PASSWORD_CHANGED", "User changed account password");
                $_SESSION['flash_success'] = "Password changed successfully!";
                header("Location: " . BASE_URL . "/customer/profile.php");
                exit;
            }
        }
    }
}

$page_title = "Account Settings & Security";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Account & Security Settings</h2>
                    <p class="text-secondary small mb-0">Manage billing contact details and secure credentials (PDF Page 2).</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Profile Details -->
                <div class="col-lg-7">
                    <div class="card-nordic p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-user-pen text-primary me-2"></i> Profile & Company Information</h5>
                        <form method="POST" action="<?= BASE_URL; ?>/customer/profile.php">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Email Address (Read-Only)</label>
                                    <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']); ?>" readonly>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Billing Address</label>
                                <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-nordic-primary fw-semibold">
                                <i class="fa-solid fa-check me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Password Change Form -->
                <div class="col-lg-5">
                    <div class="card-nordic p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-shield-halved text-primary me-2"></i> Update Password</h5>
                        <form method="POST" action="<?= BASE_URL; ?>/customer/profile.php">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="new_password" required>
                                <div class="form-text" style="font-size: 0.725rem;">Must be &gt;8 characters with uppercase, lowercase, and special character.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-outline-primary fw-semibold w-100">
                                <i class="fa-solid fa-key me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
