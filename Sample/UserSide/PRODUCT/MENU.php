<?php
require_once __DIR__ . '/../../PHP/db_connect.php';
require_once __DIR__ . '/../../PHP/product_functions.php';

$products = [];
if ($pdo) {
    try {
        $products = getAllProducts($pdo);
    } catch (Throwable $e) {
        $products = [];
    }
}

function normalizeCategory(string $rawCategory): string
{
    $category = strtolower(trim($rawCategory));
    if ($category === '') {
        return 'other';
    }

    $breadAliases = ['bread', 'breads', 'pastry', 'pastries'];
    if (in_array($category, $breadAliases, true)) {
        return 'bread';
    }

    $cakeAliases = ['cake', 'cakes'];
    if (in_array($category, $cakeAliases, true)) {
        return 'cake';
    }

    return $category;
}

function buildProductPayload(array $product): array
{
    $category = normalizeCategory((string)($product['Category'] ?? ''));
    $stock = (int)($product['Stock_Quantity'] ?? 0);
    if ($stock < 0) {
        $stock = 0;
    }
    return [
        'id' => (int)($product['Product_ID'] ?? 0),
        'name' => (string)($product['Name'] ?? 'Untitled Product'),
        'description' => (string)($product['Description'] ?? ''),
        'price' => (float)($product['Price'] ?? 0),
        'stock' => $stock,
        'category' => $category,
        'image' => getProductImageUrl($product, '../../'),
        'isPreorder' => false,
    ];
}

$payloadProducts = array_map('buildProductPayload', $products);
$categoryBuckets = [];
foreach ($payloadProducts as $product) {
    $categoryBuckets[$product['category']][] = $product;
}

$bestSellers = $payloadProducts;
usort($bestSellers, static function ($a, $b) {
    return $b['price'] <=> $a['price'];
});
$bestSellers = array_slice($bestSellers, 0, 6);

$preorderItems = [];

$pageData = [
    'all' => $payloadProducts,
    'bestSellers' => $bestSellers,
    'categories' => $categoryBuckets,
];

$dataJson = json_encode($pageData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cindy's Bakeshop — Menu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-1f/QN3zbZp8C3Auvt9bF6+BgqsSVqS+8CA0nVddOZXS6jttuPAHyBs+K6TfGsZpbbHK1Nn7A8jC2xOQkX8xYkg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.menu-view {
      display: flex;
      flex-direction: column;
    }

    main {
      width: min(1200px, 100% - 3rem);
      margin: 140px auto 80px;
    }

    .page-header {
      background: linear-gradient(135deg, #8b4513, #a0522d);
      text-align: center;
      color: #fff;
      padding: 4rem 1.5rem;
      border-radius: 32px;
      box-shadow: 0 32px 60px rgba(139, 69, 19, 0.28);
      margin-bottom: 3rem;
    }

    .page-header h1 {
      font-size: clamp(2.4rem, 4vw, 3.1rem);
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .page-header p {
      font-size: 1.15rem;
      margin-top: 1rem;
      opacity: 0.9;
    }

    .controls {
      max-width: 1200px;
      margin: 0 auto 2rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      background: #ffffff;
      border-radius: 20px;
      padding: 1.75rem 2rem;
      box-shadow: 0 20px 40px rgba(139, 69, 19, 0.12);
      border: 1px solid rgba(139, 69, 19, 0.12);
    }

    .categories {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .categories button {
      padding: 0.75rem 1.6rem;
      border-radius: 999px;
      border: none;
      background: #f8f9fa;
      color: #666666;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .categories button.active,
    .categories button:hover {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      box-shadow: 0 16px 32px rgba(231, 76, 60, 0.35);
      transform: translateY(-2px);
    }

    .search-bar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      flex: 1 1 240px;
    }

    .search-bar input {
      width: min(320px, 100%);
      padding: 0.85rem 1.5rem;
      border: 2px solid #ddd;
      border-radius: 999px;
      outline: none;
      background: #ffffff;
      color: #2c2c2c;
      transition: all 0.3s ease;
    }

    .search-bar input:focus {
      border-color: #e74c3c;
      box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.18);
    }

    .best-sellers {
      max-width: 1200px;
      margin: 2rem auto;
      background: #ffffff;
      border-radius: 20px;
      padding: 1.75rem;
      box-shadow: 0 20px 44px rgba(139, 69, 19, 0.1);
      border: 1px solid rgba(139, 69, 19, 0.12);
    }

    .best-sellers h2 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #8b4513;
      margin-bottom: 1.2rem;
    }

    .best-seller-list {
      display: flex;
      gap: 1.2rem;
      overflow-x: auto;
      padding-bottom: 0.5rem;
    }

    .best-seller-list::-webkit-scrollbar {
      height: 6px;
    }

    .best-seller-list::-webkit-scrollbar-thumb {
      background: #8b4513;
      border-radius: 8px;
    }

    .menu-section {
      max-width: 1200px;
      margin: 2.5rem auto;
      text-align: center;
    }

    .menu-section h2 {
      font-size: 1.9rem;
      font-weight: 700;
      color: #8b4513;
    }

    .section-subtitle {
      color: rgba(102, 102, 102, 0.85);
      margin: 0.7rem auto 2rem;
      max-width: 540px;
      font-size: 0.98rem;
    }

    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.6rem;
    }

    .empty-state {
      margin-top: 2rem;
      padding: 2rem;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(231, 76, 60, 0.12), rgba(192, 57, 43, 0.08));
      color: #8b4513;
      font-weight: 600;
      box-shadow: 0 18px 40px rgba(231, 76, 60, 0.12);
    }

    .pagination-controls {
      margin-top: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1.5rem;
      padding: 1rem 1.5rem;
      background: #ffffff;
      border-radius: 14px;
      border: 1px solid rgba(139, 69, 19, 0.12);
      box-shadow: 0 20px 44px rgba(139, 69, 19, 0.08);
    }

    .pagination-controls[hidden] {
      display: none;
    }

    .pagination-details {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.6rem;
    }

    .pagination-status {
      font-size: 0.95rem;
      color: rgba(90, 45, 12, 0.75);
      font-weight: 500;
    }

    .pagination-pages {
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .pagination-ellipsis {
      color: rgba(90, 45, 12, 0.65);
      font-weight: 600;
    }

    .pagination-arrow,
    .pagination-page {
      border: none;
      border-radius: 999px;
      padding: 0.55rem 1.1rem;
      font-weight: 600;
      cursor: pointer;
      background: #f8f9fa;
      color: #8b4513;
      transition: all 0.3s ease;
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
    }

    .pagination-arrow[disabled],
    .pagination-page[disabled] {
      cursor: not-allowed;
      opacity: 0.55;
      box-shadow: none;
    }

    .pagination-arrow:hover:not([disabled]),
    .pagination-page:hover:not([disabled]) {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      box-shadow: 0 14px 30px rgba(231, 76, 60, 0.32);
    }

    .pagination-page.active {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      box-shadow: 0 16px 32px rgba(231, 76, 60, 0.35);
    }

    .menu-item {
      position: relative;
      background: #ffffff;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      border: none;
      display: flex;
      flex-direction: column;
      min-height: 360px;
    }

    .menu-item:hover {
      transform: translateY(-6px);
      box-shadow: 0 22px 48px rgba(0, 0, 0, 0.16);
    }

    .menu-item img {
      width: 100%;
      height: 200px;
      object-fit: cover;
    }

    .favorite-btn {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 40px;
      height: 40px;
      display: grid;
      place-items: center;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      font-size: 1.3rem;
      color: #999999;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
      cursor: pointer;
      border: none;
      transition: all 0.3s ease;
    }

    .favorite-btn:hover {
      background: rgba(231, 76, 60, 0.15);
      color: #e74c3c;
      transform: scale(1.08);
    }

    .favorite-btn.active {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      box-shadow: 0 16px 32px rgba(231, 76, 60, 0.35);
    }

    .menu-content {
      padding: 1.6rem;
      display: flex;
      flex-direction: column;
      flex: 1;
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    }

    .menu-content h3 {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 0.6rem;
      color: #2c2c2c;
    }

    .menu-content p {
      font-size: 0.94rem;
      color: rgba(90, 45, 12, 0.65);
      min-height: 48px;
    }

    .details-link {
      margin-top: 0.75rem;
      font-weight: 600;
      color: #8b4513;
      text-decoration: none;
      align-self: flex-start;
    }

    .details-link:hover {
      color: #a0522d;
      text-decoration: underline;
    }

    .menu-footer {
      margin-top: auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      padding-top: 1rem;
      border-top: 1px solid #e9ecef;
    }

    .price-section {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0.35rem;
    }

    .price-section .stock {
      font-size: 0.85rem;
      color: rgba(102, 102, 102, 0.85);
    }

    .price-section .price {
      font-size: 1.2rem;
      font-weight: 700;
      color: #e74c3c;
    }

    .add-btn {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 0.75rem 1.6rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 12px 28px rgba(231, 76, 60, 0.35);
    }

    .add-btn:hover {
      background: linear-gradient(135deg, #c0392b, #a93226);
      transform: translateY(-2px);
    }

    .menu-item.out-of-stock {
      pointer-events: none;
      opacity: 0.45;
    }

    .menu-item.out-of-stock::after {
      content: 'Out of Stock';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(255, 255, 255, 0.95);
      padding: 0.6rem 1.2rem;
      border-radius: 12px;
      font-weight: 700;
      color: #e74c3c;
      border: 2px solid #e74c3c;
      box-shadow: 0 12px 30px rgba(231, 76, 60, 0.3);
    }

    .best-seller-list .menu-item {
      min-width: 260px;
      flex: 0 0 260px;
    }

    .best-seller-list .menu-item img {
      height: 160px;
    }

    footer {
      text-align: center;
      padding: 3rem 1rem;
      background: #2c2c2c;
      color: #f5f5f5;
      font-size: 0.9rem;
      margin-top: 4rem;
    }

    .modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #ffffff;
      border-radius: 20px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
      padding: 2.5rem;
      width: min(420px, 90%);
      z-index: 3200;
      display: none;
      border: 1px solid rgba(139, 69, 19, 0.12);
    }

    .modal.show {
      display: block;
    }

    .modal h2 {
      font-size: 1.6rem;
      color: #8b4513;
      margin-bottom: 0.75rem;
      font-weight: 700;
    }

    .modal p {
      color: rgba(90, 45, 12, 0.75);
      font-size: 0.95rem;
    }

    .quantity-control {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 1.5rem;
      margin: 2rem 0 1.5rem;
    }

    .quantity-control button {
      width: 48px;
      height: 48px;
      border: none;
      background: #f8f9fa;
      color: #e74c3c;
      border-radius: 12px;
      cursor: pointer;
      font-size: 1.4rem;
      font-weight: 700;
      transition: all 0.3s ease;
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.1);
    }

    .quantity-control button:hover {
      background: #e74c3c;
      color: #fff;
      transform: scale(1.05);
    }

    .quantity-control button:disabled,
    .quantity-control button.is-disabled {
      background: #f0f0f0;
      color: rgba(231, 76, 60, 0.4);
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
    }

    .quantity-control button:disabled:hover,
    .quantity-control button.is-disabled:hover {
      background: #f0f0f0;
      color: rgba(231, 76, 60, 0.4);
      transform: none;
    }

    .quantity-control input {
      font-size: 1.4rem;
      font-weight: 700;
      color: #2c2c2c;
      width: 72px;
      height: 52px;
      text-align: center;
      border: 2px solid #f0f0f0;
      border-radius: 12px;
      background: #ffffff;
      box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.08);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .quantity-control input:focus {
      outline: none;
      border-color: #e74c3c;
      box-shadow: inset 0 2px 6px rgba(231, 76, 60, 0.25);
    }

    .quantity-control input::-webkit-outer-spin-button,
    .quantity-control input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .quantity-control input[type="number"] {
      -moz-appearance: textfield;
    }

    .modal-actions {
      display: flex;
      gap: 1rem;
    }

    .modal-actions button {
      flex: 1;
      padding: 0.85rem 1.4rem;
      border-radius: 12px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .modal-actions #confirmAdd {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      box-shadow: 0 12px 28px rgba(231, 76, 60, 0.35);
    }

    .modal-actions #confirmAdd:hover {
      background: linear-gradient(135deg, #c0392b, #a93226);
      transform: translateY(-2px);
    }

    .modal-actions #cancelAdd {
      background: #f8f9fa;
      color: #666666;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
    }

    .modal-actions #cancelAdd:hover {
      background: #e9ecef;
      color: #2c2c2c;
      transform: translateY(-1px);
    }

    .modal-actions #removeFromCart {
      background: #fff5f5;
      color: #c0392b;
      box-shadow: 0 8px 18px rgba(192, 57, 43, 0.18);
      border: 1px solid rgba(192, 57, 43, 0.25);
    }

    .modal-actions #removeFromCart:hover {
      background: #ffe3e3;
      color: #922b21;
      transform: translateY(-1px);
    }

    @media (max-width: 1024px) {
      main {
        width: min(100% - 2rem, 960px);
      }

      .menu-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      }
    }

    @media (max-width: 768px) {
      main {
        width: min(100% - 1.5rem, 720px);
        margin: 120px auto 60px;
      }

      .controls {
        flex-direction: column;
        align-items: stretch;
      }

      .categories {
        justify-content: center;
      }

      .menu-grid {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      }

      .menu-item {
        min-height: 320px;
      }

      .menu-item img {
        height: 170px;
      }

      .best-sellers {
        margin: 1.2rem auto;
        padding: 1.25rem;
      }

      .modal {
        width: min(95%, 420px);
        padding: 2rem 1.5rem;
      }

      .quantity-control {
        gap: 1rem;
      }

      .quantity-control button {
        width: 42px;
        height: 42px;
      }

      .quantity-control input {
        width: 68px;
        height: 46px;
      }

      .pagination-controls {
        flex-direction: column;
        gap: 0.9rem;
        width: 100%;
      }

      .pagination-arrow,
      .pagination-page {
        width: 100%;
      }

      footer {
        padding: 2.5rem 1rem;
        margin-top: 3rem;
      }
    }

    @media (max-width: 480px) {
      .menu-grid {
        grid-template-columns: 1fr;
      }

      .categories {
        gap: 0.6rem;
      }

      .categories button {
        padding: 0.6rem 1.1rem;
        font-size: 0.9rem;
      }

      .search-bar input {
        width: 100%;
      }

      .page-header {
        padding: 3rem 1rem;
      }

      .page-header h1 {
        font-size: 2.2rem;
      }

      .page-header p {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body class="menu-view">
  <?php include __DIR__ . '/../topbar.php'; ?>

  <main>
    <section class="page-header">
      <h1>Freshly Baked with Love</h1>
      <p>Delicious breads and cakes made daily.</p>
    </section>

    <div class="controls">
      <div class="categories" id="categoryPills"></div>
      <div class="search-bar">
        <input id="searchInput" type="search" placeholder="Search for items..." autocomplete="off" />
      </div>
    </div>

    <section class="best-sellers" aria-labelledby="best-sellers-title" id="bestSellersSection">
      <h2 id="best-sellers-title">⭐ Best Sellers</h2>
      <div class="best-seller-list" id="bestSellerList"></div>
    </section>

    <section class="menu-section" aria-labelledby="menu-grid-title">
      <h2 id="menu-grid-title">All Baked Goodies</h2>
      <p class="section-subtitle">Filter by category or search to find your next favorite bite.</p>
      <div class="menu-grid" id="menuGrid"></div>
      <div class="empty-state" id="menuEmpty" hidden>No treats match your filters yet. Try searching for another item!</div>
      <div class="pagination-controls" id="paginationControls" hidden>
        <button type="button" class="pagination-arrow" id="prevPage" aria-label="Previous page">
          ‹
        </button>
        <div class="pagination-details">
          <span class="pagination-status" id="paginationStatus"></span>
          <nav class="pagination-pages" id="paginationList" aria-label="Menu pages"></nav>
        </div>
        <button type="button" class="pagination-arrow" id="nextPage" aria-label="Next page">
          ›
        </button>
      </div>
    </section>

  </main>

  <footer>© <?= date('Y') ?> Cindy's Bakeshop • Freshness Guaranteed</footer>

  <div class="modal" id="quantityModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
    <h2 id="modalTitle">Add to Cart</h2>
    <p id="modalSubtitle"></p>
    <div class="quantity-control">
      <button type="button" id="decreaseQty" aria-label="Reduce quantity">−</button>
      <input
        type="number"
        id="currentQty"
        min="1"
        step="1"
        value="1"
        inputmode="numeric"
        aria-label="Selected quantity"
      />
      <button type="button" id="increaseQty" aria-label="Increase quantity">+</button>
    </div>
    <div class="modal-actions">
      <button type="button" id="cancelAdd">Cancel</button>
      <button type="button" id="removeFromCart" hidden aria-hidden="true">Remove from Cart</button>
      <button type="button" id="confirmAdd">Add to Cart</button>
    </div>
  </div>

  <div class="toast" id="menuToast" role="status" aria-live="polite"></div>

  <script id="menuData" type="application/json"><?= $dataJson ?: '{}' ?></script>
  <script type="module">
    import '../firebase-init.js';
    import { getAuth, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const dataElement = document.getElementById('menuData');
    const rawData = dataElement ? JSON.parse(dataElement.textContent || '{}') : {};
    const products = Array.isArray(rawData.all) ? rawData.all : [];
    const bestSellers = Array.isArray(rawData.bestSellers) ? rawData.bestSellers : [];

    const menuGrid = document.getElementById('menuGrid');
    const menuEmpty = document.getElementById('menuEmpty');
    const paginationControls = document.getElementById('paginationControls');
    const paginationStatus = document.getElementById('paginationStatus');
    const paginationList = document.getElementById('paginationList');
    const prevPageButton = document.getElementById('prevPage');
    const nextPageButton = document.getElementById('nextPage');
    const bestSellerList = document.getElementById('bestSellerList');
    const bestSellersSection = document.getElementById('bestSellersSection');
    const categoryPills = document.getElementById('categoryPills');
    const searchInput = document.getElementById('searchInput');
    const toast = document.getElementById('menuToast');

    const modal = document.getElementById('quantityModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const decreaseQty = document.getElementById('decreaseQty');
    const increaseQty = document.getElementById('increaseQty');
    const currentQty = document.getElementById('currentQty');
    const confirmAdd = document.getElementById('confirmAdd');
    const removeFromCart = document.getElementById('removeFromCart');
    const cancelAdd = document.getElementById('cancelAdd');

    const auth = getAuth();
    let userEmail = null;
    let favorites = new Map();
    let cartItems = new Map();
    let cartId = null;
    let cartSyncPromise = null;
    let activeCategory = 'all';
    let currentProduct = null;
    let currentQuantity = 1;
    let filteredProducts = Array.isArray(products) ? [...products] : [];
    let currentPage = 1;

    const ITEMS_PER_PAGE = 8;
    const MAX_VISIBLE_PAGES = 6;

    const CATEGORY_LABELS = new Map([
      ['all', 'All'],
      ['bread', 'Breads'],
      ['breads', 'Breads'],
      ['pastry', 'Breads'],
      ['pastries', 'Breads'],
      ['cake', 'Cakes'],
      ['cakes', 'Cakes'],
    ]);

    function getMaxSelectableQuantity() {
      if (!currentProduct) {
        return 1;
      }
      const stock = Number(currentProduct.stock);
      if (!Number.isFinite(stock)) {
        return 1;
      }
      return Math.max(1, stock);
    }

    function setButtonAvailability(button, isDisabled) {
      if (!button) return;
      button.disabled = isDisabled;
      button.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
      button.classList.toggle('is-disabled', isDisabled);
    }

    function refreshQuantityState() {
      const max = getMaxSelectableQuantity();
      if (currentQty) {
        currentQty.setAttribute('min', '1');
        currentQty.setAttribute('step', '1');
        currentQty.setAttribute('max', String(max));
        currentQty.value = String(currentQuantity);
      }
      setButtonAvailability(decreaseQty, currentQuantity <= 1);
      setButtonAvailability(increaseQty, currentQuantity >= max);
    }

    function setQuantityFromValue(value) {
      const max = getMaxSelectableQuantity();
      const parsed = Math.floor(Number(value));
      const nextQuantity = Number.isFinite(parsed) ? parsed : 1;
      currentQuantity = Math.min(Math.max(1, nextQuantity), max);
      refreshQuantityState();
    }

    function formatCurrency(amount) {
      return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount || 0);
    }

    function showToast(message, tone = 'success') {
      if (!toast) return;
      toast.textContent = message;
      toast.dataset.tone = tone;
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 2600);
    }

    function escapeHtml(value) {
      return (value || '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;',
      })[char] || char);
    }

    function hydrateCart(email) {
      if (!email) {
        cartItems.clear();
        cartId = null;
        cartSyncPromise = null;
        return Promise.resolve();
      }

      if (cartSyncPromise) {
        return cartSyncPromise;
      }

      const url = `../../PHP/cart_api.php?action=list&email=${encodeURIComponent(email)}`;
      cartSyncPromise = fetch(url)
        .then(res => {
          if (!res.ok) {
            throw new Error('Failed to fetch cart');
          }
          return res.json();
        })
        .then(data => {
          cartItems.clear();
          cartId = Number.isFinite(Number(data.cart_id)) && Number(data.cart_id) > 0
            ? Number(data.cart_id)
            : null;

          if (Array.isArray(data.items)) {
            data.items.forEach(item => {
              const productId = Number(item.Product_ID);
              const cartItemId = Number(item.Cart_Item_ID);
              const quantity = Math.floor(Number(item.Quantity));
              if (Number.isFinite(productId) && productId > 0 && Number.isFinite(cartItemId) && cartItemId > 0) {
                cartItems.set(productId, {
                  cartItemId,
                  quantity: Number.isFinite(quantity) && quantity > 0 ? quantity : 0,
                });
              }
            });
          }

          return cartItems;
        })
        .catch(error => {
          cartItems.clear();
          cartId = null;
          throw error;
        })
        .finally(() => {
          cartSyncPromise = null;
        });

      return cartSyncPromise;
    }

    function openModal(product) {
      currentProduct = product;
      const available = Number.isFinite(product.stock) && product.stock > 0 ? product.stock : 0;
      const existing = cartItems.get(product.id);
      const existingQuantity = existing && Number.isFinite(existing.quantity) && existing.quantity > 0
        ? existing.quantity
        : 0;
      const maxSelectable = available > 0 ? available : 1;
      const startingQuantity = existingQuantity > 0 ? existingQuantity : 1;
      currentQuantity = Math.min(Math.max(1, startingQuantity), maxSelectable);
      const hasExisting = existingQuantity > 0;

      if (modalTitle) {
        modalTitle.textContent = `Add ${product.name}`;
      }
      if (modalSubtitle) {
        if (available > 0) {
          modalSubtitle.textContent = existingQuantity > 0
            ? `Maximum available: ${available} · In cart: ${existingQuantity}`
            : `Maximum available: ${available}`;
        } else {
          modalSubtitle.textContent = 'This item is currently out of stock.';
        }
      }
      if (confirmAdd) {
        confirmAdd.textContent = hasExisting ? 'Update Cart' : 'Add to Cart';
      }
      if (removeFromCart) {
        removeFromCart.hidden = !hasExisting;
        removeFromCart.disabled = !hasExisting;
        removeFromCart.setAttribute('aria-hidden', hasExisting ? 'false' : 'true');
      }
      refreshQuantityState();
      if (currentQty) {
        currentQty.disabled = !(available > 0);
        if (available > 0) {
          currentQty.value = String(currentQuantity);
          currentQty.focus();
          currentQty.select();
        }
      }
      if (modal) {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
      }
      if (confirmAdd) {
        confirmAdd.disabled = !(available > 0);
      }
    }

    function closeModal() {
      if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
      }
      currentProduct = null;
    }

    function updateQuantity(delta) {
      if (!currentProduct) return;
      setQuantityFromValue(currentQuantity + delta);
    }

    function toggleFavorite(button, product) {
      if (!userEmail) {
        showToast('Please sign in to manage favorites.', 'warn');
        return;
      }

      const favoriteId = favorites.get(product.id);
      const endpoint = favoriteId ? '../../PHP/favorite_api.php?action=remove' : '../../PHP/favorite_api.php?action=add';
      const payload = favoriteId
        ? new URLSearchParams({ favorite_id: favoriteId })
        : new URLSearchParams({ product_id: product.id, email: userEmail });

      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload,
      })
        .then(res => res.json())
        .then(result => {
          if (result.error) {
            throw new Error(result.error);
          }
          if (favoriteId) {
            favorites.delete(product.id);
            button.classList.remove('active');
            button.textContent = '♡';
            showToast('Removed from favorites.');
          } else if (result.favorite_id) {
            favorites.set(product.id, result.favorite_id);
            button.classList.add('active');
            button.textContent = '♥';
            showToast('Added to favorites!');
          }
        })
        .catch(() => {
          showToast('Could not update favorites.', 'error');
        });
    }

    function renderCard(product) {
      const card = document.createElement('article');
      card.className = 'menu-item';
      card.dataset.productId = product.id;
      card.dataset.category = product.category;

      const safeName = escapeHtml(product.name);
      const safeDescription = escapeHtml(product.description || "Freshly baked goodness from Cindy's kitchen.");
      const available = Number.isFinite(product.stock) ? Math.max(0, product.stock) : 0;
      const inStock = available > 0;
      const stockLabel = inStock ? `Stock: ${available}` : 'Out of stock';

      card.innerHTML = `
        <button type="button" class="favorite-btn" aria-label="Toggle favorite">♡</button>
        <img src="${product.image}" alt="${safeName}" loading="lazy">
        <div class="menu-content">
          <h3>${safeName}</h3>
          <p>${safeDescription}</p>
          <a class="details-link" href="product.php?id=${product.id}">View details →</a>
          <div class="menu-footer">
            <div class="price-section">
              <span class="stock">${stockLabel}</span>
              <span class="price">${formatCurrency(product.price)}</span>
            </div>
            <button type="button" class="add-btn">${inStock ? 'Add to Cart' : 'Unavailable'}</button>
          </div>
        </div>
      `;

      if (!inStock) {
        card.classList.add('out-of-stock');
        const addBtn = card.querySelector('.add-btn');
        if (addBtn) {
          addBtn.disabled = true;
        }
      }

      const favBtn = card.querySelector('.favorite-btn');
      const addBtn = card.querySelector('.add-btn');

      if (favorites.has(product.id)) {
        favBtn.classList.add('active');
        favBtn.textContent = '♥';
      }

      favBtn.addEventListener('click', () => toggleFavorite(favBtn, product));

      addBtn.addEventListener('click', () => {
        if (!userEmail) {
          showToast('Please sign in to add items to your cart.', 'warn');
          return;
        }
        if (!inStock) {
          showToast('This item is currently unavailable.', 'warn');
          return;
        }

        const waitForCart = cartSyncPromise
          ? cartSyncPromise.catch(() => {})
          : Promise.resolve();

        waitForCart.finally(() => openModal(product));
      });

      return card;
    }

    function populateSection(container, list) {
      if (!container) return;
      container.innerHTML = '';
      list.forEach(product => {
        const card = renderCard(product);
        container.appendChild(card);
      });
    }

    function renderPagination(totalItems, totalPages) {
      if (!paginationControls) return;

      if (totalItems === 0 || totalPages <= 1) {
        paginationControls.hidden = true;
        if (paginationStatus) paginationStatus.textContent = '';
        if (paginationList) paginationList.innerHTML = '';
        if (prevPageButton) prevPageButton.disabled = true;
        if (nextPageButton) nextPageButton.disabled = true;
        return;
      }

      paginationControls.hidden = false;

      const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
      const endItem = Math.min(totalItems, currentPage * ITEMS_PER_PAGE);

      if (paginationStatus) {
        paginationStatus.textContent = `Showing ${startItem}–${endItem} of ${totalItems}`;
      }

      if (prevPageButton) {
        prevPageButton.disabled = currentPage <= 1;
      }
      if (nextPageButton) {
        nextPageButton.disabled = currentPage >= totalPages;
      }

      if (!paginationList) return;

      paginationList.innerHTML = '';
      const fragment = document.createDocumentFragment();

      const appendButton = (pageNumber) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pagination-page';
        button.textContent = pageNumber.toString();
        if (pageNumber === currentPage) {
          button.classList.add('active');
          button.setAttribute('aria-current', 'page');
        }
        button.addEventListener('click', () => {
          if (pageNumber !== currentPage) {
            currentPage = pageNumber;
            refreshMenu();
          }
        });
        fragment.appendChild(button);
      };

      const appendEllipsis = () => {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'pagination-ellipsis';
        ellipsis.textContent = '…';
        fragment.appendChild(ellipsis);
      };

      if (totalPages <= MAX_VISIBLE_PAGES) {
        for (let page = 1; page <= totalPages; page += 1) {
          appendButton(page);
        }
      } else {
        appendButton(1);

        const startRange = Math.max(2, currentPage - 1);
        const endRange = Math.min(totalPages - 1, currentPage + 1);

        if (startRange > 2) {
          appendEllipsis();
        }

        for (let page = startRange; page <= endRange; page += 1) {
          appendButton(page);
        }

        if (endRange < totalPages - 1) {
          appendEllipsis();
        }

        appendButton(totalPages);
      }

      paginationList.appendChild(fragment);
    }

    function refreshMenu({ resetPage = false } = {}) {
      if (!menuGrid) return;
      const query = searchInput ? searchInput.value.trim().toLowerCase() : '';

      if (bestSellersSection) {
        const shouldShowBestSellers = activeCategory === 'all' && !query;
        bestSellersSection.hidden = !shouldShowBestSellers;
      }

      filteredProducts = products.filter(product => {
        const matchesCategory = activeCategory === 'all' || product.category === activeCategory;
        const description = (product.description || '').toLowerCase();
        const matchesQuery = !query || product.name.toLowerCase().includes(query) || description.includes(query);
        return matchesCategory && matchesQuery;
      });

      const totalItems = filteredProducts.length;
      const totalPages = totalItems === 0 ? 1 : Math.ceil(totalItems / ITEMS_PER_PAGE);

      if (resetPage) {
        currentPage = 1;
      }

      currentPage = Math.min(Math.max(currentPage, 1), totalPages);

      menuGrid.innerHTML = '';

      const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
      const pageItems = filteredProducts.slice(startIndex, startIndex + ITEMS_PER_PAGE);

      pageItems.forEach(product => {
        const card = renderCard(product);
        menuGrid.appendChild(card);
      });

      if (menuEmpty) {
        menuEmpty.hidden = totalItems > 0;
      }

      menuGrid.hidden = totalItems === 0;

      renderPagination(totalItems, totalPages);
    }

    function buildCategoryFilters() {
      if (!categoryPills) return;
      const fragment = document.createDocumentFragment();
      const uniqueKeys = new Set(['all']);
      products.forEach(item => uniqueKeys.add(item.category));

      uniqueKeys.forEach(key => {
        const label = CATEGORY_LABELS.get(key) || key.charAt(0).toUpperCase() + key.slice(1);
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.category = key;
        button.textContent = label;
        if (key === activeCategory) button.classList.add('active');
        button.addEventListener('click', () => {
          activeCategory = key;
          categoryPills.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
          button.classList.add('active');
          refreshMenu({ resetPage: true });
        });
        fragment.appendChild(button);
      });

      categoryPills.innerHTML = '';
      categoryPills.appendChild(fragment);
    }

    function hydrateFavorites(email) {
      if (!email) return;
      fetch(`../../PHP/favorite_api.php?action=list&email=${encodeURIComponent(email)}`)
        .then(res => res.json())
        .then(list => {
          if (!Array.isArray(list)) return;
          favorites.clear();
          list.forEach(item => {
            favorites.set(Number(item.Product_ID), item.Favorite_ID);
          });
          refreshMenu();
          populateSection(bestSellerList, bestSellers);
        })
        .catch(() => {
          favorites.clear();
        });
    }

    if (decreaseQty) {
      decreaseQty.addEventListener('click', () => updateQuantity(-1));
    }
    if (increaseQty) {
      increaseQty.addEventListener('click', () => updateQuantity(1));
    }

    if (currentQty) {
      currentQty.addEventListener('input', () => {
        if (!currentProduct) return;
        const rawValue = currentQty.value;
        if (rawValue === '') {
          return;
        }
        setQuantityFromValue(rawValue);
      });

      currentQty.addEventListener('blur', () => {
        if (!currentProduct) return;
        const rawValue = currentQty.value;
        if (rawValue === '') {
          refreshQuantityState();
          return;
        }
        setQuantityFromValue(rawValue);
      });
    }

    if (cancelAdd) {
      cancelAdd.addEventListener('click', closeModal);
    }

    if (removeFromCart) {
      removeFromCart.addEventListener('click', async () => {
        if (!currentProduct || !userEmail) {
          closeModal();
          return;
        }

        const existing = cartItems.get(currentProduct.id);
        if (!existing) {
          closeModal();
          return;
        }

        if (cartSyncPromise) {
          try {
            await cartSyncPromise;
          } catch (error) {
            console.error('Unable to sync cart before removing item.', error);
          }
        }

        const body = new URLSearchParams();
        body.set('cart_item_id', String(existing.cartItemId));

        try {
          const response = await fetch('../../PHP/cart_api.php?action=remove', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
          });

          if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
          }

          let result;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('Invalid response format');
          }

          if (!result.deleted) {
            throw new Error('Unable to remove cart item');
          }

          cartItems.delete(currentProduct.id);
          showToast('Removed from cart.');
        } catch (error) {
          console.error('Remove from cart failed.', error);
          showToast('Unable to remove from cart.', 'error');
        } finally {
          closeModal();
        }
      });
    }

    if (modal) {
      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          closeModal();
        }
      });
    }

    if (confirmAdd) {
      confirmAdd.addEventListener('click', async () => {
        if (!currentProduct || !userEmail) {
          closeModal();
          return;
        }

        if (cartSyncPromise) {
          try {
            await cartSyncPromise;
          } catch (error) {
            console.error('Unable to sync cart before adding item.', error);
          }
        }

        const available = Number.isFinite(currentProduct.stock) ? currentProduct.stock : 0;
        if (available <= 0) {
          showToast('This item is currently unavailable.', 'warn');
          closeModal();
          return;
        }

        if (currentQty) {
          setQuantityFromValue(currentQty.value);
        } else {
          refreshQuantityState();
        }

        const desiredQuantity = Math.min(Math.max(1, Math.floor(currentQuantity)), available);
        const existing = cartItems.get(currentProduct.id);

        if (existing && desiredQuantity === existing.quantity) {
          showToast('Cart already has this quantity.');
          closeModal();
          return;
        }

        const body = new URLSearchParams();
        body.set('email', userEmail);

        let endpoint = '../../PHP/cart_api.php?action=add';
        if (existing) {
          endpoint = '../../PHP/cart_api.php?action=update';
          body.set('cart_item_id', existing.cartItemId);
          body.set('quantity', desiredQuantity.toString());
        } else {
          if (cartId) {
            body.set('cart_id', cartId);
          }
          body.set('product_id', currentProduct.id);
          body.set('quantity', desiredQuantity.toString());
        }

        try {
          const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
          });

          if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
          }

          let result;
          try {
            result = await response.json();
          } catch (parseError) {
            throw new Error('Invalid response format');
          }

          if (result.error) {
            throw new Error(result.error);
          }

          const parsedQuantity = Math.floor(Number(result.quantity));
          const actualQuantity = Number.isFinite(parsedQuantity) && parsedQuantity > 0
            ? parsedQuantity
            : desiredQuantity;

          if (existing) {
            if (!result.updated) {
              throw new Error('Unable to update cart item');
            }

            cartItems.set(currentProduct.id, {
              cartItemId: existing.cartItemId,
              quantity: actualQuantity,
            });

            const tone = result.capped ? 'warn' : 'success';
            const message = result.capped
              ? 'Cart quantity adjusted to available stock.'
              : 'Cart quantity updated!';
            showToast(message, tone);
          } else {
            const cartItemId = Number(result.cart_item_id);
            if (!cartItemId) {
              throw new Error('Unable to add cart item');
            }

            if (Number.isFinite(Number(result.cart_id)) && Number(result.cart_id) > 0) {
              cartId = Number(result.cart_id);
            }

            cartItems.set(currentProduct.id, {
              cartItemId,
              quantity: actualQuantity,
            });

            const tone = result.capped ? 'warn' : 'success';
            const message = result.capped
              ? 'Added to cart, adjusted to available stock.'
              : 'Added to cart!';
            showToast(message, tone);
          }
        } catch (error) {
          console.error('Add to cart failed.', error);
          showToast('Unable to add to cart.', 'error');
        } finally {
          closeModal();
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', () => refreshMenu({ resetPage: true }));
    }

    buildCategoryFilters();
    populateSection(bestSellerList, bestSellers);
    refreshMenu({ resetPage: true });

    if (prevPageButton) {
      prevPageButton.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage -= 1;
          refreshMenu();
        }
      });
    }

    if (nextPageButton) {
      nextPageButton.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredProducts.length / ITEMS_PER_PAGE));
        if (currentPage < totalPages) {
          currentPage += 1;
          refreshMenu();
        }
      });
    }

    onAuthStateChanged(auth, (user) => {
      userEmail = user ? user.email : null;
      if (userEmail) {
        hydrateCart(userEmail).catch(error => {
          console.error('Unable to load cart items.', error);
        });
        hydrateFavorites(userEmail);
      } else {
        favorites.clear();
        cartItems.clear();
        cartId = null;
        cartSyncPromise = null;
        refreshMenu();
        populateSection(bestSellerList, bestSellers);
      }
    });
  </script>
</body>
</html>
