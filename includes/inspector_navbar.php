<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$pageTitles = [
    'dashboard.php' => ['title' => 'Dashboard', 'subtitle' => 'Overview of inspection activity'],
    'customer_registration.php' => ['title' => 'Customer Registration', 'subtitle' => 'Register a customer and generate a QR code.'],
    'ante_mortems.php' => ['title' => 'Ante Mortem List', 'subtitle' => 'View saved Ante Mortem reports.'],
    'ante_mortem.php' => ['title' => 'Ante Mortem Report', 'subtitle' => 'Record animal details and documents before inspection.'],
    'condemnation.php' => ['title' => 'Condemnation', 'subtitle' => 'Record an authorized condemnation decision.'],
    'profile.php' => ['title' => 'My Profile', 'subtitle' => 'View and update your inspector profile.'],
    'inspection_certificate.php' => ['title' => 'Certificate', 'subtitle' => 'Print the generated inspection certificate.'],
    'certificates.php' => ['title' => 'Meat Inspection Certificates', 'subtitle' => 'View and manage issued meat inspection certificates.'],
    'generate_certificate.php' => ['title' => 'Generate Certificate', 'subtitle' => 'Review inspection details and generate the official certificate.'],
];
$pageTitle = $pageTitles[$currentPage]['title'] ?? 'Inspector';
$pageSubtitle = $pageTitles[$currentPage]['subtitle'] ?? 'Manage the meat inspection workflow.';
?>
<nav class="admin-navbar navbar navbar-expand-lg navbar-dark bg-surface shadow-sm sticky-top no-print">
    <div class="container-fluid py-3">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3 w-100">
            <button class="btn btn-sm btn-outline-light d-md-none me-2" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h4 class="mb-1 text-white"><?php echo htmlspecialchars($pageTitle); ?></h4>
                <p class="text-secondary mb-0 small"><?php echo htmlspecialchars($pageSubtitle); ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center ms-auto gap-2">
            <button class="btn btn-sm btn-outline-light date-filter">
                <i class="fa-regular fa-calendar-days me-2"></i>
                <?php echo date('F d, Y'); ?>
                <i class="fa-solid fa-chevron-down ms-2"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="inspectorProfileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar bg-success text-white rounded-circle me-2">I</div>
                    <span><?php echo htmlspecialchars($_SESSION['inspector_username'] ?? 'Inspector'); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="inspectorProfileMenu">
                    <li><a class="dropdown-item" href="/inspector/profile.php"><i class="fa-solid fa-user me-2"></i>See Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/inspector/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
