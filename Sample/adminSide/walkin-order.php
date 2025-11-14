<?php
require_once __DIR__ . '/includes/require_admin_login.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../PHP/db_connect.php';
require_once '../PHP/product_functions.php';

$productCatalog = [];
if ($pdo) {
    try {
        $sql = "SELECT p.Product_ID, p.Name, p.Price, p.Category,\n                       COALESCE(i.Stock_Quantity, p.Stock_Quantity) AS Stock_Quantity,\n                       (i.Stock_Quantity IS NULL) AS Stock_Not_Tracked\n                FROM product p\n                LEFT JOIN inventory i ON i.Product_ID = p.Product_ID\n                ORDER BY p.Name ASC";
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $rawStock = array_key_exists('Stock_Quantity', $row) ? $row['Stock_Quantity'] : null;
                $stockNotTracked = !empty($row['Stock_Not_Tracked']);
                $categoryValue = normalizeProductCategoryValue($row['Category'] ?? '');
                $productCatalog[] = [
                    'id' => (int)$row['Product_ID'],
                    'name' => (string)($row['Name'] ?? ''),
                    'price' => isset($row['Price']) ? (float)$row['Price'] : 0.0,
                    'category' => $categoryValue === '' ? 'Uncategorized' : $categoryValue,
                    'stock' => $stockNotTracked ? null : ($rawStock === null ? null : (int)$rawStock),
                ];
            }
        }
    } catch (\PDOException $exception) {
        error_log('Failed to load POS product catalog: ' . $exception->getMessage());
        $productCatalog = [];
    }
}

$activePage = 'walkin-order';
$pageTitle = "Walk-in POS - Cindy's Bakeshop";

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Walk-in POS</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="pos-container">
    <form id="walkinOrderForm" class="pos-form" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
      <input type="hidden" id="orderStatus" value="Confirmed">

      <div class="pos-layout">
        <section class="pos-products">
          <div class="pos-toolbar">
            <input type="search" id="productSearch" placeholder="Search products or categories">
            <label class="pos-checkbox">
              <input type="checkbox" id="inStockOnly" checked>
              In stock only
            </label>
          </div>
          <div class="pos-product-grid" id="productResults" aria-live="polite"></div>
        </section>

        <aside class="pos-sidebar">
          <section class="pos-card">
            <h2>Cart</h2>
            <div class="pos-cart-table-wrapper">
              <table class="summary-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th style="width:110px;">Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="orderSummaryBody">
                  <tr class="summary-empty">
                    <td colspan="5">No items added.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="pos-card">
            <h2>Customer</h2>
            <p class="pos-customer-note">Walk-in sales are always recorded under a guest profile.</p>
          </section>

          <section class="pos-card pos-checkout">
            <h2>Payment</h2>
            <div class="form-field">
              <label for="fulfillmentType">Fulfillment</label>
              <select id="fulfillmentType">
                <option value="Pick up">Pick up</option>
                <option value="Delivery">Delivery</option>
              </select>
            </div>
            <div class="form-field">
              <label for="paymentMethod">Payment method</label>
              <select id="paymentMethod">
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
              </select>
            </div>
            <p class="pos-payment-note">Walk-in orders are automatically recorded as fully paid.</p>
            <div class="form-field">
              <label for="referenceNumber">Reference # (optional)</label>
              <input type="text" id="referenceNumber" maxlength="100">
            </div>
            <div class="pos-total">
              <span>Total</span>
              <strong id="orderTotal">₱0.00</strong>
            </div>
            <div id="formErrors" class="form-messages is-hidden" role="alert"></div>
            <button type="submit" class="btn btn-primary pos-submit">Complete order</button>
          </section>
        </aside>
      </div>
    </form>
    <div id="walkinMessages" class="form-messages is-hidden" role="alert"></div>
  </div>
</div>

<?php
$csrfTokenJson = json_encode($_SESSION['csrf_token']);
$apiUrlJson = json_encode('../PHP/walkin_order_actions.php');
$ordersUrlJson = json_encode('orders.php');
$productCatalogJson = json_encode($productCatalog, JSON_UNESCAPED_UNICODE);
if ($productCatalogJson === false) {
    $productCatalogJson = '[]';
}
$scriptTemplate = <<<'JS'
<script>
(() => {
  const csrfToken = %s;
  const apiUrl = %s;
  const ordersUrl = %s;
  const rawProductCatalog = %s;
  const form = document.getElementById('walkinOrderForm');
  const fulfillmentTypeSelect = document.getElementById('fulfillmentType');
  const orderStatusInput = document.getElementById('orderStatus');
  const paymentMethodSelect = document.getElementById('paymentMethod');
  const PAYMENT_STATUS = 'Paid';
  let currentOrderTotal = 0;
  const referenceNumberInput = document.getElementById('referenceNumber');
  const productSearchInput = document.getElementById('productSearch');
  const inStockOnlyCheckbox = document.getElementById('inStockOnly');
  const productResultsContainer = document.getElementById('productResults');
  const summaryBody = document.getElementById('orderSummaryBody');
  const orderTotalLabel = document.getElementById('orderTotal');
  const formErrors = document.getElementById('formErrors');
  const messages = document.getElementById('walkinMessages');

  const hasConsole = typeof console !== 'undefined';

  const normalizeCategoryLabel = (value) => {
    if (typeof value !== 'string') {
      return value || '';
    }
    const trimmed = value.trim();
    if (trimmed === '') {
      return 'Uncategorized';
    }
    return trimmed.toLowerCase().includes('pastry') ? 'Bread' : trimmed;
  };

  const productCatalog = Array.isArray(rawProductCatalog)
    ? rawProductCatalog.map((product) => Object.assign({}, product, {
        category: normalizeCategoryLabel(product.category),
      }))
    : [];
  const log = (level, ...args) => {
    if (!hasConsole) return;
    const method = console[level] || console.log;
    method.call(console, '[Walk-in POS]', ...args);
  };

  log('debug', 'POS script initialised');
  log('debug', 'Product catalog loaded', { count: productCatalog.length });

  const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

  const selectedItems = new Map();
  let productSearchTimer = null;

  function setMessage(target, type, text) {
    if (!target) return;
    log('debug', 'Setting message', { targetId: target.id || null, type, text });
    target.textContent = text;
    target.classList.remove('is-hidden', 'is-success', 'is-error');
    if (type === 'success') {
      target.classList.add('is-success');
    } else if (type === 'error') {
      target.classList.add('is-error');
    }
  }

  function clearMessage(target) {
    if (!target) return;
    log('debug', 'Clearing message', { targetId: target.id || null });
    target.textContent = '';
    target.classList.add('is-hidden');
    target.classList.remove('is-success', 'is-error');
  }

  function isFiniteStock(value) {
    if (value === null || value === undefined) {
      return null;
    }
    const numeric = Number(value);
    return Number.isFinite(numeric) ? numeric : null;
  }

  function createProductCard(product) {
    const normalizedStock = isFiniteStock(product.stock);
    log('debug', 'Creating product card', {
      id: product.id,
      name: product.name,
      category: product.category || null,
      rawStock: product.stock,
      normalizedStock
    });
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'pos-product-card';
    card.dataset.id = String(product.id);

    const header = document.createElement('div');
    header.className = 'pos-product-header';
    const name = document.createElement('strong');
    name.textContent = product.name;
    header.appendChild(name);
    const category = document.createElement('span');
    category.textContent = product.category || 'Uncategorised';
    header.appendChild(category);
    card.appendChild(header);

    const meta = document.createElement('div');
    meta.className = 'pos-product-meta';
    const price = document.createElement('span');
    price.textContent = peso.format(product.price);
    meta.appendChild(price);

    const stock = document.createElement('span');
    const hasTrackedStock = normalizedStock !== null;
    const stockClass = hasTrackedStock
      ? normalizedStock <= 0
        ? 'out'
        : normalizedStock < 10
          ? 'low'
          : 'ok'
      : 'not-tracked';
    stock.className = `pos-product-stock ${stockClass}`;
    stock.textContent = hasTrackedStock
      ? normalizedStock > 0
        ? `Stock: ${normalizedStock}`
        : 'Out of stock'
      : 'Not tracked';
    meta.appendChild(stock);
    card.appendChild(meta);

    card.disabled = hasTrackedStock && normalizedStock <= 0;
    card.addEventListener('click', () => {
      log('debug', 'Product card clicked', { product });
      const existing = selectedItems.get(product.id) || {
        id: product.id,
        name: product.name,
        price: product.price,
        stock: hasTrackedStock ? normalizedStock : null,
        quantity: 0
      };
      const stockLimit = hasTrackedStock ? normalizedStock : null;
      if (stockLimit !== null && existing.quantity >= stockLimit) {
        setMessage(formErrors, 'error', `Only ${stockLimit} piece(s) of ${product.name} available.`);
        return;
      }
      existing.quantity += 1;
      existing.stock = stockLimit;
      selectedItems.set(product.id, existing);
      renderSelectedItems();
      clearMessage(formErrors);
    });

    return card;
  }

  function renderProductResults(products) {
    log('debug', 'Rendering product results', { count: products.length });
    if (products.length) {
      log('debug', 'Product result sample', {
        sample: products.slice(0, 5).map((product) => ({
          id: product.id,
          name: product.name,
          category: product.category || null,
          stock: product.stock
        }))
      });
    } else {
      log('debug', 'Product result set empty for current query');
    }
    productResultsContainer.innerHTML = '';
    if (!products.length) {
      const empty = document.createElement('div');
      empty.className = 'empty-state';
      empty.textContent = 'No products matched the search.';
      productResultsContainer.appendChild(empty);
      return;
    }
    products.forEach((product) => {
      productResultsContainer.appendChild(createProductCard(product));
    });
  }

  function updateQuantity(id, nextQuantity) {
    log('debug', 'Update quantity requested', { id, nextQuantity });
    const item = selectedItems.get(id);
    if (!item) return;
    if (nextQuantity < 1) {
      nextQuantity = 1;
    }
    const stockLimit = typeof item.stock === 'number' && Number.isFinite(item.stock) ? item.stock : null;
    if (stockLimit !== null && nextQuantity > stockLimit) {
      nextQuantity = stockLimit;
      setMessage(formErrors, 'error', `Only ${stockLimit} piece(s) of ${item.name} available.`);
    } else {
      clearMessage(formErrors);
    }
    item.quantity = nextQuantity;
    selectedItems.set(id, item);
    renderSelectedItems();
  }

  function renderSelectedItems() {
    log('debug', 'Rendering selected items', { itemCount: selectedItems.size });
    summaryBody.innerHTML = '';
    let total = 0;
    if (!selectedItems.size) {
      const row = document.createElement('tr');
      row.className = 'summary-empty';
      const cell = document.createElement('td');
      cell.colSpan = 5;
      cell.textContent = 'No items added.';
      row.appendChild(cell);
      summaryBody.appendChild(row);
    } else {
      selectedItems.forEach((item, id) => {
        const row = document.createElement('tr');
        row.dataset.id = String(id);

        const nameCell = document.createElement('td');
        nameCell.className = 'summary-name';
        nameCell.textContent = item.name;
        row.appendChild(nameCell);

        const qtyCell = document.createElement('td');
        const qtyWrapper = document.createElement('div');
        qtyWrapper.className = 'quantity-control';

        const minusBtn = document.createElement('button');
        minusBtn.type = 'button';
        minusBtn.className = 'qty-btn';
        minusBtn.textContent = '−';
        minusBtn.addEventListener('click', () => updateQuantity(id, item.quantity - 1));
        qtyWrapper.appendChild(minusBtn);

        const qtyInput = document.createElement('input');
        const stockLimit = typeof item.stock === 'number' && Number.isFinite(item.stock) ? item.stock : null;
        qtyInput.type = 'number';
        qtyInput.min = '1';
        if (stockLimit !== null) {
          qtyInput.max = String(stockLimit);
        } else {
          qtyInput.removeAttribute('max');
        }
        qtyInput.value = String(item.quantity);
        qtyInput.addEventListener('change', () => {
          let next = parseInt(qtyInput.value, 10);
          if (Number.isNaN(next) || next < 1) {
            next = 1;
          }
          if (stockLimit !== null && next > stockLimit) {
            next = stockLimit;
          }
          updateQuantity(id, next);
        });
        qtyWrapper.appendChild(qtyInput);

        const plusBtn = document.createElement('button');
        plusBtn.type = 'button';
        plusBtn.className = 'qty-btn';
        plusBtn.textContent = '+';
        plusBtn.addEventListener('click', () => updateQuantity(id, item.quantity + 1));
        qtyWrapper.appendChild(plusBtn);

        qtyCell.appendChild(qtyWrapper);
        row.appendChild(qtyCell);

        const priceCell = document.createElement('td');
        priceCell.textContent = peso.format(item.price);
        row.appendChild(priceCell);

        const subtotal = item.price * item.quantity;
        total += subtotal;
        const subtotalCell = document.createElement('td');
        subtotalCell.textContent = peso.format(subtotal);
        row.appendChild(subtotalCell);

        const removeCell = document.createElement('td');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-muted btn-small';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', () => {
          log('debug', 'Removing item from cart', { id, name: item.name });
          selectedItems.delete(id);
          renderSelectedItems();
        });
        removeCell.appendChild(removeBtn);
        row.appendChild(removeCell);

        summaryBody.appendChild(row);
      });
    }

    orderTotalLabel.textContent = peso.format(total);
    currentOrderTotal = total;
    log('debug', 'Cart totals updated', {
      total,
      paymentStatus: PAYMENT_STATUS,
      items: Array.from(selectedItems.values()).map((item) => ({
        id: item.id,
        name: item.name,
        quantity: item.quantity,
        stock: item.stock,
        price: item.price,
      })),
    });
  }

  function fetchProducts(term) {
    const normalizedTerm = typeof term === 'string' ? term.trim().toLowerCase() : '';
    const inStockOnly = inStockOnlyCheckbox.checked;
    log('debug', 'Filtering product catalog', { term: normalizedTerm, inStockOnly });

    const filtered = productCatalog.filter((product) => {
      const name = (product.name || '').toLowerCase();
      const category = (product.category || '').toLowerCase();
      const matchesTerm = normalizedTerm === '' || name.includes(normalizedTerm) || category.includes(normalizedTerm);
      if (!matchesTerm) {
        return false;
      }
      if (!inStockOnly) {
        return true;
      }
      const stockValue = isFiniteStock(product.stock);
      return stockValue === null || stockValue > 0;
    });

    log('debug', 'Product filter results', { count: filtered.length });
    if (filtered.length) {
      log('debug', 'Product filter sample', {
        sample: filtered.slice(0, 5).map((product) => ({
          id: product.id,
          name: product.name,
          category: product.category || null,
          stock: product.stock
        }))
      });
    } else {
      log('debug', 'Product filter returned no matches for current criteria');
    }
    renderProductResults(filtered);
  }

  async function callApi(action, params = {}) {
    const body = new URLSearchParams();
    body.set('action', action);
    Object.entries(params).forEach(([key, value]) => {
      if (value === undefined || value === null) {
        return;
      }
      body.set(key, String(value));
    });
    body.set('csrf_token', csrfToken);
    const debugPayload = {};
    body.forEach((value, key) => {
      debugPayload[key] = key === 'csrf_token' ? '[redacted]' : value;
    });
    log('debug', 'API request payload', debugPayload);
    const response = await fetch(apiUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body
    });
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (error) {
      log('error', 'Failed to parse API response', { text });
      throw new Error(text || 'Unexpected response from server.');
    }
    log('debug', 'API response received', { status: response.status, ok: response.ok, body: json });
    if (!response.ok) {
      log('error', 'API responded with error status', { status: response.status, body: json });
      throw new Error(json.message || 'Request failed.');
    }
    return json;
  }

  async function submitForm(event) {
    event.preventDefault();
    clearMessage(formErrors);
    clearMessage(messages);

    log('debug', 'Form submission initiated', {
      itemCount: selectedItems.size,
      paymentStatus: PAYMENT_STATUS,
    });

    if (!selectedItems.size) {
      log('warn', 'Submission blocked: no items selected');
      setMessage(formErrors, 'error', 'Add at least one product to the cart.');
      return;
    }

    const items = Array.from(selectedItems.values()).map((item) => ({
      product_id: item.id,
      quantity: item.quantity
    }));
    log('debug', 'Items prepared for submission', { items });

    const payload = {
      customer_mode: 'guest',
      fulfillment_type: fulfillmentTypeSelect.value,
      order_status: orderStatusInput ? orderStatusInput.value : 'Confirmed',
      payment_method: paymentMethodSelect.value,
      payment_status: PAYMENT_STATUS,
      payment_amount: currentOrderTotal.toFixed(2),
      reference_number: referenceNumberInput.value.trim(),
      items: JSON.stringify(items)
    };

    const payloadPreview = Object.assign({
      action: 'create_order',
      csrf_token: '[redacted]'
    }, payload);
    log('debug', 'Payload built', payloadPreview);

    try {
      const result = await callApi('create_order', payload);
      if (!result.success) {
        throw new Error(result.message || 'Failed to create order.');
      }

      log('debug', 'Order created successfully', result);

      clearMessage(formErrors);
      setMessage(messages, 'success', `Order #${String(result.order_id).padStart(5, '0')} created.`);
      const link = document.createElement('a');
      link.href = ordersUrl;
      link.textContent = 'View orders';
      link.className = 'link-order';
      messages.appendChild(document.createTextNode(' '));
      messages.appendChild(link);

      selectedItems.clear();
      renderSelectedItems();

      referenceNumberInput.value = '';
      paymentMethodSelect.value = 'Cash';
      if (fulfillmentTypeSelect) {
        fulfillmentTypeSelect.value = 'Pick up';
      }
      if (orderStatusInput) {
        orderStatusInput.value = 'Confirmed';
      }
      log('debug', 'Form reset after successful submission');
    } catch (error) {
      log('error', 'Order submission failed', error);
      setMessage(messages, 'error', error.message || 'Something went wrong while creating the order.');
    }
  }

  productSearchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      log('debug', 'Prevented Enter key default in product search input');
    }
  });
  productSearchInput.addEventListener('input', () => {
    if (productSearchTimer) {
      clearTimeout(productSearchTimer);
    }
    const term = productSearchInput.value.trim();
    log('debug', 'Product search term updated', { term });
    productSearchTimer = setTimeout(() => {
      log('debug', 'Product search debounce fired', {
        term,
        inStockOnly: inStockOnlyCheckbox.checked
      });
      fetchProducts(term);
    }, 200);
  });

  inStockOnlyCheckbox.addEventListener('change', () => {
    log('debug', 'In-stock filter toggled', { checked: inStockOnlyCheckbox.checked });
    fetchProducts(productSearchInput.value.trim());
  });

  form.addEventListener('submit', submitForm);

  renderSelectedItems();
  fetchProducts('');
})();
</script>
JS;

$extraScripts = sprintf($scriptTemplate, $csrfTokenJson, $apiUrlJson, $ordersUrlJson, $productCatalogJson);

include 'includes/footer.php';
