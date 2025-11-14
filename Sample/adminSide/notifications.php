<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/notification_functions.php';

$activePage = 'notifications';
$pageTitle = "Notifications - Cindy's Bakeshop";

$notifications = [];
if ($pdo) {
    $notifications = getAllNotifications($pdo);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Notifications</h1>
    <button class="btn btn-primary" id="markAll">Mark all as read</button>
  </div>

  <div class="table-container">
    <table id="notificationTable">
      <thead>
        <tr>
          <th>Type</th>
          <th>Message</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($notifications)): ?>
          <tr><td colspan="5" class="table-empty">No notifications yet.</td></tr>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <?php
              $createdAtRaw = $notification['Created_At'] ?? null;
              $createdAtDisplay = formatAdminDateTime($createdAtRaw, 'F j, Y g:i A', '—');
            ?>
            <tr data-id="<?= $notification['Notification_ID']; ?>" data-read="<?= (int)$notification['Is_Read']; ?>">
              <td><?= htmlspecialchars(ucfirst($notification['Type'] ?? 'System')); ?></td>
              <td><?= htmlspecialchars($notification['Message'] ?? ''); ?></td>
              <td><?= htmlspecialchars($notification['Reference_ID'] ?? ''); ?></td>
              <td><?= htmlspecialchars($createdAtDisplay); ?></td>
              <td>
                <span class="badge <?= $notification['Is_Read'] ? 'badge-success' : 'badge-warning'; ?>">
                  <?= $notification['Is_Read'] ? 'Read' : 'Unread'; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraScripts = <<<JS
<script>
  document.getElementById('markAll').addEventListener('click', async () => {
    const ids = Array.from(document.querySelectorAll('#notificationTable tbody tr')).map(row => row.dataset.id);
    if (!ids.length) return;
    try {
      const response = await fetch('../PHP/notification_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_read', ids })
      });
      const result = await response.json();
      if (!result.success) throw new Error(result.message || 'Failed to update notifications');
      document.querySelectorAll('#notificationTable tbody tr').forEach(row => {
        row.dataset.read = '1';
        const badge = row.querySelector('.badge');
        badge.className = 'badge badge-success';
        badge.textContent = 'Read';
      });
    } catch (error) {
      alert(error.message);
    }
  });
</script>
JS;
include 'includes/footer.php';
