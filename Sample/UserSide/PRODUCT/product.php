<?php
require_once __DIR__ . '/../../PHP/db_connect.php';
require_once __DIR__ . '/../../PHP/product_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === null || $id === false) {
    http_response_code(400);
    echo 'Missing or invalid product ID.';
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo 'Database connection not available.';
    exit;
}

try {
    $product = getProductById($pdo, $id);
    if (!$product) {
        http_response_code(404);
        echo "Product with ID {$id} not found.";
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error fetching product: ' . htmlspecialchars($e->getMessage());
    exit;
}

$category = $product['Category'] ?? '';
$price = isset($product['Price']) ? number_format((float)$product['Price'], 2) : '0.00';
$imageUrl = getProductImageUrl($product, '../../');
$stock = (int)($product['Stock_Quantity'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($product['Name']) ?> - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.product-detail {
      display: flex;
      flex-direction: column;
    }

    .detail-wrapper {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2.5rem;
      align-items: center;
      margin-top: 3rem;
    }

    .product-visual {
      position: relative;
      background: linear-gradient(135deg, rgba(255, 216, 180, 0.8), rgba(255, 255, 255, 0.9));
      border-radius: 40px;
      padding: 2.5rem;
      display: grid;
      place-items: center;
      box-shadow: 0 35px 60px rgba(139, 69, 19, 0.18);
    }

    .product-visual img {
      width: 100%;
      height: 100%;
      max-height: 360px;
      object-fit: contain;
    }

    .product-info {
      display: grid;
      gap: 1.4rem;
    }

    .product-info h1 {
      font-size: clamp(2rem, 3.2vw, 2.8rem);
      font-weight: 700;
      color: var(--primary-brown);
    }

    .product-info p {
      color: var(--text-muted);
      line-height: 1.6;
    }

    .price-tag {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary-brown);
    }

    .tag-pill {
      margin-right: auto;
    }

    .quantity-row {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .quantity-row button {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: rgba(139, 69, 19, 0.12);
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--primary-brown);
      border: none;
      cursor: pointer;
    }

    .quantity-row input {
      width: 64px;
      text-align: center;
      border-radius: 16px;
      border: 1px solid rgba(139, 69, 19, 0.15);
      padding: 0.6rem 0.5rem;
      font-weight: 600;
      font-size: 1.1rem;
    }

    .quantity-row input[type="number"]::-webkit-outer-spin-button,
    .quantity-row input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .quantity-row input[type="number"] {
      -moz-appearance: textfield;
    }

    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .action-buttons button {
      padding: 0.85rem 1.8rem;
      border-radius: var(--radius-pill);
      font-weight: 600;
      border: none;
      cursor: pointer;
    }

    .primary-btn {
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
      box-shadow: 0 18px 36px rgba(139, 69, 19, 0.2);
    }

    .secondary-btn {
      background: rgba(139, 69, 19, 0.12);
      color: var(--primary-brown);
    }

    .status-text {
      font-weight: 600;
      color: <?= $stock > 0 ? '#2d8659' : '#c8283c' ?>;
    }
  </style>
</head>
<body class="product-detail">
  <?php include __DIR__ . '/../topbar.php'; ?>

  <main class="page-container">
    <a href="MENU.php" class="tag-pill">← Back to menu</a>
    <div class="detail-wrapper">
      <div class="product-visual">
        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($product['Name']) ?>">
      </div>
      <div class="product-info">
        <span class="tag-pill">Category: <?= htmlspecialchars($category ?: '—') ?></span>
        <h1><?= htmlspecialchars($product['Name']) ?></h1>
        <div class="price-tag">₱<?= htmlspecialchars($price) ?></div>
        <p><?= htmlspecialchars($product['Description'] ?? 'Freshly baked and ready to delight.') ?></p>
        <div class="status-text" id="stockDisplay">Stock: <?= $stock ?></div>
        <div class="quantity-row">
          <span>Quantity</span>
          <button type="button" onclick="changeQty(-1)">−</button>
          <input
            type="number"
            id="qty"
            value="<?= $stock > 0 ? 1 : 0 ?>"
            min="0"
            step="1"
          >
          <button type="button" onclick="changeQty(1)">+</button>
        </div>
        <div class="action-buttons">
          <button class="primary-btn" id="addToCartBtn" onclick="addToCart()">Add to cart</button>
          <button class="secondary-btn" id="buyNowBtn" onclick="buyNow()">Buy now</button>
          <button class="secondary-btn" id="shareBtn" onclick="shareNow()">Share</button>
        </div>
      </div>
    </div>
  </main>

  <script type="module" src="../firebase-init.js"></script>
  <script src="js/cart.js"></script>
  <script>
    let maxStock = <?= $stock ?>;
    window.maxStock = maxStock;

    const addToCartBtn = document.getElementById('addToCartBtn');
    const buyNowBtn = document.getElementById('buyNowBtn');
    const qtyEl = document.getElementById('qty');

    function enforceQtyBounds(options = {}) {
      const { shouldAlert = false, allowEmpty = false } = options;
      if (!qtyEl) {
        return;
      }

      const rawValue = qtyEl.value.trim();
      if (rawValue === '') {
        if (allowEmpty) {
          return;
        }
        qtyEl.value = maxStock === 0 ? 0 : 1;
        return;
      }

      let current = parseInt(rawValue, 10);
      const fallback = maxStock === 0 ? 0 : 1;

      if (Number.isNaN(current)) {
        current = fallback;
      }

      if (maxStock === 0) {
        current = 0;
      } else {
        if (current < 1) {
          current = 1;
        }
        if (current > maxStock) {
          current = maxStock;
          if (shouldAlert) {
            alert(`Only ${maxStock} left in stock.`);
          }
        }
      }

      qtyEl.value = current;
    }

    function syncStockUI() {
      const stockEl = document.getElementById('stockDisplay');
      if (stockEl) {
        stockEl.textContent = `Stock: ${maxStock}`;
      }

      if (qtyEl) {
        qtyEl.max = maxStock > 0 ? maxStock : '';
        qtyEl.min = maxStock === 0 ? 0 : 1;
        qtyEl.disabled = maxStock === 0;
        enforceQtyBounds();
      }

      const shouldDisablePurchase = maxStock === 0;
      if (addToCartBtn) {
        addToCartBtn.disabled = shouldDisablePurchase;
      }
      if (buyNowBtn) {
        buyNowBtn.disabled = shouldDisablePurchase;
      }
    }

    async function updateMaxStockFromCart() {
      const productId = <?= (int)$id ?>;
      try {
        const auth = window.getAuth ? window.getAuth() : null;
        const email = auth && auth.currentUser ? auth.currentUser.email : null;
        const listUrl = email
          ? `/PHP/cart_api.php?action=list&email=${encodeURIComponent(email)}`
          : `/PHP/cart_api.php?action=list`;
        const resp = await fetch(listUrl);
        const contentType = resp.headers.get('Content-Type') || '';
        const text = await resp.text();
        if (!resp.ok || !contentType.includes('application/json')) {
          return;
        }
        const data = JSON.parse(text);
        if (data.items) {
          const existing = data.items.find(item => String(item.Product_ID) === String(productId));
          if (existing) {
            const existingQty = parseInt(existing.Quantity, 10) || 0;
            maxStock = Math.max(0, maxStock - existingQty);
            window.maxStock = maxStock;
          }
        }
      } catch (err) {
        console.error('Failed to fetch cart', err);
      }

      syncStockUI();
    }

    updateMaxStockFromCart();
    syncStockUI();

    function changeQty(delta) {
      if (maxStock === 0) {
        alert('This item is currently out of stock.');
        return;
      }

      let current = parseInt(qtyEl.value, 10);
      if (Number.isNaN(current)) {
        current = 1;
      }

      current += delta;
      qtyEl.value = current;
      enforceQtyBounds({ shouldAlert: delta > 0 });
    }

    if (qtyEl) {
      qtyEl.addEventListener('input', () => enforceQtyBounds({ allowEmpty: true }));
      qtyEl.addEventListener('blur', () => enforceQtyBounds());
    }

    function toggleFavorite(button) {
      button.textContent = button.textContent === '❤️' ? '💖' : '❤️';
      alert(button.textContent === '💖' ? 'Added to favorites!' : 'Removed from favorites.');
    }

    function shareNow() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href)
          .then(() => alert('Product link copied to clipboard!'))
          .catch(() => alert('Failed to copy link.'));
      } else {
        alert('Clipboard not supported.');
      }
    }
  </script>
</body>
</html>
