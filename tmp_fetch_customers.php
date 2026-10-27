<?php
require __DIR__ . '/includes/admin_functions.php';
$customers = firebase_get('customers');
if (!is_array($customers)) {
    echo "NO_CUSTOMERS\n";
    var_export($customers);
    exit;
}
$found = false;
foreach ($customers as $k => $c) {
    if (!empty($c['qr_image'])) {
        echo "KEY:" . $k . "\n";
        echo "QR:" . $c['qr_image'] . "\n";
        $found = true;
        break;
    }
}
if (!$found) echo "NO_QR_FIELDS_FOUND\n";
