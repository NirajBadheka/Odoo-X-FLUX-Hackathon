<?php
/**
 * SubNexa Pro - Global Configuration (Indian Localization)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone (Indian Standard Time)
date_default_timezone_set('Asia/Kolkata');

// App Constants
define('APP_NAME', 'SubNexa Pro');
define('APP_TAGLINE', 'Enterprise Subscription & Recurring Billing Management');
define('APP_VERSION', '1.0.0');
define('APP_COMPANY', 'Brightloop Technologies India Pvt. Ltd.');
define('APP_CURRENCY', '₹');
define('BASE_URL', '/SubNexa_pro');

// Indian Corporate Details & GST
define('COMPANY_GSTIN', '29AAAAA0000A1Z5');
define('COMPANY_CIN', 'U72200KA2026PTC189201');
define('COMPANY_ADDRESS', 'Level 8, Cyber Park, Electronic City Phase 1, Bengaluru, Karnataka 560100');

// System Email Settings
define('SUPPORT_EMAIL', 'support@brightloop.in');
define('BILLING_EMAIL', 'billing@brightloop.in');

// Include Database connection
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
