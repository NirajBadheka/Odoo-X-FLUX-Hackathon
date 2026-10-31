<?php
/**
 * SubNexa Pro - Real-Time Add-On Toggle Handler (AJAX)
 * Solves Scene 1 from PDF: "add-ons customers switch on and off... without having to email anyone"
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$sub_id = (int)($input['subscription_id'] ?? 0);
$addon_id = (int)($input['addon_id'] ?? 0);
$is_checked = isset($input['is_active']) ? (int)$input['is_active'] : 0;

if ($sub_id <= 0 || $addon_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

// Fetch subscription & verify ownership
$sub = db_fetch_one("SELECT * FROM subscriptions WHERE id = ?", [$sub_id], "i");
if (!$sub) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Subscription not found.']);
    exit;
}

if (user_role() === 'customer' && $sub['customer_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied: You do not own this subscription.']);
    exit;
}

// Fetch add-on product info
$addon = db_fetch_one("SELECT * FROM products WHERE id = ? AND type = 'add_on'", [$addon_id], "i");
if (!$addon) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Add-on product not found.']);
    exit;
}

// Check if line exists in subscription_lines
$line = db_fetch_one(
    "SELECT * FROM subscription_lines WHERE subscription_id = ? AND product_id = ? AND is_addon = 1 LIMIT 1",
    [$sub_id, $addon_id],
    "ii"
);

if ($line) {
    // Update existing line
    db_execute(
        "UPDATE subscription_lines SET is_addon_active = ? WHERE id = ?",
        [$is_checked, $line['id']],
        "ii"
    );
} else {
    // Create new line if customer enabled it
    if ($is_checked) {
        $tax_rate = 18.00; // Standard GST/VAT
        $tax_amount = $addon['sales_price'] * ($tax_rate / 100);
        
        db_insert(
            "INSERT INTO subscription_lines (subscription_id, product_id, is_addon, is_addon_active, quantity, unit_price, tax_id, tax_amount, discount_amount, subtotal) 
             VALUES (?, ?, 1, 1, 1, ?, 1, ?, 0.00, ?)",
            [$sub_id, $addon_id, $addon['sales_price'], $tax_amount, $addon['sales_price']],
            "iiddd"
        );
    }
}

// Recalculate Subscription Totals
$active_lines = db_fetch_all("
    SELECT * FROM subscription_lines 
    WHERE subscription_id = ? AND (is_addon = 0 OR is_addon_active = 1)
", [$sub_id], "i");

$new_subtotal = 0;
$new_tax = 0;

foreach ($active_lines as $al) {
    $new_subtotal += ($al['quantity'] * $al['unit_price']);
    $new_tax += $al['tax_amount'];
}

$discount_total = (float)$sub['discount_total'];
$new_grand = max(0, ($new_subtotal + $new_tax - $discount_total));

db_execute(
    "UPDATE subscriptions SET subtotal = ?, tax_total = ?, total_amount = ? WHERE id = ?",
    [$new_subtotal, $new_tax, $new_grand, $sub_id],
    "dddi"
);

log_audit("ADDON_TOGGLED", "Customer toggled add-on {$addon['name']} to $is_checked on Sub #{$sub['subscription_number']}");

echo json_encode([
    'success' => true,
    'is_active' => $is_checked,
    'subtotal' => number_format($new_subtotal, 2),
    'tax_total' => number_format($new_tax, 2),
    'grand_total' => number_format($new_grand, 2),
    'message' => $is_checked ? "{$addon['name']} enabled! Added to your recurring cycle." : "{$addon['name']} disabled! Removed from next billing cycle."
]);
