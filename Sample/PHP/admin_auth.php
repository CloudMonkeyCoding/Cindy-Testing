<?php
require_once __DIR__ . '/action_helpers.php';

startJsonResponse(true);
requirePostRequest('Only POST requests are allowed');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/store_staff_functions.php';
require_once __DIR__ . '/audit_log_functions.php';

requireDatabaseConnection($pdo);

function readJsonBody(): array
{
    $rawInput = file_get_contents('php://input');
    if ($rawInput === false || $rawInput === '') {
        return [];
    }

    $decoded = json_decode($rawInput, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
}

if (!function_exists('normalizeFaceImagePath')) {
    function normalizeFaceImagePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim((string) $path);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return $trimmed;
        }

        $normalized = str_replace('\\', '/', $trimmed);
        if ($normalized === '') {
            return null;
        }

        return $normalized[0] === '/' ? $normalized : '/' . ltrim($normalized, '/');
    }
}

function firebaseSignIn(string $apiKey, string $email, string $password): array
{
    $endpoint = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . urlencode($apiKey);

    $payload = json_encode([
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true,
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
    ]);

    $responseBody = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'success' => false,
            'status' => 502,
            'message' => 'Unable to reach the authentication service. Please try again.',
            'errorCode' => null,
        ];
    }

    $decoded = json_decode($responseBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'status' => 500,
            'message' => 'Received an unexpected response from the authentication service.',
            'errorCode' => null,
        ];
    }

    if ($statusCode !== 200) {
        $firebaseCode = $decoded['error']['message'] ?? 'UNKNOWN_ERROR';

        $messageMap = [
            'EMAIL_NOT_FOUND' => 'No account found with that email address.',
            'INVALID_PASSWORD' => 'Incorrect password. Please try again.',
            'USER_DISABLED' => 'This account has been disabled. Contact support for assistance.',
            'TOO_MANY_ATTEMPTS_TRY_LATER' => 'Too many failed attempts. Please wait a moment and try again.',
        ];

        $message = $messageMap[$firebaseCode] ?? 'Login failed. Please check your credentials and try again.';
        $httpStatus = $firebaseCode === 'USER_DISABLED' ? 403 : 401;

        return [
            'success' => false,
            'status' => $httpStatus,
            'message' => $message,
            'errorCode' => $firebaseCode,
        ];
    }

    return [
        'success' => true,
        'status' => 200,
        'message' => 'Authenticated',
        'email' => $decoded['email'] ?? $email,
        'localId' => $decoded['localId'] ?? null,
        'idToken' => $decoded['idToken'] ?? null,
    ];
}

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;

/**
 * @param array<string, mixed> $options
 */
function logAdminAuthEvent($pdo, string $eventType, string $message, array $options = []): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

    global $remoteAddress;

    $meta = [];
    if (isset($options['metadata']) && is_array($options['metadata'])) {
        $meta = $options['metadata'];
    }
    if ($remoteAddress) {
        $meta['ip_address'] = $remoteAddress;
    }

    record_audit_log($pdo, $eventType, $message, [
        'actor_email' => $options['actor_email'] ?? ($options['email'] ?? null),
        'actor_id' => $options['actor_id'] ?? null,
        'source' => 'admin_auth',
        'metadata' => $meta,
    ]);
}

$data = readJsonBody();
if (!$data) {
    $data = $_POST;
}

$email = isset($data['email']) ? trim((string) $data['email']) : '';
$password = isset($data['password']) ? (string) $data['password'] : '';

if ($email === '' || $password === '') {
    logAdminAuthEvent($pdo, 'admin_login_validation_failed', 'Login blocked: missing email or password.', [
        'actor_email' => $email,
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'Email and password are required.'
    ], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAdminAuthEvent($pdo, 'admin_login_validation_failed', 'Login blocked: invalid email format.', [
        'actor_email' => $email,
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ], 422);
}

$apiKey = $_ENV['FIREBASE_API_KEY'] ?? getenv('FIREBASE_API_KEY');
if (!$apiKey) {
    logAdminAuthEvent($pdo, 'admin_login_error', 'Login failed: Firebase API key is not configured.', [
        'actor_email' => $email,
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'Authentication service is not configured. Please contact support.'
    ], 500);
}

$firebaseResult = firebaseSignIn($apiKey, $email, $password);
if (!$firebaseResult['success']) {
    logAdminAuthEvent($pdo, 'admin_login_failed', 'Firebase rejected admin login attempt.', [
        'actor_email' => $email,
        'metadata' => [
            'firebase_code' => $firebaseResult['errorCode'] ?? null,
            'status' => $firebaseResult['status'] ?? null,
        ],
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => $firebaseResult['message'],
        'code' => $firebaseResult['errorCode'] ?? null,
    ], $firebaseResult['status']);
}

$user = getUserByEmail($pdo, $firebaseResult['email']);
if (!$user) {
    logAdminAuthEvent($pdo, 'admin_login_denied', 'Login denied: user not found in admin database.', [
        'actor_email' => $firebaseResult['email'] ?? $email,
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'Your account is not registered in the admin database.'
    ], 403);
}

$staffRecord = getStoreStaffByUserId($pdo, (int) $user['User_ID']);
if (!$staffRecord) {
    logAdminAuthEvent($pdo, 'admin_login_denied', 'Login denied: user lacks staff access.', [
        'actor_email' => $firebaseResult['email'] ?? $email,
        'actor_id' => (int) $user['User_ID'],
    ]);
    sendJsonResponse([
        'success' => false,
        'message' => 'You must be marked as an employee or super admin to access the admin portal.'
    ], 403);
}

$isSuperAdmin = !empty($staffRecord['Is_Super_Admin']) && (int) $staffRecord['Is_Super_Admin'] === 1;
$isEmployee = !$isSuperAdmin;
$storeStaffId = isset($staffRecord['Store_Staff_ID']) ? (int) $staffRecord['Store_Staff_ID'] : null;

session_regenerate_id(true);
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_user_id'] = (int) $user['User_ID'];
$_SESSION['admin_name'] = $user['Name'] ?? 'Admin';
$_SESSION['admin_email'] = $user['Email'] ?? $firebaseResult['email'];
$_SESSION['admin_firebase_local_id'] = $firebaseResult['localId'] ?? null;
$_SESSION['admin_last_activity'] = time();
$_SESSION['admin_is_super_admin'] = $isSuperAdmin;
$_SESSION['admin_is_employee'] = $isEmployee;
$_SESSION['admin_has_staff_access'] = true;
if ($storeStaffId) {
    $_SESSION['admin_store_staff_id'] = $storeStaffId;
}

$faceImagePath = normalizeFaceImagePath($user['Face_Image_Path'] ?? null);
if ($faceImagePath) {
    $_SESSION['admin_face_image_path'] = $faceImagePath;
} else {
    unset($_SESSION['admin_face_image_path']);
}

logAdminAuthEvent($pdo, 'admin_login_success', 'Admin login successful.', [
    'actor_email' => $_SESSION['admin_email'] ?? $firebaseResult['email'] ?? $email,
    'actor_id' => (int) $user['User_ID'],
    'metadata' => [
        'is_super_admin' => $isSuperAdmin,
        'store_staff_id' => $storeStaffId,
    ],
]);

sendJsonResponse([
    'success' => true,
    'message' => 'Login successful.'
]);
