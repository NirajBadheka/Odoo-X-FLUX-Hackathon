<?php
/**
 * SubNexa Pro - Forgot Password (Scene 2 from PDF)
 * "If they forget it later, a reset link should arrive at their registered email."
 */
require_once __DIR__ . '/../config/config.php';

$error_message = "";
$success_message = "";
$reset_link = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid registered email address.";
    } else {
        $user = db_fetch_one("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1", [$email], "s");

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store in password_resets table
            db_insert(
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
                [$email, $token, $expires_at],
                "sss"
            );

            log_audit("PASSWORD_RESET_REQUESTED", "Reset link generated for email: $email");

            $reset_link = BASE_URL . "/auth/reset_password.php?token=" . $token;
            $success_message = "Password reset link has been dispatched to <strong>" . htmlspecialchars($email) . "</strong>.";
        } else {
            // For security, do not disclose if email exists or not
            $success_message = "If an account exists with that email, a password reset link has been sent.";
        }
    }
}

$page_title = "Forgot Password — SubNexa Pro";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="min-vh-100 d-flex flex-column justify-content-center py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex p-3 rounded-circle bg-light text-primary mb-2">
                                <i class="fa-solid fa-key fs-3"></i>
                            </div>
                            <h3 class="fw-bold text-dark">Reset Your Password</h3>
                            <p class="text-muted small">Enter your email and a secure reset link will be generated.</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger small py-2.5 rounded-3 mb-4">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success small py-3 rounded-3 mb-4">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="fa-solid fa-circle-check fs-5 text-success"></i>
                                    <span class="fw-semibold"><?= $success_message; ?></span>
                                </div>
                                <?php if (!empty($reset_link)): ?>
                                    <div class="mt-3 p-3 bg-white rounded border border-success-subtle">
                                        <div class="small text-muted mb-1 fw-bold">Hackathon Demo Link (Simulated Email Dispatch):</div>
                                        <a href="<?= $reset_link; ?>" class="btn btn-sm btn-success w-100 fw-semibold text-white">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open Password Reset Page
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL; ?>/auth/forgot_password.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Registered Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="name@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm mt-2">
                                <i class="fa-solid fa-paper-plane me-2"></i> Send Reset Link
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="<?= BASE_URL; ?>/auth/login.php" class="text-primary small fw-semibold text-decoration-none">
                                <i class="fa-solid fa-arrow-left me-1"></i> Back to Sign In
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
