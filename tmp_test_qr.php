<?php
require __DIR__ . '/includes/admin_functions.php';
$result = save_customer_qr_image('TEST-QR-0001');
echo "RESULT: ";
var_export($result);
echo PHP_EOL;
if ($result !== null) {
    echo "EXISTS: " . (file_exists($result) ? 'yes' : 'no') . PHP_EOL;
    if (file_exists($result)) {
        echo "SIZE: " . filesize($result) . PHP_EOL;
    }
}
