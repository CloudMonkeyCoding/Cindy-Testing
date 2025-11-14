<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/cart_functions.php';
require_once __DIR__ . '/cart_item_functions.php';
require_once __DIR__ . '/product_functions.php';
require_once __DIR__ . '/inventory_functions.php';
require_once __DIR__ . '/user_request_helpers.php';

startJsonResponse();
requireDatabaseConnection($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        [$userId] = resolveUserContext($pdo, $_GET, ['allowMissing' => true]);
        if ($userId <= 0) {
            sendJsonResponse(['cart_id' => 0, 'items' => []]);
        }

        $cart = getCartByUserId($pdo, $userId);
        if (!$cart) {
            $cartId = createCart($pdo, $userId);
            $items = [];
        } else {
            $cartId = $cart['Cart_ID'];
            $stmt = $pdo->prepare(
                'SELECT ci.Cart_Item_ID,
                        ci.Product_ID,
                        ci.Quantity,
                        p.Name,
                        p.Price,
                        COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,
                        p.Image_Path,
                        p.Category
                 FROM cart_item ci
                 JOIN product p ON ci.Product_ID = p.Product_ID
                 LEFT JOIN inventory i ON i.Product_ID = p.Product_ID
                 WHERE ci.Cart_ID = :cart_id'
            );
            $stmt->execute([':cart_id' => $cartId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendJsonResponse(['cart_id' => $cartId, 'items' => $items]);

    case 'add':
        $cartId = (int)($_POST['cart_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);
        [$userId] = resolveUserContext($pdo, $_POST, ['allowMissing' => true, 'emailOptional' => true]);

        if ($qty <= 0) {
            sendJsonResponse(['error' => 'Quantity must be greater than zero'], 400);
        }

        $product = getProductById($pdo, $productId);
        if (!$product) {
            sendJsonResponse(['error' => 'Product not found'], 404);
        }

        $inventory = getInventoryByProductId($pdo, $productId);
        $available = $inventory ? (int)$inventory['Stock_Quantity'] : (int)($product['Stock_Quantity'] ?? 0);
        if ($available <= 0) {
            sendJsonResponse(['error' => 'Product out of stock'], 400);
        }

        $cartExists = $cartId > 0 ? getCartById($pdo, $cartId) : null;
        if (!$cartExists) {
            if ($userId <= 0) {
                sendJsonResponse(['error' => 'Invalid or missing cart_id'], 400);
            }

            $cart = getCartByUserId($pdo, $userId);
            $cartId = $cart ? $cart['Cart_ID'] : createCart($pdo, $userId);
        }

        $existing = getCartItemByCartAndProduct($pdo, $cartId, $productId);
        $existingQty = $existing ? (int)$existing['Quantity'] : 0;
        $maxAdd = $available - $existingQty;
        if ($maxAdd <= 0) {
            sendJsonResponse(['error' => 'Requested quantity exceeds available stock'], 400);
        }

        $capped = false;
        if ($qty > $maxAdd) {
            $qty = $maxAdd;
            $capped = true;
        }

        if ($existing) {
            $newQty = $existingQty + $qty;
            $updated = updateCartItemQuantity($pdo, $existing['Cart_Item_ID'], $newQty);
            $response = [
                'cart_item_id' => $existing['Cart_Item_ID'],
                'cart_id' => $cartId,
                'updated' => $updated,
                'quantity' => $newQty,
            ];
        } else {
            $id = addCartItem($pdo, $cartId, $productId, $qty);
            $response = ['cart_item_id' => $id, 'cart_id' => $cartId, 'quantity' => $qty];
        }

        if ($capped) {
            $response['capped'] = true;
        }

        sendJsonResponse($response);

    case 'update':
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($qty <= 0) {
            sendJsonResponse(['error' => 'Quantity must be greater than zero'], 400);
        }

        $cartItem = getCartItemById($pdo, $cartItemId);
        if (!$cartItem) {
            sendJsonResponse(['error' => 'Cart item not found'], 404);
        }

        $productId = (int)$cartItem['Product_ID'];
        $product = getProductById($pdo, $productId);
        if (!$product) {
            sendJsonResponse(['error' => 'Product not found'], 404);
        }

        $inventory = getInventoryByProductId($pdo, $productId);
        $available = $inventory ? (int)$inventory['Stock_Quantity'] : (int)($product['Stock_Quantity'] ?? 0);
        if ($available <= 0) {
            sendJsonResponse(['error' => 'Product out of stock'], 400);
        }

        $capped = false;
        if ($qty > $available) {
            $qty = $available;
            $capped = true;
        }

        $result = updateCartItemQuantity($pdo, $cartItemId, $qty);
        $response = ['updated' => $result, 'cart_item_id' => $cartItemId, 'quantity' => $qty];
        if ($capped) {
            $response['capped'] = true;
        }

        sendJsonResponse($response);

    case 'remove':
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
        $deleted = deleteCartItemById($pdo, $cartItemId);
        sendJsonResponse(['deleted' => $deleted]);

    case 'get_count':
        [$userId] = resolveUserContext($pdo, $_GET, ['allowMissing' => true]);
        if ($userId <= 0) {
            sendJsonResponse(['count' => 0]);
        }

        $cart = getCartByUserId($pdo, $userId);
        if (!$cart) {
            sendJsonResponse(['count' => 0]);
        }

        $stmt = $pdo->prepare('SELECT SUM(Quantity) as total FROM cart_item WHERE Cart_ID = :cart_id');
        $stmt->execute([':cart_id' => $cart['Cart_ID']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result ? (int)($result['total'] ?? 0) : 0;

        sendJsonResponse(['count' => $count]);

    default:
        sendJsonResponse(['error' => 'Invalid action'], 400);
}
?>
