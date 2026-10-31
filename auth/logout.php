<?php
/**
 * SubNexa Pro - Logout Handler
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    log_audit("USER_LOGOUT", "User logged out");
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
$_SESSION['flash_success'] = "You have been logged out successfully.";
header("Location: " . BASE_URL . "/auth/login.php");
exit;
