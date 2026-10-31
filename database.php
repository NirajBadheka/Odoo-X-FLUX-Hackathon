<?php
/**
 * SubNexa Pro - Enterprise Subscription & Recurring Billing Management
 * Database Connection Pool using MySQLi (Prepared Statements)
 */

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'subnexa_pro');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

// Establish connection with strict error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Graceful fallback with clean developer information
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:30px;background:#FEF2F2;border:1px solid #F87171;border-radius:8px;max-width:600px;margin:50px auto;color:#991B1B;">
        <h3 style="margin-top:0;">Database Connection Error</h3>
        <p>Could not connect to MySQL database <strong>' . htmlspecialchars(DB_NAME) . '</strong> on port ' . DB_PORT . '.</p>
        <p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>
        <p>Please make sure MySQL is running in XAMPP and <code>subnexa_pro</code> database is imported.</p>
    </div>');
}

/**
 * Helper function to run prepared queries easily and safely
 */
function db_query($sql, $params = [], $types = "") {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat("s", count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/**
 * Fetch all rows from a prepared statement as associative array
 */
function db_fetch_all($sql, $params = [], $types = "") {
    $stmt = db_query($sql, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();
    return $rows;
}

/**
 * Fetch a single row as associative array
 */
function db_fetch_one($sql, $params = [], $types = "") {
    $stmt = db_query($sql, $params, $types);
    $result = $stmt->get_result();
    $row = null;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $result->free();
    }
    $stmt->close();
    return $row;
}

/**
 * Execute an INSERT query and return the inserted ID
 */
function db_insert($sql, $params = [], $types = "") {
    global $conn;
    $stmt = db_query($sql, $params, $types);
    $insert_id = $conn->insert_id;
    $stmt->close();
    return $insert_id;
}

/**
 * Execute an UPDATE / DELETE query and return affected rows
 */
function db_execute($sql, $params = [], $types = "") {
    $stmt = db_query($sql, $params, $types);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}
