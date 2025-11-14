<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/user_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$name = $input['fullName'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (!$name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Prevent duplicate registrations for the same email
if (checkEmailExists($pdo, $email)) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

try {
    $userId = addUser($pdo, $name, $email, $password, '', 0, null);
    echo json_encode(['success' => true, 'userId' => $userId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to register user']);
}
?>
