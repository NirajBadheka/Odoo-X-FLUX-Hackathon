<?php
/**
 * SubNexa Pro - Reset Password Portal
 */
require_once __DIR__ . '/../config/config.php';

$token = clean_input($_GET['token'] ?? ($_POST['token'] ?? ''));
$error_message = "";
$success_message = "";

if (empty($token)) {
    $_SESSION['flash_error'] = "Invalid or missing password reset token.";
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

// Verify token validity
$reset_entry = db_fetch_one(
    "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
    [$token],
    "s"
);

if (!$reset_entry) {
    $error_message = "This password reset link is invalid or has expired. Please request a new one.";
}

// Process new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_entry) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password)) {
        $error_message = "Please enter a new password.";
    } elseif ($password !== $password_confirm) {
        $error_message = "Password confirmation does not match.";
    } else {
        $validation = validate_password_strength($password);
        if ($validation !== true) {
            $error_message = $validation;
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            // Update user password
            db_execute(
                "UPDATE users SET password_hash = ? WHERE email = ?",
                [$password_hash, $reset_entry['email']],
                "ss"
            );

            // Delete used reset tokens for this email
            db_execute("DELETE FROM password_resets WHERE email = ?", [$reset_entry['email']], "s");

            log_audit("PASSWORD_RESET_COMPLETED", "Password successfully reset for " . $reset_entry['email']);

            $_SESSION['flash_success'] = "Password reset successfully! You can now log in with your new credentials.";
            header("Location: " . BASE_URL . "/auth/login.php");
            exit;
        }
    }
}

$page_title = "Set New Password — SubNexa Pro";
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
                                <i class="fa-solid fa-lock-open fs-3"></i>
                            </div>
                            <h3 class="fw-bold text-dark">Create New Password</h3>
                            <p class="text-muted small">Enter a new secure password for your account.</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger small py-2.5 rounded-3 mb-4">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($reset_entry): ?>
                            <form method="POST" action="<?= BASE_URL; ?>/auth/reset_password.php">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">

                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                                    <div class="form-text" style="font-size: 0.725rem;">Must be &gt;8 characters with uppercase, lowercase, and special character.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirm" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="••••••••" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                                    <i class="fa-solid fa-check me-2"></i> Update Password & Sign In
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <a href="<?= BASE_URL; ?>/auth/forgot_password.php" class="btn btn-outline-primary">Request New Link</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
