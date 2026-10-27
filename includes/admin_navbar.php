<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$pageTitles = [
    'dashboard.php' => ['title' => 'Dashboard', 'subtitle' => 'Overview of today’s operations'],
    'inspection_certificate.php' => ['title' => 'Inspection Certificate', 'subtitle' => 'Print the certification for a passed inspection.'],
    'ante_mortem.php' => ['title' => 'Ante-Mortem', 'subtitle' => 'Review ante-mortem inspection records.'],
    'post_mortem.php' => ['title' => 'Post-Mortem', 'subtitle' => 'Review slaughter details and post-mortem decisions.'],
    'condemnations.php' => ['title' => 'Condemnation Records', 'subtitle' => 'Review condemnation records submitted by inspectors.'],
    'mic_viewer.php' => ['title' => 'MIC Viewer', 'subtitle' => 'Browse saved MIC records for the admin panel.'],
    'requests.php' => ['title' => 'Inspection Requests', 'subtitle' => 'Monitor inspection statuses and request details.'],
    'registrations.php' => ['title' => 'Client Registrations', 'subtitle' => 'View registration records submitted by clients.'],
    'users.php' => ['title' => 'User Management', 'subtitle' => 'View and manage registered meat inspectors.'],
    'reports.php' => ['title' => 'Reports', 'subtitle' => 'Review inspection and registration trends.'],
    'settings.php' => ['title' => 'Settings', 'subtitle' => 'Manage administrator and system configuration.'],
];
$pageTitle = $pageTitles[$currentPage]['title'] ?? 'Admin';
$pageSubtitle = $pageTitles[$currentPage]['subtitle'] ?? 'Manage the MIC system.';
?>
<nav class="admin-navbar navbar navbar-expand-lg navbar-dark bg-surface shadow-sm sticky-top">
    <div class="container-fluid py-3">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
            <div>
                <h4 class="mb-1 text-white"><?php echo htmlspecialchars($pageTitle); ?></h4>
                <p class="text-secondary mb-0 small"><?php echo htmlspecialchars($pageSubtitle); ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center ms-auto gap-2">
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '/admin/dashboard.php', PHP_URL_PATH);
            $dateFilterValue = $today ?? ($_GET['date'] ?? date('Y-m-d'));
            ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light date-filter dropdown-toggle" type="button" id="dateFilterMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-regular fa-calendar-days me-2"></i>
                    <?php echo htmlspecialchars(date('F d, Y', strtotime($dateFilterValue))); ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-3 shadow-sm" aria-labelledby="dateFilterMenu" style="min-width: 250px;">
                    <form action="<?php echo htmlspecialchars($currentPath); ?>" method="get">
                        <label class="form-label small fw-semibold" for="dashboardDate">Select date</label>
                        <input class="form-control form-control-sm mb-3" type="date" id="dashboardDate" name="date" value="<?php echo htmlspecialchars($dateFilterValue); ?>" required>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-secondary flex-fill" href="<?php echo htmlspecialchars($currentPath); ?>">Today</a>
                            <button class="btn btn-sm btn-success flex-fill" type="submit">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
            <button class="btn btn-icon btn-light position-relative" type="button">
                <i class="fa-regular fa-bell"></i>
                <span class="badge bg-success rounded-pill position-absolute top-0 start-100 translate-middle">5</span>
            </button>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar bg-success text-white rounded-circle me-2">A</div>
                    <span><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin User'); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="profileMenu">
                    <li><a class="dropdown-item" href="/admin/settings.php"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                    <li><a class="dropdown-item" href="/admin/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
