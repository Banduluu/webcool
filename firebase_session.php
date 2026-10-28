<?php
require_once __DIR__ . '/includes/admin_functions.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$idToken = trim((string) ($payload['id_token'] ?? ''));
$email = strtolower(trim((string) ($payload['email'] ?? '')));
$expectedEmail = strtolower(trim((string) ($payload['expected_email'] ?? '')));
$isRegistration = !empty($payload['register']);
if ($idToken === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid Firebase account is required.']);
    exit;
}

$apiKey = (string) (get_config()['firebase_web']['api_key'] ?? '');
$url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode($apiKey);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['idToken' => $idToken]),
        'ignore_errors' => true,
    ],
]);
$response = @file_get_contents($url, false, $context);
$verified = is_string($response) ? json_decode($response, true) : null;
$verifiedEmail = strtolower((string) ($verified['users'][0]['email'] ?? ''));
$emailVerified = !empty($verified['users'][0]['emailVerified']);
if ($verifiedEmail === '' || !hash_equals($verifiedEmail, $email)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Firebase account verification failed.']);
    exit;
}
if (!$emailVerified) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please verify your email address before accessing the system.']);
    exit;
}
if ($expectedEmail !== '' && (!filter_var($expectedEmail, FILTER_VALIDATE_EMAIL) || !hash_equals($verifiedEmail, $expectedEmail))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'The Google account email must match the email you entered.']);
    exit;
}

$inspectors = firebase_get('users/inspectors');
$matchedKey = null;
$matchedInspector = null;
if (is_array($inspectors)) {
    foreach ($inspectors as $key => $inspector) {
        if (is_array($inspector) && strtolower((string) ($inspector['email'] ?? '')) === $verifiedEmail) {
            $matchedKey = $key;
            $matchedInspector = $inspector;
            break;
        }
    }
}

if ($matchedKey === null) {
    $result = firebase_post('users/inspectors', [
        'name' => $verified['users'][0]['displayName'] ?? $verifiedEmail,
        'username' => $verifiedEmail,
        'email' => $verifiedEmail,
        'phone' => '',
        'position' => 'Meat Inspector',
        'status' => 'Active',
        'auth_provider' => 'firebase',
        'firebase_uid' => $verified['users'][0]['localId'] ?? '',
        'created_at' => date('c'),
    ]);
    if (!is_array($result) || empty($result['name'])) {
        http_response_code(502);
        echo json_encode(['success' => false, 'message' => 'The email is verified, but the inspector profile could not be created.']);
        exit;
    }
    $matchedInspector = [
        'name' => $verified['users'][0]['displayName'] ?? $verifiedEmail,
        'username' => $verifiedEmail,
        'status' => 'Active',
        'position' => 'Meat Inspector',
    ];
}

if (($matchedInspector['status'] ?? 'Active') === 'Inactive') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This inspector account is inactive.']);
    exit;
}
$_SESSION['inspector_logged_in'] = true;
$_SESSION['inspector_username'] = $verifiedEmail;
record_activity_log('Signed in');
echo json_encode(['success' => true, 'redirect' => '/inspector/dashboard.php']);
