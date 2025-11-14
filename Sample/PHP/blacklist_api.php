<?php
require_once __DIR__ . '/action_helpers.php';

startJsonResponse();
requirePostRequest('Method not allowed');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/blacklist_functions.php';

requireDatabaseConnection($pdo);

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS);
if ($action === 'unblock') {
    $id = filter_input(INPUT_POST, 'blacklist_id', FILTER_VALIDATE_INT);
    if ($id) {
        $deleted = deleteBlacklistById($pdo, $id);
        if ($deleted) {
            sendJsonResponse(['success' => true]);
        }
        sendJsonResponse(['success' => false, 'message' => 'Blacklist entry not found'], 404);
    }
    sendJsonResponse(['success' => false, 'message' => 'Invalid ID'], 400);
}

sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
