<?php
function startJsonResponse(bool $withSession = false): void
{
    if ($withSession && session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
}

function sendJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function requirePostRequest(string $errorMessage = 'Invalid request method'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(['success' => false, 'message' => $errorMessage], 405);
    }
}

function requireDatabaseConnection(?PDO $pdo): void
{
    if (!$pdo) {
        sendJsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    }
}

function requireCsrfToken(string $token): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 400);
    }
}
