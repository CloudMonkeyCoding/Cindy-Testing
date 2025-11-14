<?php
// 1) Retrieve all product ratings
function getAllProductRatings($pdo) {
    $stmt = $pdo->query("SELECT Rating_ID, Product_Name, Average_Rating, Total_Review, Comments FROM product_ratings");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 2) Delete a product rating by ID
function deleteProductRatingById($pdo, $ratingId) {
    $stmt = $pdo->prepare("DELETE FROM product_ratings WHERE Rating_ID = :rating_id");
    $stmt->execute([':rating_id' => $ratingId]);
    return $stmt->rowCount();
}
?>
