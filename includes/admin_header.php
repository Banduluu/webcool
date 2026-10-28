<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isInspectorPage = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/inspector/') !== false;
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdminPage = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
$usesDataTables = $isAdminPage && in_array($currentPage, [
    'ante_mortem.php',
    'condemnations.php',
    'customers.php',
    'dashboard.php',
    'mic_viewer.php',
    'registrations.php',
    'users.php',
], true);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>MIC Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($usesDataTables): ?>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?php if ($isAdminPage && $currentPage === 'dashboard.php'): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
</head>
<body class="admin-body<?php echo $isInspectorPage ? ' inspector-page' : ''; ?>">
<script>
    (function () {
        const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        document.documentElement.setAttribute('data-device', isMobileDevice ? 'mobile' : 'desktop');
        document.body.classList.toggle('is-mobile', isMobileDevice);
    })();
</script>
<div class="toast-container position-fixed top-0 end-0 p-3"></div>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
