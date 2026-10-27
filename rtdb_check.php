<?php
$config = require __DIR__ . '/config.php';
$baseUrl = trim((string) ($config['firebase_database_url'] ?? ''));
if ($baseUrl === '') {
    echo "Firebase database URL is not configured.\n";
    exit(0);
}

$urls = [
    'http' => preg_replace('#^https://#i', 'http://', rtrim($baseUrl, '/')) . '/.json',
    'https' => rtrim($baseUrl, '/') . '/.json',
];
foreach ($urls as $scheme => $url) {
    echo "Testing $scheme URL: $url\n";
    $data = @file_get_contents($url);
    if ($data === false) {
        echo "Fetch failed\n";
        var_dump(error_get_last());
        echo "---\n";
        continue;
    }
    echo "Fetch OK, length=" . strlen($data) . "\n";
    $decoded = json_decode($data, true);
    echo "JSON decode: ";
    var_export($decoded);
    echo "\n---\n";
}
