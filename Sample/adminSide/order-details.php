<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/order_functions.php';
require_once '../PHP/order_item_functions.php';
require_once '../PHP/transaction_functions.php';
require_once '../PHP/user_functions.php';

function formatLongDate(?string $value, string $emptyFallback = 'N/A'): string
{
    if (!$value) {
        return $emptyFallback;
    }

    try {
        return (new \DateTime($value))->format('F j, Y');
    } catch (\Exception $exception) {
        $timestamp = strtotime($value);
        if ($timestamp) {
            return date('F j, Y', $timestamp);
        }
    }

    return $value;
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId) {
    header('Location: orders.php');
    exit;
}

$order = null;
$user = null;
$items = [];
$transaction = null;
$total = 0;

if ($pdo) {
    $order = getOrderById($pdo, $orderId);
    if ($order) {
        $user = getUserById($pdo, $order['User_ID']);
        $items = getOrderItemsByOrderId($pdo, $orderId);
        $total = calculateOrderTotal($pdo, $orderId) ?? 0;
        $stmt = $pdo->prepare('SELECT * FROM transaction WHERE Order_ID = :order_id LIMIT 1');
        $stmt->execute([':order_id' => $orderId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$formattedOrderDate = $order ? formatLongDate($order['Order_Date'] ?? null, 'N/A') : 'N/A';
$formattedPaymentDate = $transaction ? formatLongDate($transaction['Payment_Date'] ?? null, 'N/A') : 'N/A';

$activePage = 'orders';
$pageTitle = 'Order Details - Cindy\'s Bakeshop';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Order #<?= str_pad($orderId, 5, '0', STR_PAD_LEFT); ?></h1>
    <a href="orders.php" class="btn btn-primary" style="text-decoration:none;color:#fff;">← Back to Orders</a>
  </div>

  <?php if (!$order): ?>
    <div class="card">
      <p class="table-empty">Order not found.</p>
    </div>
  <?php else: ?>
    <section class="stats-grid columns-4" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
      <div class="stat-card">
        <h3>Status</h3>
        <div class="value" style="font-size:20px;">
          <span class="status-pill status-<?= strtolower($order['Status']); ?>">
            <?= htmlspecialchars($order['Status']); ?>
          </span>
        </div>
        <div class="meta">Last updated: <?= htmlspecialchars($formattedOrderDate); ?></div>
      </div>
      <div class="stat-card">
        <h3>Total Amount</h3>
        <div class="value">₱<?= number_format($total, 2); ?></div>
        <div class="meta">Includes all items</div>
      </div>
      <div class="stat-card">
        <h3>Payment Method</h3>
        <div class="value" style="font-size:20px;">
          <?= htmlspecialchars($transaction['Payment_Method'] ?? 'N/A'); ?>
        </div>
        <div class="meta">Status: <?= htmlspecialchars($transaction['Payment_Status'] ?? 'Pending'); ?></div>
      </div>
      <div class="stat-card">
        <h3>Customer</h3>
        <div class="value" style="font-size:20px;">
          <?= htmlspecialchars($user['Name'] ?? 'Customer ' . $order['User_ID']); ?>
        </div>
        <div class="meta">Email: <?= htmlspecialchars($user['Email'] ?? 'Not provided'); ?></div>
      </div>
    </section>

    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Items</h2>
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="3" class="table-empty">No items recorded.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['Name'] ?? 'Product ' . $item['Product_ID']); ?></td>
                <td><?= number_format($item['Quantity']); ?></td>
                <td>₱<?= number_format($item['Subtotal'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Payment Details</h2>
      <?php if (!$transaction): ?>
        <p class="table-empty">No transaction recorded.</p>
      <?php else: ?>
        <table>
          <tbody>
            <tr>
              <th style="width:200px;">Reference Number</th>
              <td><?= htmlspecialchars($transaction['Reference_Number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
              <th>Amount Paid</th>
              <td>₱<?= number_format($transaction['Amount_Paid'] ?? 0, 2); ?></td>
            </tr>
            <tr>
              <th>Payment Date</th>
              <td><?= htmlspecialchars($formattedPaymentDate); ?></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
