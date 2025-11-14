<?php
require_once __DIR__ . '/includes/require_admin_login.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../PHP/db_connect.php';
require_once '../PHP/order_functions.php';

$activePage = 'orders';
$pageTitle = "Manage Orders - Cindy's Bakeshop";

$orders = [];
$statusOptions = ['Pending', 'Confirmed', 'Shipped', 'Delivered'];
if ($pdo) {
    $sql = "SELECT o.Order_ID, o.Order_Date, o.Status, o.Source, o.Fulfillment_Type, u.Name, 
                   COALESCE(SUM(oi.Quantity), 0) AS Item_Count,
                   COALESCE(SUM(oi.Subtotal), 0) AS Total_Amount,
                   GROUP_CONCAT(CONCAT(p.Name, ' x', oi.Quantity) SEPARATOR ', ') AS Item_Summary
            FROM `order` o
            LEFT JOIN user u ON o.User_ID = u.User_ID
            LEFT JOIN order_item oi ON oi.Order_ID = o.Order_ID
            LEFT JOIN product p ON oi.Product_ID = p.Product_ID
            GROUP BY o.Order_ID, o.Order_Date, o.Status, o.Source, o.Fulfillment_Type, u.Name
            ORDER BY o.Order_Date DESC, o.Order_ID DESC";
    $stmt = $pdo->query($sql);
    $orders = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Manage Orders</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="table-container">
    <div class="table-actions">
      <input type="text" id="searchBox" placeholder="🔍 Search order...">
      <select id="filterStatus">
        <option value="all">All Status</option>
        <?php foreach ($statusOptions as $statusOption): ?>
          <option value="<?= htmlspecialchars($statusOption); ?>"><?= htmlspecialchars($statusOption); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <table id="orderTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Source</th>
          <th>Fulfillment</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="9" class="table-empty">No orders found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $order): ?>
            <?php
              $sourceValue = $order['Source'] ?? '';
              $sourceLabel = $sourceValue ? ucwords(str_replace(['-', '_'], [' ', ' '], $sourceValue)) : '—';
              $fulfillmentLabel = $order['Fulfillment_Type'] ?? '—';
              $itemSummary = $order['Item_Summary'] ?? 'No items recorded';
              $formattedOrderDate = formatAdminDateTime($order['Order_Date'] ?? null, 'F j, Y g:i A', '—');
            ?>
            <tr data-order-id="<?= $order['Order_ID']; ?>"
                data-status="<?= htmlspecialchars($order['Status']); ?>"
                data-source="<?= htmlspecialchars($sourceValue); ?>"
                data-fulfillment="<?= htmlspecialchars($order['Fulfillment_Type'] ?? ''); ?>"
                data-summary="<?= htmlspecialchars($itemSummary); ?>">
              <td>#<?= str_pad($order['Order_ID'], 5, '0', STR_PAD_LEFT); ?></td>
              <td><?= htmlspecialchars($order['Name'] ?? 'Customer ' . $order['Order_ID']); ?></td>
              <td><?= htmlspecialchars($itemSummary); ?></td>
              <td><?= htmlspecialchars($sourceLabel); ?></td>
              <td><?= htmlspecialchars($fulfillmentLabel); ?></td>
              <td>₱<?= number_format((float)($order['Total_Amount'] ?? 0), 2); ?></td>
              <td>
                <span class="status-pill status-<?= strtolower($order['Status']); ?>">
                  <?= htmlspecialchars($order['Status']); ?>
                </span>
              </td>
              <td><?= htmlspecialchars($formattedOrderDate); ?></td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn btn-primary btn-view" data-order="<?= $order['Order_ID']; ?>">View</button>
                <button class="btn btn-secondary btn-edit" data-order="<?= $order['Order_ID']; ?>">Update</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal" id="orderModal">
  <div class="modal-content">
    <h2>Update Order Status</h2>
    <form id="updateStatusForm">
      <input type="hidden" name="order_id" id="modalOrderId">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
      <div class="form-group">
        <label for="modalStatus">Status</label>
        <select name="status" id="modalStatus">
          <?php foreach ($statusOptions as $statusOption): ?>
            <option value="<?= htmlspecialchars($statusOption); ?>"><?= htmlspecialchars($statusOption); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:12px;justify-content:flex-end;">
        <button type="button" class="btn btn-muted" id="closeModal">Close</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<?php
$ordersJson = json_encode(array_map(static function ($order) {
    $formattedDate = formatAdminDateTime($order['Order_Date'] ?? null, 'F j, Y g:i A');

    return [
        'id' => (int)$order['Order_ID'],
        'status' => $order['Status'],
        'summary' => $order['Item_Summary'] ?? '',
        'date' => $formattedDate,
        'source' => $order['Source'] ?? '',
        'fulfillment' => $order['Fulfillment_Type'] ?? '',
    ];
}, $orders));
$csrfToken = json_encode($_SESSION['csrf_token']);
$extraScripts = <<<JS
<script>
  const ordersData = $ordersJson;
  const csrfToken = $csrfToken;
  const modal = document.getElementById('orderModal');
  const modalOrderId = document.getElementById('modalOrderId');
  const modalStatus = document.getElementById('modalStatus');
  const closeModalBtn = document.getElementById('closeModal');
  const updateForm = document.getElementById('updateStatusForm');
  const searchBox = document.getElementById('searchBox');
  const filterStatus = document.getElementById('filterStatus');

  if (filterStatus) {
    const statusOptions = Array.from(filterStatus.options).map(option => option.value);
    const params = new URLSearchParams(window.location.search);
    const statusParam = params.get('status');
    if (statusParam && statusOptions.includes(statusParam)) {
      filterStatus.value = statusParam;
    }
  }

  function openModal(orderId, currentStatus) {
    modal.classList.add('active');
    modalOrderId.value = orderId;
    modalStatus.value = currentStatus || 'Pending';
  }

  function closeModal() {
    modal.classList.remove('active');
  }

  closeModalBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal();
  });

  document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', () => {
      const orderId = button.dataset.order;
      const row = document.querySelector(`tr[data-order-id="\${orderId}"]`);
      const status = row ? row.dataset.status : 'Pending';
      openModal(orderId, status);
    });
  });

  document.querySelectorAll('.btn-view').forEach(button => {
    button.addEventListener('click', () => {
      const orderId = button.dataset.order;
      window.location.href = `order-details.php?order_id=\${orderId}`;
    });
  });

  function refreshRow(orderId, newStatus) {
    const row = document.querySelector(`tr[data-order-id="\${orderId}"]`);
    if (!row) return;
    row.dataset.status = newStatus;
    const pill = row.querySelector('.status-pill');
    pill.textContent = newStatus;
    pill.className = 'status-pill status-' + newStatus.toLowerCase();
  }

  async function updateStatus(orderId, status) {
    try {
      const response = await fetch('../PHP/order_actions.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams({
          action: 'update_status',
          order_id: orderId,
          status,
          csrf_token: csrfToken
        })
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Failed to update status');
      }
      refreshRow(orderId, status);
    } catch (error) {
      alert(error.message);
    }
  }

  updateForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const orderId = modalOrderId.value;
    const status = modalStatus.value;
    await updateStatus(orderId, status);
    closeModal();
  });

  function applyFilters() {
    const query = searchBox.value.toLowerCase();
    const status = filterStatus.value;
    document.querySelectorAll('#orderTable tbody tr').forEach(row => {
      if (row.classList.contains('hidden-row')) return;
      const cells = row.querySelectorAll('td');
      if (!cells.length) return;
      const id = cells[0].textContent.toLowerCase();
      const customer = cells[1].textContent.toLowerCase();
      const summary = (row.dataset.summary || '').toLowerCase();
      const source = (row.dataset.source || '').toLowerCase();
      const fulfillment = (row.dataset.fulfillment || '').toLowerCase();
      const rowStatus = row.dataset.status;
      const matchesSearch = id.includes(query) || customer.includes(query) || summary.includes(query) || source.includes(query) || fulfillment.includes(query);
      const matchesStatus = status === 'all' || rowStatus === status;
      row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
  }

  searchBox.addEventListener('input', applyFilters);
  filterStatus.addEventListener('change', applyFilters);
  applyFilters();
</script>
JS;
include 'includes/footer.php';
