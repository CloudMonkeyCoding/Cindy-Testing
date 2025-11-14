<?php
require_once __DIR__ . '/action_helpers.php';

startJsonResponse(true);
requirePostRequest();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/delivery_functions.php';

requireDatabaseConnection($pdo);

$token = $_POST['csrf_token'] ?? '';
requireCsrfToken($token);

$action = $_POST['action'] ?? '';
if ($action !== 'update') {
    sendJsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}

$deliveryId = filter_input(INPUT_POST, 'delivery_id', FILTER_VALIDATE_INT);
if (!$deliveryId) {
    sendJsonResponse(['success' => false, 'message' => 'Invalid delivery ID'], 400);
}

$status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Pending';
$date = filter_input(INPUT_POST, 'delivery_date', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$personnel = filter_input(INPUT_POST, 'delivery_personnel', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;

updateDelivery($pdo, $deliveryId, $status, $date, $personnel);
$updated = getDeliveryById($pdo, $deliveryId);

sendJsonResponse(['success' => true, 'delivery' => $updated]);
