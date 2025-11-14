<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';

$activePage = 'settings';
$pageTitle = "Edit Profile - Cindy's Bakeshop";
$message = '';
$profile = null;
$userId = isset($adminSession['id']) ? (int) $adminSession['id'] : 0;

if ($pdo instanceof PDO && $userId > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS));
        $email = trim((string) filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

        if ($name !== '' && $email !== '') {
            $stmt = $pdo->prepare('UPDATE user SET Name = :name, Email = :email WHERE User_ID = :id');
            $success = $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':id' => $userId,
            ]);
            if ($success) {
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                $adminSession['name'] = $name;
                $adminSession['email'] = $email;
            }
            $message = $success ? 'Profile updated successfully.' : 'Failed to update profile.';
        } else {
            $message = 'Please provide a valid name and email.';
        }
    }

    $stmt = $pdo->prepare('SELECT User_ID, Name, Email FROM user WHERE User_ID = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Edit Profile</h1>
    <a href="profile.php" class="btn btn-muted" style="text-decoration:none;">← Back to Profile</a>
  </div>

  <div class="card" style="max-width:480px;">
    <?php if ($message): ?>
      <p style="color:#1e8449;font-weight:600;"><?= htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($profile): ?>
      <form method="post" style="display:grid;gap:16px;">
        <input type="hidden" name="user_id" value="<?= $profile['User_ID']; ?>">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($profile['Name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['Email'] ?? ''); ?>" required>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
      </form>
    <?php else: ?>
      <p class="table-empty">No profile data available.</p>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
