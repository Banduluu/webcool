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

function firebase_request_with_metadata(string $method, string $path, $data = null, array $additionalHeaders = []): array
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
            'header' => implode("\r\n", array_merge(build_firebase_headers($token), $additionalHeaders)) . "\r\n",
        ],
    ];

    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);

    global $http_response_header;
    $statusCode = 200;
    $responseHeaders = is_array($http_response_header ?? null) ? $http_response_header : [];
    if ($responseHeaders) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $responseHeaders[0], $matches);
        if (!empty($matches[1])) {
            $statusCode = (int) $matches[1];
        }
    }

    return [
        'status' => $statusCode,
        'body' => $response,
        'headers' => $responseHeaders,
        'error' => $response === false ? 'Unable to reach Firebase Realtime Database.' : null,
    ];
}

function firebase_request(string $method, string $path, $data = null)
{
    $request = firebase_request_with_metadata($method, $path, $data);
    if ($request['error'] !== null) {
        return ['error' => $request['error']];
    }

    if ($request['status'] >= 400) {
        return ['error' => 'Firebase request failed with HTTP ' . $request['status']];
    }

    if ($request['body'] === '') {
        $result = ['success' => true];
    } else {
        $decoded = json_decode($request['body'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = $decoded === null && strtoupper($method) !== 'GET'
                ? ['success' => true]
                : $decoded;
        } else {
            $result = ['raw' => $request['body']];
        }
    }

    if (strtoupper($method) !== 'GET') {
        $activity = describe_firebase_activity($method, $path);
        if ($activity !== null) {
            record_activity_log($activity['action'], $activity['resource']);
        }
    }

    return $result;
}

function describe_firebase_activity(string $method, string $path): ?array
{
    $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    if (!$segments || in_array($segments[0], ['activity_logs', 'mic_control_number_counters'], true)) {
        return null;
    }

    $operation = [
        'POST' => 'Created',
        'PUT' => 'Saved',
        'PATCH' => 'Updated',
        'DELETE' => 'Deleted',
    ][strtoupper($method)] ?? null;
    if ($operation === null) {
        return null;
    }

    $resource = $segments[0];
    if ($resource === 'users' && ($segments[1] ?? '') === 'inspectors') {
        $resourceLabel = 'inspector account';
    } else {
        $resourceLabel = [
            'inspections' => 'inspection record',
            'certificates' => 'MIC certificate',
            'customers' => 'customer record',
            'client_registrations' => 'customer registration',
        ][$resource] ?? str_replace('_', ' ', $resource);
    }

    return [
        'action' => $operation . ' ' . $resourceLabel,
        'resource' => implode('/', $segments),
    ];
}

function record_activity_log(string $action, string $resource = ''): bool
{
    $actorRole = !empty($_SESSION['admin_logged_in'])
        ? 'Admin'
        : (!empty($_SESSION['inspector_logged_in']) ? 'Inspector' : 'System');
    $actorName = $actorRole === 'Admin'
        ? (string) ($_SESSION['admin_username'] ?? 'Admin')
        : ($actorRole === 'Inspector' ? (string) ($_SESSION['inspector_username'] ?? 'Inspector') : 'System');
    $entry = [
        'action' => $action,
        'resource' => $resource,
        'actor_name' => $actorName,
        'actor_role' => $actorRole,
        'created_at' => date('c'),
    ];
    $result = firebase_request_with_metadata('POST', 'activity_logs', $entry);
    if ($result['error'] !== null || $result['status'] >= 400) {
        $reason = $result['error'] ?? ('Firebase request failed with HTTP ' . $result['status']);
        error_log('Unable to save activity log: ' . $reason);
        return false;
    }

    return true;
}

function firebase_increment_counter(string $path, int $minimumValue = 0): array
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $read = firebase_request_with_metadata('GET', $path, null, ['X-Firebase-ETag: true']);
        if ($read['error'] !== null) {
            return ['error' => $read['error']];
        }
        if ($read['status'] >= 400) {
            return ['error' => 'Firebase counter read failed with HTTP ' . $read['status']];
        }

        $etag = '';
        foreach ($read['headers'] as $header) {
            if (stripos($header, 'ETag:') === 0) {
                $etag = trim(substr($header, strlen('ETag:')));
                break;
            }
        }
        if ($etag === '') {
            return ['error' => 'Firebase did not return a counter version.'];
        }

        $currentValue = json_decode((string) $read['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE || ($currentValue !== null && !is_numeric($currentValue))) {
            return ['error' => 'Firebase counter contains an invalid value.'];
        }
        $nextValue = max($minimumValue, (int) $currentValue) + 1;
        $write = firebase_request_with_metadata('PUT', $path, $nextValue, ['If-Match: ' . $etag]);
        if ($write['error'] !== null) {
            return ['error' => $write['error']];
        }
        if ($write['status'] === 412) {
            continue;
        }
        if ($write['status'] >= 400) {
            return ['error' => 'Firebase counter update failed with HTTP ' . $write['status']];
        }

        return ['value' => $nextValue];
    }

    return ['error' => 'Unable to reserve a unique MIC control number after several attempts.'];
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
