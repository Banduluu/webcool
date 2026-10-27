<?php
require __DIR__ . '/includes/admin_functions.php';
$customers = firebase_get('customers');
if (!is_array($customers)) { echo "NO_CUSTOMERS\n"; var_export($customers); exit; }
foreach ($customers as $k => $c) {
    if (!empty($c['customer_id']) && $c['customer_id'] === 'CUST-000002') {
        echo "KEY:" . $k . "\n";
        echo "customer_id: " . ($c['customer_id'] ?? '') . "\n";
        echo "qr_image: " . ($c['qr_image'] ?? '') . "\n";
        exit;
    }
}
echo "NOT_FOUND\n";
