<?php
require_once __DIR__ . '/../includes/admin_functions.php';
ensure_admin_logged_in();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';
$inspectorId = $_POST['inspector_id'] ?? '';
$name = sanitize_text($_POST['name'] ?? '');
$email = sanitize_text($_POST['email'] ?? '');
$phone = sanitize_phone($_POST['phone'] ?? '');
$status = sanitize_text($_POST['status'] ?? 'Inactive');
$password = trim($_POST['password'] ?? '');

if ($action === 'add') {
    if ($name === '' || $email === '' || $phone === '') {
        echo json_encode(['success' => false, 'message' => 'Name, email, and phone are required.']);
        exit;
    }

    $inspectorData = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'status' => $status === 'Active' ? 'Active' : 'Inactive',
        'created_at' => date('c'),
        'password_hash' => hash_password($password !== '' ? $password : 'inspector123'),
    ];

    $result = firebase_post('users/inspectors', $inspectorData);
    if (isset($result['name'])) {
        echo json_encode(['success' => true, 'message' => 'Inspector added successfully.', 'inspector_id' => $result['name']]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to add inspector.']);
    exit;
}

if ($action === 'edit') {
    if ($inspectorId === '' || $name === '' || $email === '' || $phone === '') {
        echo json_encode(['success' => false, 'message' => 'Inspector ID, name, email, and phone are required.']);
        exit;
    }

    $fields = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'status' => $status === 'Active' ? 'Active' : 'Inactive',
        'updated_at' => date('c'),
    ];

    $result = firebase_patch('users/inspectors/' . $inspectorId, $fields);
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Inspector profile updated successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to update inspector.']);
    exit;
}

if ($action === 'delete') {
    if ($inspectorId === '') {
        echo json_encode(['success' => false, 'message' => 'Inspector ID is required.']);
        exit;
    }

    $result = firebase_delete('users/inspectors/' . $inspectorId);
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Inspector deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to delete inspector.']);
    exit;
}

if ($action === 'toggle_status') {
    if ($inspectorId === '' || ($status !== 'Active' && $status !== 'Inactive')) {
        echo json_encode(['success' => false, 'message' => 'Inspector ID and status are required.']);
        exit;
    }

    $result = firebase_patch('users/inspectors/' . $inspectorId, ['status' => $status, 'updated_at' => date('c')]);
    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Inspector status updated successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to update inspector status.']);
    exit;
}

if ($action === 'reset_password') {
    if ($inspectorId === '') {
        echo json_encode(['success' => false, 'message' => 'Inspector ID is required.']);
        exit;
    }

    $result = firebase_patch('users/inspectors/' . $inspectorId, [
        'password_hash' => hash_password('inspector123'),
        'updated_at' => date('c'),
    ]);

    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully to default.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to reset password.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not recognized.']);
