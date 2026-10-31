<?php
/**
 * SubNexa Pro - Recurring Plans Manager
 * Solves Scene 2 from PDF: "what it costs and how often it bills (daily, weekly, monthly or yearly), the minimum quantity, and the dates it is available. Some plans should close on their own when they end, and others can be closed early, paused or renewed."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $code = clean_input($_POST['code'] ?? '');
        $billing_frequency = clean_input($_POST['billing_frequency'] ?? 'monthly');
        $cost = (float)($_POST['cost'] ?? 0);
        $min_quantity = (int)($_POST['min_quantity'] ?? 1);
        $auto_close_on_end = isset($_POST['auto_close_on_end']) ? 1 : 0;
        $can_pause = isset($_POST['can_pause']) ? 1 : 0;
        $can_renew = isset($_POST['can_renew']) ? 1 : 0;
        $available_from = !empty($_POST['available_from']) ? clean_input($_POST['available_from']) : null;
        $available_to = !empty($_POST['available_to']) ? clean_input($_POST['available_to']) : null;
        $description = clean_input($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            $_SESSION['flash_error'] = "Plan name and code are required.";
        } else {
            db_insert(
                "INSERT INTO plans (name, code, billing_frequency, cost, min_quantity, auto_close_on_end, can_pause, can_renew, available_from, available_to, description, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$name, $code, $billing_frequency, $cost, $min_quantity, $auto_close_on_end, $can_pause, $can_renew, $available_from, $available_to, $description],
                "sssdiidisss"
            );
            log_audit("PLAN_CREATED", "Created plan: $name ($code)");
            $_SESSION['flash_success'] = "Plan '$name' created successfully!";
            header("Location: " . BASE_URL . "/admin/plans.php");
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM plans WHERE id = ?", [$id], "i");
            log_audit("PLAN_DELETED", "Deleted plan ID: $id");
            $_SESSION['flash_success'] = "Plan deleted.";
            header("Location: " . BASE_URL . "/admin/plans.php");
            exit;
        }
    }
}

$plans = db_fetch_all("SELECT * FROM plans ORDER BY id ASC");

$page_title = "Recurring Billing Plans";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Recurring Billing Plans</h2>
                    <p class="text-secondary small mb-0">Configure daily, weekly, monthly, and yearly billing cycles with lifecycle auto-close and pause rules (PDF Page 3).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Create New Plan
                    </button>
                </div>
            </div>

            <!-- Plans Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Plans Configured (<?= count($plans); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search plans..." data-table-search="#plansTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="plansTable">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Frequency</th>
                                <th>Cost</th>
                                <th>Min Qty</th>
                                <th>Lifecycle Rules</th>
                                <th>Validity Dates</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']); ?></div>
                                        <span class="badge bg-light text-muted border font-monospace"><?= htmlspecialchars($p['code']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border text-uppercase" style="font-size: 0.725rem;">
                                            <i class="fa-solid fa-clock me-1"></i> <?= htmlspecialchars($p['billing_frequency']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark fs-6"><?= format_currency($p['cost']); ?></td>
                                    <td><strong><?= (int)$p['min_quantity']; ?></strong> seat(s)</td>
                                    <td>
                                        <div class="d-flex flex-column gap-1 small">
                                            <span>
                                                <i class="fa-solid <?= $p['auto_close_on_end'] ? 'fa-circle-check text-warning' : 'fa-circle-xmark text-muted'; ?> me-1"></i>
                                                <?= $p['auto_close_on_end'] ? 'Auto-closes on end' : 'Continuous renewal'; ?>
                                            </span>
                                            <span>
                                                <i class="fa-solid <?= $p['can_pause'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-muted'; ?> me-1"></i>
                                                <?= $p['can_pause'] ? 'Pausable by customer' : 'Pause locked'; ?>
                                            </span>
                                            <span>
                                                <i class="fa-solid <?= $p['can_renew'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-muted'; ?> me-1"></i>
                                                <?= $p['can_renew'] ? 'Renewable' : 'Fixed-term only'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="small text-secondary">
                                        <div>From: <?= format_date($p['available_from']); ?></div>
                                        <div>To: <?= format_date($p['available_to']); ?></div>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL; ?>/admin/plans.php" onsubmit="return confirm('Delete this plan?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5">
                                                <i class="fa-solid fa-trash-can"></i>
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

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1" aria-labelledby="addPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addPlanModalLabel"><i class="fa-solid fa-layer-group text-primary me-2"></i> Create Recurring Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/plans.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="e.g. Quarterly Growth Plan" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Plan Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" placeholder="PLAN-Q-GROWTH" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label">Billing Frequency <span class="text-danger">*</span></label>
                            <select class="form-select" name="billing_frequency" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Base Cost ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="cost" placeholder="199.00" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" name="min_quantity" value="1" min="1" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Available From Date</label>
                            <input type="date" class="form-control" name="available_from" value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Available Until Date</label>
                            <input type="date" class="form-control" name="available_to">
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="fw-bold text-dark small mb-2">Plan Lifecycle Options (PDF Page 3):</div>
                        <div class="row g-2 small">
                            <div class="col-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_close" name="auto_close_on_end" value="1">
                                    <label class="form-check-label" for="auto_close">Auto-Close on End</label>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_pause" name="can_pause" value="1" checked>
                                    <label class="form-check-label" for="can_pause">Allow Pause</label>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_renew" name="can_renew" value="1" checked>
                                    <label class="form-check-label" for="can_renew">Allow Renewal</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Description & Notes</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Summary of SLA and plan inclusions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
