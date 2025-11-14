<?php
require_once __DIR__ . '/includes/require_super_admin.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/audit_log_functions.php';

$activePage = 'logs';
$pageTitle = "Activity Logs - Cindy's Bakeshop";
$logs = [];
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
if ($limit <= 0) {
    $limit = 200;
}

if ($pdo instanceof PDO) {
    $logs = fetch_audit_logs($pdo, $limit);
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="main">
  <div class="header">
    <h1>Activity Logs</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="table-container">
    <div class="table-actions" style="justify-content: space-between; align-items: center;">
      <span class="muted">Showing <?= count($logs); ?> most recent events</span>
      <form method="get" style="display:flex; gap:8px; align-items:center;">
        <label for="limit" class="muted" style="font-size:0.85rem;">Rows:</label>
        <input type="number" id="limit" name="limit" min="20" max="500" value="<?= htmlspecialchars((string)$limit); ?>" style="width:90px;">
        <button type="submit" class="btn btn-secondary">Refresh</button>
      </form>
    </div>

    <table class="log-table">
      <thead>
        <tr>
          <th style="width:140px;">Timestamp</th>
          <th style="width:140px;">Event</th>
          <th>Description</th>
          <th style="width:180px;">Actor</th>
          <th style="width:140px;">Source</th>
          <th>Metadata</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" class="muted" style="text-align:center; padding:24px;">No activity has been logged yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <?php
              $timestamp = $log['Created_At'] ?? '';
              $eventType = $log['Event_Type'] ?? '';
              $description = $log['Description'] ?? '';
              $actorEmail = $log['Actor_Email'] ?? '';
              $actorId = isset($log['Actor_User_ID']) ? (int) $log['Actor_User_ID'] : null;
              $source = $log['Source'] ?? '';
              $metadata = isset($log['Metadata']) && is_array($log['Metadata']) ? $log['Metadata'] : [];
              $metadataDisplay = $metadata ? json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
              $timestampFormatted = formatAdminDateTime($timestamp, 'F j, Y g:i A', '—');
            ?>
            <tr>
              <td><?= htmlspecialchars($timestampFormatted); ?></td>
              <td><span class="log-event-type"><?= htmlspecialchars($eventType); ?></span></td>
              <td><?= htmlspecialchars($description); ?></td>
              <td>
                <?php if ($actorEmail): ?>
                  <?= htmlspecialchars($actorEmail); ?>
                <?php else: ?>
                  <span class="muted">System</span>
                <?php endif; ?>
                <?php if ($actorId): ?>
                  <span class="log-actor-meta">User ID: <?= $actorId; ?></span>
                <?php endif; ?>
              </td>
              <td><?= $source ? htmlspecialchars($source) : '<span class="muted">&mdash;</span>'; ?></td>
              <td>
                <?php if ($metadataDisplay): ?>
                  <details>
                    <summary>View</summary>
                    <pre><?= htmlspecialchars($metadataDisplay); ?></pre>
                  </details>
                <?php else: ?>
                  <span class="muted">&mdash;</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
