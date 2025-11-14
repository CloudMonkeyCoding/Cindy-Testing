<?php
require_once __DIR__ . '/../../PHP/db_connect.php';
require_once __DIR__ . '/../../PHP/product_functions.php';

$products = [];
if ($pdo) {
    $products = getProductsByCategory($pdo, 'bread');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Breads - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.category-view {
      display: flex;
      flex-direction: column;
    }

    .category-hero {
      background: linear-gradient(135deg, rgba(255, 195, 113, 0.9), rgba(255, 243, 224, 0.9));
      border-radius: 32px;
      padding: clamp(2.5rem, 5vw, 4rem);
      margin-bottom: 2.5rem;
      box-shadow: 0 30px 60px rgba(139, 69, 19, 0.2);
    }

    .category-hero h1 {
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
      color: var(--primary-brown);
    }

    .category-hero p {
      max-width: 540px;
      color: var(--text-muted);
      margin-top: 0.8rem;
    }

    .category-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 2rem;
    }

    .category-card {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 28px;
      padding: 1.6rem;
      display: grid;
      gap: 1rem;
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(139, 69, 19, 0.12);
      cursor: pointer;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .category-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 24px 45px rgba(139, 69, 19, 0.22);
    }

    .category-card img {
      width: 100%;
      height: 160px;
      object-fit: cover;
      border-radius: 20px;
      box-shadow: 0 12px 24px rgba(139, 69, 19, 0.15);
    }

    .category-card h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--primary-brown);
    }

    .category-card span {
      font-size: 0.95rem;
      color: var(--text-muted);
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      border-radius: 28px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px dashed rgba(139, 69, 19, 0.2);
      color: var(--text-muted);
      margin-top: 2rem;
      box-shadow: var(--shadow-soft);
    }
  </style>
</head>
<body class="category-view">
  <?php include __DIR__ . '/../topbar.php'; ?>

  <main class="page-container">
    <section class="category-hero">
      <h1>Daily breads</h1>
      <p>Soft, warm, and lovingly baked. Browse our hearty breads to pair with your favourite spreads or to enjoy on their own.</p>
    </section>

    <?php if (empty($products)): ?>
      <div class="empty-state">No breads available right now. Check back soon!</div>
    <?php else: ?>
      <div class="category-grid">
        <?php foreach ($products as $product): ?>
          <?php $imageUrl = getProductImageUrl($product, '../../'); ?>
          <article class="category-card" onclick="goToProduct(<?= (int)$product['Product_ID'] ?>)">
            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['Name']) ?>">
            <h3><?= htmlspecialchars($product['Name']) ?></h3>
            <span>₱<?= htmlspecialchars(number_format((float)$product['Price'], 2)) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <script>
    function goToProduct(id) {
      window.location.href = `product.php?id=${encodeURIComponent(id)}`;
    }
  </script>
</body>
</html>
