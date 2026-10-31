<?php
/**
 * SubNexa Pro - Core Security, Sanitization & Helper Functions
 */

/**
 * Sanitize user input to prevent XSS attacks
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency with configured symbol
 */
function format_currency($amount) {
    return APP_CURRENCY . ' ' . number_format((float)$amount, 2);
}

/**
 * Format dates cleanly
 */
function format_date($date_string, $format = "M d, Y") {
    if (empty($date_string) || $date_string === '0000-00-00') {
        return "—";
    }
    $timestamp = strtotime($date_string);
    return $timestamp ? date($format, $timestamp) : "—";
}

/**
 * Validate password strength strictly matching Odoo Challenge PDF Page 2:
 * "more than eight characters, with an uppercase letter, a lowercase letter and a special character"
 */
function validate_password_strength($password) {
    if (strlen($password) <= 8) {
        return "Password must be more than 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter (A-Z).";
    }
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter (a-z).";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return "Password must contain at least one special character (!@#$%^&* etc.).";
    }
    return true;
}

/**
 * Generate CSRF Token for forms
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get currently logged-in user array
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'customer',
        'company' => $_SESSION['user_company'] ?? ''
    ];
}

/**
 * Get logged-in user role
 */
function user_role() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Route protection: requires authentication
 */
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Please log in to access this area.";
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

/**
 * Route protection: Role-Based Access Control (RBAC)
 */
function require_role($allowed_roles = []) {
    require_login();
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    if (!in_array(user_role(), $allowed_roles, true)) {
        $_SESSION['flash_error'] = "Access denied. You do not have permissions for this section.";
        if (user_role() === 'admin') {
            header("Location: " . BASE_URL . "/admin/dashboard.php");
        } elseif (user_role() === 'staff') {
            header("Location: " . BASE_URL . "/staff/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "/customer/dashboard.php");
        }
        exit;
    }
}

/**
 * Audit Logger
 */
function log_audit($action, $details = "") {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    db_insert(
        "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
        [$user_id, $action, $details, $ip],
        "isss"
    );
}

/**
 * Render visual badge for subscription and invoice lifecycle states
 */
function get_status_badge($status) {
    $status_clean = strtolower(trim((string)$status));
    switch ($status_clean) {
        case 'active':
        case 'paid':
            return '<span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> ' . ucfirst($status_clean) . '</span>';
        case 'quotation':
        case 'confirmed':
            return '<span class="badge badge-quotation"><i class="fa-solid fa-file-contract me-1"></i> ' . ucfirst($status_clean) . '</span>';
        case 'draft':
            return '<span class="badge badge-draft"><i class="fa-solid fa-pen-ruler me-1"></i> Draft</span>';
        case 'paused':
            return '<span class="badge badge-paused"><i class="fa-solid fa-circle-pause me-1"></i> Paused</span>';
        case 'closed':
        case 'cancelled':
            return '<span class="badge badge-closed"><i class="fa-solid fa-ban me-1"></i> ' . ucfirst($status_clean) . '</span>';
        case 'overdue':
            return '<span class="badge badge-overdue"><i class="fa-solid fa-triangle-exclamation me-1"></i> Overdue</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status_clean)) . '</span>';
    }
}
