<?php
$rootPrefix = '';
$userPrefix = '';
$apiBase = '';
$imagesBase = '';

if (isset($topbarContext) && is_array($topbarContext)) {
    $rootPrefix = isset($topbarContext['rootPrefix']) ? (string) $topbarContext['rootPrefix'] : '';
    $userPrefix = isset($topbarContext['userPrefix']) ? (string) $topbarContext['userPrefix'] : '';
    $imagesBase = isset($topbarContext['imagesBase']) ? (string) $topbarContext['imagesBase'] : ($rootPrefix . 'Images/');
    $apiBase = isset($topbarContext['apiBase']) ? (string) $topbarContext['apiBase'] : ($rootPrefix . 'PHP/');
} else {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    $callerFile = '';
    foreach ($trace as $frame) {
        if (!empty($frame['file']) && $frame['file'] !== __FILE__) {
            $callerFile = $frame['file'];
            break;
        }
    }

    if ($callerFile === '' && isset($_SERVER['SCRIPT_FILENAME']) && is_string($_SERVER['SCRIPT_FILENAME'])) {
        $callerFile = $_SERVER['SCRIPT_FILENAME'];
    }

    if ($callerFile === '') {
        $callerFile = __FILE__;
    }

    $callerDirRaw = dirname($callerFile);
    $callerDirReal = $callerDirRaw !== '' ? realpath($callerDirRaw) : false;
    $callerDir = str_replace('\\', '/', $callerDirReal !== false ? $callerDirReal : $callerDirRaw);

    $projectRootRaw = dirname(__DIR__);
    $projectRootReal = $projectRootRaw !== '' ? realpath($projectRootRaw) : false;
    $projectRoot = str_replace('\\', '/', $projectRootReal !== false ? $projectRootReal : $projectRootRaw);

    $rootPrefix = '';

    if ($callerDir !== '' && $projectRoot !== '' && strpos($callerDir, $projectRoot) === 0) {
        $relative = trim(substr($callerDir, strlen($projectRoot)), '/');
        if ($relative !== '') {
            $depth = substr_count($relative, '/') + 1;
            $rootPrefix = str_repeat('../', $depth);
        }
    } else {
        $callerParts = $callerDir === '' ? [] : explode('/', trim($callerDir, '/'));
        $projectParts = $projectRoot === '' ? [] : explode('/', trim($projectRoot, '/'));
        $maxCommon = min(count($callerParts), count($projectParts));
        $common = 0;

        while ($common < $maxCommon && $callerParts[$common] === $projectParts[$common]) {
            $common++;
        }

        if ($callerParts) {
            $rootPrefix = str_repeat('../', count($callerParts) - $common);
        }

        $downParts = array_slice($projectParts, $common);
        if (!empty($downParts)) {
            $rootPrefix .= implode('/', $downParts) . '/';
        }
    }

    $userPrefix = $rootPrefix . 'UserSide/';
    $imagesBase = $rootPrefix . 'Images/';
    $apiBase = $rootPrefix . 'PHP/';
}

unset($topbarContext);
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

$menuScripts = array('MENU.php', 'bread.php', 'cakes.php', 'product.php');
$favoriteScripts = array('my favorite.php');
$profileScripts = array('EditProfile.php');
$purchasesScripts = array('MyPurchase.php');

$navItems = [
    [
        'label' => 'Home',
        'href' => $rootPrefix . 'index.php',
        'match' => ['index.php']
    ],
    [
        'label' => 'Menu',
        'href' => $userPrefix . 'PRODUCT/MENU.php',
        'match' => $menuScripts
    ],
    [
        'label' => 'Favorites',
        'href' => $userPrefix . 'FAVORITE/my favorite.php',
        'match' => $favoriteScripts
    ],
    [
        'label' => 'Orders',
        'href' => $userPrefix . 'PURCHASES/MyPurchase.php',
        'match' => array_merge($purchasesScripts, ['orderDetails.php'])
    ]
];

$activeResolver = function (array $needles) use ($currentScript): string {
    foreach ($needles as $needle) {
        if (strcasecmp($currentScript, $needle) === 0) {
            return 'active';
        }
    }
    return '';
};
?>
<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<header id="mainHeader" data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>" data-images-base="<?= htmlspecialchars($imagesBase, ENT_QUOTES) ?>" data-root-prefix="<?= htmlspecialchars($rootPrefix, ENT_QUOTES) ?>" data-user-prefix="<?= htmlspecialchars($userPrefix, ENT_QUOTES) ?>">
  <div class="header-content">
    <div class="logo">
      <span class="logo-icon" aria-hidden="true">🥐</span>
      <a href="<?= htmlspecialchars($rootPrefix . 'index.php', ENT_QUOTES) ?>" class="logo-text">Cindy's Bakeshop</a>
    </div>

    <button type="button" class="menu-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>

    <nav aria-label="Main navigation">
      <ul id="mainNav">
        <?php foreach ($navItems as $item): ?>
          <?php $active = $activeResolver($item['match']); ?>
          <li><a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>" class="<?= $active ?>"><?= htmlspecialchars($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="auth-links" id="authLinks">
        <a class="auth-link" href="<?= htmlspecialchars($userPrefix . 'login.html', ENT_QUOTES) ?>">Log in</a>
        <a class="auth-link auth-primary" href="<?= htmlspecialchars($userPrefix . 'signup.html', ENT_QUOTES) ?>">Sign up</a>
      </div>
    </nav>

    <div class="nav-right">
      <a class="cart-link" href="<?= htmlspecialchars($userPrefix . 'CART/cart_checkout_page.php', ENT_QUOTES) ?>">
        <i class="fas fa-shopping-cart"></i>
        <span>Cart</span>
        <span class="cart-badge" id="cartBadge" style="display: none;">0</span>
      </a>
      <div class="profile-dropdown hidden">
        <button type="button" class="profile-trigger" id="profileToggle" aria-haspopup="true" aria-expanded="false">
          <img src="<?= htmlspecialchars($imagesBase . 'logo.png', ENT_QUOTES) ?>" alt="User avatar" class="profile-img" id="profileAvatar">
          <span class="profile-meta">
            <strong id="profileName">Guest</strong>
            <span id="profileEmail">Sign in</span>
          </span>
        </button>
        <ul class="dropdown-menu" id="profileMenu">
          <li><a href="<?= htmlspecialchars($userPrefix . 'PROFILE/EditProfile.php', ENT_QUOTES) ?>">Edit Profile</a></li>
          <li><a href="<?= htmlspecialchars($userPrefix . 'PURCHASES/MyPurchase.php', ENT_QUOTES) ?>">Order History</a></li>
          <li><a href="<?= htmlspecialchars($userPrefix . 'logout.html', ENT_QUOTES) ?>">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</header>
<script type="module" src="<?= htmlspecialchars($userPrefix . 'js/topbar.js', ENT_QUOTES) ?>"></script>
