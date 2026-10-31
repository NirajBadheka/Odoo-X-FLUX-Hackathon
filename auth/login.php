<?php
/**
 * SubNexa Pro - Multi-Role Authentication Gateway
 * With 1-Click Fast-Login Auto-Fill Bar for Hackathon Judges
 */
require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = user_role();
    if ($role === 'admin') header("Location: " . BASE_URL . "/admin/dashboard.php");
    elseif ($role === 'staff') header("Location: " . BASE_URL . "/staff/dashboard.php");
    else header("Location: " . BASE_URL . "/customer/dashboard.php");
    exit;
}

$error_message = "";
$success_message = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        $user = db_fetch_one("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email], "s");

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_company'] = $user['company_name'];
            $_SESSION['flash_success'] = "Welcome back, " . htmlspecialchars($user['name']) . "!";

            // Audit Log
            log_audit("USER_LOGIN", "Logged in successfully from IP: " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

            // Role-based redirection
            if ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "/admin/dashboard.php");
            } elseif ($user['role'] === 'staff') {
                header("Location: " . BASE_URL . "/staff/dashboard.php");
            } else {
                header("Location: " . BASE_URL . "/customer/dashboard.php");
            }
            exit;
        } else {
            $error_message = "Invalid email or password. Please verify credentials.";
        }
    }
}

$page_title = "Sign In — SubNexa Pro";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="min-vh-100 d-flex flex-column justify-content-center py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">

                <!-- 1-Click Fast Login Demonstration Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); border: 1px solid #BAE6FD !important;">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold small">
                            <i class="fa-solid fa-bolt"></i>
                            <span>HACKATHON DEMO FAST-LOGIN (Click to Auto-Fill):</span>
                        </div>
                        <p class="text-secondary small mb-3" style="font-size: 0.775rem;">
                            Click any persona below to automatically load verified credentials into the form:
                        </p>
                        <div class="d-flex flex-column gap-2">
                            <button type="button" onclick="fillDemoCredentials('admin')" class="btn btn-sm btn-white border shadow-sm text-start d-flex align-items-center justify-content-between p-2">
                                <div>
                                    <span class="badge bg-primary me-2">Admin</span>
                                    <span class="fw-semibold text-dark small">admin@brightloop.in</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">Aarav Sharma (Executive)</span>
                            </button>
                            <button type="button" onclick="fillDemoCredentials('staff')" class="btn btn-sm btn-white border shadow-sm text-start d-flex align-items-center justify-content-between p-2">
                                <div>
                                    <span class="badge bg-success me-2">Staff</span>
                                    <span class="fw-semibold text-dark small">staff@brightloop.in</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">Priya Patel (Sales & Billing)</span>
                            </button>
                            <button type="button" onclick="fillDemoCredentials('customer')" class="btn btn-sm btn-white border shadow-sm text-start d-flex align-items-center justify-content-between p-2">
                                <div>
                                    <span class="badge bg-info text-white me-2">Customer</span>
                                    <span class="fw-semibold text-dark small">client@apex.in</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">Vikram Malhotra (Subscriber)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Login Card -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex p-3 rounded-circle bg-light text-primary mb-2">
                                <i class="fa-solid fa-arrows-spin fs-3"></i>
                            </div>
                            <h3 class="fw-bold text-dark">Sign In to SubNexa Pro</h3>
                            <p class="text-muted small">Enter your credentials to access your portal</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 rounded-3 mb-4" role="alert">
                                <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
                                <div><?= htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 small py-2.5 rounded-3 mb-4" role="alert">
                                <i class="fa-solid fa-circle-check flex-shrink-0"></i>
                                <div><?= htmlspecialchars($success_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL; ?>/auth/login.php" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="name@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="password" class="form-label mb-0">Password</label>
                                    <a href="<?= BASE_URL; ?>/auth/forgot_password.php" class="text-primary small text-decoration-none">Forgot password?</a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm mt-3">
                                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Sign In to Account
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <span class="text-muted small">Don't have an account yet? </span>
                            <a href="<?= BASE_URL; ?>/auth/register.php" class="text-primary small fw-semibold text-decoration-none">Create Customer Account</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
