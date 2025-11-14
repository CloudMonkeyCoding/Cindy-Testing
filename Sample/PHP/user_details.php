<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/user_functions.php';
require_once __DIR__ . '/order_functions.php';
require_once __DIR__ . '/store_staff_functions.php';

function normalizeFacePath($path)
{
    if (!$path) {
        return null;
    }

    return $path[0] === '/' ? $path : '/' . ltrim((string)$path, '/');
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing user ID']);
    exit;
}

$user = getUserById($pdo, $userId);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$staffRecord = getStoreStaffByUserId($pdo, $userId);
$orders = getOrdersByUserId($pdo, $userId) ?: [];

$normalizedOrders = array_map(static function ($order) {
    $total = isset($order['Total_Amount']) ? (float)$order['Total_Amount'] : 0.0;
    $itemCount = isset($order['Item_Count']) ? (int)$order['Item_Count'] : 0;
    return [
        'id' => (int)($order['Order_ID'] ?? 0),
        'date' => $order['Order_Date'] ?? null,
        'status' => $order['Status'] ?? 'Pending',
        'source' => $order['Source'] ?? '',
        'fulfillment' => $order['Fulfillment_Type'] ?? '',
        'item_count' => $itemCount,
        'total_amount' => $total,
        'summary' => $order['Item_Summary'] ?? '',
    ];
}, $orders);

$totalSpent = array_reduce($normalizedOrders, static function ($carry, $order) {
    return $carry + ($order['total_amount'] ?? 0);
}, 0.0);

$statusCounts = [];
foreach ($normalizedOrders as $order) {
    $status = $order['status'] ?? 'Pending';
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}

$lastOrderDate = $normalizedOrders[0]['date'] ?? null;

$addressParts = getUserAddressParts($user);

$response = [
    'success' => true,
    'user' => [
        'id' => (int)$user['User_ID'],
        'name' => $user['Name'] ?? '',
        'email' => $user['Email'] ?? '',
        'address' => $user['Address'] ?? '',
        'address_street' => $addressParts['street'],
        'address_barangay' => $addressParts['barangay'],
        'address_city' => $addressParts['city'],
        'address_province' => $addressParts['province'],
        'warning_count' => isset($user['Warning_Count']) ? (int)$user['Warning_Count'] : 0,
        'face_image_path' => normalizeFacePath($user['Face_Image_Path'] ?? null),
        'is_employee' => $staffRecord ? true : false,
        'is_super_admin' => $staffRecord && !empty($staffRecord['Is_Super_Admin']) && (int)$staffRecord['Is_Super_Admin'] === 1,
    ],
    'orders' => $normalizedOrders,
    'summary' => [
        'total_orders' => count($normalizedOrders),
        'total_spent' => $totalSpent,
        'status_counts' => $statusCounts,
        'last_order_date' => $lastOrderDate,
    ],
];

echo json_encode($response);
