<?php
require_once __DIR__ . '/includes/require_super_admin.php';
require_once '../PHP/db_connect.php';

$activePage = 'financial-report';
$pageTitle = "Financial Report - Cindy's Bakeshop";

$lastPaymentDate = null;
$reportRows = [];

$timeRanges = [
    'today' => ['label' => 'Today', 'days' => 1, 'granularity' => 'hour'],
    '7d' => ['label' => 'Last 7 Days', 'days' => 7, 'granularity' => 'day'],
    '30d' => ['label' => 'Last 30 Days', 'days' => 30, 'granularity' => 'day'],
    '90d' => ['label' => 'Last 90 Days', 'days' => 90, 'granularity' => 'day'],
];

$revenueTrendDefaultRange = '7d';
if (!array_key_exists($revenueTrendDefaultRange, $timeRanges)) {
    if (function_exists('array_key_first')) {
        $firstRangeKey = array_key_first($timeRanges);
    } else {
        reset($timeRanges);
        $firstRangeKey = key($timeRanges);
    }
    $revenueTrendDefaultRange = $firstRangeKey ?? 'today';
}

$revenueTrendDefaultLabel = $timeRanges[$revenueTrendDefaultRange]['label'] ?? 'Selected Range';
if (!function_exists('formatPesoAmount')) {
    function formatPesoAmount($value, int $decimals = 2): string
    {
        $numericValue = is_numeric($value) ? (float)$value : 0.0;
        $formatted = number_format($numericValue, $decimals, '.', ',');
        return '₱' . $formatted;
    }
}
$dailyRevenueMap = [];
$hourlyRevenueMap = array_fill(0, 24, 0.0);
$revenueTrendByRange = [];
$maxDays = 1;

foreach ($timeRanges as $config) {
    $days = isset($config['days']) ? (int)$config['days'] : 0;
    if ($days > $maxDays) {
        $maxDays = $days;
    }
}

if ($pdo) {
    $stmtSummary = $pdo->query("SELECT COALESCE(SUM(Amount_Paid),0) AS revenue, COUNT(*) AS transactions, COUNT(DISTINCT Order_ID) AS orders, MAX(Payment_Date) AS last_payment FROM transaction");
    if ($stmtSummary) {
        $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC) ?: [];
        $lastPaymentDate = $summary['last_payment'] ?? null;
    }

    $daysInterval = max($maxDays - 1, 0);

    $trendSql = "
        SELECT
            DATE(Payment_Date) AS payment_date,
            COALESCE(SUM(Amount_Paid), 0) AS revenue
        FROM transaction
        WHERE Payment_Date IS NOT NULL
          AND DATE(Payment_Date) >= DATE_SUB(CURDATE(), INTERVAL $daysInterval DAY)
        GROUP BY payment_date
        ORDER BY payment_date ASC
    ";
    $stmtTrend = $pdo->query($trendSql);
    if ($stmtTrend) {
        while ($row = $stmtTrend->fetch(PDO::FETCH_ASSOC)) {
            $paymentDate = $row['payment_date'] ?? null;
            if ($paymentDate) {
                $dailyRevenueMap[$paymentDate] = (float)($row['revenue'] ?? 0);
            }
        }
    }

    $hourlySql = "
        SELECT
            HOUR(Payment_Date) AS payment_hour,
            COALESCE(SUM(Amount_Paid), 0) AS revenue
        FROM transaction
        WHERE Payment_Date IS NOT NULL
          AND Payment_Date >= CURDATE()
          AND Payment_Date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        GROUP BY payment_hour
        ORDER BY payment_hour ASC
    ";
    $stmtHourly = $pdo->query($hourlySql);
    if ($stmtHourly) {
        while ($row = $stmtHourly->fetch(PDO::FETCH_ASSOC)) {
            $hourValue = isset($row['payment_hour']) ? (int)$row['payment_hour'] : null;
            if ($hourValue !== null && $hourValue >= 0 && $hourValue <= 23) {
                $hourlyRevenueMap[$hourValue] = (float)($row['revenue'] ?? 0);
            }
        }
    }

    $daysForAverage = 30;
    if ($daysForAverage > 0) {
        $totalRevenueWindow = 0.0;
        $todayTimestamp = time();
        for ($i = 0; $i < $daysForAverage; $i++) {
            $timestamp = strtotime("-{$i} day", $todayTimestamp);
            if ($timestamp === false) {
                continue;
            }
            $dateKey = date('Y-m-d', $timestamp);
            $totalRevenueWindow += (float)($dailyRevenueMap[$dateKey] ?? 0);
        }
        $dailyAverageRevenue = $totalRevenueWindow / $daysForAverage;
    }

    $stmtReport = $pdo->query(
        "SELECT t.Transaction_ID, t.Order_ID, t.Payment_Method, t.Payment_Status, t.Payment_Date, t.Amount_Paid, t.Reference_Number,\n" .
        "       o.Order_Date, u.Name AS Customer, COALESCE(SUM(oi.Subtotal),0) AS Product_Total\n" .
        "FROM transaction t\n" .
        "LEFT JOIN `order` o ON t.Order_ID = o.Order_ID\n" .
        "LEFT JOIN user u ON o.User_ID = u.User_ID\n" .
        "LEFT JOIN order_item oi ON oi.Order_ID = o.Order_ID\n" .
        "GROUP BY t.Transaction_ID, t.Order_ID, t.Payment_Method, t.Payment_Status, t.Payment_Date, t.Amount_Paid, t.Reference_Number, o.Order_Date, u.Name\n" .
        "ORDER BY COALESCE(t.Payment_Date, o.Order_Date) DESC, t.Transaction_ID DESC"
    );
    if ($stmtReport) {
        $reportRows = $stmtReport->fetchAll(PDO::FETCH_ASSOC);
    }
}

$financeSnapshotsByRange = [];
$rangeBoundaries = [];
$uniqueOrdersByRange = [];
$methodTotalsByRange = [];
$statusTotalsByRange = [];
$dailySnapshotsByDate = [];
$dailyUniqueOrdersByDate = [];
$dailyMethodTotalsByDate = [];
$dailyStatusTotalsByDate = [];
$dailyHourlyTotalsByDate = [];
$todayStart = strtotime('today');
$latestPaymentTimestamp = null;
if ($todayStart === false) {
    $todayStart = strtotime(date('Y-m-d')) ?: time();
}

foreach ($timeRanges as $rangeKey => $config) {
    $rangeLabel = isset($config['label']) ? (string)$config['label'] : 'Selected Range';
    if ($rangeLabel === '') {
        $rangeLabel = 'Selected Range';
    }
    $days = isset($config['days']) ? (int)$config['days'] : 0;
    $granularity = $config['granularity'] ?? 'day';
    $daysForAverage = $days > 0 ? $days : 1;
    $rangeStart = null;
    if ($granularity === 'hour') {
        $rangeStart = $todayStart;
        $daysForAverage = 1;
    } elseif ($daysForAverage > 0) {
        $offsetDays = $daysForAverage - 1;
        $rangeStart = strtotime('-' . $offsetDays . ' day', $todayStart);
    }
    if ($rangeStart === false) {
        $rangeStart = null;
    }
    if ($daysForAverage <= 0) {
        $daysForAverage = 1;
    }

    $financeSnapshotsByRange[$rangeKey] = [
        'label' => $rangeLabel,
        'totalRevenue' => 0.0,
        'transactionCount' => 0,
        'uniqueOrders' => 0,
        'averageOrderValue' => 0.0,
        'dailyAverageRevenue' => 0.0,
        'methods' => [],
        'statuses' => [],
    ];
    $rangeBoundaries[$rangeKey] = [
        'start' => $rangeStart,
        'days' => $daysForAverage,
    ];
    $uniqueOrdersByRange[$rangeKey] = [];
    $methodTotalsByRange[$rangeKey] = [];
    $statusTotalsByRange[$rangeKey] = [];
}

foreach ($reportRows as $row) {
    $dateForFilter = $row['Payment_Date'] ?? null;
    if (!$dateForFilter) {
        $dateForFilter = $row['Order_Date'] ?? null;
    }
    $timestamp = $dateForFilter ? strtotime($dateForFilter) : false;
    if ($timestamp === false || $timestamp === null) {
        continue;
    }

    $amountPaid = (float)($row['Amount_Paid'] ?? 0);
    $orderId = $row['Order_ID'] ?? null;
    $methodNameRaw = isset($row['Payment_Method']) ? trim((string)$row['Payment_Method']) : '';
    $statusNameRaw = isset($row['Payment_Status']) ? trim((string)$row['Payment_Status']) : '';
    $methodName = $methodNameRaw !== '' ? $methodNameRaw : 'Unknown';
    $statusName = $statusNameRaw !== '' ? $statusNameRaw : 'Unknown';

    $paymentTimestamp = null;
    if (!empty($row['Payment_Date'])) {
        $paymentTimestamp = strtotime($row['Payment_Date']);
        if ($paymentTimestamp !== false && ($latestPaymentTimestamp === null || $paymentTimestamp > $latestPaymentTimestamp)) {
            $latestPaymentTimestamp = $paymentTimestamp;
        }
    }

    $dateKey = $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    if ($dateKey !== null) {
        if (!isset($dailySnapshotsByDate[$dateKey])) {
            $dailySnapshotsByDate[$dateKey] = [
                'totalRevenue' => 0.0,
                'transactionCount' => 0,
            ];
            $dailyUniqueOrdersByDate[$dateKey] = [];
            $dailyMethodTotalsByDate[$dateKey] = [];
            $dailyStatusTotalsByDate[$dateKey] = [];
            $dailyHourlyTotalsByDate[$dateKey] = array_fill(0, 24, 0.0);
        }

        $dailySnapshotsByDate[$dateKey]['totalRevenue'] += $amountPaid;
        $dailySnapshotsByDate[$dateKey]['transactionCount']++;
        if ($orderId) {
            $dailyUniqueOrdersByDate[$dateKey][$orderId] = true;
        }

        if (!isset($dailyMethodTotalsByDate[$dateKey][$methodName])) {
            $dailyMethodTotalsByDate[$dateKey][$methodName] = 0.0;
        }
        $dailyMethodTotalsByDate[$dateKey][$methodName] += $amountPaid;

        if (!isset($dailyStatusTotalsByDate[$dateKey][$statusName])) {
            $dailyStatusTotalsByDate[$dateKey][$statusName] = 0.0;
        }
        $dailyStatusTotalsByDate[$dateKey][$statusName] += $amountPaid;

        if ($paymentTimestamp !== null && $paymentTimestamp !== false) {
            $hourIndex = (int)date('G', $paymentTimestamp);
            if ($hourIndex >= 0 && $hourIndex <= 23) {
                $dailyHourlyTotalsByDate[$dateKey][$hourIndex] += $amountPaid;
            }
        }
    }

    foreach ($rangeBoundaries as $rangeKey => $boundary) {
        $rangeStart = $boundary['start'];
        if ($rangeStart !== null && $timestamp < $rangeStart) {
            continue;
        }

        $financeSnapshotsByRange[$rangeKey]['totalRevenue'] += $amountPaid;
        $financeSnapshotsByRange[$rangeKey]['transactionCount']++;
        if ($orderId) {
            $uniqueOrdersByRange[$rangeKey][$orderId] = true;
        }

        if (!isset($methodTotalsByRange[$rangeKey][$methodName])) {
            $methodTotalsByRange[$rangeKey][$methodName] = 0.0;
        }
        $methodTotalsByRange[$rangeKey][$methodName] += $amountPaid;

        if (!isset($statusTotalsByRange[$rangeKey][$statusName])) {
            $statusTotalsByRange[$rangeKey][$statusName] = 0.0;
        }
        $statusTotalsByRange[$rangeKey][$statusName] += $amountPaid;
    }
}

foreach ($financeSnapshotsByRange as $rangeKey => &$snapshot) {
    $uniqueOrders = isset($uniqueOrdersByRange[$rangeKey]) ? count($uniqueOrdersByRange[$rangeKey]) : 0;
    $snapshot['uniqueOrders'] = $uniqueOrders;
    $snapshot['averageOrderValue'] = $uniqueOrders > 0 ? $snapshot['totalRevenue'] / $uniqueOrders : 0.0;

    $daysForAverage = $rangeBoundaries[$rangeKey]['days'] ?? 1;
    if ($daysForAverage <= 0) {
        $daysForAverage = 1;
    }
    $snapshot['dailyAverageRevenue'] = $daysForAverage > 0 ? $snapshot['totalRevenue'] / $daysForAverage : 0.0;

    $methods = $methodTotalsByRange[$rangeKey] ?? [];
    arsort($methods);
    $snapshot['methods'] = [];
    foreach ($methods as $methodName => $methodTotal) {
        $snapshot['methods'][] = [
            'method' => $methodName,
            'total' => $methodTotal,
        ];
    }

    $statuses = $statusTotalsByRange[$rangeKey] ?? [];
    arsort($statuses);
    $snapshot['statuses'] = [];
    foreach ($statuses as $statusName => $statusTotal) {
        $snapshot['statuses'][] = [
            'status' => $statusName,
            'total' => $statusTotal,
        ];
    }
}
unset($snapshot);

$dailySnapshotsOutput = [];
$dailyTrendByDateOutput = [];
$specificDayKeys = [];

if (!empty($dailySnapshotsByDate)) {
    ksort($dailySnapshotsByDate);
}

foreach ($dailySnapshotsByDate as $dateKey => $dailyTotals) {
    if (!is_string($dateKey) || $dateKey === '') {
        continue;
    }

    $uniqueOrdersForDay = isset($dailyUniqueOrdersByDate[$dateKey]) ? count($dailyUniqueOrdersByDate[$dateKey]) : 0;
    $totalRevenueForDay = (float)($dailyTotals['totalRevenue'] ?? 0.0);
    $transactionCountForDay = (int)($dailyTotals['transactionCount'] ?? 0);

    $methodsForDay = $dailyMethodTotalsByDate[$dateKey] ?? [];
    arsort($methodsForDay);
    $methodList = [];
    foreach ($methodsForDay as $methodName => $methodTotal) {
        $methodList[] = [
            'method' => (string)$methodName,
            'total' => (float)$methodTotal,
        ];
    }

    $statusesForDay = $dailyStatusTotalsByDate[$dateKey] ?? [];
    arsort($statusesForDay);
    $statusList = [];
    foreach ($statusesForDay as $statusName => $statusTotal) {
        $statusList[] = [
            'status' => (string)$statusName,
            'total' => (float)$statusTotal,
        ];
    }

    $dateTimestamp = strtotime($dateKey);
    if ($dateTimestamp !== false) {
        $formattedLabel = date('F j, Y', $dateTimestamp);
    } else {
        $formattedLabel = $dateKey;
    }

    $dailySnapshotsOutput[$dateKey] = [
        'label' => $formattedLabel,
        'totalRevenue' => $totalRevenueForDay,
        'transactionCount' => $transactionCountForDay,
        'uniqueOrders' => $uniqueOrdersForDay,
        'averageOrderValue' => $uniqueOrdersForDay > 0 ? $totalRevenueForDay / $uniqueOrdersForDay : 0.0,
        'dailyAverageRevenue' => $totalRevenueForDay,
        'methods' => $methodList,
        'statuses' => $statusList,
    ];

    $hourlyTotals = $dailyHourlyTotalsByDate[$dateKey] ?? array_fill(0, 24, 0.0);
    $hourLabels = [];
    $hourValues = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hourLabels[] = date('g A', mktime($hour, 0, 0));
        $hourValues[] = (int)round($hourlyTotals[$hour] ?? 0);
    }

    $dailyTrendByDateOutput[$dateKey] = [
        'labels' => $hourLabels,
        'values' => $hourValues,
        'rangeLabel' => $formattedLabel . ' (hourly)',
    ];

    $specificDayKeys[] = $dateKey;
}

sort($specificDayKeys);

if ($latestPaymentTimestamp !== null) {
    $existingLastPaymentTimestamp = $lastPaymentDate ? strtotime($lastPaymentDate) : false;
    if ($existingLastPaymentTimestamp === false || $latestPaymentTimestamp > $existingLastPaymentTimestamp) {
        $lastPaymentDate = date('Y-m-d H:i:s', $latestPaymentTimestamp);
    }
}

$defaultSnapshot = isset($financeSnapshotsByRange[$revenueTrendDefaultRange])
    ? $financeSnapshotsByRange[$revenueTrendDefaultRange]
    : null;
if ($defaultSnapshot === null) {
    if (function_exists('array_key_first')) {
        $firstSnapshotKey = array_key_first($financeSnapshotsByRange);
    } else {
        reset($financeSnapshotsByRange);
        $firstSnapshotKey = key($financeSnapshotsByRange);
    }
    if ($firstSnapshotKey !== null && isset($financeSnapshotsByRange[$firstSnapshotKey])) {
        $defaultSnapshot = $financeSnapshotsByRange[$firstSnapshotKey];
    }
}

$defaultSnapshotLabel = $defaultSnapshot['label'] ?? $revenueTrendDefaultLabel;
if ($defaultSnapshotLabel === '' || $defaultSnapshotLabel === null) {
    $defaultSnapshotLabel = 'Selected Range';
}

if ($defaultSnapshot === null) {
    $defaultSnapshot = [
        'label' => $defaultSnapshotLabel,
        'totalRevenue' => 0.0,
        'transactionCount' => 0,
        'uniqueOrders' => 0,
        'averageOrderValue' => 0.0,
        'dailyAverageRevenue' => 0.0,
        'methods' => [],
        'statuses' => [],
    ];
}

$defaultTotalRevenue = (float)($defaultSnapshot['totalRevenue'] ?? 0.0);
$defaultTransactions = (int)($defaultSnapshot['transactionCount'] ?? 0);
$defaultUniqueOrders = (int)($defaultSnapshot['uniqueOrders'] ?? 0);
$defaultDailyAverage = (float)($defaultSnapshot['dailyAverageRevenue'] ?? 0.0);
$defaultAverageOrderValue = (float)($defaultSnapshot['averageOrderValue'] ?? 0.0);

$defaultRangeContext = strtolower($defaultSnapshotLabel);
if ($defaultRangeContext === '') {
    $defaultRangeContext = 'selected range';
}

$defaultTotalRevenueMeta = 'Across ' . number_format($defaultTransactions) . ' payments (' . $defaultRangeContext . ')';
$defaultDailyAverageMeta = 'Average per day (' . $defaultRangeContext . ')';
$defaultAverageOrderMeta = 'Based on ' . number_format($defaultUniqueOrders) . ' orders';
$defaultMethodCaption = 'Showing ' . $defaultSnapshotLabel;

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

$financeSnapshotsJson = json_encode($financeSnapshotsByRange, $jsonFlags);
if ($financeSnapshotsJson === false) {
    $financeSnapshotsJson = '{}';
}

$dailySnapshotsJson = json_encode($dailySnapshotsOutput, $jsonFlags);
if ($dailySnapshotsJson === false) {
    $dailySnapshotsJson = '{}';
}

$dailyTrendByDateJson = json_encode($dailyTrendByDateOutput, $jsonFlags);
if ($dailyTrendByDateJson === false) {
    $dailyTrendByDateJson = '{}';
}

$specificDayKeysJson = json_encode($specificDayKeys, $jsonFlags);
if ($specificDayKeysJson === false) {
    $specificDayKeysJson = '[]';
}

$specificDayMin = '';
$specificDayMax = '';
if (!empty($specificDayKeys)) {
    $specificDayMin = min($specificDayKeys);
    $specificDayMax = max($specificDayKeys);
} else {
    $specificDayMax = date('Y-m-d');
}

$currentTimestamp = time();
foreach ($timeRanges as $rangeKey => $config) {
    $granularity = $config['granularity'] ?? 'day';
    if ($granularity === 'hour') {
        $labels = [];
        $values = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = date('g A', mktime($hour, 0, 0));
            $values[] = (int)round($hourlyRevenueMap[$hour] ?? 0);
        }
        $revenueTrendByRange[$rangeKey] = [
            'labels' => $labels,
            'values' => $values,
            'rangeLabel' => ($config['label'] ?? 'Today') . ' (hourly)',
        ];
        continue;
    }

    $days = isset($config['days']) ? (int)$config['days'] : 0;
    if ($days <= 0) {
        continue;
    }
    $labels = [];
    $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $timestamp = strtotime("-{$i} day", $currentTimestamp);
        if ($timestamp === false) {
            continue;
        }
        $dateKey = date('Y-m-d', $timestamp);
        $labels[] = $days <= 7 ? date('D', $timestamp) : date('M d', $timestamp);
        $values[] = (int)round($dailyRevenueMap[$dateKey] ?? 0);
    }
    if (!empty($labels)) {
        $revenueTrendByRange[$rangeKey] = [
            'labels' => $labels,
            'values' => $values,
            'rangeLabel' => $config['label'] ?? 'Selected Range',
        ];
    }
}

if (empty($revenueTrendByRange) && !empty($timeRanges)) {
    $revenueTrendByRange[$revenueTrendDefaultRange] = [
        'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'values' => array_fill(0, 7, 0),
        'rangeLabel' => $revenueTrendDefaultLabel,
    ];
}

$revenueTrendDataJson = json_encode($revenueTrendByRange, $jsonFlags);
if ($revenueTrendDataJson === false) {
    $revenueTrendDataJson = '{}';
}

$revenueTrendDefaultRangeJson = json_encode($revenueTrendDefaultRange, $jsonFlags);
if ($revenueTrendDefaultRangeJson === false) {
    $revenueTrendDefaultRangeJson = 'null';
}

$lastPaymentDisplay = 'No payments recorded yet';
if (!empty($lastPaymentDate)) {
    $formattedLastPayment = formatAdminDateTime($lastPaymentDate, 'F j, Y');
    if ($formattedLastPayment !== '') {
        $lastPaymentDisplay = $formattedLastPayment;
    } else {
        $lastPaymentDisplay = $lastPaymentDate;
    }
}

$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Financial Report</h1>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:14px;color:#7f8c8d;">Last payment: <?= htmlspecialchars($lastPaymentDisplay); ?></span>
    </div>
  </div>

  <section class="stats-grid columns-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <div class="stat-card">
      <h3>Total Revenue</h3>
      <div class="value" id="statTotalRevenueValue"><?= htmlspecialchars(formatPesoAmount($defaultTotalRevenue)); ?></div>
      <div class="meta" id="statTotalRevenueMeta"><?= htmlspecialchars($defaultTotalRevenueMeta); ?></div>
    </div>
    <div class="stat-card">
      <h3>Daily Avg Revenue</h3>
      <div class="value" id="statDailyAverageValue"><?= htmlspecialchars(formatPesoAmount($defaultDailyAverage)); ?></div>
      <div class="meta" id="statDailyAverageMeta"><?= htmlspecialchars($defaultDailyAverageMeta); ?></div>
    </div>
    <div class="stat-card">
      <h3>Average Order Value</h3>
      <div class="value" id="statAverageOrderValue"><?= htmlspecialchars(formatPesoAmount($defaultAverageOrderValue)); ?></div>
      <div class="meta" id="statAverageOrderMeta"><?= htmlspecialchars($defaultAverageOrderMeta); ?></div>
    </div>
  </section>

  <div class="stats-grid columns-4" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));">
    <div class="card">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px;">
        <div>
          <h2 style="font-size:18px;margin:0;">Sales Trend</h2>
          <p id="revenueTrendRangeCaption" style="margin:4px 0 0;font-size:13px;color:#7f8c8d;">
            <?= htmlspecialchars($revenueTrendDefaultLabel); ?>
          </p>
        </div>
        <select id="revenueTrendRange" aria-label="Change sales trend range" style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;min-width:180px;">
          <?php foreach ($timeRanges as $rangeKey => $rangeConfig): ?>
            <option value="<?= htmlspecialchars($rangeKey); ?>" <?= $rangeKey === $revenueTrendDefaultRange ? 'selected' : ''; ?>>
              <?= htmlspecialchars($rangeConfig['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <canvas id="revenueTrendChart" height="220"></canvas>
    </div>
    <div class="card">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px;">
        <h2 style="font-size:18px;margin:0;">Payment Methods</h2>
        <p id="methodRangeCaption" style="margin:4px 0 0;font-size:13px;color:#7f8c8d;">
          <?= htmlspecialchars($defaultMethodCaption); ?>
        </p>
      </div>
      <canvas id="methodChart" height="220"></canvas>
    </div>
  </div>

  <div class="card" style="margin-top:24px;">
    <div class="table-actions">
      <input
        type="text"
        id="financeSearch"
        placeholder="🔍 Search payment record..."
        aria-label="Search finance transactions"
      >
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <input
            type="date"
            id="financeDayFilter"
            aria-label="Filter finance records by specific day"
            style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;min-width:165px;"
            value=""
            <?php if ($specificDayMin !== ''): ?>min="<?= htmlspecialchars($specificDayMin); ?>"<?php endif; ?>
            <?php if ($specificDayMax !== ''): ?>max="<?= htmlspecialchars($specificDayMax); ?>"<?php endif; ?>
          >
          <button type="button" class="btn btn-secondary" id="clearFinanceDay">Clear Day</button>
        </div>
        <select
          id="financeTimeRange"
          aria-label="Filter finance records by time period"
          style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;min-width:180px;"
        >
          <option value="all" data-days="0" selected>All Time</option>
          <?php foreach ($timeRanges as $rangeKey => $rangeConfig): ?>
            <option value="<?= htmlspecialchars($rangeKey); ?>" data-days="<?= (int)($rangeConfig['days'] ?? 0); ?>">
              <?= htmlspecialchars($rangeConfig['label']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" id="exportFinance">Export Finance PDF</button>
      </div>
    </div>
    <div class="table-responsive">
      <?php if (empty($reportRows)): ?>
        <p class="table-empty">No payment records available.</p>
      <?php else: ?>
        <table id="financialTable">
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Order ID</th>
              <th>Order Date</th>
              <th>Payment Date</th>
              <th>Customer</th>
              <th>Product Total</th>
              <th>Amount Paid</th>
              <th>Method</th>
              <th>Status</th>
              <th>Reference</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reportRows as $row): ?>
              <?php
                $rawPaymentDate = $row['Payment_Date'] ?? null;
                $rawOrderDate = $row['Order_Date'] ?? null;
                $transactionDateForFilter = $rawPaymentDate ?: $rawOrderDate;
                $transactionTimestamp = $transactionDateForFilter ? strtotime($transactionDateForFilter) : false;
                $transactionTimestampAttr = $transactionTimestamp !== false ? (string)$transactionTimestamp : '';
                $transactionDayAttr = '';
                if ($transactionTimestamp !== false && $transactionTimestamp !== null) {
                  $transactionDayAttr = date('Y-m-d', $transactionTimestamp);
                }
                $formattedPaymentDate = formatAdminDateTime($rawPaymentDate, 'F j, Y', '—');
                $formattedOrderDate = formatAdminDateTime($rawOrderDate, 'F j, Y', '—');

                $productTotalValue = (float)($row['Product_Total'] ?? 0);
                $amountPaidValue = (float)($row['Amount_Paid'] ?? 0);
                $productTotalAttr = number_format($productTotalValue, 2, '.', '');
                $amountPaidAttr = number_format($amountPaidValue, 2, '.', '');
              ?>
              <tr
                data-transaction-ts="<?= htmlspecialchars($transactionTimestampAttr); ?>"
                data-transaction-day="<?= htmlspecialchars($transactionDayAttr); ?>"
                data-product-total="<?= htmlspecialchars($productTotalAttr); ?>"
                data-amount-paid="<?= htmlspecialchars($amountPaidAttr); ?>"
              >
                <td>#<?= str_pad((int)$row['Transaction_ID'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?= $row['Order_ID'] ? '#' . str_pad((int)$row['Order_ID'], 5, '0', STR_PAD_LEFT) : '—'; ?></td>
                <td><?= htmlspecialchars($formattedOrderDate); ?></td>
                <td><?= htmlspecialchars($formattedPaymentDate); ?></td>
                <td><?= htmlspecialchars($row['Customer'] ?? 'Walk-in'); ?></td>
                <td class="currency-cell"><?= htmlspecialchars(formatPesoAmount($row['Product_Total'] ?? 0)); ?></td>
                <td class="currency-cell"><?= htmlspecialchars(formatPesoAmount($row['Amount_Paid'] ?? 0)); ?></td>
                <td><?= htmlspecialchars($row['Payment_Method'] ?? 'Unknown'); ?></td>
                <td>
                  <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $row['Payment_Status'] ?? 'unknown')); ?>">
                    <?= htmlspecialchars($row['Payment_Status'] ?? 'Unknown'); ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($row['Reference_Number'] ?? '—'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
ob_start();
?>
<script>
  const revenueTrendDataByRange = <?= $revenueTrendDataJson; ?>;
  const revenueTrendDefaultRange = <?= $revenueTrendDefaultRangeJson; ?>;
  const financeSnapshotsByRange = <?= $financeSnapshotsJson; ?>;
  const dailySnapshotsByDate = <?= $dailySnapshotsJson; ?>;
  const dailyTrendByDate = <?= $dailyTrendByDateJson; ?>;
  const availableSpecificDayKeys = <?= $specificDayKeysJson; ?>;
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

  const trendRangeOptions = (revenueTrendDataByRange && typeof revenueTrendDataByRange === 'object' && !Array.isArray(revenueTrendDataByRange))
    ? revenueTrendDataByRange
    : {};
  const trendRangeKeys = Object.keys(trendRangeOptions);
  const snapshotKeys = (financeSnapshotsByRange && typeof financeSnapshotsByRange === 'object' && !Array.isArray(financeSnapshotsByRange))
    ? Object.keys(financeSnapshotsByRange)
    : [];
  let defaultRangeKey = null;
  if (typeof revenueTrendDefaultRange === 'string' && Object.prototype.hasOwnProperty.call(trendRangeOptions, revenueTrendDefaultRange)) {
    defaultRangeKey = revenueTrendDefaultRange;
  } else if (trendRangeKeys.length > 0) {
    defaultRangeKey = trendRangeKeys[0];
  } else if (snapshotKeys.length > 0) {
    defaultRangeKey = snapshotKeys[0];
  }

  const specificDayKeys = Array.isArray(availableSpecificDayKeys) ? availableSpecificDayKeys.slice() : [];
  let activeRangeKey = defaultRangeKey;
  let activeSpecificDay = null;

  const revenueTrendCanvas = document.getElementById('revenueTrendChart');
  const revenueTrendRangeSelect = document.getElementById('revenueTrendRange');
  const revenueTrendRangeCaption = document.getElementById('revenueTrendRangeCaption');
  const methodChartCanvas = document.getElementById('methodChart');
  const methodRangeCaption = document.getElementById('methodRangeCaption');
  const statusChartCanvas = document.getElementById('statusChart');
  const statTotalRevenueValue = document.getElementById('statTotalRevenueValue');
  const statTotalRevenueMeta = document.getElementById('statTotalRevenueMeta');
  const statDailyAverageValue = document.getElementById('statDailyAverageValue');
  const statDailyAverageMeta = document.getElementById('statDailyAverageMeta');
  const statAverageOrderValue = document.getElementById('statAverageOrderValue');
  const statAverageOrderMeta = document.getElementById('statAverageOrderMeta');

  let methodChart = null;
  let statusChart = null;
  let revenueTrendChart = null;
  let applyRangeSelection = () => {};
  let applySpecificDaySelection = () => {};

  const fallbackSnapshot = {
    label: 'Selected Range',
    totalRevenue: 0,
    transactionCount: 0,
    uniqueOrders: 0,
    averageOrderValue: 0,
    dailyAverageRevenue: 0,
    methods: [],
    statuses: [],
  };

  function buildEmptySnapshot(labelText) {
    const resolvedLabel = typeof labelText === 'string' && labelText.trim() ? labelText.trim() : 'Selected Range';
    return {
      label: resolvedLabel,
      totalRevenue: 0,
      transactionCount: 0,
      uniqueOrders: 0,
      averageOrderValue: 0,
      dailyAverageRevenue: 0,
      methods: [],
      statuses: [],
    };
  }

  function getSnapshotForRange(rangeKey) {
    if (financeSnapshotsByRange && typeof financeSnapshotsByRange === 'object' && !Array.isArray(financeSnapshotsByRange)) {
      if (rangeKey && Object.prototype.hasOwnProperty.call(financeSnapshotsByRange, rangeKey)) {
        return financeSnapshotsByRange[rangeKey];
      }
      const availableKeys = Object.keys(financeSnapshotsByRange);
      if (availableKeys.length > 0) {
        return financeSnapshotsByRange[availableKeys[0]];
      }
    }
    return fallbackSnapshot;
  }

  function getSnapshotForSpecificDay(dayKey) {
    if (!dayKey || !dailySnapshotsByDate || typeof dailySnapshotsByDate !== 'object' || Array.isArray(dailySnapshotsByDate)) {
      return null;
    }
    if (Object.prototype.hasOwnProperty.call(dailySnapshotsByDate, dayKey)) {
      return dailySnapshotsByDate[dayKey];
    }
    return null;
  }

  function formatSpecificDayLabel(dayKey) {
    if (typeof dayKey !== 'string' || !dayKey) {
      return 'Selected Day';
    }
    try {
      const date = new Date(`${dayKey}T00:00:00`);
      if (!Number.isNaN(date.getTime())) {
        const adjusted = addEightHours(date) || date;
        return adjusted.toLocaleDateString(undefined, {
          timeZone: MANILA_TIME_ZONE,
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        });
      }
    } catch (error) {
      console.warn('Unable to format specific day label:', error);
    }
    return dayKey;
  }

  function formatCurrency(value) {
    const numericValue = Number.parseFloat(value);
    if (!Number.isFinite(numericValue)) {
      return '₱0.00';
    }
    const safeValue = Math.max(0, numericValue);
    return `₱${safeValue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function formatRangeContext(label) {
    if (typeof label !== 'string' || !label.trim()) {
      return 'selected range';
    }
    return label.trim().toLowerCase();
  }

  function updateFinanceSummary(snapshot) {
    const activeSnapshot = snapshot || fallbackSnapshot;
    if (statTotalRevenueValue) {
      statTotalRevenueValue.textContent = formatCurrency(activeSnapshot.totalRevenue);
    }
    if (statTotalRevenueMeta) {
      const paymentCount = Number(activeSnapshot.transactionCount) || 0;
      statTotalRevenueMeta.textContent = `Across ${paymentCount.toLocaleString()} payments (${formatRangeContext(activeSnapshot.label)})`;
    }
    if (statDailyAverageValue) {
      statDailyAverageValue.textContent = formatCurrency(activeSnapshot.dailyAverageRevenue);
    }
    if (statDailyAverageMeta) {
      statDailyAverageMeta.textContent = `Average per day (${formatRangeContext(activeSnapshot.label)})`;
    }
    if (statAverageOrderValue) {
      statAverageOrderValue.textContent = formatCurrency(activeSnapshot.averageOrderValue);
    }
    if (statAverageOrderMeta) {
      const orderCount = Number(activeSnapshot.uniqueOrders) || 0;
      statAverageOrderMeta.textContent = `Based on ${orderCount.toLocaleString()} orders`;
    }
  }

  function buildMethodDataset(snapshot) {
    const methods = snapshot && Array.isArray(snapshot.methods) ? snapshot.methods : [];
    const labels = [];
    const values = [];
    methods.forEach(entry => {
      const methodName = typeof entry.method === 'string' && entry.method.trim() ? entry.method : 'Unknown';
      const numericValue = Number.parseFloat(entry.total);
      labels.push(methodName);
      values.push(Number.isFinite(numericValue) ? Math.round(numericValue) : 0);
    });
    if (!labels.length) {
      labels.push('No Data');
      values.push(0);
    }
    return { labels, values };
  }

  function buildStatusDataset(snapshot) {
    const statuses = snapshot && Array.isArray(snapshot.statuses) ? snapshot.statuses : [];
    const labels = [];
    const values = [];
    statuses.forEach(entry => {
      const statusName = typeof entry.status === 'string' && entry.status.trim() ? entry.status : 'Unknown';
      const numericValue = Number.parseFloat(entry.total);
      labels.push(statusName);
      values.push(Number.isFinite(numericValue) ? Math.round(numericValue) : 0);
    });
    if (!labels.length) {
      labels.push('No Data');
      values.push(0);
    }
    return { labels, values };
  }

  function updateMethodChart(snapshot) {
    if (!methodChartCanvas || typeof Chart === 'undefined') {
      return;
    }
    const dataset = buildMethodDataset(snapshot);
    if (!methodChart) {
      methodChart = new Chart(methodChartCanvas, {
        type: 'doughnut',
        data: {
          labels: dataset.labels,
          datasets: [{
            data: dataset.values,
            backgroundColor: ['#3498db', '#9b59b6', '#1abc9c', '#f1c40f', '#e74c3c']
          }]
        },
        options: {
          plugins: { legend: { position: 'bottom' } }
        }
      });
    } else {
      methodChart.data.labels = dataset.labels;
      methodChart.data.datasets[0].data = dataset.values;
      methodChart.update();
    }
    if (methodRangeCaption) {
      const label = snapshot && snapshot.label ? snapshot.label : 'Selected Range';
      methodRangeCaption.textContent = `Showing ${label}`;
    }
  }

  function updateStatusChart(snapshot) {
    if (!statusChartCanvas || typeof Chart === 'undefined') {
      return;
    }
    const dataset = buildStatusDataset(snapshot);
    if (!statusChart) {
      statusChart = new Chart(statusChartCanvas, {
        type: 'bar',
        data: {
          labels: dataset.labels,
          datasets: [{
            label: 'Revenue (₱)',
            data: dataset.values,
            backgroundColor: '#2ecc71'
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: value => `₱${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
              }
            }
          }
        }
      });
    } else {
      statusChart.data.labels = dataset.labels;
      statusChart.data.datasets[0].data = dataset.values;
      statusChart.update();
    }
  }

  function applyFinanceSnapshot(rangeKey, snapshotOverride) {
    const snapshot = snapshotOverride || getSnapshotForRange(rangeKey);
    updateFinanceSummary(snapshot);
    updateMethodChart(snapshot);
    updateStatusChart(snapshot);
  }

  if (revenueTrendCanvas && typeof Chart !== 'undefined') {
    const ctx = revenueTrendCanvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, revenueTrendCanvas.height || 400);
    gradient.addColorStop(0, 'rgba(230, 126, 34, 0.4)');
    gradient.addColorStop(1, 'rgba(230, 126, 34, 0)');

    const fallbackLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    const fallbackValues = new Array(fallbackLabels.length).fill(0);
    const fallbackHourlyLabels = Array.from({ length: 24 }, (_, hour) => {
      const baseDate = new Date(Date.UTC(1970, 0, 1, hour));
      return baseDate.toLocaleTimeString(undefined, { hour: 'numeric', hour12: true, timeZone: MANILA_TIME_ZONE });
    });
    const fallbackHourlyValues = new Array(fallbackHourlyLabels.length).fill(0);

    const getPointStyling = labelCount => {
      const isDense = labelCount > 31;
      return {
        radius: isDense ? 3 : 6,
        hoverRadius: isDense ? 5 : 8,
      };
    };

    const sanitizeValues = (labels, rawValues) => labels.map((_, index) => {
      const value = rawValues[index] ?? 0;
      const numericValue = Number.parseFloat(value);
      return Number.isFinite(numericValue) ? Math.round(numericValue) : 0;
    });

    const getDatasetForRange = rangeKey => {
      const dataset = rangeKey && Object.prototype.hasOwnProperty.call(trendRangeOptions, rangeKey) ? trendRangeOptions[rangeKey] : null;
      const labels = dataset && Array.isArray(dataset.labels) ? dataset.labels.slice() : fallbackLabels.slice();
      const rawValues = dataset && Array.isArray(dataset.values) ? dataset.values.slice() : fallbackValues.slice();
      const sanitizedValues = sanitizeValues(labels, rawValues);
      const rangeLabel = dataset && typeof dataset.rangeLabel === 'string' ? dataset.rangeLabel : 'No revenue recorded yet';
      return { labels, values: sanitizedValues, rangeLabel };
    };

    const getDatasetForSpecificDay = dayKey => {
      const dataset = dayKey && dailyTrendByDate && typeof dailyTrendByDate === 'object' && !Array.isArray(dailyTrendByDate)
        && Object.prototype.hasOwnProperty.call(dailyTrendByDate, dayKey)
        ? dailyTrendByDate[dayKey]
        : null;
      const labels = dataset && Array.isArray(dataset.labels) ? dataset.labels.slice() : fallbackHourlyLabels.slice();
      const rawValues = dataset && Array.isArray(dataset.values) ? dataset.values.slice() : fallbackHourlyValues.slice();
      const sanitizedValues = sanitizeValues(labels, rawValues);
      const defaultLabel = `${formatSpecificDayLabel(dayKey)} (hourly)`;
      const rangeLabel = dataset && typeof dataset.rangeLabel === 'string' ? dataset.rangeLabel : defaultLabel;
      return { labels, values: sanitizedValues, rangeLabel };
    };

    const setRevenueTrendDataset = dataset => {
      if (!revenueTrendChart) {
        return;
      }
      const safeDataset = dataset || { labels: fallbackLabels.slice(), values: fallbackValues.slice(), rangeLabel: 'No revenue recorded yet' };
      const pointStyle = getPointStyling(safeDataset.labels.length);
      revenueTrendChart.data.labels = safeDataset.labels;
      revenueTrendChart.data.datasets[0].data = safeDataset.values;
      revenueTrendChart.data.datasets[0].pointRadius = pointStyle.radius;
      revenueTrendChart.data.datasets[0].pointHoverRadius = pointStyle.hoverRadius;
      revenueTrendChart.update();
      if (revenueTrendRangeCaption) {
        revenueTrendRangeCaption.textContent = safeDataset.rangeLabel;
      }
    };

    const resolvedDefaultKey = defaultRangeKey || (trendRangeKeys.length > 0 ? trendRangeKeys[0] : null);
    const initialDataset = getDatasetForRange(resolvedDefaultKey);
    const initialPointStyle = getPointStyling(initialDataset.labels.length);

    revenueTrendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: initialDataset.labels,
        datasets: [{
          label: 'Revenue (₱)',
          data: initialDataset.values,
          borderColor: '#e67e22',
          borderWidth: 3,
          backgroundColor: gradient,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#e67e22',
          pointRadius: initialPointStyle.radius,
          pointHoverRadius: initialPointStyle.hoverRadius,
          tension: 0.4,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => {
                const value = context.parsed.y ?? 0;
                return 'Revenue: ₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              autoSkip: true,
              maxRotation: 0,
              maxTicksLimit: 15,
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            }
          }
        }
      }
    });

    if (revenueTrendRangeCaption) {
      revenueTrendRangeCaption.textContent = initialDataset.rangeLabel;
    }

    if (revenueTrendRangeSelect) {
      if (resolvedDefaultKey) {
        revenueTrendRangeSelect.value = resolvedDefaultKey;
      } else if (!revenueTrendRangeSelect.value && revenueTrendRangeSelect.options.length > 0) {
        revenueTrendRangeSelect.selectedIndex = 0;
      }
    }

    applyRangeSelection = rangeKey => {
      const dataset = getDatasetForRange(rangeKey);
      activeRangeKey = rangeKey;
      activeSpecificDay = null;
      setRevenueTrendDataset(dataset);
      if (financeDayFilterInput) {
        financeDayFilterInput.value = '';
      }
      if (revenueTrendRangeSelect && typeof rangeKey === 'string' && rangeKey) {
        revenueTrendRangeSelect.value = rangeKey;
      }
      applyFinanceSnapshot(rangeKey);
    };

    applySpecificDaySelection = dayKey => {
      if (!dayKey) {
        activeSpecificDay = null;
        if (activeRangeKey) {
          applyRangeSelection(activeRangeKey);
        } else {
          applyRangeSelection(resolvedDefaultKey);
        }
        return;
      }
      const dataset = getDatasetForSpecificDay(dayKey);
      const snapshot = getSnapshotForSpecificDay(dayKey) || buildEmptySnapshot(formatSpecificDayLabel(dayKey));
      activeSpecificDay = dayKey;
      setRevenueTrendDataset(dataset);
      applyFinanceSnapshot(null, snapshot);
    };

    if (revenueTrendRangeSelect) {
      revenueTrendRangeSelect.addEventListener('change', event => {
        applyRangeSelection(event.target.value);
      });
    }

    activeRangeKey = resolvedDefaultKey;
    applyFinanceSnapshot(resolvedDefaultKey);
  } else {
    applyRangeSelection = rangeKey => {
      activeRangeKey = rangeKey;
      activeSpecificDay = null;
      applyFinanceSnapshot(rangeKey);
    };
    applySpecificDaySelection = dayKey => {
      if (!dayKey) {
        activeSpecificDay = null;
        if (activeRangeKey) {
          applyRangeSelection(activeRangeKey);
        } else {
          applyFinanceSnapshot(defaultRangeKey);
        }
        return;
      }
      const snapshot = getSnapshotForSpecificDay(dayKey) || buildEmptySnapshot(formatSpecificDayLabel(dayKey));
      activeSpecificDay = dayKey;
      applyFinanceSnapshot(null, snapshot);
    };
    applyFinanceSnapshot(defaultRangeKey);
  }

  const financeSearchInput = document.getElementById('financeSearch');
  const financeTimeRangeSelect = document.getElementById('financeTimeRange');
  const financeTableBody = document.querySelector('#financialTable tbody');
  const financeDayFilterInput = document.getElementById('financeDayFilter');
  const clearFinanceDayButton = document.getElementById('clearFinanceDay');

  const getSelectedFinanceRangeDays = () => {
    if (!financeTimeRangeSelect) {
      return null;
    }
    const selectedOption = financeTimeRangeSelect.options[financeTimeRangeSelect.selectedIndex];
    if (!selectedOption) {
      return null;
    }
    const dayValueRaw = selectedOption.dataset ? selectedOption.dataset.days : undefined;
    const parsedDays = dayValueRaw !== undefined ? Number.parseInt(dayValueRaw, 10) : NaN;
    if (!Number.isFinite(parsedDays) || parsedDays <= 0) {
      return null;
    }
    return parsedDays;
  };

  const getFinanceTimeRangeLabel = () => {
    if (financeDayFilterInput && financeDayFilterInput.value) {
      return formatSpecificDayLabel(financeDayFilterInput.value);
    }
    if (!financeTimeRangeSelect) {
      return 'All Time';
    }
    const selectedOption = financeTimeRangeSelect.options[financeTimeRangeSelect.selectedIndex];
    if (!selectedOption) {
      return 'All Time';
    }
    const optionText = typeof selectedOption.textContent === 'string' ? selectedOption.textContent.trim() : '';
    if (optionText) {
      return optionText;
    }
    const optionLabel = typeof selectedOption.label === 'string' ? selectedOption.label.trim() : '';
    if (optionLabel) {
      return optionLabel;
    }
    return 'All Time';
  };

  const applyFinanceFilters = () => {
    if (!financeTableBody) {
      return;
    }
    const rows = Array.from(financeTableBody.querySelectorAll('tr'));
    if (!rows.length) {
      return;
    }
    const searchQuery = financeSearchInput ? financeSearchInput.value.trim().toLowerCase() : '';
    const specificDayValue = financeDayFilterInput ? financeDayFilterInput.value : '';
    const hasSpecificDay = Boolean(specificDayValue);
    const rangeDays = hasSpecificDay ? null : getSelectedFinanceRangeDays();
    const hasRangeFilter = typeof rangeDays === 'number';
    const cutoffMs = hasRangeFilter ? Date.now() - rangeDays * 24 * 60 * 60 * 1000 : null;

    rows.forEach(row => {
      const matchesSearch = !searchQuery || row.textContent.toLowerCase().includes(searchQuery);
      let matchesRange = true;
      if (hasSpecificDay) {
        const rowDay = row.getAttribute('data-transaction-day') || '';
        matchesRange = rowDay === specificDayValue;
      } else if (hasRangeFilter && cutoffMs !== null) {
        const tsAttr = row.getAttribute('data-transaction-ts');
        const tsSeconds = tsAttr ? Number.parseInt(tsAttr, 10) : NaN;
        if (Number.isFinite(tsSeconds)) {
          matchesRange = tsSeconds * 1000 >= cutoffMs;
        } else {
          matchesRange = false;
        }
      }
      row.style.display = matchesSearch && matchesRange ? '' : 'none';
    });
  };

  if (financeSearchInput) {
    financeSearchInput.addEventListener('input', applyFinanceFilters);
  }

  if (financeTimeRangeSelect) {
    financeTimeRangeSelect.addEventListener('change', applyFinanceFilters);
  }

  if (financeDayFilterInput) {
    financeDayFilterInput.addEventListener('change', event => {
      applySpecificDaySelection(event.target.value);
      applyFinanceFilters();
    });
  }

  if (clearFinanceDayButton) {
    clearFinanceDayButton.addEventListener('click', () => {
      if (financeDayFilterInput) {
        financeDayFilterInput.value = '';
      }
      applySpecificDaySelection('');
      applyFinanceFilters();
    });
  }

  applyFinanceFilters();

  const hiddenClassTokens = ['hidden', 'is-hidden', 'd-none'];
  const rowIsHidden = row => {
    if (!row) {
      return true;
    }
    if (row.hidden) {
      return true;
    }
    if (row.getAttribute && row.getAttribute('aria-hidden') === 'true') {
      return true;
    }
    if (row.style && row.style.display === 'none') {
      return true;
    }
    if (row.classList && hiddenClassTokens.some(token => row.classList.contains(token))) {
      return true;
    }
    if (typeof window.getComputedStyle === 'function') {
      const computedStyle = window.getComputedStyle(row);
      if (computedStyle && computedStyle.display === 'none') {
        return true;
      }
    }
    return false;
  };

  const showFinancePdfPreview = (pdfDoc, filename) => {
    if (!pdfDoc || typeof pdfDoc.output !== 'function') {
      if (pdfDoc && typeof pdfDoc.save === 'function') {
        pdfDoc.save(filename);
      }
      return;
    }

    const supportsObjectUrl = typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function';
    let blob = null;
    let blobUrl = null;

    if (supportsObjectUrl) {
      try {
        blob = pdfDoc.output('blob');
      } catch (error) {
        console.error('Failed to build PDF blob for preview:', error);
      }
      if (blob instanceof Blob) {
        blobUrl = URL.createObjectURL(blob);
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

    const existingOverlay = document.getElementById('financePdfPreviewOverlay');
    if (existingOverlay) {
      existingOverlay.remove();
    }

    if (!document.body) {
      pdfDoc.save(filename);
      return;
    }

    const overlay = document.createElement('div');
    overlay.id = 'financePdfPreviewOverlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Financial report PDF preview');
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
    frame.title = 'Financial report preview';
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
    document.body.appendChild(overlay);

    const cleanup = () => {
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
      }
      document.removeEventListener('keydown', handleKeyDown);
    };

    const handleKeyDown = event => {
      if (event.key === 'Escape') {
        cleanup();
      }
    };

    document.addEventListener('keydown', handleKeyDown);

    overlay.addEventListener('click', event => {
      if (event.target === overlay) {
        cleanup();
      }
    });

    closeBtn.addEventListener('click', cleanup);

    downloadBtn.addEventListener('click', () => {
      pdfDoc.save(filename);
      cleanup();
    });
  };

  const warnOnEmptyFilteredExport = true;
  const exportBtn = document.getElementById('exportFinance');

  const normalizeWhitespace = value => {
    if (typeof value !== 'string') {
      return '';
    }
    return value.replace(/\s+/g, ' ').trim();
  };

  const parseNumericString = rawValue => {
    if (typeof rawValue === 'number') {
      return Number.isFinite(rawValue) ? rawValue : null;
    }
    if (typeof rawValue !== 'string') {
      return null;
    }
    const cleaned = rawValue.replace(/[^0-9.\-]/g, '');
    const parsed = Number.parseFloat(cleaned);
    return Number.isFinite(parsed) ? parsed : null;
  };

  const formatPesoForPdf = rawValue => {
    const numeric = parseNumericString(rawValue);
    if (!Number.isFinite(numeric)) {
      const fallback = typeof rawValue === 'string' ? normalizeWhitespace(rawValue) : '';
      return fallback || '₱0.00';
    }
    const isNegative = numeric < 0;
    const absolute = Math.abs(numeric);
    const formattedNumber = absolute
      .toFixed(2)
      .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `${isNegative ? '-₱' : '₱'}${formattedNumber}`;
  };

  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
        window.alert('PDF generator is not ready yet. Please try again in a moment.');
        return;
      }
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ orientation: 'landscape' });

      const rows = [];
      const visibleTimestamps = [];
      document.querySelectorAll('#financialTable tbody tr').forEach(tr => {
        if (rowIsHidden(tr)) {
          return;
        }
        const productTotalRaw = tr.dataset ? tr.dataset.productTotal : tr.getAttribute('data-product-total');
        const amountPaidRaw = tr.dataset ? tr.dataset.amountPaid : tr.getAttribute('data-amount-paid');
        const cells = Array.from(tr.cells).map((td, cellIndex) => {
          if (cellIndex === 5) {
            return formatPesoForPdf(productTotalRaw ?? td.textContent);
          }
          if (cellIndex === 6) {
            return formatPesoForPdf(amountPaidRaw ?? td.textContent);
          }
          return normalizeWhitespace(td.textContent || '');
        });
        rows.push(cells);
        const tsAttr = tr.getAttribute('data-transaction-ts');
        const tsSeconds = tsAttr ? Number.parseInt(tsAttr, 10) : NaN;
        if (Number.isFinite(tsSeconds)) {
          visibleTimestamps.push(tsSeconds * 1000);
        }
      });

      if (warnOnEmptyFilteredExport && rows.length === 0) {
        window.alert('No visible rows to export. Please adjust your filters and try again.');
        return;
      }

      const formatCoverageDate = timestampMs => {
        if (!Number.isFinite(timestampMs)) {
          return null;
        }
        const date = new Date(timestampMs);
        if (Number.isNaN(date.getTime())) {
          return null;
        }
        const adjusted = addEightHours(date) || date;
        return adjusted.toLocaleDateString(undefined, {
          timeZone: MANILA_TIME_ZONE,
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        });
      };

      const timeRangeLabel = getFinanceTimeRangeLabel();
      let coverageDetails = '';
      if (visibleTimestamps.length > 0) {
        let earliestTimestamp = visibleTimestamps[0];
        let latestTimestamp = visibleTimestamps[0];
        for (let index = 1; index < visibleTimestamps.length; index += 1) {
          const currentTimestamp = visibleTimestamps[index];
          if (currentTimestamp < earliestTimestamp) {
            earliestTimestamp = currentTimestamp;
          }
          if (currentTimestamp > latestTimestamp) {
            latestTimestamp = currentTimestamp;
          }
        }
        const earliestLabel = formatCoverageDate(earliestTimestamp);
        const latestLabel = formatCoverageDate(latestTimestamp);
        if (earliestLabel && latestLabel) {
          coverageDetails = earliestLabel === latestLabel
            ? ` (${earliestLabel})`
            : ` (${earliestLabel} - ${latestLabel})`;
        }
      }
      if (coverageDetails && coverageDetails.trim() === `(${timeRangeLabel})`) {
        coverageDetails = '';
      }
      const coverageText = `Time Range: ${timeRangeLabel}${coverageDetails}`;

      doc.setFontSize(16);
      doc.text('Financial Report', 14, 18);
      doc.setFontSize(11);
      doc.setTextColor(80);
      doc.text(coverageText, 14, 26);
      doc.setTextColor(0);

      doc.autoTable({
        startY: 34,
        head: [['Transaction ID', 'Order ID', 'Order Date', 'Payment Date', 'Customer', 'Product Total', 'Amount Paid', 'Method', 'Status', 'Reference']],
        body: rows,
        theme: 'grid',
        styles: { fontSize: 9 }
      });

      showFinancePdfPreview(doc, 'financial-report.pdf');
    });
  }
</script>
<?php
$extraScripts = ob_get_clean();
include 'includes/footer.php';
