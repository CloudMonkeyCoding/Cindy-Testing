<?php
// 1) Add a transaction
function addTransaction($pdo, $orderId, $paymentMethod, $paymentStatus, $paymentDate, $amountPaid, $referenceNumber) {
    $stmt = $pdo->prepare("
        INSERT INTO transaction (Order_ID, Payment_Method, Payment_Status, Payment_Date, Amount_Paid, Reference_Number)
        VALUES (:order_id, :payment_method, :payment_status, :payment_date, :amount_paid, :reference_number)
    ");
    $stmt->execute([
        ':order_id' => $orderId,
        ':payment_method' => $paymentMethod,
        ':payment_status' => $paymentStatus,
        ':payment_date' => $paymentDate,
        ':amount_paid' => $amountPaid,
        ':reference_number' => $referenceNumber
    ]);
    return $pdo->lastInsertId();
}

// 2) Get all transactions
function getAllTransactions($pdo) {
    $stmt = $pdo->query("SELECT * FROM transaction");
    return $stmt->fetchAll();
}

// 3) Get all transactions for an Order_ID
function getTransactionsByOrderId($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT * FROM transaction WHERE Order_ID = :order_id
    ");
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll();
}

// 4) Get a single transaction by ID
function getTransactionById($pdo, $transactionId) {
    $stmt = $pdo->prepare("
        SELECT * FROM transaction WHERE Transaction_ID = :transaction_id
    ");
    $stmt->execute([':transaction_id' => $transactionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 5) Update payment status
function updateTransactionStatus($pdo, $transactionId, $paymentStatus) {
    $stmt = $pdo->prepare("
        UPDATE transaction
        SET Payment_Status = :payment_status
        WHERE Transaction_ID = :transaction_id
    ");
    $stmt->execute([
        ':payment_status' => $paymentStatus,
        ':transaction_id' => $transactionId
    ]);
    return $stmt->rowCount();
}

// 6) Delete a transaction by ID
function deleteTransactionById($pdo, $transactionId) {
    $stmt = $pdo->prepare("
        DELETE FROM transaction WHERE Transaction_ID = :transaction_id
    ");
    $stmt->execute([':transaction_id' => $transactionId]);
    return $stmt->rowCount();
}

// 7) Get transactions by payment method
function getTransactionsByMethod($pdo, $paymentMethod) {
    $stmt = $pdo->prepare("
        SELECT * FROM transaction WHERE Payment_Method = :payment_method
    ");
    $stmt->execute([':payment_method' => $paymentMethod]);
    return $stmt->fetchAll();
}

// 8) Get transactions by payment status
function getTransactionsByStatus($pdo, $paymentStatus) {
    $stmt = $pdo->prepare("
        SELECT * FROM transaction WHERE Payment_Status = :payment_status
    ");
    $stmt->execute([':payment_status' => $paymentStatus]);
    return $stmt->fetchAll();
}

// 9) Calculate total paid for an Order_ID
function getTotalPaidByOrderId($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT SUM(Amount_Paid) FROM transaction WHERE Order_ID = :order_id
    ");
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchColumn();
}
?>
