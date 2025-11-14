<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/blacklist_functions.php';

$activePage = 'users';
$pageTitle = "Users - Cindy's Bakeshop";

$allUsers = [];
$blockedUsers = [];

if ($pdo) {
    $stmt = $pdo->query("SELECT User_ID, Name, Email FROM user");
    $allUsers = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $staffStmt = $pdo->query("SELECT User_ID, Is_Super_Admin FROM store_staff");
    $staffRows = $staffStmt ? $staffStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $staffLookup = [];
    foreach ($staffRows as $staffRow) {
        $staffUserId = isset($staffRow['User_ID']) ? (int)$staffRow['User_ID'] : 0;
        if (!$staffUserId) {
            continue;
        }
        $isSuperAdmin = !empty($staffRow['Is_Super_Admin']) && (int)$staffRow['Is_Super_Admin'] === 1;
        $staffLookup[$staffUserId] = [
            'is_employee' => true,
            'is_super_admin' => $isSuperAdmin,
        ];
    }

    foreach ($allUsers as &$user) {
        $userId = isset($user['User_ID']) ? (int)$user['User_ID'] : 0;
        $lookup = $staffLookup[$userId] ?? ['is_employee' => false, 'is_super_admin' => false];
        $user['Is_Employee'] = $lookup['is_employee'] ? 1 : 0;
        $user['Is_Super_Admin'] = $lookup['is_super_admin'] ? 1 : 0;
    }
    unset($user);

    $sql = "SELECT b.Blacklist_ID, u.Name, u.Email, b.Blacklist_reason AS Reason,
                   b.IP_Address, b.User_ID
            FROM blacklist b
            LEFT JOIN user u ON b.User_ID = u.User_ID
            ORDER BY b.Blacklist_ID DESC";
    $stmtBlocked = $pdo->query($sql);
    $blockedUsers = $stmtBlocked ? $stmtBlocked->fetchAll(PDO::FETCH_ASSOC) : [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<?php
$currentAdminId = isset($adminSession['id']) ? (int)$adminSession['id'] : 0;
$currentAdminIsSuper = !empty($adminSession['is_super_admin']);
?>
<div class="main" data-admin-id="<?= $currentAdminId; ?>" data-admin-super="<?= $currentAdminIsSuper ? '1' : '0'; ?>">
  <div class="header">
    <h1>Users</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="table-container">
    <div class="table-actions">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button class="btn btn-secondary" id="showAll">All Users</button>
        <button class="btn btn-muted" id="showCustomers">Customers</button>
        <button class="btn btn-muted" id="showEmployees">Employees</button>
        <button class="btn btn-muted" id="showBlocked">Blocked Users</button>
      </div>
      <input type="text" id="userSearch" placeholder="🔍 Search user...">
    </div>

    <table id="allUsersTable">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allUsers)): ?>
          <tr data-empty-state="no-users"><td colspan="3" class="table-empty">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($allUsers as $user): ?>
            <?php
              $userId = isset($user['User_ID']) ? (int)$user['User_ID'] : 0;
              $isEmployee = isset($user['Is_Employee']) && (int)$user['Is_Employee'] === 1;
              $isSuperAdmin = isset($user['Is_Super_Admin']) && (int)$user['Is_Super_Admin'] === 1;
              $userNameSafe = htmlspecialchars($user['Name'] ?? '', ENT_QUOTES, 'UTF-8');
              $userEmailSafe = htmlspecialchars($user['Email'] ?? '', ENT_QUOTES, 'UTF-8');
              $isSelf = $currentAdminId === $userId;
              $canPromoteSuper = $currentAdminIsSuper && !$isSuperAdmin;
              $canDemoteSuper = $currentAdminIsSuper && $isSuperAdmin && !$isSelf;
            ?>
            <tr
              data-user-id="<?= $userId; ?>"
              data-is-employee="<?= $isEmployee ? '1' : '0'; ?>"
              data-is-super-admin="<?= $isSuperAdmin ? '1' : '0'; ?>"
              data-is-self="<?= $isSelf ? '1' : '0'; ?>"
            >
              <td><?= $userNameSafe; ?></td>
              <td><?= $userEmailSafe; ?></td>
              <td>
                <div class="table-action-list">
                  <button type="button" class="btn btn-primary btn-view-user" data-user="<?= $userId; ?>">
                    View Details
                  </button>
                  <?php if ($isSuperAdmin): ?>
                    <span class="badge badge-warning super-admin-badge" data-super-admin-badge role="status" aria-hidden="false">
                      Super Admin
                    </span>
                  <?php endif; ?>
                  <?php if ($isEmployee): ?>
                    <span class="badge badge-success employee-badge" role="status" data-role-badge aria-hidden="false">
                      Employee
                    </span>
                  <?php endif; ?>
                  <?php if ($currentAdminIsSuper): ?>
                    <button
                      type="button"
                      class="btn btn-secondary btn-mark-employee"<?= $isEmployee ? ' hidden' : ''; ?>
                      data-user-id="<?= $userId; ?>"
                      data-user-name="<?= $userNameSafe; ?>"
                      data-action="mark_employee"
                    >
                      Mark as Employee
                    </button>
                  <?php endif; ?>
                  <?php if ($currentAdminIsSuper): ?>
                    <button
                      type="button"
                      class="btn btn-muted btn-remove-employee"<?= $isEmployee ? '' : ' hidden'; ?>
                      data-user-id="<?= $userId; ?>"
                      data-user-name="<?= $userNameSafe; ?>"
                      data-action="remove_employee"
                    >
                      Remove Employee
                    </button>
                  <?php endif; ?>
                  <?php if ($currentAdminIsSuper): ?>
                    <button
                      type="button"
                      class="btn btn-secondary btn-promote-super-admin"<?= $canPromoteSuper ? '' : ' hidden'; ?>
                      data-user-id="<?= $userId; ?>"
                      data-user-name="<?= $userNameSafe; ?>"
                      data-action="promote_super_admin"
                    >
                      Make Super Admin
                    </button>
                    <button
                      type="button"
                      class="btn btn-muted btn-demote-super-admin"<?= $canDemoteSuper ? '' : ' hidden'; ?>
                      data-user-id="<?= $userId; ?>"
                      data-user-name="<?= $userNameSafe; ?>"
                      data-action="demote_super_admin"
                    >
                      Remove Super Admin
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr data-empty-state="no-match" class="table-empty" hidden>
          <td colspan="3">No users match your search or filters.</td>
        </tr>
      </tbody>
    </table>

    <table id="blockedUsersTable" style="display:none;margin-top:20px;">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Reason</th>
          <th>IP Address</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($blockedUsers)): ?>
          <tr><td colspan="5" class="table-empty">No blocked users.</td></tr>
        <?php else: ?>
          <?php foreach ($blockedUsers as $user): ?>
            <?php $blockedUserId = isset($user['User_ID']) ? (int)$user['User_ID'] : 0; ?>
            <tr data-blacklist-id="<?= $user['Blacklist_ID']; ?>"<?= $blockedUserId ? " data-user-id=\"{$blockedUserId}\"" : ''; ?>>
              <td><?= htmlspecialchars($user['Name'] ?? ''); ?></td>
              <td><?= htmlspecialchars($user['Email'] ?? ''); ?></td>
              <td><?= htmlspecialchars($user['Reason'] ?? ''); ?></td>
              <td><?= htmlspecialchars($user['IP_Address'] ?? ''); ?></td>
              <td style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($blockedUserId): ?>
                  <button type="button" class="btn btn-primary btn-view-user" data-user="<?= $blockedUserId; ?>">View Details</button>
                <?php else: ?>
                  <button class="btn btn-muted" disabled title="User profile unavailable">View Details</button>
                <?php endif; ?>
                <button class="btn btn-secondary btn-unblock">Unblock</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal" id="userDetailsModal" aria-hidden="true">
  <div class="modal-content modal-user-details" role="dialog" aria-labelledby="userDetailsName">
    <button type="button" class="modal-close" id="closeUserDetails" aria-label="Close details">&times;</button>
    <div class="user-modal-body">
      <div class="user-profile-header">
        <div class="user-avatar" id="userDetailsAvatarWrapper">
          <img src="" alt="User avatar" id="userDetailsAvatar" hidden>
          <span id="userDetailsAvatarFallback" aria-hidden="true">?</span>
        </div>
        <div class="user-profile-meta">
          <h2 id="userDetailsName">Select a user</h2>
          <span class="badge badge-success employee-badge" id="userDetailsEmployeeBadge" role="status" hidden aria-hidden="true">Employee</span>
          <span class="badge badge-warning super-admin-badge" id="userDetailsSuperAdminBadge" role="status" hidden aria-hidden="true">Super Admin</span>
          <p class="user-contact"><a id="userDetailsEmail" href="#">—</a></p>
          <p class="user-contact muted" id="userDetailsAddress">—</p>
          <div class="user-warning" id="userDetailsWarnings" aria-live="polite">Warnings: 0</div>
        </div>
      </div>
      <div class="user-metrics-grid">
        <div class="metric-card">
          <span class="metric-label">Total Orders</span>
          <span class="metric-value" id="userDetailsTotalOrders">0</span>
        </div>
        <div class="metric-card">
          <span class="metric-label">Lifetime Spend</span>
          <span class="metric-value" id="userDetailsTotalSpent">₱0.00</span>
        </div>
        <div class="metric-card">
          <span class="metric-label">Last Order</span>
          <span class="metric-value" id="userDetailsLastOrder">—</span>
        </div>
      </div>
      <div class="user-status-breakdown" id="userStatusBreakdown"></div>
      <div class="user-order-controls">
        <input type="text" id="userOrdersSearch" placeholder="🔍 Search order...">
        <select id="userOrdersStatus">
          <option value="all">All Status</option>
        </select>
        <select id="userOrdersSort">
          <option value="date_desc">Newest first</option>
          <option value="date_asc">Oldest first</option>
          <option value="total_desc">Total: High to Low</option>
          <option value="total_asc">Total: Low to High</option>
        </select>
      </div>
      <div class="user-orders-table-wrapper">
        <table class="user-orders-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Items</th>
              <th>Qty</th>
              <th>Source</th>
              <th>Fulfillment</th>
              <th>Total</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody id="userOrdersBody">
            <tr>
              <td colspan="8" class="table-empty">Select a user to view recent orders.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
  const showAllBtn = document.getElementById('showAll');
  const showCustomersBtn = document.getElementById('showCustomers');
  const showEmployeesBtn = document.getElementById('showEmployees');
  const showBlockedBtn = document.getElementById('showBlocked');
  const allTable = document.getElementById('allUsersTable');
  const blockedTable = document.getElementById('blockedUsersTable');
  const searchInput = document.getElementById('userSearch');
  const noMatchRow = document.querySelector('#allUsersTable tr[data-empty-state="no-match"]');
  let currentView = 'all';

  const adminContainer = document.querySelector('.main');
  const adminIsSuperAdmin = adminContainer?.dataset.adminSuper === '1';
  const adminUserId = Number.parseInt(adminContainer?.dataset.adminId || '0', 10) || 0;

  function searchUsers() {
    const query = (searchInput?.value || '').toLowerCase();
    if (!allTable || !blockedTable) return;
    if (currentView !== 'blocked') {
      const userRows = Array.from(allTable.querySelectorAll('tbody tr[data-user-id]'));
      let visibleCount = 0;
      userRows.forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const email = row.cells[1]?.textContent.toLowerCase() || '';
        const matchesQuery = name.includes(query) || email.includes(query);
        if (!matchesQuery) {
          row.style.display = 'none';
          return;
        }

        const isEmployee = row.dataset.isEmployee === '1';
        const isSuperAdmin = row.dataset.isSuperAdmin === '1';
        let matchesRole = true;
        if (currentView === 'employees') {
          matchesRole = isEmployee;
        } else if (currentView === 'customers') {
          matchesRole = !isEmployee && !isSuperAdmin;
        }

        if (matchesRole) {
          row.style.display = '';
          visibleCount += 1;
        } else {
          row.style.display = 'none';
        }
      });

      if (noMatchRow) {
        const shouldShowNoMatch = userRows.length > 0 && visibleCount === 0;
        noMatchRow.hidden = !shouldShowNoMatch;
        noMatchRow.style.display = shouldShowNoMatch ? '' : 'none';
      }
    } else {
      blockedTable.querySelectorAll('tbody tr').forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const email = row.cells[1]?.textContent.toLowerCase() || '';
        const reason = row.cells[2]?.textContent.toLowerCase() || '';
        const ip = row.cells[3]?.textContent.toLowerCase() || '';
        const matchesSearch = [name, email, reason, ip].some(value => value.includes(query));
        row.style.display = matchesSearch ? '' : 'none';
      });
      if (noMatchRow) {
        noMatchRow.hidden = true;
        noMatchRow.style.display = 'none';
      }
    }
  }

  function setButtonState(button, isActive) {
    if (!button) return;
    if (isActive) {
      button.classList.add('btn-secondary');
      button.classList.remove('btn-muted');
    } else {
      button.classList.add('btn-muted');
      button.classList.remove('btn-secondary');
    }
  }

  function toggleView(target) {
    currentView = target;
    const showingBlocked = target === 'blocked';

    if (allTable) allTable.style.display = showingBlocked ? 'none' : '';
    if (blockedTable) blockedTable.style.display = showingBlocked ? '' : 'none';

    setButtonState(showAllBtn, target === 'all');
    setButtonState(showCustomersBtn, target === 'customers');
    setButtonState(showEmployeesBtn, target === 'employees');
    setButtonState(showBlockedBtn, target === 'blocked');

    searchUsers();
  }

  searchInput?.addEventListener('input', searchUsers);
  showAllBtn?.addEventListener('click', () => toggleView('all'));
  showCustomersBtn?.addEventListener('click', () => toggleView('customers'));
  showEmployeesBtn?.addEventListener('click', () => toggleView('employees'));
  showBlockedBtn?.addEventListener('click', () => toggleView('blocked'));

  toggleView('all');

  const userModal = document.getElementById('userDetailsModal');
  const userModalBody = userModal ? userModal.querySelector('.user-modal-body') : null;
  const closeUserModalBtn = document.getElementById('closeUserDetails');
  const userOrdersSearch = document.getElementById('userOrdersSearch');
  const userOrdersStatus = document.getElementById('userOrdersStatus');
  const userOrdersSort = document.getElementById('userOrdersSort');
  const userOrdersBody = document.getElementById('userOrdersBody');
  const userStatusBreakdown = document.getElementById('userStatusBreakdown');
  const userDetailsName = document.getElementById('userDetailsName');
  const userDetailsEmail = document.getElementById('userDetailsEmail');
  const userDetailsAddress = document.getElementById('userDetailsAddress');
  const userDetailsWarnings = document.getElementById('userDetailsWarnings');
  const userDetailsEmployeeBadge = document.getElementById('userDetailsEmployeeBadge');
  const userDetailsSuperAdminBadge = document.getElementById('userDetailsSuperAdminBadge');
  const userDetailsAvatar = document.getElementById('userDetailsAvatar');
  const userDetailsAvatarFallback = document.getElementById('userDetailsAvatarFallback');
  const userDetailsTotalOrders = document.getElementById('userDetailsTotalOrders');
  const userDetailsTotalSpent = document.getElementById('userDetailsTotalSpent');
  const userDetailsLastOrder = document.getElementById('userDetailsLastOrder');

  const currencyFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
  const MANILA_TIME_ZONE = 'Asia/Manila';
  const dateFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: MANILA_TIME_ZONE,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });

  let userOrders = [];
  let activeUserId = null;

  if (userDetailsAvatar) {
    userDetailsAvatar.addEventListener('error', () => {
      userDetailsAvatar.hidden = true;
      userDetailsAvatar.src = '';
      if (userDetailsAvatarFallback) {
        userDetailsAvatarFallback.style.display = '';
      }
    });
  }

  function openUserModal() {
    if (!userModal) return;
    userModal.classList.add('active');
    userModal.setAttribute('aria-hidden', 'false');
    if (userModalBody) {
      userModalBody.scrollTop = 0;
    }
  }

  function closeUserModal() {
    if (!userModal) return;
    userModal.classList.remove('active');
    userModal.setAttribute('aria-hidden', 'true');
    if (userModal.dataset.activeUserId) {
      delete userModal.dataset.activeUserId;
    }
    activeUserId = null;
  }

  closeUserModalBtn?.addEventListener('click', closeUserModal);
  if (userModal) {
    userModal.addEventListener('click', (event) => {
      if (event.target === userModal) {
        closeUserModal();
      }
    });
  }

  function formatSourceLabel(value) {
    if (!value) return '—';
    return value
      .toString()
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  }

  function padOrderId(id) {
    const numeric = Number.parseInt(id, 10);
    if (Number.isNaN(numeric)) {
      return '#00000';
    }
    return `#${String(numeric).padStart(5, '0')}`;
  }

  function formatDate(value) {
    if (!value) return '—';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return value;
    }
    return dateFormatter.format(parsed);
  }

  function setUserAvatar(name, path) {
    const initials = (name || '?')
      .split(' ')
      .filter(Boolean)
      .map(part => part[0].toUpperCase())
      .slice(0, 2)
      .join('') || '?';

    if (userDetailsAvatarFallback) {
      userDetailsAvatarFallback.textContent = initials;
      userDetailsAvatarFallback.style.display = '';
    }

    if (userDetailsAvatar) {
      if (path) {
        const normalized = path.startsWith('http')
          ? path
          : path.startsWith('/')
            ? `..${path}`
            : `../${path}`;
        userDetailsAvatar.src = normalized;
        userDetailsAvatar.alt = `Avatar of ${name || 'user'}`;
        userDetailsAvatar.hidden = false;
        if (userDetailsAvatarFallback) {
          userDetailsAvatarFallback.style.display = 'none';
        }
      } else {
        userDetailsAvatar.hidden = true;
        userDetailsAvatar.src = '';
      }
    }
  }

  function populateUserInfo(user) {
    const name = user?.name || 'Unknown user';
    setUserAvatar(name, user?.face_image_path || '');
    if (userDetailsName) {
      userDetailsName.textContent = name;
    }
    if (userDetailsEmail) {
      const email = user?.email ? String(user.email).trim() : '';
      userDetailsEmail.textContent = email || '—';
      if (email) {
        userDetailsEmail.href = `mailto:${email}`;
        userDetailsEmail.classList.remove('muted');
        userDetailsEmail.removeAttribute('aria-disabled');
      } else {
        userDetailsEmail.classList.add('muted');
        userDetailsEmail.removeAttribute('href');
        userDetailsEmail.setAttribute('aria-disabled', 'true');
      }
    }
    if (userDetailsAddress) {
      const address = user?.address;
      userDetailsAddress.textContent = address ? address : 'No address on file.';
    }
    if (userDetailsWarnings) {
      const warnings = Number.parseInt(user?.warning_count ?? 0, 10) || 0;
      userDetailsWarnings.textContent = `Warnings: ${warnings}`;
      userDetailsWarnings.classList.toggle('has-warning', warnings > 0);
    }
    if (userDetailsEmployeeBadge) {
      const isEmployee = Boolean(user?.is_employee);
      userDetailsEmployeeBadge.hidden = !isEmployee;
      userDetailsEmployeeBadge.setAttribute('aria-hidden', isEmployee ? 'false' : 'true');
    }
    if (userDetailsSuperAdminBadge) {
      const isSuperAdmin = Boolean(user?.is_super_admin);
      userDetailsSuperAdminBadge.hidden = !isSuperAdmin;
      userDetailsSuperAdminBadge.setAttribute('aria-hidden', isSuperAdmin ? 'false' : 'true');
    }
  }

  function renderStatusBreakdown(counts) {
    if (!userStatusBreakdown) return;
    userStatusBreakdown.innerHTML = '';
    const entries = Object.entries(counts || {}).filter(([, count]) => count > 0);
    if (!entries.length) {
      const empty = document.createElement('span');
      empty.className = 'muted';
      empty.textContent = 'No order history yet.';
      userStatusBreakdown.append(empty);
      return;
    }
    entries.sort((a, b) => b[1] - a[1]);
    entries.forEach(([status, count]) => {
      const badge = document.createElement('span');
      badge.className = `status-chip status-${status.toLowerCase().replace(/\s+/g, '-')}`;
      badge.textContent = `${status}: ${count}`;
      userStatusBreakdown.append(badge);
    });
  }

  function updateSummary(summary) {
    const totalOrders = summary?.total_orders ?? 0;
    const totalSpent = summary?.total_spent ?? 0;
    const lastOrder = summary?.last_order_date ?? null;
    if (userDetailsTotalOrders) {
      userDetailsTotalOrders.textContent = totalOrders;
    }
    if (userDetailsTotalSpent) {
      userDetailsTotalSpent.textContent = currencyFormatter.format(totalSpent);
    }
    if (userDetailsLastOrder) {
      userDetailsLastOrder.textContent = lastOrder ? formatDate(lastOrder) : '—';
    }
    renderStatusBreakdown(summary?.status_counts || {});
  }

  function renderUserOrders(orders) {
    if (!userOrdersBody) return;
    userOrdersBody.innerHTML = '';
    if (!orders.length) {
      const row = document.createElement('tr');
      row.innerHTML = '<td colspan="8" class="table-empty">No orders found for this user.</td>';
      userOrdersBody.append(row);
      return;
    }
    orders.forEach(order => {
      const row = document.createElement('tr');
      const statusText = order?.status || 'Pending';
      const statusClass = statusText.toLowerCase().replace(/\s+/g, '-');
      const sourceLabel = formatSourceLabel(order?.source);
      const fulfillmentLabel = order?.fulfillment || '—';
      const summaryText = order?.summary || 'No items recorded';
      const qty = Number.parseInt(order?.item_count ?? 0, 10) || 0;
      const total = Number.parseFloat(order?.total_amount ?? 0) || 0;
      row.innerHTML = `
        <td>${padOrderId(order?.id)}</td>
        <td>${summaryText}</td>
        <td>${qty}</td>
        <td>${sourceLabel}</td>
        <td>${fulfillmentLabel || '—'}</td>
        <td>${currencyFormatter.format(total)}</td>
        <td><span class="status-pill status-${statusClass}">${statusText}</span></td>
        <td>${formatDate(order?.date)}</td>
      `;
      userOrdersBody.append(row);
    });
  }

  function applyUserOrderFilters() {
    if (!Array.isArray(userOrders)) {
      renderUserOrders([]);
      return;
    }
    const query = (userOrdersSearch?.value || '').trim().toLowerCase();
    const statusFilter = (userOrdersStatus?.value || 'all').toLowerCase();
    const sortValue = userOrdersSort?.value || 'date_desc';

    let filtered = userOrders.filter(order => {
      const status = (order?.status || '').toLowerCase();
      if (statusFilter !== 'all' && status !== statusFilter) {
        return false;
      }
      if (!query) {
        return true;
      }
      const searchTargets = [
        padOrderId(order?.id).toLowerCase(),
        (order?.summary || '').toLowerCase(),
        (order?.source || '').toLowerCase(),
        (order?.fulfillment || '').toLowerCase(),
        status,
      ];
      return searchTargets.some(target => target.includes(query));
    });

    filtered.sort((a, b) => {
      switch (sortValue) {
        case 'total_asc':
          return (Number(a?.total_amount) || 0) - (Number(b?.total_amount) || 0);
        case 'total_desc':
          return (Number(b?.total_amount) || 0) - (Number(a?.total_amount) || 0);
        case 'date_asc': {
          const dateA = new Date(a?.date || 0).getTime();
          const dateB = new Date(b?.date || 0).getTime();
          return dateA - dateB;
        }
        case 'date_desc':
        default: {
          const dateA = new Date(a?.date || 0).getTime();
          const dateB = new Date(b?.date || 0).getTime();
          return dateB - dateA;
        }
      }
    });

    renderUserOrders(filtered);
  }

  function resetUserOrderControls() {
    if (userOrdersSearch) userOrdersSearch.value = '';
    if (userOrdersSort) userOrdersSort.value = 'date_desc';
    if (userOrdersStatus) userOrdersStatus.value = 'all';
  }

  function updateStatusFilterOptions(counts) {
    if (!userOrdersStatus) return;
    userOrdersStatus.innerHTML = '<option value="all">All Status</option>';
    const statuses = Object.keys(counts || {}).sort((a, b) => a.localeCompare(b));
    statuses.forEach(status => {
      const option = document.createElement('option');
      option.value = status;
      option.textContent = `${status} (${counts[status]})`;
      userOrdersStatus.append(option);
    });
    userOrdersStatus.value = 'all';
  }

  userOrdersSearch?.addEventListener('input', applyUserOrderFilters);
  userOrdersStatus?.addEventListener('change', applyUserOrderFilters);
  userOrdersSort?.addEventListener('change', applyUserOrderFilters);

  async function handleViewUser(event) {
    const button = event.currentTarget;
    const row = button.closest('tr');
    const userId = button.dataset.user || row?.dataset.userId;
    if (!userId) {
      alert('Unable to determine user profile for this entry.');
      return;
    }
    const previousText = button.textContent;
    button.disabled = true;
    button.textContent = 'Loading...';
    try {
      const response = await fetch(`../PHP/user_details.php?user_id=${encodeURIComponent(userId)}`);
      const data = await response.json();
      if (!response.ok || !data?.success) {
        throw new Error(data?.message || 'Failed to load user details');
      }
      userOrders = data.orders || [];
      populateUserInfo(data.user || {});
      updateSummary(data.summary || {});
      updateStatusFilterOptions((data.summary && data.summary.status_counts) || {});
      resetUserOrderControls();
      applyUserOrderFilters();
      const parsedUserId = Number.parseInt(userId, 10);
      activeUserId = Number.isNaN(parsedUserId) ? null : parsedUserId;
      if (userModal && activeUserId !== null) {
        userModal.dataset.activeUserId = String(activeUserId);
      }
      openUserModal();
    } catch (error) {
      alert(error.message);
    } finally {
      button.disabled = false;
      button.textContent = previousText;
    }
  }

  function updateEmployeeControls(row, isEmployee) {
    if (!row) return;
    const isSuperAdmin = row.dataset.isSuperAdmin === '1';
    const shouldBeEmployee = isSuperAdmin ? true : Boolean(isEmployee);
    row.dataset.isEmployee = shouldBeEmployee ? '1' : '0';
    let badge = row.querySelector('[data-role-badge]');
    if (!badge && shouldBeEmployee) {
      badge = document.createElement('span');
      badge.className = 'badge badge-success employee-badge';
      badge.setAttribute('role', 'status');
      badge.setAttribute('data-role-badge', '');
      badge.setAttribute('aria-hidden', 'false');
      badge.textContent = 'Employee';
      const container = row.querySelector('.table-action-list');
      if (container) {
        container.insertBefore(badge, container.firstChild);
      } else {
        row.append(badge);
      }
    }
    if (badge) {
      if (!shouldBeEmployee) {
        badge.remove();
        badge = null;
      } else {
        badge.hidden = false;
        badge.removeAttribute('hidden');
        badge.setAttribute('aria-hidden', 'false');
      }
    }

    const markButton = row.querySelector('.btn-mark-employee');
    if (markButton) {
      markButton.hidden = shouldBeEmployee;
      if (shouldBeEmployee) {
        markButton.setAttribute('hidden', '');
      } else {
        markButton.removeAttribute('hidden');
      }
      markButton.disabled = false;
      markButton.textContent = 'Mark as Employee';
    }

    const removeButton = row.querySelector('.btn-remove-employee');
    if (removeButton) {
      if (!adminIsSuperAdmin) {
        removeButton.hidden = true;
        removeButton.setAttribute('hidden', '');
        removeButton.disabled = true;
        return;
      }
      removeButton.hidden = !shouldBeEmployee;
      if (!shouldBeEmployee) {
        removeButton.setAttribute('hidden', '');
      } else {
        removeButton.removeAttribute('hidden');
      }
      const disableRemoval = isSuperAdmin;
      removeButton.disabled = disableRemoval;
      if (disableRemoval) {
        removeButton.title = 'Remove super admin access first.';
      } else {
        removeButton.removeAttribute('title');
      }
      removeButton.textContent = 'Remove Employee';
    }
  }

  function updateSuperAdminControls(row, isSuperAdmin) {
    if (!row) return;
    row.dataset.isSuperAdmin = isSuperAdmin ? '1' : '0';

    let badge = row.querySelector('[data-super-admin-badge]');
    if (isSuperAdmin) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge badge-warning super-admin-badge';
        badge.setAttribute('role', 'status');
        badge.setAttribute('data-super-admin-badge', '');
        badge.textContent = 'Super Admin';
        const container = row.querySelector('.table-action-list');
        if (container) {
          container.insertBefore(badge, container.firstChild);
        } else {
          row.append(badge);
        }
      } else {
        badge.hidden = false;
        badge.removeAttribute('hidden');
      }
      badge.setAttribute('aria-hidden', 'false');
    } else if (badge) {
      badge.remove();
      badge = null;
    }

    const promoteButton = row.querySelector('.btn-promote-super-admin');
    if (promoteButton) {
      const shouldShow = adminIsSuperAdmin && !isSuperAdmin;
      promoteButton.hidden = !shouldShow;
      if (shouldShow) {
        promoteButton.removeAttribute('hidden');
      } else {
        promoteButton.setAttribute('hidden', '');
      }
      promoteButton.disabled = false;
      promoteButton.textContent = 'Make Super Admin';
    }

    const demoteButton = row.querySelector('.btn-demote-super-admin');
    if (demoteButton) {
      const isSelf = row.dataset.isSelf === '1' || Number.parseInt(row.dataset.userId || '0', 10) === adminUserId;
      const shouldShow = adminIsSuperAdmin && isSuperAdmin && !isSelf;
      demoteButton.hidden = !shouldShow;
      if (shouldShow) {
        demoteButton.removeAttribute('hidden');
      } else {
        demoteButton.setAttribute('hidden', '');
      }
      demoteButton.disabled = false;
      demoteButton.textContent = 'Remove Super Admin';
    }
  }

  function updateModalEmployeeBadge(isEmployee) {
    if (!userDetailsEmployeeBadge) return;
    userDetailsEmployeeBadge.hidden = !isEmployee;
    if (!isEmployee) {
      userDetailsEmployeeBadge.setAttribute('aria-hidden', 'true');
    } else {
      userDetailsEmployeeBadge.setAttribute('aria-hidden', 'false');
    }
  }

  function updateModalSuperAdminBadge(isSuperAdmin) {
    if (!userDetailsSuperAdminBadge) return;
    userDetailsSuperAdminBadge.hidden = !isSuperAdmin;
    userDetailsSuperAdminBadge.setAttribute('aria-hidden', isSuperAdmin ? 'false' : 'true');
  }

  async function handleEmployeeAction(event) {
    const button = event.currentTarget;
    const action = button.dataset.action;
    const userId = button.dataset.userId;
    const userName = (button.dataset.userName || 'this user').trim() || 'this user';
    if (action === 'mark_employee' && !adminIsSuperAdmin) {
      alert('Only the super admin can mark other users as employees.');
      return;
    }
    if (action === 'remove_employee' && !adminIsSuperAdmin) {
      alert('Only the super admin can remove employee status.');
      return;
    }
    if (!userId || !action) {
      alert('Missing user information.');
      return;
    }

    const confirmMessage = action === 'remove_employee'
      ? `Remove ${userName}'s employee status?`
      : `Mark ${userName} as an employee?`;
    if (!window.confirm(confirmMessage)) {
      return;
    }

    const previousText = button.textContent;
    button.disabled = true;
    button.textContent = action === 'remove_employee' ? 'Removing...' : 'Marking...';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('user_id', userId);

    try {
      const response = await fetch('../PHP/user_staff_actions.php', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result?.success) {
        throw new Error(result?.message || 'Failed to update employee status.');
      }

      const numericUserId = Number.parseInt(userId, 10);
      const row = button.closest('tr');
      const isEmployee = action === 'mark_employee';
      let rowIsSuperAdmin = false;
      if (row) {
        rowIsSuperAdmin = row.dataset.isSuperAdmin === '1';
        updateEmployeeControls(row, isEmployee);
        updateSuperAdminControls(row, rowIsSuperAdmin);
      }

      searchUsers();

      if (activeUserId !== null && !Number.isNaN(numericUserId) && numericUserId === activeUserId) {
        updateModalEmployeeBadge(isEmployee);
        updateModalSuperAdminBadge(rowIsSuperAdmin);
      }

      alert(result?.message || (isEmployee
        ? `${userName} is now marked as an employee.`
        : `${userName}'s employee status has been removed.`));
    } catch (error) {
      alert(error.message);
      button.disabled = false;
      button.textContent = previousText;
    }
  }

  async function handleSuperAdminAction(event) {
    const button = event.currentTarget;
    const action = button.dataset.action;
    const userId = button.dataset.userId;
    const userName = (button.dataset.userName || 'this user').trim() || 'this user';
    if (!userId || !action) {
      alert('Missing user information.');
      return;
    }

    const isPromote = action === 'promote_super_admin';
    const confirmMessage = isPromote
      ? `Make ${userName} the super admin? This will replace the current super admin.`
      : `Remove ${userName}'s super admin access?`;
    if (!window.confirm(confirmMessage)) {
      return;
    }

    const previousText = button.textContent;
    button.disabled = true;
    button.textContent = isPromote ? 'Updating...' : 'Removing...';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('user_id', userId);

    try {
      const response = await fetch('../PHP/user_staff_actions.php', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result?.success) {
        throw new Error(result?.message || 'Failed to update super admin access.');
      }

      const numericUserId = Number.parseInt(userId, 10);
      const row = button.closest('tr');

      if (isPromote) {
        document.querySelectorAll('#allUsersTable tbody tr[data-is-super-admin="1"]').forEach(existingRow => {
          const existingId = Number.parseInt(existingRow.dataset.userId || '0', 10);
          const isTarget = !Number.isNaN(existingId) && existingId === numericUserId;
          updateSuperAdminControls(existingRow, isTarget);
          updateEmployeeControls(existingRow, existingRow.dataset.isEmployee === '1');
        });
        if (row) {
          updateSuperAdminControls(row, true);
          updateEmployeeControls(row, true);
        }
        if (activeUserId !== null && !Number.isNaN(numericUserId)) {
          const isActiveUser = numericUserId === activeUserId;
          updateModalSuperAdminBadge(isActiveUser);
          if (isActiveUser) {
            updateModalEmployeeBadge(true);
          }
        }
      } else {
        if (row) {
          updateSuperAdminControls(row, false);
          updateEmployeeControls(row, row.dataset.isEmployee === '1');
        }
        if (activeUserId !== null && !Number.isNaN(numericUserId) && numericUserId === activeUserId) {
          updateModalSuperAdminBadge(false);
        }
      }

      searchUsers();

      alert(result?.message || 'Super admin updated successfully.');
    } catch (error) {
      alert(error.message);
      button.disabled = false;
      button.textContent = previousText;
      return;
    }

    button.disabled = false;
    button.textContent = previousText;
  }

  function attachViewUserListeners() {
    document.querySelectorAll('.btn-view-user').forEach(button => {
      if (!button.dataset.initialized) {
        button.addEventListener('click', handleViewUser);
        button.dataset.initialized = 'true';
      }
    });
  }

  function attachEmployeeActionListeners() {
    document.querySelectorAll('.btn-mark-employee, .btn-remove-employee').forEach(button => {
      if (!button.dataset.initialized) {
        button.addEventListener('click', handleEmployeeAction);
        button.dataset.initialized = 'true';
      }
    });
  }

  function attachSuperAdminActionListeners() {
    document.querySelectorAll('.btn-promote-super-admin, .btn-demote-super-admin').forEach(button => {
      if (!button.dataset.initialized) {
        button.addEventListener('click', handleSuperAdminAction);
        button.dataset.initialized = 'true';
      }
    });
  }

  attachViewUserListeners();
  attachEmployeeActionListeners();
  attachSuperAdminActionListeners();

  document.querySelectorAll('.btn-unblock').forEach(button => {
    button.addEventListener('click', async () => {
      const row = button.closest('tr');
      const id = row?.dataset.blacklistId;
      if (!id) {
        alert('Missing blacklist ID.');
        return;
      }
      const formData = new FormData();
      formData.append('action', 'unblock');
      formData.append('blacklist_id', id);
      try {
        const response = await fetch('../PHP/blacklist_api.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (!result?.success) throw new Error(result?.message || 'Failed to unblock user');
        row.remove();
      } catch (error) {
        alert(error.message);
      }
    });
  });
</script>
JS;
include 'includes/footer.php';
