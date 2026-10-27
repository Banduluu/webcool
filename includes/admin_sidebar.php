<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$navLinks = [
    'dashboard.php' => ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'section' => 'Main'],
    'ante_mortem.php' => ['label' => 'Ante-Mortem', 'icon' => 'fa-heart-pulse', 'section' => 'Operations'],
    'post_mortem.php' => ['label' => 'Post-Mortem', 'icon' => 'fa-microscope', 'section' => 'Operations'],
    'condemnations.php' => ['label' => 'Condemnation Records', 'icon' => 'fa-file-circle-exclamation', 'section' => 'Operations'],
    'mic_viewer.php' => ['label' => 'MIC Viewer', 'icon' => 'fa-eye', 'href' => '/admin/mic_viewer.php', 'section' => 'Operations'],
    'customers.php' => ['label' => 'Customers', 'icon' => 'fa-id-card', 'section' => 'Operations'],
    'users.php' => ['label' => 'Users', 'icon' => 'fa-users', 'section' => 'Administration'],
    'reports.php' => ['label' => 'Reports', 'icon' => 'fa-chart-line', 'section' => 'Administration'],
    'settings.php' => ['label' => 'Settings', 'icon' => 'fa-gear', 'section' => 'Administration'],
];
$sections = [];
foreach ($navLinks as $file => $link) {
    $sections[$link['section']] = $sections[$link['section']] ?? [];
    $sections[$link['section']][$file] = $link;
}
?>
<aside class="sidebar d-flex flex-column p-4">
    <div class="sidebar-brand mb-4">
        <a href="/admin/dashboard.php" class="d-flex align-items-center text-decoration-none">
            <div class="brand-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <div>
                <h5 class="mb-0 text-white">Slaughterhouse<br>Management System</h5>
                <small class="text-secondary">Administrator</small>
            </div>
        </a>
    </div>

    <?php foreach ($sections as $sectionLabel => $links): ?>
        <div class="sidebar-section text-uppercase text-muted small mb-2"><?php echo $sectionLabel; ?></div>
        <nav class="nav flex-column gap-1 mb-3">
            <?php foreach ($links as $file => $link): ?>
                <?php $href = $link['href'] ?? '/admin/' . $file; ?>
                <?php $isActive = $currentPage === $file; ?>
                <a class="nav-link<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $href; ?>">
                    <i class="fa-solid <?php echo $link['icon']; ?> me-3"></i>
                    <span><?php echo $link['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>

    <div class="sidebar-section text-uppercase text-muted small mt-4 mb-2">More</div>
    <a class="nav-link logout-link" href="/admin/logout.php"><i class="fa-solid fa-right-from-bracket me-3"></i>Logout</a>

    <div class="sidebar-footer mt-auto pt-4 border-top text-white-50">
        <small class="text-secondary">Need help?</small>
        <p class="mb-0 small">Contact system administrator for support.</p>
    </div>
</aside>
