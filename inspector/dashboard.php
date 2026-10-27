<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_inspector_logged_in();

$inspections = array_values(array_filter(get_array_values(firebase_get('inspections')), 'is_array'));
$customers = array_values(array_filter(get_array_values(firebase_get('customers')), 'is_array'));
$today = date('Y-m-d');
$todayInspections = array_values(array_filter($inspections, static function (array $inspection) use ($today): bool {
    return substr((string) ($inspection['inspection_date'] ?? $inspection['created_at'] ?? ''), 0, 10) === $today;
}));
$dashboardStats = [
    ['label' => 'Registered Customers', 'value' => count($customers), 'icon' => 'fa-users', 'tone' => 'green'],
    ['label' => 'QR Codes Generated', 'value' => count(array_filter($inspections, fn($inspection) => !empty($inspection['qr_code_value']))), 'icon' => 'fa-qrcode', 'tone' => 'teal'],
    ['label' => 'Inspections', 'value' => count($todayInspections), 'icon' => 'fa-clipboard-check', 'tone' => 'blue'],
    ['label' => 'Ante Mortem', 'value' => count(array_filter($todayInspections, fn($inspection) => !empty($inspection['inspection_data']['ante_mortem']))), 'icon' => 'fa-file-circle-check', 'tone' => 'orange'],
];
$dashboardFunctions = [
    ['title' => 'Customer Registration', 'description' => 'Enter customer information and generate a QR code.', 'icon' => 'fa-user-plus', 'href' => '/inspector/customer_registration.php'],
    ['title' => 'Scan Customer QR Code', 'description' => 'Identify the customer using their unique code.', 'icon' => 'fa-qrcode', 'href' => '/inspector/customer_scanner.php'],
    ['title' => 'Ante Mortem List', 'description' => 'View saved animal details and documents before inspection.', 'icon' => 'fa-clipboard-list', 'href' => '/inspector/ante_mortems.php'],
    ['title' => 'Condemnation', 'description' => 'Record condemned animals or affected carcass parts and the authorized disposition.', 'icon' => 'fa-file-circle-exclamation', 'href' => '/inspector/condemnation.php'],
    ['title' => 'Meat Inspection Certificates', 'description' => 'View and manage issued meat inspection certificates.', 'icon' => 'fa-file-circle-check', 'href' => '/inspector/certificates.php'],
];

require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="main-content inspector-dashboard">
    <?php require_once __DIR__ . '/../includes/inspector_navbar.php'; ?>
    <div class="container-fluid px-4 py-4">
        <div class="inspector-dashboard-hero mb-4">
            <div>
                <span class="inspector-dashboard-kicker">Meat Inspection System</span>
                <h1 class="mb-2">Meat Inspector Dashboard</h1>
                <p class="mb-0">Manage customer registration, animal inspections, and certificates.</p>
            </div>
        </div>

        <div class="inspector-dashboard-stats mb-4">
            <?php foreach ($dashboardStats as $stat): ?>
                <div class="inspector-stat-card">
                    <div class="inspector-stat-icon <?php echo $stat['tone']; ?>"><i class="fa-solid <?php echo $stat['icon']; ?>"></i></div>
                    <div>
                        <p class="inspector-stat-label mb-2"><?php echo htmlspecialchars($stat['label']); ?></p>
                        <strong class="inspector-stat-value"><?php echo htmlspecialchars((string) $stat['value']); ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="inspector-functions mb-4" aria-labelledby="mainFunctionsTitle">
            <h2 id="mainFunctionsTitle">Main Functions</h2>
            <div class="inspector-function-list">
                <?php foreach ($dashboardFunctions as $function): ?>
                    <a class="inspector-function-link" href="<?php echo htmlspecialchars($function['href']); ?>">
                        <span class="inspector-function-icon"><i class="fa-solid <?php echo $function['icon']; ?>"></i></span>
                        <span class="inspector-function-copy">
                            <strong><?php echo htmlspecialchars($function['title']); ?></strong>
                            <small><?php echo htmlspecialchars($function['description']); ?></small>
                        </span>
                        <i class="fa-solid fa-chevron-right inspector-function-arrow" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
