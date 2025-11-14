<?php
require_once __DIR__ . '/includes/require_admin_login.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/inventory_functions.php';
require_once '../PHP/product_functions.php';

$activePage = 'inventory-report';
$pageTitle = "Inventory Report - Cindy's Bakeshop";

$inventoryData = [];
$categoryTotals = [];
$totalTracked = 0;
$preOrderCount = 0;
$lowStockCount = 0;
$inventoryLogEntries = [];
$inventoryLogDateCounts = [];
$reportDateInput = isset($_GET['report_date']) ? trim((string)$_GET['report_date']) : '';
$reportDate = null;
if ($reportDateInput !== '') {
    $parsedDate = DateTime::createFromFormat('Y-m-d', $reportDateInput);
    if ($parsedDate !== false) {
        $reportDate = $parsedDate->format('Y-m-d');
    }
}
$reportDateLabel = $reportDate ? date('M j, Y', strtotime($reportDate)) : null;
$reportRangeDescription = $reportDateLabel ? 'Showing changes for ' . $reportDateLabel : 'Showing changes for all dates';
$inventorySnapshotDescription = $reportDateLabel
    ? 'Stock levels reflect end-of-day balances for ' . $reportDateLabel . '.'
    : 'Stock levels reflect current stock levels.';
$inventoryCalendarDescription = $reportDateLabel
    ? 'Currently viewing inventory activity for ' . $reportDateLabel . '.'
    : 'Select a date to view daily inventory history.';
$reportGeneratedBy = isset($adminSession['name']) && $adminSession['name'] !== ''
    ? $adminSession['name']
    : 'Admin';

$todayDate = date('Y-m-d');
$inventoryEditingLocked = $reportDate !== null && $reportDate < $todayDate;

if ($pdo) {
    $rows = getInventoryWithProducts($pdo, $reportDate);
    foreach ($rows as $row) {
        $categoryRaw = $row['Category'] ?? '';
        $normalizedCategory = normalizeProductCategoryValue($categoryRaw);
        $category = $normalizedCategory === '' ? 'Uncategorized' : $normalizedCategory;
        $stockRaw = $row['Stock_Quantity'];
        $stockValue = max(0, (int)$stockRaw);

        if (!array_key_exists($category, $inventoryData)) {
            $inventoryData[$category] = [];
        }
        if (!array_key_exists($category, $categoryTotals)) {
            $categoryTotals[$category] = 0;
        }

        $inventoryData[$category][] = [
            'id' => $row['Product_ID'],
            'name' => $row['Name'],
            'stock' => $stockValue
        ];

        $totalTracked++;

        $categoryTotals[$category] += $stockValue;
        if ($stockValue <= 10) {
            $lowStockCount++;
        }
    }

    ksort($inventoryData);
    ksort($categoryTotals);

    $logRows = getInventoryChangeLog($pdo, $reportDate, $reportDate);
    foreach ($logRows as $logRow) {
        $orderId = isset($logRow['Order_ID']) ? (int)$logRow['Order_ID'] : 0;
        $rawDate = $logRow['Created_At'] ?? null;
        $logDateRaw = $logRow['Log_Date'] ?? null;
        $normalizedLogDate = null;

        if ($logDateRaw) {
            $logDateTimestamp = strtotime((string)$logDateRaw);
            if ($logDateTimestamp !== false) {
                $normalizedLogDate = date('Y-m-d', $logDateTimestamp);
            }
        }

        $rawDateTimestamp = false;
        if ($rawDate) {
            $rawDateTimestamp = strtotime((string)$rawDate);
            if ($rawDateTimestamp !== false && $normalizedLogDate === null) {
                $normalizedLogDate = date('Y-m-d', $rawDateTimestamp);
            }
        }

        if ($normalizedLogDate === null && !empty($logRow['Order_Date'])) {
            $orderDateTimestamp = strtotime((string)$logRow['Order_Date']);
            if ($orderDateTimestamp !== false) {
                $normalizedLogDate = date('Y-m-d', $orderDateTimestamp);
            }
        }

        if ($reportDate !== null && $normalizedLogDate !== $reportDate) {
            continue;
        }

        if (($rawDate === null || $rawDate === '') && $normalizedLogDate !== null) {
            $rawDate = $normalizedLogDate . ' 00:00:00';
            $rawDateTimestamp = strtotime($rawDate);
        }

        if (($rawDate === null || $rawDate === '') && !empty($logRow['Order_Date'])) {
            $rawDate = (string)$logRow['Order_Date'];
            $rawDateTimestamp = strtotime($rawDate);
        }

        $formattedDate = null;
        if (!empty($rawDate)) {
            $formattedDate = formatAdminDateTime($rawDate, 'M j, Y g:i A');
        }

        if (($formattedDate === null || $formattedDate === '') && $normalizedLogDate !== null) {
            $formattedDate = formatAdminDateTime($normalizedLogDate, 'M j, Y');
        }

        $source = $logRow['Change_Source'] ?? '';
        $note = $logRow['Note'] ?? '';
        $referenceType = $logRow['Reference_Type'] ?? '';
        $referenceLabel = null;

        if ($referenceType === 'order' && $orderId > 0) {
            $referenceLabel = '#' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);
        }

        if ($referenceLabel === null || $referenceLabel === '') {
            $referenceLabel = $note !== '' ? $note : ucwords(str_replace('_', ' ', $source !== '' ? $source : 'Stock Update'));
        }

        $changeTypeMap = [
            'order' => 'Order Placement',
            'manual_adjustment' => 'Manual Adjustment',
            'initial_entry' => 'Inventory Seed',
            'adjustment' => 'Stock Adjustment',
        ];
        $changeType = $changeTypeMap[$source] ?? 'Stock Update';

        $previousQuantityRaw = $logRow['Previous_Quantity'] ?? null;
        $previousQuantity = $previousQuantityRaw !== null ? (int)$previousQuantityRaw : null;
        $newQuantityRaw = $logRow['New_Quantity'] ?? null;
        $newQuantity = $newQuantityRaw !== null ? (int)$newQuantityRaw : null;
        $changeValueRaw = $logRow['Change_Amount'] ?? null;
        $changeValue = $changeValueRaw !== null ? (int)$changeValueRaw : 0;

        $inventoryLogEntries[] = [
            'log_id' => (int)($logRow['Log_ID'] ?? 0),
            'order_id' => $orderId,
            'order_code' => $referenceLabel,
            'product_id' => (int)($logRow['Product_ID'] ?? 0),
            'product_name' => $logRow['Product_Name'] ?? '',
            'change' => $changeValue,
            'change_type' => $changeType,
            'created_at' => $rawDate,
            'created_at_formatted' => $formattedDate,
            'customer_name' => $logRow['Customer_Name'] ?? '',
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'note' => $note,
            'reference_label' => $referenceLabel,
            'change_source' => $source,
            'log_date' => $normalizedLogDate,
        ];
    }

    $logDateCountRows = getInventoryLogDateCounts($pdo);
    foreach ($logDateCountRows as $countRow) {
        $rawDate = $countRow['Report_Date'] ?? null;
        $changes = isset($countRow['Change_Count']) ? (int)$countRow['Change_Count'] : 0;
        if ($rawDate !== null) {
            $normalizedDate = date('Y-m-d', strtotime((string)$rawDate));
            $inventoryLogDateCounts[$normalizedDate] = (
                ($inventoryLogDateCounts[$normalizedDate] ?? 0) + $changes
            );
        }
    }
}

$inventoryJson = json_encode($inventoryData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($inventoryJson === '[]' || $inventoryJson === 'null') {
    $inventoryJson = '{}';
}

$inventoryLogJson = json_encode($inventoryLogEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($inventoryLogJson === 'null') {
    $inventoryLogJson = '[]';
}

$inventoryLogDateCountsJson = json_encode($inventoryLogDateCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($inventoryLogDateCountsJson === 'null') {
    $inventoryLogDateCountsJson = '{}';
}

$extraHead = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
<style>
  .inventory-calendar {
    margin-top: 16px;
    border: 1px solid #e0e6ed;
    border-radius: 10px;
    padding: 16px;
    background: #ffffff;
    display: grid;
    gap: 12px;
  }

  .inventory-calendar:empty {
    display: none;
  }

  .calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
    color: #2c3e50;
  }

  .calendar-nav {
    border: none;
    background: #ecf0f1;
    color: #2c3e50;
    font-size: 18px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, color 0.2s ease;
  }

  .calendar-nav:hover {
    background: #d6dbdf;
    color: #1a252f;
  }

  .calendar-title {
    font-size: 16px;
  }

  .calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    text-align: center;
    font-size: 12px;
    text-transform: uppercase;
    color: #7f8c8d;
    gap: 6px;
  }

  .calendar-weekdays span {
    padding: 4px 0;
  }

  .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
  }

  .calendar-day {
    border: none;
    border-radius: 8px;
    padding: 8px 0;
    background: #f8f9fa;
    color: #2c3e50;
    font-size: 14px;
    cursor: pointer;
    position: relative;
    transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
  }

  .calendar-day:hover {
    background: #eaeff2;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  }

  .calendar-day.is-outside {
    background: #f1f4f6;
    color: #95a5a6;
  }

  .calendar-day.is-selected {
    background: #3498db;
    color: #ffffff;
    font-weight: 600;
  }

  .calendar-day.is-today:not(.is-selected) {
    border: 1px solid #3498db;
  }

  .calendar-day.has-entries:not(.is-selected) {
    background: #eaf7f0;
    color: #1e8449;
  }

  .calendar-day:disabled {
    background: #f4f6f7;
    color: #c0c7ce;
    cursor: not-allowed;
    box-shadow: none;
  }

  .calendar-count {
    position: absolute;
    bottom: 4px;
    right: 6px;
    background: rgba(39, 174, 96, 0.9);
    color: #ffffff;
    border-radius: 999px;
    padding: 2px 6px;
    font-size: 10px;
    line-height: 1;
  }

  .table-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 12px 0 0;
  }

  .table-pagination .btn {
    padding: 8px 14px;
    font-size: 13px;
  }

  .table-pagination-status {
    font-size: 14px;
    color: #2c3e50;
  }
</style>
HTML;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Inventory Report</h1>
  </div>

  <div class="card inventory-date-card">
    <div class="inventory-log-header">
      <div class="inventory-log-heading">
        <h2 style="margin:0;font-size:18px;font-weight:600;">Select Report Date</h2>
        <p class="inventory-log-meta"><?= htmlspecialchars($inventoryCalendarDescription); ?></p>
      </div>
      <form method="get" action="report.php" class="inventory-log-filter" autocomplete="off">
        <label for="reportDate">Report date</label>
        <input type="date" id="reportDate" name="report_date" value="<?= htmlspecialchars($reportDate ?? ''); ?>">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if ($reportDate !== null): ?>
          <a href="report.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div
      id="inventoryCalendar"
      class="inventory-calendar"
      data-selected-date="<?= htmlspecialchars($reportDate ?? ''); ?>"
      aria-live="polite"
    ></div>
  </div>

  <section class="stats-grid columns-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <div class="stat-card">
      <h3>Inventory Categories</h3>
      <div class="value" id="inventoryCategoryCount"><?= number_format(count($inventoryData)); ?></div>
      <div class="meta">Grouped by product type</div>
    </div>
    <div class="stat-card">
      <h3>Tracked SKUs</h3>
      <div class="value" id="inventorySkuCount"><?= number_format($totalTracked); ?></div>
      <div class="meta">Products with stock records</div>
    </div>
    <div class="stat-card">
      <h3>Pre-order Items</h3>
      <div class="value" id="inventoryPreorderCount">0</div>
      <div class="meta">Pre-ordering disabled</div>
    </div>
    <div class="stat-card">
      <h3>Low Stock Alerts</h3>
      <div class="value" id="inventoryLowStockCount"><?= number_format($lowStockCount); ?></div>
      <div class="meta">Items at or below 10 pcs</div>
    </div>
  </section>

  <div class="card" style="margin-top:24px;">
    <div class="table-actions">
      <input type="text" id="inventorySearch" placeholder="🔍 Search inventory item...">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <button class="btn btn-secondary" type="button" id="toggleInventoryEdit" aria-pressed="false" data-editing-locked="<?= $inventoryEditingLocked ? '1' : '0'; ?>"<?php if ($inventoryEditingLocked): ?> disabled aria-disabled="true" title="Editing historical inventory snapshots is disabled."<?php endif; ?>>Enable Editing</button>
        <button class="btn btn-primary" type="button" id="exportInventory">Export Inventory PDF</button>
      </div>
    </div>
    <div id="inventoryContainer" class="inventory-groups">
      <noscript>
        <?php if (empty($inventoryData)): ?>
          <p class="table-empty">No inventory records found.</p>
        <?php else: ?>
          <?php foreach ($inventoryData as $category => $items): ?>
            <h2 style="margin-top:24px;">
              <?= htmlspecialchars($category); ?>
            </h2>
            <table class="inventory-table">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Stock</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['name']); ?></td>
                    <td><?= number_format(max(0, (int)$item['stock'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endforeach; ?>
        <?php endif; ?>
      </noscript>
    </div>
  </div>

  <div class="card" style="margin-top:24px;">
    <div class="table-actions">
      <div class="inventory-log-header">
        <div class="inventory-log-heading">
          <h2 style="margin:0;font-size:18px;font-weight:600;">Inventory Change Log</h2>
          <p class="inventory-log-meta"><?= htmlspecialchars($reportRangeDescription); ?></p>
        </div>
      </div>
      <input type="text" id="inventoryLogSearch" placeholder="🔍 Search change log...">
    </div>
    <table class="inventory-log-table" id="inventoryLogTable">
      <thead>
        <tr>
          <th>Date</th>
          <th>Order</th>
          <th>Customer</th>
          <th>Product</th>
          <th>Change</th>
        </tr>
      </thead>
      <tbody id="inventoryLogBody">
        <?php if (empty($inventoryLogEntries)): ?>
          <tr>
            <td colspan="5" class="table-empty">No inventory changes recorded.</td>
          </tr>
        <?php else: ?>
            <?php foreach ($inventoryLogEntries as $entry): ?>
              <?php
                $changeValue = (int)($entry['change'] ?? 0);
                $changeUnit = abs($changeValue) === 1 ? ' pc' : ' pcs';
                $changeLabel = ($changeValue > 0 ? '+' : '') . number_format($changeValue) . $changeUnit;
                $changeClass = $changeValue > 0 ? 'stock-change-positive' : ($changeValue < 0 ? 'stock-change-negative' : 'stock-change-zero');
                $customerName = $entry['customer_name'] ?? '';
                $referenceLabel = $entry['reference_label'] ?? $entry['order_code'] ?? ($entry['order_id'] ?? 0 ? '#' . str_pad((string)($entry['order_id'] ?? 0), 5, '0', STR_PAD_LEFT) : '—');
              ?>
              <tr data-order-id="<?= (int)($entry['order_id'] ?? 0); ?>">
                <td><?= htmlspecialchars($entry['created_at_formatted'] ?? $entry['created_at'] ?? '—'); ?></td>
                <td class="log-order"><?= htmlspecialchars($referenceLabel); ?></td>
                <td><?= htmlspecialchars($customerName !== '' ? $customerName : '—'); ?></td>
                <td class="log-product"><?= htmlspecialchars($entry['product_name'] ?? ''); ?></td>
                <td><span class="<?= $changeClass; ?>"><?= htmlspecialchars($changeLabel); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
      </tbody>
    </table>
    <div class="table-pagination" id="inventoryLogPagination" aria-live="polite"></div>
  </div>
</div>
<?php
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const inventoryEndpoint = '../PHP/inventory_functions.php';
  const inventoryContainer = document.getElementById('inventoryContainer');
  const searchInput = document.getElementById('inventorySearch');
  const exportBtn = document.getElementById('exportInventory');
  const editToggleBtn = document.getElementById('toggleInventoryEdit');
  const editingLockedForDate = editToggleBtn?.dataset?.editingLocked === '1';
  const logSearchInput = document.getElementById('inventoryLogSearch');
  const logTableBody = document.getElementById('inventoryLogBody');
  const logPaginationContainer = document.getElementById('inventoryLogPagination');
  const reportForm = document.querySelector('.inventory-log-filter');
  const reportDateInput = document.getElementById('reportDate');
  const logCalendarContainer = document.getElementById('inventoryCalendar');
  const statsEls = {
    categoryCount: document.getElementById('inventoryCategoryCount'),
    skuCount: document.getElementById('inventorySkuCount'),
    preOrderCount: document.getElementById('inventoryPreorderCount'),
    lowStockCount: document.getElementById('inventoryLowStockCount')
  };

  const reportMetadata = {
    companyName: "Cindy's Bakeshop",
    generatedAt: <?= json_encode(formatAdminDateTime('now', 'F j, Y g:i A')); ?>,
    generatedBy: <?= json_encode($reportGeneratedBy); ?>,
    reportRange: <?= json_encode($reportRangeDescription); ?>,
    snapshotDescription: <?= json_encode($inventorySnapshotDescription); ?>,
    reportDateLabel: <?= json_encode($reportDateLabel); ?>
  };

  const MANILA_TIME_ZONE = 'Asia/Manila';
  const EIGHT_HOURS_MS = 8 * 60 * 60 * 1000;

  const addEightHours = (date) => {
    if (!(date instanceof Date)) {
      return null;
    }
    const timestamp = date.getTime();
    if (!Number.isFinite(timestamp)) {
      return null;
    }
    return new Date(timestamp + EIGHT_HOURS_MS);
  };
  const numberFormatter = new Intl.NumberFormat();
  const parseNullableInteger = (value) => {
    if (value === null || typeof value === 'undefined' || value === '') {
      return null;
    }
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? null : parsed;
  };
  const normalizeInventoryCategory = (value) => {
    if (typeof value !== 'string') {
      return '';
    }
    const trimmed = value.trim();
    if (trimmed === '') {
      return '';
    }
    return trimmed.toLowerCase().includes('pastry') ? 'Bread' : trimmed;
  };
  const LOG_PAGE_SIZE = 20;
  let currentSearchTerm = '';
  let currentLogSearchTerm = '';
  let currentLogPage = 1;
  let latestUpdateToken = 0;
  let categoryChartInstance = null;
  let inventoryIndex = new Map();
  let inventoryData = {};
  let inventoryLogEntries = [];
  let logDateCountsByDate = new Map();
  let calendarFocusYear = null;
  let calendarFocusMonth = null;
  let inventoryEditingEnabled = false;

  function submitReportForm() {
    if (!reportForm) {
      return;
    }
    if (typeof reportForm.requestSubmit === 'function') {
      reportForm.requestSubmit();
      return;
    }
    reportForm.submit();
  }

  if (searchInput) {
    currentSearchTerm = searchInput.value.toLowerCase();
    searchInput.addEventListener('input', () => {
      currentSearchTerm = searchInput.value.toLowerCase();
      applySearchFilter();
    });
  }

  if (inventoryContainer) {
    inventoryContainer.addEventListener('click', (event) => {
      if (!inventoryEditingEnabled || editingLockedForDate) {
        return;
      }
      const saveButton = event.target.closest('.inventory-save-btn');
      if (saveButton && inventoryContainer.contains(saveButton)) {
        const control = saveButton.closest('.inventory-stock-cell')?.querySelector('.inventory-stock-control')
          || saveButton.closest('.inventory-stock-control');
        if (control) {
          persistStock(control);
        }
        return;
      }

      const button = event.target.closest('.inventory-adjust-btn');
      if (!button || !inventoryContainer.contains(button)) {
        return;
      }
      const control = button.closest('.inventory-stock-control');
      if (!control) {
        return;
      }
      const delta = button.classList.contains('inventory-minus') ? -1 : 1;
      adjustStock(control, delta);
    });

    inventoryContainer.addEventListener('input', (event) => {
      if (!inventoryEditingEnabled || editingLockedForDate) {
        return;
      }
      const input = event.target.closest('.inventory-stock-input');
      if (!input) {
        return;
      }
      const control = input.closest('.inventory-stock-control');
      updateStockStatus(control);
      refreshControlDirtyState(control);
    });

    inventoryContainer.addEventListener('change', (event) => {
      if (!inventoryEditingEnabled || editingLockedForDate) {
        return;
      }
      const input = event.target.closest('.inventory-stock-input');
      if (!input) {
        return;
      }
      const control = input.closest('.inventory-stock-control');
      updateStockStatus(control);
      refreshControlDirtyState(control);
    });

    inventoryContainer.addEventListener('keydown', (event) => {
      if (!inventoryEditingEnabled || editingLockedForDate) {
        return;
      }
      if (event.key === 'Enter') {
        const input = event.target.closest('.inventory-stock-input');
        if (input) {
          event.preventDefault();
          input.blur();
        }
      }
    });
  }

  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
        window.alert('PDF generator is not ready yet. Please try again in a moment.');
        return;
      }

      const doc = buildInventoryPDFDocument();
      if (!doc) {
        window.alert('Unable to build the inventory report right now. Please try again.');
        return;
      }

      showInventoryPdfPreview(doc, 'inventory-report.pdf');
    });
  }

  if (reportDateInput && reportForm) {
    reportDateInput.addEventListener('change', () => {
      submitReportForm();
    });
  }

  if (logSearchInput) {
    currentLogSearchTerm = logSearchInput.value.trim().toLowerCase();
    logSearchInput.addEventListener('input', () => {
      currentLogSearchTerm = logSearchInput.value.trim().toLowerCase();
      currentLogPage = 1;
      renderInventoryLogTable();
    });
  }

  if (editToggleBtn) {
    if (editingLockedForDate) {
      editToggleBtn.disabled = true;
      editToggleBtn.setAttribute('aria-disabled', 'true');
      editToggleBtn.setAttribute('aria-pressed', 'false');
      editToggleBtn.setAttribute('title', 'Editing historical inventory snapshots is disabled.');
    } else {
      editToggleBtn.addEventListener('click', () => {
        inventoryEditingEnabled = !inventoryEditingEnabled;
        if (!inventoryEditingEnabled) {
          renderInventoryUI();
          return;
        }

        applyEditingState();
        const firstInput = inventoryContainer?.querySelector('.inventory-stock-input');
        if (firstInput) {
          firstInput.focus();
          if (typeof firstInput.select === 'function') {
            firstInput.select();
          }
        }
      });
    }
  }

  function renderInventoryUI() {
    buildInventoryIndex();
    const metrics = computeMetrics(inventoryData);
    updateStats(metrics);
    renderCategoryChart(metrics);
    renderInventoryTable();
  }

  function buildInventoryIndex() {
    inventoryIndex.clear();
    Object.entries(inventoryData).forEach(([category, items]) => {
      if (!Array.isArray(items)) {
        return;
      }
      items.forEach((item, position) => {
        if (!item || typeof item.id === 'undefined') {
          return;
        }
        inventoryIndex.set(Number(item.id), { category, position });
      });
    });
  }

  function renderInventoryTable() {
    if (!inventoryContainer) {
      return;
    }

    inventoryContainer.innerHTML = '';

    const categories = Object.keys(inventoryData);
    if (!categories.length) {
      const emptyMessage = document.createElement('p');
      emptyMessage.className = 'table-empty';
      emptyMessage.textContent = 'No inventory records found.';
      inventoryContainer.appendChild(emptyMessage);
      return;
    }

    categories.sort((a, b) => a.localeCompare(b));

    categories.forEach((category) => {
      const section = document.createElement('div');
      section.className = 'inventory-section';

      const heading = document.createElement('h2');
      heading.textContent = category;
      section.appendChild(heading);

      const table = document.createElement('table');
      table.className = 'inventory-table';

      const thead = document.createElement('thead');
      const headerRow = document.createElement('tr');
      ['Item Name', 'Stock'].forEach((label) => {
        const th = document.createElement('th');
        th.textContent = label;
        headerRow.appendChild(th);
      });
      thead.appendChild(headerRow);
      table.appendChild(thead);

      const tbody = document.createElement('tbody');
      const items = Array.isArray(inventoryData[category]) ? [...inventoryData[category]] : [];
      items.sort((a, b) => (a?.name || '').localeCompare(b?.name || ''));

      items.forEach((item) => {
        const row = document.createElement('tr');
        row.dataset.itemName = (item?.name || '').toLowerCase();

        const nameCell = document.createElement('td');
        nameCell.textContent = item?.name || 'Unnamed Item';
        row.appendChild(nameCell);

        const stockCell = document.createElement('td');
        stockCell.className = 'inventory-stock-cell';
        const control = document.createElement('div');
        control.className = 'inventory-stock-control';
        control.dataset.productId = Number(item?.id ?? 0);
        control.dataset.category = category;

        const minusButton = document.createElement('button');
        minusButton.type = 'button';
        minusButton.className = 'inventory-adjust-btn inventory-minus';
        minusButton.setAttribute('aria-label', 'Decrease stock');
        minusButton.textContent = '−';

        const stockInput = document.createElement('input');
        stockInput.type = 'number';
        stockInput.className = 'inventory-stock-input';
        stockInput.min = '0';
        stockInput.step = '1';
        stockInput.setAttribute('inputmode', 'numeric');
        stockInput.placeholder = '0';
        const parsedStock = parseInt(item?.stock, 10);
        const safeStock = Number.isNaN(parsedStock) ? 0 : Math.max(0, parsedStock);
        stockInput.value = safeStock;

        const plusButton = document.createElement('button');
        plusButton.type = 'button';
        plusButton.className = 'inventory-adjust-btn inventory-plus';
        plusButton.setAttribute('aria-label', 'Increase stock');
        plusButton.textContent = '+';

        control.appendChild(minusButton);
        control.appendChild(stockInput);
        control.appendChild(plusButton);

        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'inventory-save-btn';
        saveButton.textContent = 'Save';
        saveButton.disabled = true;
        saveButton.dataset.defaultLabel = 'Save';

        const note = document.createElement('span');
        note.className = 'inventory-stock-note';

        stockCell.appendChild(control);
        stockCell.appendChild(saveButton);
        stockCell.appendChild(note);

        row.appendChild(stockCell);
        tbody.appendChild(row);
      });

      table.appendChild(tbody);
      section.appendChild(table);
      inventoryContainer.appendChild(section);
    });

    inventoryContainer.querySelectorAll('.inventory-stock-control').forEach((control) => {
      updateStockStatus(control);
      refreshControlDirtyState(control);
    });

    applyEditingState();

    applySearchFilter();
  }

  function applyEditingState() {
    const effectiveEditingEnabled = !editingLockedForDate && inventoryEditingEnabled;

    if (editToggleBtn) {
      if (editingLockedForDate) {
        editToggleBtn.disabled = true;
        editToggleBtn.setAttribute('aria-disabled', 'true');
        editToggleBtn.setAttribute('aria-pressed', 'false');
        editToggleBtn.setAttribute('title', 'Editing historical inventory snapshots is disabled.');
        editToggleBtn.textContent = 'Enable Editing';
        editToggleBtn.classList.remove('is-active');
        editToggleBtn.classList.remove('btn-primary');
        editToggleBtn.classList.add('btn-secondary');
      } else {
        editToggleBtn.disabled = false;
        editToggleBtn.removeAttribute('aria-disabled');
        editToggleBtn.removeAttribute('title');
        editToggleBtn.textContent = effectiveEditingEnabled ? 'Disable Editing' : 'Enable Editing';
        editToggleBtn.setAttribute('aria-pressed', effectiveEditingEnabled ? 'true' : 'false');
        editToggleBtn.classList.toggle('is-active', effectiveEditingEnabled);
        editToggleBtn.classList.toggle('btn-primary', effectiveEditingEnabled);
        editToggleBtn.classList.toggle('btn-secondary', !effectiveEditingEnabled);
      }
    }

    if (!inventoryContainer) {
      return;
    }

    inventoryContainer.classList.toggle('is-editing', effectiveEditingEnabled);

    inventoryContainer.querySelectorAll('.inventory-stock-control').forEach((control) => {
      control.classList.toggle('is-readonly', !effectiveEditingEnabled);
      const minusButton = control.querySelector('.inventory-minus');
      const plusButton = control.querySelector('.inventory-plus');
      const stockInput = control.querySelector('.inventory-stock-input');

      [minusButton, plusButton].forEach((button) => {
        if (button) {
          button.disabled = !effectiveEditingEnabled;
        }
      });

      if (stockInput) {
        stockInput.disabled = !effectiveEditingEnabled;
        stockInput.readOnly = !effectiveEditingEnabled;
        if (!effectiveEditingEnabled) {
          stockInput.blur();
        }
      }

      refreshControlDirtyState(control);
    });
  }

  function updateStats(metrics) {
    if (statsEls.categoryCount) {
      statsEls.categoryCount.textContent = numberFormatter.format(metrics.categoryCount);
    }
    if (statsEls.skuCount) {
      statsEls.skuCount.textContent = numberFormatter.format(metrics.totalTracked);
    }
    if (statsEls.preOrderCount) {
      statsEls.preOrderCount.textContent = numberFormatter.format(metrics.preOrderCount);
    }
    if (statsEls.lowStockCount) {
      statsEls.lowStockCount.textContent = numberFormatter.format(metrics.lowStockCount);
    }
  }

  function renderCategoryChart(metrics) {
    const chartCanvas = document.getElementById('categoryChart');
    if (!chartCanvas || typeof Chart === 'undefined') {
      return;
    }

    const labels = Object.keys(metrics.totals);
    const values = labels.map((label) => metrics.totals[label]);
    const chartLabels = labels.length ? labels : ['No Data'];
    const chartValues = labels.length ? values : [0];

    if (!categoryChartInstance) {
      categoryChartInstance = new Chart(chartCanvas, {
        type: 'bar',
        data: {
          labels: chartLabels,
          datasets: [{
            label: 'Units on Hand',
            data: chartValues,
            backgroundColor: '#27ae60'
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 }
            }
          }
        }
      });
    } else {
      categoryChartInstance.data.labels = chartLabels;
      categoryChartInstance.data.datasets[0].data = chartValues;
      categoryChartInstance.update();
    }
  }

  function applySearchFilter() {
    if (!inventoryContainer) {
      return;
    }

    let visibleRows = 0;
    inventoryContainer.querySelectorAll('.inventory-section').forEach((section) => {
      const rows = section.querySelectorAll('tbody tr');
      let sectionVisible = 0;

      rows.forEach((row) => {
        const name = row.dataset.itemName || '';
        const matches = !currentSearchTerm || name.includes(currentSearchTerm);
        row.style.display = matches ? '' : 'none';
        if (matches) {
          sectionVisible += 1;
          visibleRows += 1;
        }
      });

      section.style.display = sectionVisible ? '' : 'none';
    });

    const existingEmptyState = inventoryContainer.querySelector('.inventory-empty-state');
    if (!visibleRows && currentSearchTerm) {
      if (!existingEmptyState) {
        const empty = document.createElement('p');
        empty.className = 'table-empty inventory-empty-state';
        empty.textContent = 'No inventory matches your search.';
        inventoryContainer.appendChild(empty);
      }
    } else if (existingEmptyState) {
      existingEmptyState.remove();
    }
  }

  function adjustStock(control, delta) {
    if (!inventoryEditingEnabled || editingLockedForDate) {
      return;
    }
    if (!control) {
      return;
    }
    const input = control.querySelector('.inventory-stock-input');
    if (!input) {
      return;
    }
    let value = parseInt(input.value, 10);
    if (Number.isNaN(value)) {
      value = 0;
    }
    value += delta;
    if (value < 0) {
      value = 0;
    }
    input.value = value;
    updateStockStatus(control);
    refreshControlDirtyState(control);
  }

  function updateStockStatus(control) {
    if (!control) {
      return;
    }
    const input = control.querySelector('.inventory-stock-input');
    const note = control.parentElement?.querySelector('.inventory-stock-note');
    const row = control.closest('tr');

    const raw = (input?.value ?? '').toString().trim();
    if (note) {
      note.textContent = '';
      note.classList.remove('is-preorder', 'is-low', 'is-zero', 'has-unsaved');
    }
    if (row) {
      row.removeAttribute('data-stock-state');
    }

    let numeric = parseInt(raw, 10);
    if (Number.isNaN(numeric) || numeric < 0) {
      numeric = 0;
    }
    if (input) {
      input.value = numeric;
    }

    if (note) {
      if (numeric === 0) {
        note.textContent = 'Out of stock';
        note.classList.add('is-zero');
      } else if (numeric <= 10) {
        note.textContent = 'Low stock';
        note.classList.add('is-low');
      } else {
        note.textContent = '';
      }
      note.dataset.baseMessage = note.textContent;
    }

    if (row) {
      if (numeric === 0) {
        row.setAttribute('data-stock-state', 'zero');
      } else if (numeric <= 10) {
        row.setAttribute('data-stock-state', 'low');
      } else {
        row.setAttribute('data-stock-state', 'ok');
      }
    }

    return numeric;
  }

  function refreshControlDirtyState(control) {
    if (!control) {
      return;
    }

    const input = control.querySelector('.inventory-stock-input');
    const saveButton = control.parentElement?.querySelector('.inventory-save-btn');
    const note = control.parentElement?.querySelector('.inventory-stock-note');
    const productId = Number(control.dataset.productId);
    const sanitized = sanitizeStockValue(input ? input.value : '0');
    const parsedSanitized = parseInt(sanitized, 10);
    const normalizedSanitized = Number.isNaN(parsedSanitized) || parsedSanitized < 0 ? 0 : parsedSanitized;

    let originalStock = 0;
    const meta = inventoryIndex.get(productId);
    if (meta && Array.isArray(inventoryData[meta.category])) {
      const original = inventoryData[meta.category][meta.position]?.stock;
      const parsedOriginal = parseInt(original, 10);
      originalStock = Number.isNaN(parsedOriginal) || parsedOriginal < 0 ? 0 : parsedOriginal;
    }

    const isDirty = normalizedSanitized !== originalStock;
    control.dataset.dirty = isDirty ? '1' : '0';
    control.classList.toggle('has-unsaved', isDirty);

    if (saveButton) {
      const defaultLabel = saveButton.dataset.defaultLabel || saveButton.textContent || 'Save';
      if (!saveButton.dataset.defaultLabel) {
        saveButton.dataset.defaultLabel = defaultLabel;
      }
      const isSaving = control.classList.contains('is-saving');
      if (!isSaving) {
        saveButton.textContent = saveButton.dataset.defaultLabel;
      }
      saveButton.disabled = isSaving || !inventoryEditingEnabled || editingLockedForDate || !isDirty;
    }

    if (note) {
      const baseMessage = typeof note.dataset.baseMessage === 'string' ? note.dataset.baseMessage : '';
      if (isDirty) {
        const unsavedMessage = baseMessage ? `${baseMessage} · Unsaved changes` : 'Unsaved changes';
        note.textContent = unsavedMessage;
        note.classList.add('has-unsaved');
      } else {
        note.textContent = baseMessage;
        note.classList.remove('has-unsaved');
      }
    }
  }

  function persistStock(control) {
    if (!inventoryEditingEnabled || editingLockedForDate) {
      return;
    }
    if (!control) {
      return;
    }
    const input = control.querySelector('.inventory-stock-input');
    if (!input) {
      return;
    }
    const productId = Number(control.dataset.productId);
    const sanitized = sanitizeStockValue(input.value);
    input.value = sanitized;
    updateStockStatus(control);
    refreshControlDirtyState(control);

    if (control.dataset.dirty !== '1') {
      return;
    }

    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('stock_quantity', sanitized);

    const requestId = ++latestUpdateToken;
    setControlSaving(control, true);

    fetch(inventoryEndpoint, {
      method: 'POST',
      body: formData
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Failed to update inventory.');
        }
        return response.json();
      })
      .then((result) => {
        if (!result?.success) {
          throw new Error(result?.error || 'Inventory update rejected.');
        }
        if (requestId !== latestUpdateToken) {
          return;
        }
        inventoryData = normalizeInventoryData(result.data);
        renderInventoryUI();
      })
      .catch((error) => {
        console.error('Error updating stock', error);
        renderInventoryUI();
      })
      .finally(() => {
        setControlSaving(control, false);
      });
  }

  function setControlSaving(control, isSaving) {
    if (!control) {
      return;
    }
    const saving = Boolean(isSaving);
    control.classList.toggle('is-saving', saving);

    const saveButton = control.parentElement?.querySelector('.inventory-save-btn');
    if (saveButton) {
      const defaultLabel = saveButton.dataset.defaultLabel || saveButton.textContent || 'Save';
      if (!saveButton.dataset.defaultLabel) {
        saveButton.dataset.defaultLabel = defaultLabel;
      }
      if (saving) {
        saveButton.textContent = 'Saving…';
        saveButton.disabled = true;
      } else {
        saveButton.textContent = saveButton.dataset.defaultLabel;
        const isDirty = control.dataset.dirty === '1';
        saveButton.disabled = !inventoryEditingEnabled || editingLockedForDate || !isDirty;
      }
    }
  }

  function sanitizeStockValue(raw) {
    const trimmed = (raw ?? '').toString().trim();
    let numeric = parseInt(trimmed, 10);
    if (Number.isNaN(numeric) || numeric < 0) {
      numeric = 0;
    }
    return String(numeric);
  }

  function normalizeInventoryData(raw) {
    const normalized = {};
    if (!raw || typeof raw !== 'object') {
      return normalized;
    }

    Object.entries(raw).forEach(([category, items]) => {
      const normalizedCategory = normalizeInventoryCategory(category);
      const targetCategory = normalizedCategory === '' ? 'Uncategorized' : normalizedCategory;

      if (!Object.prototype.hasOwnProperty.call(normalized, targetCategory)) {
        normalized[targetCategory] = [];
      }

      if (!Array.isArray(items)) {
        return;
      }

      items.forEach((item) => {
        const stockValue = item?.stock;
        const parsed = parseInt(stockValue, 10);
        const normalizedStock = Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
        const idValue = Number(item?.id ?? item?.Product_ID ?? 0);
        const normalizedId = Number.isFinite(idValue) ? idValue : 0;
        const nameValue = item?.name ?? item?.Name ?? '';

        normalized[targetCategory].push({
          id: normalizedId,
          name: nameValue,
          stock: normalizedStock
        });
      });
    });

    Object.keys(normalized).forEach((categoryKey) => {
      const seenIds = new Set();
      normalized[categoryKey] = normalized[categoryKey].filter((item) => {
        const id = Number(item.id);
        if (!Number.isFinite(id) || id <= 0) {
          return true;
        }
        if (seenIds.has(id)) {
          return false;
        }
        seenIds.add(id);
        return true;
      });
    });

    return normalized;
  }

  function computeMetrics(data) {
    const totals = {};
    let totalTracked = 0;
    let preOrderCount = 0;
    let lowStockCount = 0;

    Object.entries(data).forEach(([category, items]) => {
      let categoryTotal = 0;
      if (!Array.isArray(items)) {
        totals[category] = 0;
        return;
      }
      items.forEach((item) => {
        totalTracked += 1;
        const value = Math.max(0, parseInt(item.stock, 10) || 0);
        categoryTotal += value;
        if (value <= 10) {
          lowStockCount += 1;
        }
      });
      totals[category] = categoryTotal;
    });

    return {
      totals,
      totalTracked,
      preOrderCount,
      lowStockCount,
      categoryCount: Object.keys(data).length
    };
  }

  function buildInventoryPDFDocument() {
    if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
      return null;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 14;
    const accent = [230, 126, 34];

    const drawHeader = () => {
      doc.setFillColor(...accent);
      doc.rect(0, 0, pageWidth, 32, 'F');

      doc.setTextColor(255, 255, 255);
      doc.setFontSize(18);
      doc.text(reportMetadata.companyName || "Cindy's Bakeshop", margin, 18);
      doc.setFontSize(12);
      doc.text('Inventory Report', margin, 26);

      const headerRightX = pageWidth - margin;
      const rightColumnLines = [];
      if (reportMetadata.generatedAt) {
        rightColumnLines.push(`Generated: ${reportMetadata.generatedAt}`);
      }
      if (reportMetadata.generatedBy) {
        rightColumnLines.push(`Generated by: ${reportMetadata.generatedBy}`);
      }
      if (reportMetadata.reportDateLabel) {
        rightColumnLines.push(`Report Date: ${reportMetadata.reportDateLabel}`);
      }

      if (rightColumnLines.length) {
        doc.setFontSize(10);
        const wrapped = rightColumnLines.flatMap((line) => doc.splitTextToSize(line, pageWidth / 2));
        doc.text(wrapped, headerRightX, 18, { align: 'right' });
      }

      doc.setDrawColor(...accent);
      doc.setLineWidth(0.5);
      doc.line(margin, 32, pageWidth - margin, 32);

      doc.setTextColor(0, 0, 0);
      doc.setFontSize(11);
      return 40;
    };

    const drawPageNumbers = () => {
      const totalPages = doc.internal.getNumberOfPages();
      if (!Number.isFinite(totalPages) || totalPages <= 0) {
        return;
      }

      doc.setFontSize(9);
      doc.setTextColor(120, 120, 120);

      for (let pageIndex = 1; pageIndex <= totalPages; pageIndex += 1) {
        doc.setPage(pageIndex);
        doc.text(
          `Page ${pageIndex} of ${totalPages}`,
          pageWidth / 2,
          pageHeight - 10,
          { align: 'center' }
        );
      }

      doc.setTextColor(0, 0, 0);
    };

    let y = drawHeader();

    doc.setFontSize(12);
    doc.text('Snapshot Summary', margin, y);
    y += 6;

    const narrativeLines = [];
    if (reportMetadata.snapshotDescription) {
      narrativeLines.push(`Inventory snapshot: ${reportMetadata.snapshotDescription}`);
    }
    if (narrativeLines.length) {
      doc.setFontSize(10);
      const wrappedSummary = narrativeLines.flatMap((line) => doc.splitTextToSize(line, pageWidth - margin * 2));
      doc.text(wrappedSummary, margin, y);
      y += wrappedSummary.length * 5 + 4;
    }

    const metrics = computeMetrics(inventoryData);
    const summaryRows = [
      ['Total Categories', numberFormatter.format(metrics.categoryCount ?? 0)],
      ['Tracked Items', numberFormatter.format(metrics.totalTracked ?? 0)],
      ['Low Stock Items', numberFormatter.format(metrics.lowStockCount ?? 0)]
    ];

    if (typeof doc.autoTable === 'function') {
      doc.autoTable({
        startY: y,
        head: [['Metric', 'Value']],
        body: summaryRows,
        theme: 'striped',
        styles: { fontSize: 10 },
        headStyles: { fillColor: accent, textColor: [255, 255, 255] },
        columnStyles: {
          0: { cellWidth: pageWidth - margin * 2 - 40 },
          1: { cellWidth: 40, halign: 'right' }
        },
        margin: { left: margin, right: margin, top: 40 },
        didDrawPage: drawHeader
      });
      y = doc.lastAutoTable ? doc.lastAutoTable.finalY + 10 : y + 20;
    } else {
      doc.setFontSize(10);
      summaryRows.forEach((row) => {
        doc.text(`${row[0]}: ${row[1]}`, margin, y);
        y += 5;
      });
      y += 5;
    }

    doc.setFontSize(12);
    doc.text('Category Details', margin, y);
    y += 6;

    const categories = Object.keys(inventoryData).sort((a, b) => a.localeCompare(b));
    if (!categories.length) {
      doc.setFontSize(11);
      doc.text('No inventory records found.', margin, y);
      return doc;
    }

    const ensureSpace = (heightNeeded = 0) => {
      if (y + heightNeeded <= pageHeight - margin) {
        return;
      }
      doc.addPage();
      y = drawHeader();
      doc.setFontSize(12);
      doc.text('Category Details (cont.)', margin, y);
      y += 6;
    };

    categories.forEach((category, index) => {
      ensureSpace(18);
      doc.setFontSize(11);
      doc.setTextColor(...accent);
      doc.text(category, margin, y);
      doc.setTextColor(0, 0, 0);
      y += 6;

      const body = (inventoryData[category] || []).map((item) => {
        const stock = Math.max(0, parseInt(item.stock, 10) || 0);
        return [item.name || 'Unnamed Item', numberFormatter.format(stock)];
      });

      if (!body.length) {
        doc.setFontSize(10);
        doc.text('No items in this category.', margin + 4, y);
        y += 8;
        return;
      }

      if (typeof doc.autoTable === 'function') {
        doc.autoTable({
          startY: y,
          head: [['Item Name', 'On Hand']],
          body,
          theme: 'grid',
          styles: { fontSize: 10 },
          headStyles: { fillColor: accent, textColor: [255, 255, 255] },
          columnStyles: {
            0: { cellWidth: pageWidth - margin * 2 - 35 },
            1: { cellWidth: 35, halign: 'right' }
          },
          margin: { left: margin, right: margin, top: 40 },
          didDrawPage: drawHeader
        });
        y = doc.lastAutoTable ? doc.lastAutoTable.finalY + 8 : y + 12;
      } else {
        doc.setFontSize(10);
        body.forEach((row) => {
          ensureSpace(12);
          doc.text(`${row[0]} - ${row[1]} pcs`, margin + 4, y);
          y += 6;
        });
        y += 4;
      }

      if (index < categories.length - 1) {
        ensureSpace(6);
        doc.setDrawColor(220, 221, 225);
        doc.line(margin, y, pageWidth - margin, y);
        y += 6;
      }
    });

    drawPageNumbers();

    return doc;
  }

  function showInventoryPdfPreview(pdfDoc, filename) {
    if (!pdfDoc || typeof pdfDoc.output !== 'function') {
      if (pdfDoc && typeof pdfDoc.save === 'function') {
        pdfDoc.save(filename);
      }
      return;
    }

    const supportsObjectUrl = typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function';
    let blobUrl = null;

    if (supportsObjectUrl) {
      try {
        const blob = pdfDoc.output('blob');
        if (blob instanceof Blob) {
          blobUrl = URL.createObjectURL(blob);
        }
      } catch (error) {
        console.error('Failed to build PDF blob for preview:', error);
      }
    }

    if (!blobUrl) {
      try {
        const dataUrl = pdfDoc.output('dataurlstring');
        if (dataUrl) {
          const previewWindow = window.open(dataUrl, '_blank', 'noopener');
          if (!previewWindow) {
            window.alert('Unable to open preview window. The PDF will be downloaded instead.');
            pdfDoc.save(filename);
          }
        } else {
          pdfDoc.save(filename);
        }
      } catch (error) {
        console.error('Failed to open PDF preview window:', error);
        pdfDoc.save(filename);
      }
      return;
    }

    const existingOverlay = document.getElementById('inventoryPdfPreviewOverlay');
    if (existingOverlay) {
      existingOverlay.remove();
    }

    if (!document.body) {
      pdfDoc.save(filename);
      return;
    }

    const overlay = document.createElement('div');
    overlay.id = 'inventoryPdfPreviewOverlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Inventory report PDF preview');
    overlay.style.cssText = [
      'position:fixed',
      'inset:0',
      'background:rgba(0,0,0,0.65)',
      'display:flex',
      'align-items:center',
      'justify-content:center',
      'padding:24px',
      'z-index:9999'
    ].join(';');

    const modal = document.createElement('div');
    modal.style.cssText = [
      'background:#ffffff',
      'max-width:960px',
      'width:100%',
      'height:85vh',
      'display:flex',
      'flex-direction:column',
      'border-radius:8px',
      'box-shadow:0 12px 40px rgba(0,0,0,0.25)',
      'overflow:hidden'
    ].join(';');

    const frame = document.createElement('iframe');
    frame.src = blobUrl;
    frame.title = 'Inventory report preview';
    frame.style.cssText = ['flex:1', 'border:0'].join(';');

    const actions = document.createElement('div');
    actions.style.cssText = [
      'display:flex',
      'justify-content:flex-end',
      'gap:12px',
      'padding:12px 16px',
      'background:#f5f6fa',
      'border-top:1px solid #dcdde1'
    ].join(';');

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.textContent = 'Close';
    closeBtn.className = 'btn btn-secondary';
    closeBtn.style.cssText = [
      'padding:10px 18px',
      'border:none',
      'border-radius:4px',
      'background:#7f8c8d',
      'color:#fff',
      'cursor:pointer',
      'font-size:14px'
    ].join(';');

    const downloadBtn = document.createElement('button');
    downloadBtn.type = 'button';
    downloadBtn.textContent = 'Download PDF';
    downloadBtn.className = 'btn btn-primary';
    downloadBtn.style.cssText = [
      'padding:10px 18px',
      'border:none',
      'border-radius:4px',
      'background:#e67e22',
      'color:#fff',
      'cursor:pointer',
      'font-size:14px'
    ].join(';');

    actions.append(closeBtn, downloadBtn);
    modal.append(frame, actions);
    overlay.appendChild(modal);

    function cleanup() {
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
      }
      document.removeEventListener('keydown', handleKeyDown);
    }

    function handleKeyDown(event) {
      if (event.key === 'Escape') {
        cleanup();
      }
    }

    document.addEventListener('keydown', handleKeyDown);

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        cleanup();
      }
    });

    closeBtn.addEventListener('click', cleanup);

    downloadBtn.addEventListener('click', () => {
      pdfDoc.save(filename);
      cleanup();
    });

    document.body.appendChild(overlay);
  }

  function renderInventoryLogTable() {
    if (!logTableBody) {
      return;
    }

    const filteredEntries = inventoryLogEntries.filter((entry) => matchesLogSearch(entry));
    const totalEntries = filteredEntries.length;
    const totalPages = totalEntries ? Math.ceil(totalEntries / LOG_PAGE_SIZE) : 1;

    if (currentLogPage > totalPages) {
      currentLogPage = totalPages;
    }
    if (currentLogPage < 1) {
      currentLogPage = 1;
    }

    logTableBody.innerHTML = '';

    if (!totalEntries) {
      const emptyRow = document.createElement('tr');
      const emptyCell = document.createElement('td');
      emptyCell.colSpan = 5;
      emptyCell.className = 'table-empty';
      emptyCell.textContent = 'No inventory changes recorded.';
      emptyRow.appendChild(emptyCell);
      logTableBody.appendChild(emptyRow);
      renderInventoryLogPagination(totalPages, totalEntries);
      return;
    }

    const startIndex = (currentLogPage - 1) * LOG_PAGE_SIZE;
    const pageEntries = filteredEntries.slice(startIndex, startIndex + LOG_PAGE_SIZE);
    const fragment = document.createDocumentFragment();

    pageEntries.forEach((entry) => {
      const row = document.createElement('tr');
      if (entry.orderId) {
        row.dataset.orderId = String(entry.orderId);
      }
      if (entry.changeType) {
        row.dataset.changeType = entry.changeType;
      }
      if (entry.note) {
        row.title = entry.note;
      }

      const dateCell = document.createElement('td');
      dateCell.textContent = entry.createdAtFormatted || entry.createdAt || '—';
      row.appendChild(dateCell);

      const orderCell = document.createElement('td');
      orderCell.className = 'log-order';
      orderCell.textContent = entry.referenceLabel || '—';
      row.appendChild(orderCell);

      const customerCell = document.createElement('td');
      customerCell.textContent = entry.customerName || '—';
      row.appendChild(customerCell);

      const productCell = document.createElement('td');
      productCell.className = 'log-product';
      productCell.textContent = entry.productName || '—';
      row.appendChild(productCell);

      const changeCell = document.createElement('td');
      const changeSpan = document.createElement('span');
      const changeValue = typeof entry.change === 'number' && !Number.isNaN(entry.change) ? entry.change : 0;
      const changeClass = getStockChangeClass(changeValue);
      if (changeClass) {
        changeSpan.className = changeClass;
      }
      changeSpan.textContent = formatChangeLabel(changeValue);
      changeCell.appendChild(changeSpan);
      row.appendChild(changeCell);

      fragment.appendChild(row);
    });

    logTableBody.appendChild(fragment);
    renderInventoryLogPagination(totalPages, totalEntries);
  }

  function renderInventoryLogPagination(totalPages, totalEntries) {
    if (!logPaginationContainer) {
      return;
    }

    logPaginationContainer.innerHTML = '';

    if (!totalEntries || totalPages <= 1) {
      logPaginationContainer.style.display = 'none';
      return;
    }

    logPaginationContainer.style.display = '';

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'btn btn-secondary';
    prevBtn.textContent = 'Previous';
    prevBtn.disabled = currentLogPage <= 1;
    prevBtn.addEventListener('click', () => {
      if (currentLogPage > 1) {
        currentLogPage -= 1;
        renderInventoryLogTable();
      }
    });

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'btn btn-secondary';
    nextBtn.textContent = 'Next';
    nextBtn.disabled = currentLogPage >= totalPages;
    nextBtn.addEventListener('click', () => {
      if (currentLogPage < totalPages) {
        currentLogPage += 1;
        renderInventoryLogTable();
      }
    });

    const status = document.createElement('span');
    status.className = 'table-pagination-status';
    status.setAttribute('aria-live', 'polite');
    status.setAttribute('role', 'status');
    const startEntry = (currentLogPage - 1) * LOG_PAGE_SIZE + 1;
    const endEntry = Math.min(currentLogPage * LOG_PAGE_SIZE, totalEntries);
    status.textContent = `Showing ${numberFormatter.format(startEntry)}–${numberFormatter.format(endEntry)} of ${numberFormatter.format(totalEntries)} (Page ${numberFormatter.format(currentLogPage)} of ${numberFormatter.format(totalPages)})`;

    logPaginationContainer.append(prevBtn, status, nextBtn);
  }

  function matchesLogSearch(entry) {
    if (!currentLogSearchTerm) {
      return true;
    }
    const haystack = [
      entry.referenceLabel,
      entry.productName,
      entry.createdAtFormatted,
      entry.createdAt,
      entry.logDate,
      entry.customerName,
      entry.changeType,
      entry.changeSource,
      entry.note,
      entry.orderId ? `#\${entry.orderId}` : ''
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();
    return haystack.includes(currentLogSearchTerm);
  }

  function getStockChangeClass(changeValue) {
    if (changeValue > 0) {
      return 'stock-change-positive';
    }
    if (changeValue < 0) {
      return 'stock-change-negative';
    }
    return 'stock-change-zero';
  }

  function formatChangeLabel(changeValue) {
    const normalized = typeof changeValue === 'number' && !Number.isNaN(changeValue) ? changeValue : 0;
    const unit = Math.abs(normalized) === 1 ? ' pc' : ' pcs';
    const prefix = normalized > 0 ? '+' : '';
    return `${prefix}${numberFormatter.format(normalized)}${unit}`;
  }

  function formatDateForDisplay(dateString) {
    if (!dateString) {
      return '—';
    }
    const isoMatch = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(dateString);
    if (isoMatch) {
      const [, year, month, day] = isoMatch;
      const monthIndex = Number(month) - 1;
      const dayNumber = Number(day);
      if (monthIndex >= 0 && monthIndex < 12 && !Number.isNaN(dayNumber)) {
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `\${monthNames[monthIndex]} \${dayNumber}, \${year}`;
      }
    }
    const parsed = new Date(dateString);
    if (!Number.isNaN(parsed.getTime())) {
      const adjusted = addEightHours(parsed) || parsed;
      return new Intl.DateTimeFormat(undefined, {
        timeZone: MANILA_TIME_ZONE,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      }).format(adjusted);
    }
    return dateString;
  }

  function normalizeInventoryLog(raw) {
    if (!Array.isArray(raw)) {
      return [];
    }

    return raw.map((entry) => {
      const logId = Number(
        entry?.log_id
        ?? entry?.Log_ID
        ?? entry?.order_item_id
        ?? entry?.Order_Item_ID
        ?? 0
      );
      const orderId = Number(
        entry?.order_id
        ?? entry?.Order_ID
        ?? entry?.reference_id
        ?? entry?.Reference_ID
        ?? 0
      );
      const referenceFallback = orderId ? `#${String(orderId).padStart(5, '0')}` : '';
      const referenceLabel = entry?.reference_label
        ?? entry?.Reference_Label
        ?? entry?.order_code
        ?? entry?.Order_Code
        ?? referenceFallback;
      const changeRaw = entry?.change
        ?? entry?.Change
        ?? entry?.change_amount
        ?? entry?.Change_Amount
        ?? 0;
      const parsedChange = parseInt(changeRaw, 10);
      const changeValue = Number.isNaN(parsedChange) ? 0 : parsedChange;
      const createdAt = entry?.created_at
        ?? entry?.Created_At
        ?? entry?.order_date
        ?? entry?.Order_Date
        ?? '';
      const createdAtFormatted = entry?.created_at_formatted
        ?? entry?.Created_At_Formatted
        ?? entry?.order_date_formatted
        ?? entry?.Order_Date_Formatted
        ?? formatDateForDisplay(createdAt);
      const logDateRaw = entry?.log_date
        ?? entry?.Log_Date
        ?? (createdAt ? String(createdAt).split(' ')[0] : '');
      const logDate = sanitizeIsoDate(logDateRaw);

      return {
        id: logId,
        orderId,
        referenceLabel,
        productId: Number(entry?.product_id ?? entry?.Product_ID ?? 0),
        productName: entry?.product_name ?? entry?.Product_Name ?? '',
        change: changeValue,
        createdAt,
        createdAtFormatted,
        logDate,
        customerName: entry?.customer_name ?? entry?.Customer_Name ?? '',
        changeType: entry?.change_type ?? entry?.Change_Type ?? 'Stock Update',
        changeSource: entry?.change_source ?? entry?.Change_Source ?? '',
        note: entry?.note ?? entry?.Note ?? '',
        previousQuantity: parseNullableInteger(entry?.previous_quantity ?? entry?.Previous_Quantity),
        newQuantity: parseNullableInteger(entry?.new_quantity ?? entry?.New_Quantity)
      };
    });
  }

  function normalizeLogDateCounts(raw) {
    const counts = new Map();

    if (!raw) {
      return counts;
    }

    const assignCount = (dateKey, countValue) => {
      if (!dateKey) {
        return;
      }
      const normalizedDate = sanitizeIsoDate(dateKey);
      if (!normalizedDate) {
        return;
      }
      const numericCount = Number.parseInt(countValue, 10);
      if (Number.isNaN(numericCount)) {
        return;
      }
      counts.set(normalizedDate, (counts.get(normalizedDate) || 0) + Math.max(0, numericCount));
    };

    if (Array.isArray(raw)) {
      raw.forEach((entry) => {
        assignCount(
          entry?.report_date
            ?? entry?.Report_Date
            ?? entry?.date
            ?? entry?.Date,
          entry?.change_count
            ?? entry?.Change_Count
            ?? entry?.count
            ?? entry?.Count
            ?? 0,
        );
      });
    } else if (typeof raw === 'object') {
      Object.entries(raw).forEach(([dateKey, countValue]) => {
        assignCount(dateKey, countValue);
      });
    }

    return counts;
  }

  function sanitizeIsoDate(raw) {
    if (typeof raw !== 'string') {
      return '';
    }
    const trimmed = raw.trim();
    const isoMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
    if (isoMatch) {
      const [, year, month, day] = isoMatch;
      return `${year}-${month}-${day}`;
    }
    const parsed = createDateFromISO(trimmed);
    return parsed ? formatDateToISO(parsed) : '';
  }

  function createDateFromISO(value) {
    if (typeof value !== 'string') {
      return null;
    }
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value.trim());
    if (!match) {
      return null;
    }
    const [, yearStr, monthStr, dayStr] = match;
    const year = Number.parseInt(yearStr, 10);
    const monthIndex = Number.parseInt(monthStr, 10) - 1;
    const day = Number.parseInt(dayStr, 10);
    if (Number.isNaN(year) || Number.isNaN(monthIndex) || Number.isNaN(day)) {
      return null;
    }
    const date = new Date(Date.UTC(year, monthIndex, day));
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function formatDateToISO(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function getTodayIso() {
    const manilaNow = addEightHours(new Date()) || new Date();
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: MANILA_TIME_ZONE,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(manilaNow);
  }

  function initializeInventoryCalendar() {
    if (!logCalendarContainer) {
      return;
    }

    const selectedDateIso = sanitizeIsoDate(
      reportDateInput?.value
        ?? logCalendarContainer.dataset.selectedDate
        ?? '',
    );

    const selectedDate = createDateFromISO(selectedDateIso);
    let focusDate = selectedDate;

    if (!focusDate) {
      const sortedDates = Array.from(logDateCountsByDate.keys()).sort();
      if (sortedDates.length) {
        focusDate = createDateFromISO(sortedDates[sortedDates.length - 1]);
      }
    }

    if (!focusDate) {
      const manilaNow = addEightHours(new Date()) || new Date();
      const todayIso = new Intl.DateTimeFormat('en-CA', {
        timeZone: MANILA_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(manilaNow);
      focusDate = createDateFromISO(todayIso) || manilaNow;
    }

    calendarFocusYear = focusDate.getUTCFullYear();
    calendarFocusMonth = focusDate.getUTCMonth();
    logCalendarContainer.dataset.selectedDate = selectedDateIso;

    renderInventoryCalendar();
  }

  function shiftCalendarMonth(delta) {
    if (typeof delta !== 'number' || Number.isNaN(delta)) {
      return;
    }
    if (calendarFocusYear === null || calendarFocusMonth === null) {
      return;
    }
    const target = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth + delta, 1));
    if (Number.isNaN(target.getTime())) {
      return;
    }
    calendarFocusYear = target.getUTCFullYear();
    calendarFocusMonth = target.getUTCMonth();
  }

  function renderInventoryCalendar() {
    if (!logCalendarContainer) {
      return;
    }
    if (calendarFocusYear === null || calendarFocusMonth === null) {
      return;
    }

    const focusDate = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth, 1));
    if (Number.isNaN(focusDate.getTime())) {
      return;
    }

    const monthLabel = new Intl.DateTimeFormat(undefined, {
      timeZone: MANILA_TIME_ZONE,
      month: 'long',
      year: 'numeric',
    }).format(focusDate);

    const selectedDateIso = sanitizeIsoDate(
      reportDateInput?.value
        ?? logCalendarContainer.dataset.selectedDate
        ?? '',
    );
    logCalendarContainer.dataset.selectedDate = selectedDateIso;

    const todayIso = getTodayIso();

    logCalendarContainer.innerHTML = '';

    const header = document.createElement('div');
    header.className = 'calendar-header';

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'calendar-nav calendar-prev';
    prevBtn.setAttribute('aria-label', 'Previous month');
    prevBtn.innerHTML = '&lsaquo;';
    prevBtn.addEventListener('click', () => {
      shiftCalendarMonth(-1);
      renderInventoryCalendar();
    });

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'calendar-nav calendar-next';
    nextBtn.setAttribute('aria-label', 'Next month');
    nextBtn.innerHTML = '&rsaquo;';
    nextBtn.addEventListener('click', () => {
      shiftCalendarMonth(1);
      renderInventoryCalendar();
    });

    const title = document.createElement('div');
    title.className = 'calendar-title';
    title.textContent = monthLabel;

    header.append(prevBtn, title, nextBtn);
    logCalendarContainer.appendChild(header);

    const weekdayRow = document.createElement('div');
    weekdayRow.className = 'calendar-weekdays';
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach((weekday) => {
      const span = document.createElement('span');
      span.textContent = weekday;
      weekdayRow.appendChild(span);
    });
    logCalendarContainer.appendChild(weekdayRow);

    const grid = document.createElement('div');
    grid.className = 'calendar-grid';

    const firstOfMonth = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth, 1));
    const startDay = firstOfMonth.getUTCDay();
    const daysInMonth = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth + 1, 0)).getUTCDate();
    const daysInPrevMonth = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth, 0)).getUTCDate();
    const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;

    for (let index = 0; index < totalCells; index += 1) {
      const dayButton = document.createElement('button');
      dayButton.type = 'button';
      dayButton.className = 'calendar-day';

      let displayDay;
      let cellDate;
      let isOutside = false;

      if (index < startDay) {
        displayDay = daysInPrevMonth - (startDay - index) + 1;
        cellDate = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth - 1, displayDay));
        isOutside = true;
      } else if (index >= startDay + daysInMonth) {
        displayDay = index - (startDay + daysInMonth) + 1;
        cellDate = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth + 1, displayDay));
        isOutside = true;
      } else {
        displayDay = index - startDay + 1;
        cellDate = new Date(Date.UTC(calendarFocusYear, calendarFocusMonth, displayDay));
      }

      const isoValue = formatDateToISO(cellDate);
      if (!isoValue) {
        dayButton.disabled = true;
        grid.appendChild(dayButton);
        continue;
      }

      dayButton.textContent = String(displayDay);
      dayButton.setAttribute(
        'aria-label',
        new Intl.DateTimeFormat(undefined, {
          timeZone: MANILA_TIME_ZONE,
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric',
        }).format(cellDate),
      );

      const changeCount = logDateCountsByDate.get(isoValue) ?? 0;
      if (changeCount > 0) {
        dayButton.classList.add('has-entries');
        const badge = document.createElement('span');
        badge.className = 'calendar-count';
        badge.textContent = String(changeCount);
        dayButton.appendChild(badge);
      }

      if (isoValue === selectedDateIso) {
        dayButton.classList.add('is-selected');
      }

      if (isoValue === todayIso) {
        dayButton.classList.add('is-today');
      }

      if (isOutside) {
        dayButton.classList.add('is-outside');
        dayButton.addEventListener('click', () => {
          handleCalendarDaySelection(isoValue, true);
        });
      } else {
        dayButton.addEventListener('click', () => {
          handleCalendarDaySelection(isoValue, false);
        });
      }

      grid.appendChild(dayButton);
    }

    logCalendarContainer.appendChild(grid);
  }

  function handleCalendarDaySelection(isoValue, adjustFocus) {
    if (!isoValue) {
      return;
    }

    if (adjustFocus) {
      const targetDate = createDateFromISO(isoValue);
      if (targetDate) {
        calendarFocusYear = targetDate.getUTCFullYear();
        calendarFocusMonth = targetDate.getUTCMonth();
        renderInventoryCalendar();
      }
    } else if (logCalendarContainer) {
      logCalendarContainer.dataset.selectedDate = isoValue;
      renderInventoryCalendar();
    }

    if (reportDateInput) {
      reportDateInput.value = isoValue;
    }

    submitReportForm();
  }

  inventoryData = normalizeInventoryData(<?= $inventoryJson; ?>);
  inventoryLogEntries = normalizeInventoryLog(<?= $inventoryLogJson; ?>);
  const selectedLogDate = sanitizeIsoDate(
    reportDateInput?.value
      ?? logCalendarContainer?.dataset.selectedDate
      ?? ''
  );
  if (selectedLogDate) {
    inventoryLogEntries = inventoryLogEntries.filter((entry) => {
      if (entry.logDate) {
        return entry.logDate === selectedLogDate;
      }
      if (entry.createdAt) {
        const entryDate = sanitizeIsoDate(String(entry.createdAt).split(' ')[0]);
        return entryDate === selectedLogDate;
      }
      return false;
    });
  }
  currentLogPage = 1;
  logDateCountsByDate = normalizeLogDateCounts(<?= $inventoryLogDateCountsJson; ?>);
  renderInventoryUI();
  renderInventoryLogTable();
  initializeInventoryCalendar();
});
</script>
<?php
$extraScripts = ob_get_clean();
include 'includes/footer.php';
