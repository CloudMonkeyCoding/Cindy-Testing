<?php
// Endpoint to serve Firebase configuration from environment variables.
// Optionally requires a shared secret via the X-Firebase-Config-Token header.

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // assumes .env is in project root
$dotenv->load();

header('Content-Type: application/json');

$expectedToken = getenv('FIREBASE_CONFIG_TOKEN');
if ($expectedToken) {
    $providedToken = $_SERVER['HTTP_X_FIREBASE_CONFIG_TOKEN'] ?? '';
    if (!hash_equals($expectedToken, $providedToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

$requiredEnv = [
    'FIREBASE_API_KEY',
    'FIREBASE_AUTH_DOMAIN',
    'FIREBASE_PROJECT_ID',
    'FIREBASE_STORAGE_BUCKET',
    'FIREBASE_MESSAGING_SENDER_ID',
    'FIREBASE_APP_ID',
    'FIREBASE_MEASUREMENT_ID',
];

foreach ($requiredEnv as $envKey) {
    if (!isset($_ENV[$envKey]) || $_ENV[$envKey] === '') {
        http_response_code(500);
        echo json_encode(['error' => "Missing environment variable: {$envKey}"]);
        exit;
    }
}

$config = [
    'apiKey' => $_ENV['FIREBASE_API_KEY'],
    'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'],
    'projectId' => $_ENV['FIREBASE_PROJECT_ID'],
    'storageBucket' => $_ENV['FIREBASE_STORAGE_BUCKET'],
    'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'],
    'appId' => $_ENV['FIREBASE_APP_ID'],
    'measurementId' => $_ENV['FIREBASE_MEASUREMENT_ID'],
];

echo json_encode($config);
exit;

