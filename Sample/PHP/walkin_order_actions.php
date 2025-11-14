<?php
session_start();
header('Content-Type: application/json');

if (!function_exists('walkin_order_log')) {
    function walkin_order_log(string $message, array $context = []): void
    {
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $message .= ' ' . $encoded;
            } else {
                $message .= ' ' . var_export($context, true);
            }
        }

        error_log('[Walk-in API] ' . $message);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    walkin_order_log('Rejected request with invalid method', ['method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

if (isset($_POST['action'])) {
    unset($_POST['action']);
}
if (isset($_REQUEST['action'])) {
    unset($_REQUEST['action']);
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/order_functions.php';
require_once __DIR__ . '/order_item_functions.php';
require_once __DIR__ . '/product_functions.php';
require_once __DIR__ . '/inventory_functions.php';
require_once __DIR__ . '/transaction_functions.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/delivery_functions.php';
require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/audit_log_functions.php';

walkin_order_log('Bootstrap completed for walk-in order API');

if (!$pdo) {
    walkin_order_log('Database connection unavailable');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    walkin_order_log('CSRF token mismatch', ['session_token_present' => isset($_SESSION['csrf_token'])]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$respond = static function (int $status, array $payload): void {
    walkin_order_log('Responding', ['status' => $status, 'payload' => $payload]);
    http_response_code($status);
    echo json_encode($payload);
    exit;
};

walkin_order_log('Processing request', ['action' => $action]);

$adminUserId = isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
$adminEmail = $_SESSION['admin_email'] ?? null;

switch ($action) {
    case 'search_products':
        $query = trim(filter_input(INPUT_POST, 'query', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit <= 0) {
            $limit = 20;
        }
        $limit = min($limit, 100);
        $inStockOnly = filter_input(INPUT_POST, 'in_stock_only', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        walkin_order_log('Searching products', [
            'query' => $query,
            'limit' => $limit,
            'in_stock_only' => $inStockOnly,
        ]);

        $sql = "SELECT p.Product_ID, p.Name, p.Price, p.Category,\n                       COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n                       (i.Stock_Quantity IS NULL) AS Stock_Not_Tracked\n                FROM product p\n                LEFT JOIN inventory i ON i.Product_ID = p.Product_ID";
        $conditions = [];
        $params = [];
        if ($query !== '') {
            $conditions[] = '(p.Name LIKE :term OR p.Category LIKE :term)';
            $params[':term'] = "%$query%";
        }
        if ($inStockOnly === true) {
            $conditions[] = '((i.Stock_Quantity IS NULL) OR COALESCE(i.Stock_Quantity, p.Stock_Quantity) > 0)';
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.Name ASC LIMIT ' . $limit;

        walkin_order_log('Prepared product search SQL', [
            'sql' => $sql,
            'params' => $params,
            'conditions' => $conditions,
            'limit' => $limit,
        ]);

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        walkin_order_log('Product query executed', [
            'row_count' => count($rows),
            'sample' => array_slice($rows, 0, 5),
        ]);

        $products = [];
        foreach ($rows as $row) {
            $rawStock = array_key_exists('Stock_Quantity', $row) ? $row['Stock_Quantity'] : null;
            $stockNotTracked = !empty($row['Stock_Not_Tracked']);
            $stockValue = null;
            if (!$stockNotTracked) {
                $stockValue = $rawStock === null ? null : (int)$rawStock;
            }

            walkin_order_log('Processed product row', [
                'product_id' => (int)$row['Product_ID'],
                'raw_stock' => $rawStock,
                'stock_not_tracked' => $stockNotTracked,
                'normalized_stock' => $stockValue,
            ]);

            $products[] = [
                'id' => (int)$row['Product_ID'],
                'name' => $row['Name'],
                'price' => (float)$row['Price'],
                'category' => normalizeProductCategoryValue($row['Category'] ?? ''),
                'stock' => $stockValue
            ];
        }
        walkin_order_log('Product search completed', ['result_count' => count($products)]);
        $respond(200, ['success' => true, 'products' => $products]);
        break;

    case 'search_customers':
        $query = trim(filter_input(INPUT_POST, 'query', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $limit = filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit <= 0) {
            $limit = 10;
        }
        $limit = min($limit, 50);
        walkin_order_log('Searching customers', ['query' => $query, 'limit' => $limit]);
        $sql = "SELECT User_ID, Name, Email, Address FROM user";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE Name LIKE :term OR Email LIKE :term';
            $params[':term'] = "%$query%";
        }
        $sql .= ' ORDER BY Name ASC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        $customers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $customers[] = [
                'id' => (int)$row['User_ID'],
                'name' => $row['Name'],
                'email' => $row['Email'] ?? '',
                'address' => $row['Address'] ?? ''
            ];
        }
        walkin_order_log('Customer search completed', ['result_count' => count($customers)]);
        $respond(200, ['success' => true, 'customers' => $customers]);
        break;

    case 'create_order':
        $customerMode = filter_input(INPUT_POST, 'customer_mode', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'guest';
        $customerMode = in_array($customerMode, ['existing', 'guest', 'new'], true) ? $customerMode : 'guest';

        $itemsRaw = $_POST['items'] ?? '[]';
        $itemsData = json_decode($itemsRaw, true);
        if (!is_array($itemsData) || empty($itemsData)) {
            $respond(422, ['success' => false, 'message' => 'Please add at least one product.']);
        }

        walkin_order_log('Starting order creation', [
            'customer_mode' => $customerMode,
            'item_count' => count($itemsData),
        ]);

        $orderItems = [];
        $orderTotal = 0.0;

        foreach ($itemsData as $item) {
            $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            if ($productId <= 0 || $quantity <= 0) {
                $respond(422, ['success' => false, 'message' => 'Invalid product selection provided.']);
            }

            walkin_order_log('Processing line item', ['product_id' => $productId, 'quantity' => $quantity]);

            $product = getProductById($pdo, $productId);
            if (!$product) {
                $respond(404, ['success' => false, 'message' => "Product ID {$productId} does not exist."]); 
            }

            $inventory = getInventoryByProductId($pdo, $productId);
            $inventoryStock = is_array($inventory) && array_key_exists('Stock_Quantity', $inventory)
                ? $inventory['Stock_Quantity']
                : null;
            $productStock = array_key_exists('Stock_Quantity', $product) ? $product['Stock_Quantity'] : null;
            $trackedStock = $inventoryStock ?? $productStock;
            $availableStock = $trackedStock === null ? null : (int)$trackedStock;
            if ($availableStock !== null && $quantity > $availableStock) {
                $respond(422, ['success' => false, 'message' => "Not enough stock for {$product['Name']}."]);
            }

            $price = (float)$product['Price'];
            $subtotal = $price * $quantity;
            $orderTotal += $subtotal;
            $orderItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal
            ];
        }

        $fulfillmentType = filter_input(INPUT_POST, 'fulfillment_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
        $allowedFulfillment = ['Delivery', 'Pick up'];
        if (!in_array($fulfillmentType, $allowedFulfillment, true)) {
            $fulfillmentType = 'Pick up';
        }

        $orderStatus = filter_input(INPUT_POST, 'order_status', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Pending';
        $allowedStatuses = ['Pending', 'Confirmed', 'Shipped', 'Delivered'];
        if (!in_array($orderStatus, $allowedStatuses, true)) {
            $orderStatus = 'Pending';
        }

        $paymentMethod = trim(filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Cash');
        if ($paymentMethod === '') {
            $paymentMethod = 'Cash';
        }

        $paymentStatusInput = filter_input(INPUT_POST, 'payment_status', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Paid';
        $allowedPaymentStatuses = ['Paid', 'Pending', 'Partially Paid'];
        $paymentStatus = in_array($paymentStatusInput, $allowedPaymentStatuses, true) ? $paymentStatusInput : 'Paid';

        $paymentAmount = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);
        if (!is_float($paymentAmount) || $paymentAmount < 0) {
            $paymentAmount = $paymentStatus === 'Paid' ? $orderTotal : 0.0;
        }
        $paymentAmount = min($paymentAmount, $orderTotal);

        $referenceNumber = trim(filter_input(INPUT_POST, 'reference_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        if ($referenceNumber === '') {
            $referenceNumber = null;
        }

        $customerEmail = '';
        $userId = null;

        walkin_order_log('Order metadata prepared', [
            'fulfillment_type' => $fulfillmentType,
            'order_status' => $orderStatus,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_amount' => $paymentAmount,
            'reference_number_present' => $referenceNumber !== null,
        ]);

        try {
            $pdo->beginTransaction();
            walkin_order_log('Transaction started');

            if ($customerMode === 'new') {
                $newCustomerName = trim((string)(filter_input(INPUT_POST, 'new_customer_name', FILTER_UNSAFE_RAW) ?? ''));
                $newCustomerEmail = trim((string)(filter_input(INPUT_POST, 'new_customer_email', FILTER_SANITIZE_EMAIL) ?? ''));
                $newCustomerAddress = trim((string)(filter_input(INPUT_POST, 'new_customer_address', FILTER_UNSAFE_RAW) ?? ''));

                if ($newCustomerName === '') {
                    throw new InvalidArgumentException('Enter a customer name or record the sale as a walk-in guest.', 422);
                }
                if ($newCustomerEmail !== '' && !filter_var($newCustomerEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Enter a valid email address or leave it blank.', 422);
                }
                if ($newCustomerEmail !== '' && checkEmailExists($pdo, $newCustomerEmail)) {
                    throw new InvalidArgumentException('The email provided is already registered.', 422);
                }

                $randomPassword = bin2hex(random_bytes(8));
                $userId = addUser(
                    $pdo,
                    $newCustomerName,
                    $newCustomerEmail !== '' ? $newCustomerEmail : null,
                    $randomPassword,
                    $newCustomerAddress !== '' ? $newCustomerAddress : null
                );
                $customerEmail = $newCustomerEmail;
                walkin_order_log('Created new customer record', [
                    'user_id' => $userId,
                    'email_present' => $customerEmail !== '',
                ]);
            }

            if ($customerMode === 'existing') {
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                if (!$userId) {
                    throw new InvalidArgumentException('Select an existing customer to continue or submit as a walk-in guest.', 422);
                }
                $user = getUserById($pdo, $userId);
                if (!$user) {
                    throw new InvalidArgumentException('Selected customer could not be found.', 404);
                }
                $customerEmail = $user['Email'] ?? '';
                walkin_order_log('Using existing customer', [
                    'user_id' => $userId,
                    'email_present' => $customerEmail !== '',
                ]);
            }

            $orderId = addOrder($pdo, $userId, date('Y-m-d H:i:s'), $orderStatus, 'walk-in', $fulfillmentType);
            walkin_order_log('Order inserted', ['order_id' => $orderId, 'item_count' => count($orderItems)]);

            foreach ($orderItems as $line) {
                addOrderItem($pdo, $orderId, $line['product_id'], $line['quantity'], $line['subtotal']);
                adjustInventoryStock($pdo, $line['product_id'], -$line['quantity'], [
                    'change_source' => 'order',
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                    'note' => 'Walk-in order deduction'
                ]);
                adjustProductStock($pdo, $line['product_id'], -$line['quantity']);
                walkin_order_log('Processed order line', [
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $paymentDate = date('Y-m-d');
            addTransaction($pdo, $orderId, $paymentMethod, $paymentStatus, $paymentDate, $paymentAmount, $referenceNumber);
            walkin_order_log('Transaction recorded', [
                'order_id' => $orderId,
                'payment_status' => $paymentStatus,
                'payment_amount' => $paymentAmount,
            ]);

            if ($fulfillmentType === 'Delivery') {
                addDelivery($pdo, $orderId, 'Pending', null, null);
                walkin_order_log('Delivery seeded', ['order_id' => $orderId]);
            }

            $pdo->commit();
            walkin_order_log('Transaction committed', ['order_id' => $orderId]);
        } catch (InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = $exception->getCode();
            if ($code < 400 || $code > 599) {
                $code = 422;
            }
            walkin_order_log('Validation exception', [
                'message' => $exception->getMessage(),
                'code' => $code,
            ]);
            $respond($code, ['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            walkin_order_log('Unexpected error', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $respond(500, ['success' => false, 'message' => 'Unable to create the order. Please try again.']);
        }

        if ($customerEmail !== '') {
            try {
                sendOrderConfirmationEmail($customerEmail, $orderId, $orderTotal);
                walkin_order_log('Order confirmation email dispatched', [
                    'order_id' => $orderId,
                    'email' => $customerEmail,
                ]);
            } catch (Throwable $exception) {
                walkin_order_log('Email dispatch error', [
                    'message' => $exception->getMessage(),
                    'order_id' => $orderId,
                ]);
            }
        }

        walkin_order_log('Order creation successful', [
            'order_id' => $orderId,
            'total' => $orderTotal,
        ]);

        record_audit_log($pdo, 'walkin_order_created', "Walk-in order #{$orderId} created.", [
            'actor_id' => $adminUserId ?: null,
            'actor_email' => $adminEmail,
            'source' => 'walkin_order_actions',
            'metadata' => [
                'order_id' => $orderId,
                'order_total' => $orderTotal,
                'item_count' => count($orderItems),
                'customer_mode' => $customerMode,
                'customer_user_id' => $userId,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'fulfillment_type' => $fulfillmentType,
            ],
        ]);

        $respond(200, [
            'success' => true,
            'order_id' => (int)$orderId,
            'total' => $orderTotal
        ]);
        break;

    default:
        $respond(400, ['success' => false, 'message' => 'Unknown action']);
}
