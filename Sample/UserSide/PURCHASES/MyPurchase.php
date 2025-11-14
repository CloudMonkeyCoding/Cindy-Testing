<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Orders - Cindy's Bakeshop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css" />
  <style>
    body.purchases-view {
      display: flex;
      flex-direction: column;
    }

    .orders-hero {
      background: linear-gradient(135deg, rgba(139, 69, 19, 0.92), rgba(130, 214, 247, 0.85));
      border-radius: 32px;
      padding: clamp(2.5rem, 5vw, 4rem);
      color: #fff;
      margin-bottom: 3rem;
      box-shadow: 0 30px 60px rgba(139, 69, 19, 0.25);
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .orders-hero h1 {
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 700;
    }

    .orders-hero p {
      font-size: 1.05rem;
      max-width: 560px;
      opacity: 0.85;
    }

    .status-tabs {
      display: inline-flex;
      flex-wrap: wrap;
      background: rgba(255, 255, 255, 0.9);
      padding: 0.5rem;
      border-radius: var(--radius-pill);
      box-shadow: var(--shadow-soft);
      gap: 0.5rem;
    }

    .status-tabs button {
      border: none;
      padding: 0.65rem 1.4rem;
      border-radius: var(--radius-pill);
      background: transparent;
      font-weight: 600;
      color: var(--primary-brown);
      cursor: pointer;
    }

    .status-tabs button.active {
      background: linear-gradient(135deg, var(--primary-brown), var(--primary-brown-dark));
      color: #fff;
      box-shadow: 0 12px 28px rgba(139, 69, 19, 0.2);
    }

    .orders-grid {
      display: grid;
      gap: 1.6rem;
      margin-top: 2.5rem;
    }

    .order-card {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 28px;
      padding: 1.6rem;
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 1.5rem;
      align-items: center;
      box-shadow: var(--shadow-soft);
      border: 1px solid rgba(139, 69, 19, 0.1);
    }

    .order-summary {
      display: flex;
      gap: 1.2rem;
      align-items: center;
    }

    .order-summary img {
      width: 96px;
      height: 96px;
      object-fit: cover;
      border-radius: 22px;
      box-shadow: 0 18px 32px rgba(139, 69, 19, 0.18);
    }

    .order-meta {
      display: grid;
      gap: 0.35rem;
    }

    .order-meta h3 {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--primary-brown);
    }

    .order-meta span {
      color: var(--text-muted);
      font-size: 0.95rem;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.4rem 1rem;
      border-radius: var(--radius-pill);
      font-size: 0.85rem;
      font-weight: 600;
      background: rgba(139, 69, 19, 0.12);
      color: var(--primary-brown);
    }

    .order-actions {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.75rem;
    }

    .order-actions a,
    .order-actions button {
      padding: 0.7rem 1.4rem;
      border-radius: var(--radius-pill);
      border: none;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }

    .order-actions a {
      background: rgba(139, 69, 19, 0.1);
      color: var(--primary-brown);
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      border-radius: 28px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px dashed rgba(139, 69, 19, 0.2);
      color: var(--text-muted);
      box-shadow: var(--shadow-soft);
      margin-top: 2rem;
    }


    @media (max-width: 780px) {
      .order-card {
        grid-template-columns: 1fr;
        align-items: flex-start;
      }

      .order-actions {
        align-items: stretch;
      }
    }
  </style>
</head>
<body class="purchases-view">
  <?php include __DIR__ . '/../topbar.php'; ?>

  <main class="page-container">
    <section class="orders-hero">
      <h1>Track your Cindy's journeys</h1>
      <p>From the oven to your doorstep—see what’s baking, what’s on the way, and what you’ve already savoured.</p>
      <div class="status-tabs" role="tablist">
        <button type="button" class="active" data-filter="all">All orders</button>
        <button type="button" data-filter="to-process">To process</button>
        <button type="button" data-filter="to-receive">To receive</button>
        <button type="button" data-filter="completed">Completed</button>
      </div>
    </section>

    <div class="orders-grid" id="ordersGrid"></div>
    <div class="empty-state" id="ordersEmpty" hidden>No orders yet. Explore the menu and treat yourself!</div>
  </main>

  <script type="module">
    import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
    import "../firebase-init.js";

    const auth = getAuth();
    const ordersGrid = document.getElementById('ordersGrid');
    const ordersEmpty = document.getElementById('ordersEmpty');
    const tabs = document.querySelectorAll('.status-tabs button');

    let userEmail = null;
    let orders = [];
    let activeFilter = 'all';

    const STATUS_MAP = new Map([
      ['Pending', 'to-process'],
      ['Processing', 'to-process'],
      ['Confirmed', 'to-process'],
      ['On Delivery', 'to-receive'],
      ['Shipped', 'to-receive'],
      ['Ready for pickup', 'to-receive'],
      ['Completed', 'completed'],
      ['Delivered', 'completed'],
      ['Cancelled', 'completed']
    ]);

    function normalizeImagePath(path) {
      if (!path) {
        return '/Images/logo.png';
      }
      const trimmed = path.trim();
      if (trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('data:')) {
        return trimmed;
      }
      return trimmed.startsWith('/') ? trimmed : `/${trimmed.replace(/^\/+/, '')}`;
    }

    function resolveOrderImage(order) {
      const apiProvided = typeof order.Image_Url === 'string' ? order.Image_Url.trim() : '';
      if (apiProvided) {
        return normalizeImagePath(apiProvided);
      }

      const legacyPath = typeof order.Image_Path === 'string' ? order.Image_Path.trim() : '';
      if (legacyPath) {
        return normalizeImagePath(`/adminSide/products/uploads/${legacyPath}`);
      }

      return '/Images/logo.png';
    }

    function statusToCategory(status) {
      return STATUS_MAP.get(status) || 'to-process';
    }

    function formatDate(input) {
      if (!input) return '—';
      const date = new Date(input);
      if (Number.isNaN(date.getTime())) {
        return input;
      }
      return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function renderOrders() {
      ordersGrid.innerHTML = '';
      const filtered = orders.filter(order => activeFilter === 'all' || statusToCategory(order.Status) === activeFilter);

      if (filtered.length === 0) {
        ordersEmpty.hidden = false;
        return;
      }
      ordersEmpty.hidden = true;

      filtered.forEach(order => {
        const card = document.createElement('article');
        card.className = 'order-card';
        const category = statusToCategory(order.Status);
        const imgSrc = resolveOrderImage(order);
        card.innerHTML = `
          <div class="order-summary">
            <img src="${imgSrc}" alt="Order product image">
            <div class="order-meta">
              <h3>Order #${order.Order_ID ?? ''}</h3>
              <span>${formatDate(order.Order_Date ?? order.created_at)}</span>
              <span>Total Items: ${order.Total_Items ?? order.Quantity ?? 1}</span>
              <span class="badge">${order.Status || 'Processing'}</span>
            </div>
          </div>
          <div class="order-actions">
            <a href="../INVOICE/orderDetails.php?order_id=${order.Order_ID ?? ''}">View details</a>
          </div>
        `;
        ordersGrid.appendChild(card);
      });
    }

    function attachTabHandlers() {
      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          tabs.forEach(btn => btn.classList.remove('active'));
          tab.classList.add('active');
          activeFilter = tab.dataset.filter;
          renderOrders();
        });
      });
    }

    function fetchOrders(email) {
      return fetch(`../../PHP/order_api.php?action=list&email=${encodeURIComponent(email)}`)
        .then(res => res.json())
        .then(data => {
          if (data.error) {
            throw new Error(data.error);
          }
          orders = Array.isArray(data) ? data : [];
          renderOrders();
        })
        .catch(() => {
          orders = [];
          renderOrders();
        });
    }

    onAuthStateChanged(auth, user => {
      if (user) {
        userEmail = user.email;
        fetchOrders(userEmail);
      }
    });

    attachTabHandlers();
    renderOrders();
  </script>
</body>
</html>
