<?php
// 1) Create a cart for a user
function createCart($pdo, $userId) {
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare("
        INSERT INTO shopping_cart (User_ID)
        VALUES (:user_id)
    ");
    $stmt->execute([':user_id' => $userId]);
    return $pdo->lastInsertId();
}

// 2) Get a cart by Cart_ID
function getCartById($pdo, $cartId) {
    $stmt = $pdo->prepare("
        SELECT * FROM shopping_cart WHERE Cart_ID = :cart_id
    ");
    $stmt->execute([':cart_id' => $cartId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 3) Get a cart by User_ID (one user should have only one active cart)
function getCartByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM shopping_cart WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4) Delete a cart by Cart_ID
function deleteCartById($pdo, $cartId) {
    $stmt = $pdo->prepare("
        DELETE FROM shopping_cart WHERE Cart_ID = :cart_id
    ");
    $stmt->execute([':cart_id' => $cartId]);
    return $stmt->rowCount();
}

// 5) Delete a cart by User_ID
function deleteCartByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        DELETE FROM shopping_cart WHERE User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->rowCount();
}

// 6) Get all carts (optional)
function getAllCarts($pdo) {
    $stmt = $pdo->query("SELECT * FROM shopping_cart");
    return $stmt->fetchAll();
}
?>
