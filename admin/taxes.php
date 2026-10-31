<?php
/**
 * SubNexa Pro - Tax Rules Manager
 * Solves Scene 3 from PDF: "Taxes are calculated automatically from rules the admin has set."
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = clean_input($_POST['name'] ?? '');
        $rate = (float)($_POST['rate_percentage'] ?? 0);

        if (empty($name) || $rate < 0) {
            $_SESSION['flash_error'] = "Tax rule name and valid percentage rate are required.";
        } else {
            db_insert("INSERT INTO tax_rules (name, rate_percentage, is_active) VALUES (?, ?, 1)", [$name, $rate], "sd");
            log_audit("TAX_RULE_CREATED", "Added tax rule: $name ($rate%)");
            $_SESSION['flash_success'] = "Tax rule added successfully!";
            header("Location: " . BASE_URL . "/admin/taxes.php");
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db_execute("DELETE FROM tax_rules WHERE id = ?", [$id], "i");
            log_audit("TAX_RULE_DELETED", "Deleted tax rule ID: $id");
            $_SESSION['flash_success'] = "Tax rule deleted.";
            header("Location: " . BASE_URL . "/admin/taxes.php");
            exit;
        }
    }
}

$taxes = db_fetch_all("SELECT * FROM tax_rules ORDER BY rate_percentage DESC");

$page_title = "Tax Rules Configuration";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="app-main">
        <div class="container-fluid p-0">

            <div class="d-flex flex-column flex-md-row md:align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Automated Tax Calculation Rules</h2>
                    <p class="text-secondary small mb-0">Define regional tax brackets automatically calculated during quotation and automated billing runs (PDF Page 3).</p>
                </div>
                <div>
                    <button type="button" class="btn btn-nordic-primary" data-bs-toggle="modal" data-bs-target="#addTaxModal">
                        <i class="fa-solid fa-plus me-1.5"></i> Add Tax Rule
                    </button>
                </div>
            </div>

            <!-- Tax Rules Table -->
            <div class="card-nordic overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase tracking-wider">Tax Brackets (<?= count($taxes); ?>)</span>
                    <input type="text" class="form-control form-control-sm" placeholder="Search taxes..." data-table-search="#taxesTable" style="max-width: 220px;">
                </div>

                <div class="table-responsive">
                    <table class="table-nordic" id="taxesTable">
                        <thead>
                            <tr>
                                <th>Tax Rule Name</th>
                                <th>Percentage Rate</th>
                                <th>Calculation Mode</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxes as $t): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($t['name']); ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary fs-6 fw-bold">
                                            <?= number_format((float)$t['rate_percentage'], 2); ?>%
                                        </span>
                                    </td>
                                    <td><span class="badge bg-light text-muted border">Automatic Line-Item Compound</span></td>
                                    <td><span class="badge bg-success-subtle text-success">Active & In Use</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL; ?>/admin/taxes.php" onsubmit="return confirm('Delete this tax rule?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $t['id']; ?>">
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

<!-- Add Tax Modal -->
<div class="modal fade" id="addTaxModal" tabindex="-1" aria-labelledby="addTaxModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom p-3">
                <h5 class="modal-title fw-bold text-dark" id="addTaxModalLabel"><i class="fa-solid fa-percent text-primary me-2"></i> Add Tax Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL; ?>/admin/taxes.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Tax Rule Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. State Software Tax (10%)" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Percentage Rate (%) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="rate_percentage" placeholder="18.00" required>
                            <span class="input-group-text bg-light text-muted">%</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-nordic-primary"><i class="fa-solid fa-check me-1"></i> Save Tax Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
