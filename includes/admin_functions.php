<?php
date_default_timezone_set('Asia/Manila');

session_start();
require_once __DIR__ . '/firebase.php';

function get_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function get_admin_settings(): array
{
    static $settings;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [];
    $settingsPath = __DIR__ . '/../data/settings.json';
    if (is_file($settingsPath)) {
        $contents = file_get_contents($settingsPath);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }

    return $settings;
}

function save_admin_settings(array $settings): bool
{
    $settingsDirectory = __DIR__ . '/../data';
    if (!is_dir($settingsDirectory) && !mkdir($settingsDirectory, 0750, true) && !is_dir($settingsDirectory)) {
        return false;
    }

    $encoded = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    return file_put_contents($settingsDirectory . '/settings.json', $encoded . PHP_EOL, LOCK_EX) !== false;
}

function get_admin_account(): array
{
    $configAccount = get_config()['admin_user'] ?? [];
    $settings = get_admin_settings();
    return [
        'username' => (string) ($settings['admin_username'] ?? $configAccount['username'] ?? ''),
        'password_hash' => (string) ($settings['admin_password_hash'] ?? $configAccount['password_hash'] ?? ''),
    ];
}

function get_current_inspector_profile(): array
{
    $currentIdentity = strtolower(trim((string) ($_SESSION['inspector_username'] ?? '')));
    if ($currentIdentity === '') {
        return [];
    }

    $inspectors = firebase_get('users/inspectors');
    if (is_array($inspectors)) {
        foreach ($inspectors as $inspector) {
            if (
                is_array($inspector)
                && (
                    strtolower(trim((string) ($inspector['email'] ?? ''))) === $currentIdentity
                    || strtolower(trim((string) ($inspector['username'] ?? ''))) === $currentIdentity
                )
            ) {
                return $inspector;
            }
        }

        $legacyUsername = strtolower(trim((string) (get_config()['inspector_user']['username'] ?? '')));
        $inspectorProfiles = array_values(array_filter($inspectors, 'is_array'));
        if ($legacyUsername !== '' && $currentIdentity === $legacyUsername && count($inspectorProfiles) === 1) {
            return $inspectorProfiles[0];
        }
    }

    return [];
}

function get_current_inspector_name(): string
{
    $profile = get_current_inspector_profile();
    return trim((string) ($profile['name'] ?? '')) ?: (string) ($_SESSION['inspector_username'] ?? 'Inspector');
}

function ensure_admin_logged_in(): void
{
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /login.php');
        exit;
    }
}

function ensure_inspector_logged_in(): void
{
    if (empty($_SESSION['inspector_logged_in']) || $_SESSION['inspector_logged_in'] !== true) {
        header('Location: /login.php');
        exit;
    }
}

function admin_login(string $username, string $password): bool
{
    $account = get_admin_account();
    if ($username !== $account['username']) {
        return false;
    }
    return password_verify($password, $account['password_hash']);
}

function inspector_login(string $username, string $password): bool
{
    $config = get_config();
    if (empty($config['inspector_user']) || $username !== $config['inspector_user']['username']) {
        return false;
    }
    return password_verify($password, $config['inspector_user']['password_hash']);
}

function sanitize_text(string $value): string
{
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

function sanitize_phone(string $value): string
{
    return trim(preg_replace('/[^0-9+\-\s()]/', '', $value));
}

function sanitize_number(string $value): string
{
    return trim(preg_replace('/[^0-9.]/', '', $value));
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function count_status(array $items, string $status): int
{
    $count = 0;
    foreach ($items as $item) {
        if (isset($item['status']) && $item['status'] === $status) {
            $count++;
        }
    }
    return $count;
}

function get_items_count_by_date(array $items, string $dateField, string $targetDate, ?string $status = null, ?string $statusField = 'status'): int
{
    $count = 0;
    foreach ($items as $item) {
        if (empty($item[$dateField]) || strpos($item[$dateField], $targetDate) !== 0) {
            continue;
        }
        if ($status !== null && (!isset($item[$statusField]) || $item[$statusField] !== $status)) {
            continue;
        }
        $count++;
    }
    return $count;
}

function percent_change(int $current, int $previous): string
{
    if ($previous === 0) {
        return $current === 0 ? '0.0%' : '+100.0%';
    }
    $change = (($current - $previous) / $previous) * 100;
    return ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
}

function get_array_values($data): array
{
    if (!is_array($data)) {
        return [];
    }
    return array_values($data);
}

function generate_inspection_id(array $existingInspections): string
{
    $year = date('Y');
    $prefix = 'MIC-' . $year . '-';
    $numbers = [];
    foreach ($existingInspections as $inspection) {
        if (!empty($inspection['inspection_id']) && str_starts_with($inspection['inspection_id'], $prefix)) {
            $numbers[] = (int) substr($inspection['inspection_id'], strlen($prefix));
        }
    }
    sort($numbers, SORT_NUMERIC);
    $nextNumber = $numbers ? max($numbers) + 1 : 1;
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}

function generate_mic_control_number(array $inspections, array $certificates): array
{
    $year = date('Y');
    $prefix = 'MIC-' . $year . '-';
    $maximumSequence = 0;

    foreach (array_merge($inspections, $certificates) as $record) {
        if (!is_array($record)) {
            continue;
        }
        foreach (['control_number', 'mic_number'] as $numberField) {
            $storedNumber = trim((string) ($record[$numberField] ?? ''));
            if (preg_match('/^MIC-' . preg_quote($year, '/') . '-(\d+)$/', $storedNumber, $matches)) {
                $maximumSequence = max($maximumSequence, (int) $matches[1]);
            }
        }
    }

    $counter = firebase_increment_counter('mic_control_number_counters/' . $year, $maximumSequence);
    if (!isset($counter['value'])) {
        return ['error' => (string) ($counter['error'] ?? 'Unable to generate a MIC control number.')];
    }

    return ['control_number' => $prefix . str_pad((string) $counter['value'], 4, '0', STR_PAD_LEFT)];
}

function get_certificate_mic_numbers(array $inspections, array $certificates): array
{
    $micNumbers = [];
    $usedNumbers = [];
    $numberingRecords = $inspections;

    foreach ($certificates as $certificate) {
        if (!is_array($certificate)) {
            continue;
        }
        $certificateId = (string) ($certificate['certificate_id'] ?? '');
        $micNumber = trim((string) ($certificate['mic_number'] ?? ''));
        if ($certificateId === '' || $micNumber === '' || isset($usedNumbers[$micNumber])) {
            continue;
        }
        $micNumbers[$certificateId] = $micNumber;
        $usedNumbers[$micNumber] = true;
        $numberingRecords[] = ['inspection_id' => $micNumber];
    }

    foreach ($certificates as $certificate) {
        if (!is_array($certificate)) {
            continue;
        }
        $certificateId = (string) ($certificate['certificate_id'] ?? '');
        if ($certificateId === '' || isset($micNumbers[$certificateId])) {
            continue;
        }

        $inspectionId = trim((string) ($certificate['inspection_id'] ?? ''));
        if ($inspectionId !== '' && !isset($usedNumbers[$inspectionId])) {
            $micNumbers[$certificateId] = $inspectionId;
            $usedNumbers[$inspectionId] = true;
            continue;
        }

        $micNumber = generate_inspection_id($numberingRecords);
        while (isset($usedNumbers[$micNumber])) {
            $numberingRecords[] = ['inspection_id' => $micNumber];
            $micNumber = generate_inspection_id($numberingRecords);
        }
        $micNumbers[$certificateId] = $micNumber;
        $usedNumbers[$micNumber] = true;
        $numberingRecords[] = ['inspection_id' => $micNumber];
    }

    return $micNumbers;
}

function generate_customer_id(array $existingCustomers): string
{
    $prefix = 'CUST-';
    $numbers = [];
    foreach ($existingCustomers as $customer) {
        if (!empty($customer['customer_id']) && str_starts_with($customer['customer_id'], $prefix)) {
            $numbers[] = (int) substr($customer['customer_id'], strlen($prefix));
        }
    }
    sort($numbers, SORT_NUMERIC);
    $nextNumber = $numbers ? max($numbers) + 1 : 1;
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}

function customer_qr_url(string $customerId): string
{
    $encoded = rawurlencode($customerId);
    // Use api.qrserver.com which returns raw PNG reliably for server-side fetches.
    return "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={$encoded}";
}

function customer_qr_image_file_path(string $customerId): string
{
    return __DIR__ . '/../uploads/qrcodes/' . $customerId . '.png';
}

function customer_qr_image_web_path(string $customerId): string
{
    return '/uploads/qrcodes/' . $customerId . '.png';
}

function save_customer_qr_image(string $customerId): ?string
{
    $qrUrl = customer_qr_url($customerId);
    $qrDir = __DIR__ . '/../uploads/qrcodes';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0755, true);
    }

    $filePath = $qrDir . '/' . $customerId . '.png';
    $imageData = null;

    // Try cURL first for predictable headers
    if (function_exists('curl_version')) {
        $ch = curl_init($qrUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (QR fetch)');
        $imageData = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($imageData === false || $imageData === null || $imageData === '') {
            return null;
        }
        // Basic content-type check
        if (is_string($contentType) && stripos($contentType, 'image/') !== 0) {
            return null;
        }
    } else {
        // Fallback to file_get_contents
        if (ini_get('allow_url_fopen')) {
            $imageData = @file_get_contents($qrUrl);
            if ($imageData === false || $imageData === null || $imageData === '') {
                return null;
            }
        } else {
            return null;
        }
    }

    // Verify PNG signature (or at least starts with expected image bytes)
    if (strlen($imageData) < 8 || substr($imageData, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }

    $written = @file_put_contents($filePath, $imageData);
    if ($written === false) {
        return null;
    }
    return $filePath;
}

function generate_registration_id(array $existingRegistrations): string
{
    $year = date('Y');
    $prefix = 'REG-' . $year . '-';
    $numbers = [];
    foreach ($existingRegistrations as $registration) {
        if (!empty($registration['inspection_id']) && str_starts_with($registration['inspection_id'], $prefix)) {
            $numbers[] = (int) substr($registration['inspection_id'], strlen($prefix));
        }
    }
    sort($numbers, SORT_NUMERIC);
    $nextNumber = $numbers ? max($numbers) + 1 : 1;
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}

function generate_batch_id(array $existingInspections): string
{
    $year = date('Y');
    $prefix = 'BATCH-' . $year . '-';
    $numbers = [];
    foreach ($existingInspections as $inspection) {
        if (!empty($inspection['batch_id']) && str_starts_with($inspection['batch_id'], $prefix)) {
            $numbers[] = (int) substr($inspection['batch_id'], strlen($prefix));
        }
    }
    sort($numbers, SORT_NUMERIC);
    $nextNumber = $numbers ? max($numbers) + 1 : 1;
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}

function inspection_qr_url(string $inspectionId): string
{
    $encoded = rawurlencode($inspectionId);
    return "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={$encoded}";
}

function public_certificate_url(string $certificateId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/certificate.php?id=' . rawurlencode($certificateId);
}

function verification_qr_url(string $certificateId): string
{
    $url = public_certificate_url($certificateId);
    $encoded = rawurlencode($url);
    return "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={$encoded}";
}

function inspection_status_badge_class(string $status): string
{
    return match ($status) {
        'Waiting for Client' => 'bg-warning text-dark',
        'Slaughter' => 'bg-primary text-white',
        'Passed' => 'bg-success text-white',
        'Partially Condemned' => 'bg-warning text-dark',
        'Condemned' => 'bg-danger text-white',
        default => 'bg-light text-dark',
    };
}

function get_inspection_summary_counts(array $inspections, array $registrations): array
{
    $today = date('Y-m-d');
    $pendingRegistrations = count($registrations);
    $pendingInspections = count($inspections);
    $approvedToday = 0;
    $condemnedToday = 0;
    foreach ($inspections as $inspection) {
        $createdAt = $inspection['created_at'] ?? '';
        if (!empty($createdAt) && strpos($createdAt, $today) === 0) {
            if (($inspection['decision'] ?? '') === 'Passed') {
                $approvedToday++;
            }
            if (($inspection['decision'] ?? '') === 'Condemned') {
                $condemnedToday++;
            }
        }
    }
    return [
        'pendingRegistrations' => $pendingRegistrations,
        'pendingInspections' => $pendingInspections,
        'approvedToday' => $approvedToday,
        'condemnedToday' => $condemnedToday,
    ];
}

function generate_certificate_id(array $existingCertificates): string
{
    $year = date('Y');
    $prefix = 'CERT-' . $year . '-';
    $numbers = [];
    foreach ($existingCertificates as $certificate) {
        if (!empty($certificate['certificate_id']) && str_starts_with($certificate['certificate_id'], $prefix)) {
            $numbers[] = (int) substr($certificate['certificate_id'], strlen($prefix));
        }
    }
    sort($numbers, SORT_NUMERIC);
    $nextNumber = $numbers ? max($numbers) + 1 : 1;
    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}
