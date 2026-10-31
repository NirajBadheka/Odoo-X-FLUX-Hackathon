<?php
/**
 * SubNexa Pro - Customer Registration Portal
 * Enforces strict PDF Page 2 password policies
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "/customer/dashboard.php");
    exit;
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name'] ?? '');
    $company_name = clean_input($_POST['company_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Please complete all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($password !== $password_confirm) {
        $error_message = "Password confirmation does not match.";
    } else {
        // Enforce password criteria from PDF Page 2
        $password_validation = validate_password_strength($password);
        if ($password_validation !== true) {
            $error_message = $password_validation;
        } else {
            // Check if email already exists
            $existing_user = db_fetch_one("SELECT id FROM users WHERE email = ? LIMIT 1", [$email], "s");
            if ($existing_user) {
                $error_message = "An account with this email address already exists. Each email can belong to only one account.";
            } else {
                // Hash password securely with BCrypt
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                $new_user_id = db_insert(
                    "INSERT INTO users (name, company_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'customer', 'active')",
                    [$name, $company_name, $email, $phone, $password_hash],
                    "sssss"
                );

                if ($new_user_id) {
                    log_audit("CUSTOMER_REGISTERED", "New customer registered: $email (ID: $new_user_id)");
                    $_SESSION['flash_success'] = "Account created successfully! You can now sign in.";
                    header("Location: " . BASE_URL . "/auth/login.php");
                    exit;
                } else {
                    $error_message = "Registration failed due to a database error. Please try again.";
                }
            }
        }
    }
}

$page_title = "Customer Registration — SubNexa Pro";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="min-vh-100 d-flex flex-column justify-content-center py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-6">

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="d-inline-flex p-3 rounded-circle bg-light text-primary mb-2">
                                <i class="fa-solid fa-user-plus fs-3"></i>
                            </div>
                            <h3 class="fw-bold text-dark">Create Your Customer Account</h3>
                            <p class="text-muted small">Sign up to manage your Brightloop Software subscriptions & invoices</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 small py-2.5 rounded-3 mb-4" role="alert">
                                <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
                                <div><?= htmlspecialchars($error_message); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= BASE_URL; ?>/auth/register.php" id="registerForm">
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                <div class="col-sm-6">
                                    <label for="company_name" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Acme Technologies" value="<?= htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="john@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    <div class="form-text" style="font-size: 0.725rem;">Each email can belong to only one account.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="+1-555-0199" value="<?= htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required oninput="checkPasswordRules(this.value)">
                                </div>
                                <div class="col-sm-6">
                                    <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="••••••••" required>
                                </div>
                            </div>

                            <!-- Password Requirement Checklist (From PDF Page 2) -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <div class="fw-bold small text-dark mb-2">Password Policy Requirements (Odoo Challenge Brief):</div>
                                <div class="row g-2 small text-secondary">
                                    <div class="col-6 d-flex align-items-center gap-1.5" id="rule-len">
                                        <i class="fa-solid fa-circle-dot text-muted"></i> <span>&gt; 8 Characters</span>
                                    </div>
                                    <div class="col-6 d-flex align-items-center gap-1.5" id="rule-upper">
                                        <i class="fa-solid fa-circle-dot text-muted"></i> <span>1 Uppercase Letter</span>
                                    </div>
                                    <div class="col-6 d-flex align-items-center gap-1.5" id="rule-lower">
                                        <i class="fa-solid fa-circle-dot text-muted"></i> <span>1 Lowercase Letter</span>
                                    </div>
                                    <div class="col-6 d-flex align-items-center gap-1.5" id="rule-special">
                                        <i class="fa-solid fa-circle-dot text-muted"></i> <span>1 Special Character</span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                                <i class="fa-solid fa-user-check me-2"></i> Create Customer Account
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <span class="text-muted small">Already have an account? </span>
                            <a href="<?= BASE_URL; ?>/auth/login.php" class="text-primary small fw-semibold text-decoration-none">Sign In Here</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function checkPasswordRules(pwd) {
    const check = (id, valid) => {
        const el = document.getElementById(id);
        if (!el) return;
        const icon = el.querySelector('i');
        if (valid) {
            el.className = 'col-6 d-flex align-items-center gap-1.5 text-success fw-medium';
            icon.className = 'fa-solid fa-circle-check text-success';
        } else {
            el.className = 'col-6 d-flex align-items-center gap-1.5 text-secondary';
            icon.className = 'fa-solid fa-circle-dot text-muted';
        }
    };

    check('rule-len', pwd.length > 8);
    check('rule-upper', /[A-Z]/.test(pwd));
    check('rule-lower', /[a-z]/.test(pwd));
    check('rule-special', /[^a-zA-Z0-9]/.test(pwd));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
