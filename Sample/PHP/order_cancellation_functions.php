<?php
// Functions for order cancellation operations

function addOrderCancellation($pdo, $orderId, $userId, $reason, $date, $status = 'Pending') {
    $stmt = $pdo->prepare(
        "INSERT INTO order_cancellation (Order_ID, User_ID, Reason, Cancellation_Date, Status)
         VALUES (:order_id, :user_id, :reason, :date, :status)"
    );
    $stmt->execute([
        ':order_id' => $orderId,
        ':user_id' => $userId,
        ':reason' => $reason,
        ':date' => $date,
        ':status' => $status
    ]);
    return $pdo->lastInsertId();
}

function getAllOrderCancellations($pdo) {
    $stmt = $pdo->query(
        "SELECT oc.*, u.Name AS Customer
         FROM order_cancellation oc
         LEFT JOIN user u ON oc.User_ID = u.User_ID"
    );
    return $stmt->fetchAll();
}

function updateOrderCancellationStatus($pdo, $cancellationId, $status) {
    $stmt = $pdo->prepare(
        "UPDATE order_cancellation
         SET Status = :status
         WHERE Cancellation_ID = :id"
    );
    $stmt->execute([
        ':status' => $status,
        ':id' => $cancellationId
    ]);
    return $stmt->rowCount();
}
?>
