<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$navLinks = [
    'dashboard.php' => ['label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'section' => 'Main'],
    'customer_scanner.php' => ['label' => 'Customer Scanner', 'icon' => 'fa-qrcode', 'section' => 'Operations'],
];
$sections = [];
foreach ($navLinks as $file => $link) {
    $sections[$link['section']] = $sections[$link['section']] ?? [];
    $sections[$link['section']][$file] = $link;
}
?>
<aside class="sidebar d-flex flex-column p-4">
    <div class="sidebar-brand mb-4">
        <a href="/inspector/dashboard.php" class="d-flex align-items-center text-decoration-none">
            <div class="brand-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                <i class="fa-solid fa-seedling"></i>
            </div>
            <div>
                <h5 class="mb-0 text-white">MIC Inspector</h5>
                <small class="text-secondary">Inspector Portal</small>
            </div>
        </a>
    </div>

    <?php foreach ($sections as $sectionLabel => $links): ?>
        <div class="sidebar-section text-uppercase text-muted small mb-2"><?php echo $sectionLabel; ?></div>
        <nav class="nav flex-column gap-1 mb-3">
            <?php foreach ($links as $file => $link): ?>
                <a class="nav-link<?php echo $currentPage === $file ? ' active' : ''; ?>" href="/inspector/<?php echo $file; ?>">
                    <i class="fa-solid <?php echo $link['icon']; ?> me-3"></i>
                    <span><?php echo $link['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>

    <div class="sidebar-section text-uppercase text-muted small mt-4 mb-2">More</div>
    <a class="nav-link logout-link" href="/inspector/logout.php"><i class="fa-solid fa-right-from-bracket me-3"></i>Logout</a>

    <div class="sidebar-footer mt-auto pt-4 border-top text-white-50">
        <small class="text-secondary">Need help?</small>
        <p class="mb-0 small">Contact your supervisor for support.</p>
    </div>
</aside>
