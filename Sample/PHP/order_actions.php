<?php
require_once __DIR__ . '/action_helpers.php';

startJsonResponse(true);
requirePostRequest();

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/order_functions.php';
require_once __DIR__ . '/audit_log_functions.php';

requireDatabaseConnection($pdo);

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

requireCsrfToken($token);

switch ($action) {
    case 'update_status':
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Pending';
        $allowedStatuses = ['Pending', 'Confirmed', 'Shipped', 'Delivered'];
        if (!$orderId) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid order ID'], 400);
        }
        if (!in_array($status, $allowedStatuses, true)) {
            sendJsonResponse(['success' => false, 'message' => 'Unsupported status value'], 422);
        }
        updateOrderStatus($pdo, $orderId, $status);
        $adminId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
        $adminEmail = $_SESSION['admin_email'] ?? null;
        record_audit_log($pdo, 'order_status_updated', "Order #{$orderId} status updated to {$status}.", [
            'actor_id' => $adminId ?: null,
            'actor_email' => $adminEmail,
            'source' => 'order_actions',
            'metadata' => [
                'order_id' => $orderId,
                'status' => $status,
            ],
        ]);
        sendJsonResponse(['success' => true, 'status' => $status]);

    default:
        sendJsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
}
