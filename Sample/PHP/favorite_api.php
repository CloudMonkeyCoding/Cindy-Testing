<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/favorite_functions.php';
require_once __DIR__ . '/user_request_helpers.php';

startJsonResponse();
requireDatabaseConnection($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        [$userId] = resolveUserContext($pdo, $_GET, ['allowMissing' => true]);
        if ($userId <= 0) {
            sendJsonResponse([]);
        }

        $favorites = getFavoritesByUserId($pdo, $userId);
        sendJsonResponse($favorites);

    case 'add':
        [$userId] = resolveUserContext($pdo, $_POST);
        $productId = (int)($_POST['product_id'] ?? 0);
        $id = addFavorite($pdo, $userId, $productId);
        sendJsonResponse(['favorite_id' => $id]);

    case 'remove':
        $favoriteId = (int)($_POST['favorite_id'] ?? 0);
        $deleted = deleteFavorite($pdo, $favoriteId);
        sendJsonResponse(['deleted' => $deleted]);

    default:
        sendJsonResponse(['error' => 'Invalid action'], 400);
}
?>
