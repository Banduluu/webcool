<?php
function get_firebase_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function build_firebase_url(string $path): ?string
{
    $config = get_firebase_config();
    $baseUrl = trim((string) ($config['firebase_database_url'] ?? ''));
    if ($baseUrl === '') {
        return null;
    }

    $normalizedBase = rtrim($baseUrl, '/');
    $normalizedPath = trim($path, '/');
    $suffix = $normalizedPath === '' ? '/.json' : '/' . $normalizedPath . '.json';

    return $normalizedBase . $suffix;
}

function build_firebase_headers(string $token): array
{
    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    return $headers;
}

function firebase_request(string $method, string $path, $data = null)
{
    $config = get_firebase_config();
    $url = build_firebase_url($path);
    if ($url === null) {
        return ['error' => 'Firebase database URL is not configured.'];
    }

    $token = (string) ($config['firebase_auth_token'] ?? '');
    if ($token !== '') {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'auth=' . rawurlencode($token);
    }

    $body = null;
    if ($data !== null) {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['error' => 'Unable to encode Firebase payload.'];
        }
    }

    $contextOptions = [
        'http' => [
            'method' => strtoupper($method),
            'ignore_errors' => true,
            'header' => implode("\r\n", build_firebase_headers($token)) . "\r\n",
        ],
    ];

    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);

    global $http_response_header;
    $statusCode = 200;
    if (!empty($http_response_header)) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        if (!empty($matches[1])) {
            $statusCode = (int) $matches[1];
        }
    }

    if ($response === false) {
        return ['error' => 'Unable to reach Firebase Realtime Database.'];
    }

    if ($statusCode >= 400) {
        return ['error' => 'Firebase request failed with HTTP ' . $statusCode];
    }

    if ($response === '') {
        return ['success' => true];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if ($decoded === null && strtoupper($method) !== 'GET') {
            return ['success' => true];
        }
        return $decoded;
    }

    return ['raw' => $response];
}

function firebase_get(string $path)
{
    $result = firebase_request('get', $path);
    if (is_array($result) && isset($result['error'])) {
        return null;
    }
    return $result;
}

function firebase_put(string $path, $data)
{
    return firebase_request('put', $path, $data);
}

function firebase_patch(string $path, $data)
{
    return firebase_request('patch', $path, $data);
}

function firebase_delete(string $path)
{
    return firebase_request('delete', $path);
}

function firebase_post(string $path, $data)
{
    return firebase_request('post', $path, $data);
}
