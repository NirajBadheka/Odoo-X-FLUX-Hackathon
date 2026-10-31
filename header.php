<?php
/**
 * SubNexa Pro - Header Partial
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
$page_title = isset($page_title) ? $page_title . " — " . APP_NAME : APP_NAME . " — " . APP_TAGLINE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Chart.js (Loaded for dashboards & reports) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- SubNexa Pro Nordic Minimalist Theme -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<!-- Preloader -->
<div id="page-preloader">
    <div class="spinner-border text-primary" role="status" style="width: 2.75rem; height: 2.75rem; border-width: 3px;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-3 fw-semibold text-secondary small tracking-wider text-uppercase">Loading SubNexa Pro...</div>
</div>
