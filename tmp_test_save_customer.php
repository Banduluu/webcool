<?php
require __DIR__ . '/includes/admin_functions.php';

$customerId = 'TEST-CUST-' . time();
$data = [
    'customer_id' => $customerId,
    'owner_name' => 'Test Owner',
    'contact_number' => '1234567890',
    'email' => 'test@example.com',
    'address' => '123 Test St',
    'origin' => 'TestVille',
    'province' => 'TestProvince',
    'remarks' => 'Automated test',
    'created_at' => date('Y-m-d H:i:s'),
];

$qr = save_customer_qr_image($customerId);
if ($qr !== null) {
    $data['qr_image'] = customer_qr_image_web_path($customerId);
}

$result = firebase_post('customers', $data);
echo "POST RESULT:\n";
var_export($result);
echo PHP_EOL;
