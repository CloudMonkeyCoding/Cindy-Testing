<?php
// Add a product to a user's favorites list
function addFavorite($pdo, $userId, $productId) {
    $stmt = $pdo->prepare("
        INSERT INTO favorites (User_ID, Product_ID)
        VALUES (:user_id, :product_id)
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':product_id' => $productId
    ]);
    return $pdo->lastInsertId();
}

// Get all favorite products for a user
function getFavoritesByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT f.Favorite_ID, f.Product_ID, p.Name, p.Image_Path, p.Category
        FROM favorites f
        JOIN product p ON f.Product_ID = p.Product_ID
        WHERE f.User_ID = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Remove a favorite by its id
function deleteFavorite($pdo, $favoriteId) {
    $stmt = $pdo->prepare("
        DELETE FROM favorites WHERE Favorite_ID = :favorite_id
    ");
    $stmt->execute([':favorite_id' => $favoriteId]);
    return $stmt->rowCount();
}

// Remove a favorite for a specific user and product
function deleteFavoriteByUserAndProduct($pdo, $userId, $productId) {
    $stmt = $pdo->prepare("
        DELETE FROM favorites WHERE User_ID = :user_id AND Product_ID = :product_id
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':product_id' => $productId
    ]);
    return $stmt->rowCount();
}
?>
