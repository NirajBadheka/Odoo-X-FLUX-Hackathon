<?php
// ── Database settings ─────────────────────────────────────────
$db_host = "localhost";
$db_user = "root";
$db_pass = "Krishn@2005";
$db_name = "ca_workflow_db";

// ── Connect ───────────────────────────────────────────────────
$conn = mysqli_connect($db_host, $db_user, $db_pass);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create DB if not exists
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `ca_workflow_db`
                     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

mysqli_select_db($conn, $db_name);
mysqli_set_charset($conn, "utf8mb4");

// ── Create tables if not exist ────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `role_name`   VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM roles"));
if ($check['cnt'] == 0) {
    mysqli_query($conn, "INSERT INTO roles (id, role_name, description) VALUES
        (1, 'Client',     'Business client who uploads documents'),
        (2, 'Accountant', 'Staff accountant who processes documents'),
        (3, 'CA/Admin',   'Chartered Accountant with full access')");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `role_id`       INT          NOT NULL,
    `name`          VARCHAR(100) NOT NULL,
    `email`         VARCHAR(100) NOT NULL,
    `role`          VARCHAR(50)  NOT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `mobile_number` VARCHAR(15)  NOT NULL DEFAULT '',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users"));
if ($check['cnt'] == 0) {
    mysqli_query($conn, "INSERT INTO users (role_id, name, email, role, password, mobile_number) VALUES
        (3, 'Admin User',      'admin@caworkflow.in',      'CA/Admin',   'admin123',      '9876543210'),
        (2, 'Ravi Accountant', 'accountant@caworkflow.in', 'Accountant', 'accountant123', '9876543211'),
        (1, 'Priya Client',    'client@caworkflow.in',     'Client',     'client123',     '9876543212')");
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `documents` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `client_id`     INT          NOT NULL,
    `uploaded_by`   INT          NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `file_path`     VARCHAR(500) NOT NULL,
    `file_type`     VARCHAR(50)  DEFAULT NULL,
    `file_size`     INT          DEFAULT 0,
    `status`        ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    `remarks`       TEXT             DEFAULT NULL,
    `document_category` ENUM('Invoice','Purchase Bill','Bank Statement','Receipt','Payment Proof','Journal Support Document','Other') NOT NULL DEFAULT 'Other',
    `uploaded_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`client_id`)   REFERENCES users(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// NOTE (2026-07 database rationalization pass): `entry_reviews` is now also properly
// defined in database/ca_workflow_db.sql (previously it only existed via this bootstrap
// — see ISSUES_AND_FINDINGS.md, Cross-references). This block is harmless to leave here
// — IF NOT EXISTS makes it a no-op once the table exists either way — kept as a safety
// net for anyone still running against an older, pre-rationalization schema import.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `entry_reviews` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `entry_type`     ENUM('sales','purchase','receipt','payment','contra','journal') NOT NULL,
    `entry_id`       INT          NOT NULL,
    `client_id`      INT          NOT NULL,
    `review_status`  ENUM('Pending','Verified','Needs Correction') NOT NULL DEFAULT 'Pending',
    `review_comment` TEXT         DEFAULT NULL,
    `reviewed_by`    INT          DEFAULT NULL,
    `reviewed_at`    DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_type_entry` (`entry_type`, `entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ════════════════════════════════════════════════════════════════
// FIX (audit finding 4.2): CSRF protection.
// No CSRF token existed anywhere in the app; every state-changing POST
// (voucher save/delete, user create/edit/delete, compliance actions) was
// forgeable against an authenticated session. These three helpers are
// included by every page via config.php:
//   - csrf_token()   returns the current session's token, creating one
//                     on first call.
//   - csrf_field()   echoes a ready-to-print hidden <input> for forms.
//   - csrf_verify()  called once near the top of a page, after
//                     session_start(). No-ops on GET requests; on POST,
//                     validates $_POST['csrf_token'] with a constant-time
//                     comparison and terminates the request with 403 on
//                     mismatch (e.g. missing token, forged/cross-site POST,
//                     or a stale token from an expired session).
// ════════════════════════════════════════════════════════════════
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $expected = csrf_token();
        $provided = $_POST['csrf_token'] ?? '';
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            http_response_code(403);
            die('Security check failed (invalid or missing CSRF token). Please go back, refresh the page, and try again.');
        }
    }
}
?>
