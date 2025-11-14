<?php
// 1) Add delivery info for an order
function addDelivery($pdo, $orderId, $status, $deliveryDate, $deliveryPersonnel) {
    // $deliveryPersonnel is the User_ID of the staff or admin assigned to deliver the order
    $stmt = $pdo->prepare("
        INSERT INTO delivery (Order_ID, Status, Delivery_Date, Delivery_Personnel)
        VALUES (:order_id, :status, :delivery_date, :delivery_personnel)
    ");
    $stmt->execute([
        ':order_id' => $orderId,
        ':status' => $status,
        ':delivery_date' => $deliveryDate,
        ':delivery_personnel' => $deliveryPersonnel
    ]);
    return $pdo->lastInsertId();
}

// 2) Get all deliveries
function getAllDeliveries($pdo) {
    $stmt = $pdo->query("SELECT * FROM delivery");
    return $stmt->fetchAll();
}

// 3) Get delivery info by Order_ID
function getDeliveryByOrderId($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT * FROM delivery WHERE Order_ID = :order_id
    ");
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll();
}

// 4) Get delivery by Delivery_ID
function getDeliveryById($pdo, $deliveryId) {
    $stmt = $pdo->prepare("
        SELECT * FROM delivery WHERE Delivery_ID = :delivery_id
    ");
    $stmt->execute([':delivery_id' => $deliveryId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 5) Update delivery details (status, date, personnel)
function updateDelivery($pdo, $deliveryId, $status, $deliveryDate, $deliveryPersonnel) {
    // $deliveryPersonnel is the User_ID of the staff or admin assigned to deliver the order
    $stmt = $pdo->prepare("
        UPDATE delivery
        SET Status = :status,
            Delivery_Date = :delivery_date,
            Delivery_Personnel = :delivery_personnel
        WHERE Delivery_ID = :delivery_id
    ");
    $stmt->execute([
        ':status' => $status,
        ':delivery_date' => $deliveryDate,
        ':delivery_personnel' => $deliveryPersonnel,
        ':delivery_id' => $deliveryId
    ]);
    return $stmt->rowCount();
}

// 6) Delete delivery by Delivery_ID
function deleteDeliveryById($pdo, $deliveryId) {
    $stmt = $pdo->prepare("
        DELETE FROM delivery WHERE Delivery_ID = :delivery_id
    ");
    $stmt->execute([':delivery_id' => $deliveryId]);
    return $stmt->rowCount();
}

// 7) Get deliveries by status (e.g., all Pending)
function getDeliveriesByStatus($pdo, $status) {
    $stmt = $pdo->prepare("
        SELECT * FROM delivery WHERE Status = :status
    ");
    $stmt->execute([':status' => $status]);
    return $stmt->fetchAll();
}

// 8) Get deliveries by personnel
function getDeliveriesByPersonnel($pdo, $personnel) {
    // $personnel is the User_ID of the staff or admin handling deliveries
    $stmt = $pdo->prepare("
        SELECT * FROM delivery WHERE Delivery_Personnel = :personnel
    ");
    $stmt->execute([':personnel' => $personnel]);
    return $stmt->fetchAll();
}
?>
