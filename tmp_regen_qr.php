<?php
require __DIR__ . '/includes/admin_functions.php';
$id = 'CUST-000002';
$path = save_customer_qr_image($id);
echo "save_customer_qr_image returned: "; var_export($path); echo "\n";
if ($path && file_exists($path)) {
    echo "size=" . filesize($path) . "\n";
    echo "head=" . bin2hex(substr(file_get_contents($path),0,16)) . "\n";
}
?>