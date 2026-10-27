<?php
require __DIR__ . '/includes/admin_functions.php';
$targetId = 'CUST-000002';
$customers = firebase_get('customers');
if (!is_array($customers)) { echo "NO_CUSTOMERS\n"; var_export($customers); exit; }
$found = null;
foreach ($customers as $k => $c) {
    if (!empty($c['customer_id']) && $c['customer_id'] === $targetId) { $found = ['key'=>$k,'data'=>$c]; break; }
}
if (!$found) { echo "CUSTOMER_NOT_FOUND\n"; exit; }
$c = $found['data'];
$imgUrl = $c['qr_image'] ?? '';
echo "Customer key: " . $found['key'] . "\n";
echo "qr_image field: " . $imgUrl . "\n";
$fsPath = __DIR__ . '/' . ltrim($imgUrl, '/');
echo "Filesystem path: " . $fsPath . "\n";
if (!file_exists($fsPath)) { echo "FILE_EXISTS: no\n"; exit; }
echo "FILE_EXISTS: yes\n";
echo "SIZE: " . filesize($fsPath) . " bytes\n";
$fh = fopen($fsPath, 'rb');
$head = fread($fh, 16);
fclose($fh);
$hex = bin2hex($head);
echo "HEAD_BYTES(hex): " . $hex . "\n";
$pngSig = "89504e470d0a1a0a"; // hex
if (strpos($hex, $pngSig) === 0) echo "PNG_SIGNATURE: yes\n"; else echo "PNG_SIGNATURE: no\n";
// curl HEAD
$ch = curl_init('http://127.0.0.1:8000' . $imgUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP_HEAD_code: " . $httpCode . "\n";
if ($err) echo "CURL_ERR: " . $err . "\n";
echo "HEAD_RESPONSE_HEADER_SNIPPET:\n" . substr($response,0,400) . "\n";
