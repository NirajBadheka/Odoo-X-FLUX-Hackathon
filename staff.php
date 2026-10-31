<?php
/**
 * SubNexa Pro - Staff Management (Exclusive Admin Feature)
 * Solves Scene 1 from PDF: "Only the admin can add a new member of staff."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

// Handle Staff Addition POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        $department = clean_input($_POST['department'] ?? 'Sales & Billing');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $_SESSION['flash_error'] = "Name, email, and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Please provide a valid corporate email.";
        } else {
            $validation = validate_password_strength($password);
            if ($validation !== true) {
                $_SESSION['flash_error'] = $validation;
            } else {
                $exists = db_fetch_one("SELECT id FROM users WHERE email = ? LIMIT 1", [$email], "s");
                if ($exists) {
                    $_SESSION['flash_error'] = "A user account with this email already exists.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $new_id = db_insert(
                        "INSERT INTO users (name, email, password_hash, role, phone, company_name, status) VALUES (?, ?, ?, 'staff', ?, ?, 'active')",
                        [$name, $email, $password_hash, $phone, $department],
                        "sssss"
                    );
                    log_audit("STAFF_CREATED", "Admin added new staff member: $name ($email)");
                    $_SESSION['flash_success'] = "New staff member '$name' provisioned successfully!";
                    header("Location: " . BASE_URL . "/admin/staff.php");
                    exit;
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM users WHERE id = ? AND role = 'staff'", [$id], "i");
            log_audit("STAFF_DELETED", "Admin removed staff member ID: $id");
            $_SESSION['flash_success'] = "Staff account revoked.";
            header("Location: " . BASE_URL . "/admin/staff.php");
            exit;
        }
    }
}

$staff_members = db_fetch_all("
    SELECT u.*, COUNT(s.id) as managed_subs_count 
    FROM users u 
    LEFT JOIN subscriptions s ON u.id = s.created_by_staff_id 
    WHERE u.role = 'staff' 
    GROUP BY u.id 
    ORDER BY u.id ASC
");

$page_title = "Staff Management (Admin Exclusive)";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Internal Staff Management</h2>
                    <p class="text-secondary small mb-0">Exclusive to Admin: Provision internal sales reps, billing operators, and manage role-based credentials (PDF Page 2).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fa-solid fa-user-plus me-1.5"></i> Add New Staff Member
                    </button>
                </div>
            </div>

            <!-- Callout -->
            <div class="card border-0 shadow-sm rounded-3 mb-4" style="background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%); border: 1px solid #BAE6FD !important;">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <i class="fa-solid fa-shield-halved fs-3 text-primary"></i>
                    <div>
                        <div class="fw-bold text-primary small">Security Policy Enforcement (PDF Page 2):</div>
                        <div class="text-dark small">
                            <em>"Only the admin can add a new member of staff."</em> Staff members have day-to-day access to manage quotes, subscriptions, and record payments, but cannot modify system plans, taxes, or invite other staff.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Active Staff Directory (<?= count($staff_members); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search staff..." data-table-search="#staffTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="staffTable">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Email Address</th>
                                <th>Department / Unit</th>
                                <th>Phone</th>
                                <th>Subscriptions Managed</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_members as $sm): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($sm['name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($sm['name']); ?></div>
                                                <div class="text-muted small" style="font-size: 0.75rem;">Member since <?= format_date($sm['created_at']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-secondary"><?= htmlspecialchars($sm['email']); ?></td>
                                    <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($sm['company_name'] ?? 'Operations'); ?></span></td>
                                    <td><?= htmlspecialchars($sm['phone'] ?? '—'); ?></td>
                                    <td><strong class="text-primary"><?= (int)$sm['managed_subs_count']; ?></strong> contracts</td>
                                    <td><span class="badge bg-success-subtle text-success">Active Staff</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL; ?>/admin/staff.php" onsubmit="return confirm('Revoke this staff member account?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $sm['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5" title="Revoke Account">
                                                <i class="fa-solid fa-user-xmark"></i> Revoke
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addStaffModalLabel"><i class="fa-solid fa-user-plus text-primary me-2"></i> Provision New Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/staff.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="Sarah Jenkins" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Corporate Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" placeholder="sarah.jenkins@brightloop.com" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" placeholder="+1-555-0104">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" value="Sales & Billing" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                        <div class="form-text" style="font-size: 0.725rem;">Must be &gt;8 chars with uppercase, lowercase, and special character.</div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Provision Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
