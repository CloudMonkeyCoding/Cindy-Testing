<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';

$pageTitle = "My Profile - Cindy's Bakeshop";
$activePage = 'settings';

$profile = null;
$staffRecord = null;
$roleLabel = !empty($adminSession['is_super_admin']) ? 'Super Admin' : 'Employee';
$userId = isset($adminSession['id']) ? (int) $adminSession['id'] : 0;

if ($pdo instanceof PDO && $userId > 0) {
    $userStmt = $pdo->prepare('SELECT User_ID, Name, Email, Address, Language, Theme FROM user WHERE User_ID = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $profile = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $staffStmt = $pdo->prepare('SELECT Store_Staff_ID, Is_Super_Admin FROM store_staff WHERE User_ID = :id LIMIT 1');
    $staffStmt->execute([':id' => $userId]);
    $staffRecord = $staffStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>My Profile</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="card profile-card">
    <?php if ($profile): ?>
      <div class="profile-header">
        <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($profile['Name'] ?? ''); ?> avatar">
        <div>
          <h2><?= htmlspecialchars($profile['Name'] ?? ''); ?></h2>
          <p class="profile-role"><?= htmlspecialchars($roleLabel); ?></p>
          <?php if (!empty($profile['Email'])): ?>
            <p class="profile-email"><?= htmlspecialchars($profile['Email']); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <dl class="profile-meta">
        <?php if (!empty($profile['Address'])): ?>
          <div>
            <dt>Address</dt>
            <dd><?= htmlspecialchars($profile['Address']); ?></dd>
          </div>
        <?php endif; ?>
        <div>
          <dt>Language Preference</dt>
          <dd><?= htmlspecialchars($profile['Language'] ?? 'English'); ?></dd>
        </div>
        <div>
          <dt>Theme</dt>
          <dd><?= htmlspecialchars($profile['Theme'] ?? 'Light'); ?></dd>
        </div>
        <?php if ($staffRecord && isset($staffRecord['Store_Staff_ID'])): ?>
          <div>
            <dt>Staff ID</dt>
            <dd>#<?= str_pad((string) $staffRecord['Store_Staff_ID'], 4, '0', STR_PAD_LEFT); ?></dd>
          </div>
        <?php endif; ?>
      </dl>

      <div class="profile-actions">
        <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
        <a href="logout.php" class="btn btn-muted">Log Out</a>
      </div>
    <?php else: ?>
      <p class="table-empty">We couldn't load your profile details right now. Please try again later.</p>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
