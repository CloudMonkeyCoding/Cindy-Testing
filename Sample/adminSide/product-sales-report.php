<?php
require_once __DIR__ . '/includes/require_super_admin.php';
require_once '../PHP/db_connect.php';
require_once '../PHP/product_functions.php';

$activePage = 'product-sales-report';
$pageTitle = "Product Sales Report - Cindy's Bakeshop";

$totalRevenue = 0;
$totalUnits = 0;
$totalOrders = 0;
$averageOrderValue = 0;
$averageUnitsPerOrder = 0.0;
$averageSellingPrice = 0;
$productsWithSales = 0;
$catalogSize = 0;
$topProductName = 'No sales recorded yet';
$topProductRevenue = 0;
$topProductUnits = 0;
$topCategoryName = 'No category sales yet';
$topCategoryRevenue = 0;
$recentSaleTimestamp = null;
$recentSaleDisplay = 'No sales recorded for this date';

$productSales = [];
$statusBreakdown = [];
$categoryRevenue = [];
$categoryUnits = [];
$hourlyLabels = [];
$hourlyRevenue = [];
$hourlyUnits = [];
$dailyTotals = [];
$dailyLabels = [];
$dailyRevenue = [];
$dailyUnits = [];
$weeklyTotals = [];
$weeklyLabels = [];
$weeklyRevenue = [];
$weeklyUnits = [];
$recentSaleDisplayDateFormat = 'M d, Y g:i A';

$today = new DateTimeImmutable('today');
$todayDate = $today->format('Y-m-d');
$todayWeek = $today->format('o-\WW');
$todayMonth = $today->format('Y-m');

$viewMode = isset($_GET['view']) ? strtolower(trim((string)$_GET['view'])) : 'day';
$validViewModes = ['day', 'week', 'month', 'range'];
if (!in_array($viewMode, $validViewModes, true)) {
    $viewMode = 'day';
}

$selectedDate = $todayDate;
$selectedWeek = $todayWeek;
$selectedMonth = $todayMonth;
$rangeStartInput = $today->modify('-6 days')->format('Y-m-d');
$rangeEndInput = $todayDate;

$rangeStart = $today->setTime(0, 0, 0);
$rangeEndExclusive = $rangeStart->modify('+1 day');
$rangeLabel = $today->format('F d, Y');

switch ($viewMode) {
    case 'week':
        $candidateWeek = isset($_GET['selected_week']) ? trim((string)$_GET['selected_week']) : $selectedWeek;
        if (preg_match('/^(\d{4})-W(\d{2})$/', $candidateWeek, $matches)) {
            $weekYear = (int)$matches[1];
            $weekNumber = (int)$matches[2];
            try {
                $weekStart = (new DateTimeImmutable())->setISODate($weekYear, $weekNumber)->setTime(0, 0, 0);
                $rangeStart = $weekStart;
                $rangeEndExclusive = $weekStart->modify('+1 week');
                $rangeLabel = sprintf(
                    'Week of %s – %s',
                    $weekStart->format('M d, Y'),
                    $weekStart->modify('+6 days')->format('M d, Y')
                );
                $selectedWeek = $weekStart->format('o-\WW');
            } catch (Exception $exception) {
                $selectedWeek = $todayWeek;
            }
        }
        break;
    case 'month':
        $candidateMonth = isset($_GET['selected_month']) ? trim((string)$_GET['selected_month']) : $selectedMonth;
        $monthCandidate = DateTimeImmutable::createFromFormat('Y-m', $candidateMonth) ?: null;
        if ($monthCandidate instanceof DateTimeImmutable) {
            $monthStart = $monthCandidate->setTime(0, 0, 0)->modify('first day of this month');
            $rangeStart = $monthStart;
            $rangeEndExclusive = $monthStart->modify('first day of next month');
            $rangeLabel = $monthStart->format('F Y');
            $selectedMonth = $monthStart->format('Y-m');
        }
        break;
    case 'range':
        $candidateRangeStart = isset($_GET['range_start']) ? trim((string)$_GET['range_start']) : $rangeStartInput;
        $candidateRangeEnd = isset($_GET['range_end']) ? trim((string)$_GET['range_end']) : $rangeEndInput;
        $rangeStartCandidate = DateTimeImmutable::createFromFormat('Y-m-d', $candidateRangeStart) ?: null;
        $rangeEndCandidate = DateTimeImmutable::createFromFormat('Y-m-d', $candidateRangeEnd) ?: null;
        if ($rangeStartCandidate instanceof DateTimeImmutable && $rangeEndCandidate instanceof DateTimeImmutable) {
            if ($rangeEndCandidate < $rangeStartCandidate) {
                [$rangeStartCandidate, $rangeEndCandidate] = [$rangeEndCandidate, $rangeStartCandidate];
            }
            if ($rangeEndCandidate > $today) {
                $rangeEndCandidate = $today;
            }
            if ($rangeStartCandidate > $today) {
                $rangeStartCandidate = $today;
            }
            if ($rangeStartCandidate > $rangeEndCandidate) {
                $rangeStartCandidate = $rangeEndCandidate;
            }
            $rangeStart = $rangeStartCandidate->setTime(0, 0, 0);
            $rangeEndExclusive = $rangeEndCandidate->setTime(0, 0, 0)->modify('+1 day');
            $rangeLabel = sprintf(
                'Custom range: %s – %s',
                $rangeStart->format('M d, Y'),
                $rangeEndCandidate->format('M d, Y')
            );
            $rangeStartInput = $rangeStart->format('Y-m-d');
            $rangeEndInput = $rangeEndCandidate->format('Y-m-d');
        }
        break;
    case 'day':
    default:
        $candidateDate = isset($_GET['selected_date']) ? trim((string)$_GET['selected_date']) : $selectedDate;
        $dateCandidate = DateTimeImmutable::createFromFormat('Y-m-d', $candidateDate) ?: null;
        if (!($dateCandidate instanceof DateTimeImmutable)) {
            $dateCandidate = $today;
        }
        if ($dateCandidate > $today) {
            $dateCandidate = $today;
        }
        $selectedDate = $dateCandidate->format('Y-m-d');
        $rangeStart = $dateCandidate->setTime(0, 0, 0);
        $rangeEndExclusive = $rangeStart->modify('+1 day');
        $rangeLabel = $dateCandidate->format('F d, Y');
        break;
}

$startDateTime = $rangeStart->format('Y-m-d H:i:s');
$endDateTime = $rangeEndExclusive->format('Y-m-d H:i:s');
$rangeDurationSeconds = max(0, $rangeEndExclusive->getTimestamp() - $rangeStart->getTimestamp());
$rangeDurationHours = max(1, (int)floor($rangeDurationSeconds / 3600));
$rangeDayCount = max(1, (int)ceil($rangeDurationSeconds / 86400));
$recentSaleDisplay = $rangeDayCount > 1 ? 'No sales recorded for this range' : 'No sales recorded for this date';
$rangeStartForInputs = $rangeStart->format('Y-m-d');
$rangeEndForInputs = $rangeEndExclusive->modify('-1 day')->format('Y-m-d');
if ($viewMode !== 'range') {
    $rangeStartInput = $rangeStartForInputs;
    $rangeEndInput = $rangeEndForInputs;
}
$hourLabelFormat = $viewMode === 'day' ? 'H:i' : 'M d H:i';
$rangeFieldHidden = [
    'day' => $viewMode === 'day' ? '' : 'hidden',
    'week' => $viewMode === 'week' ? '' : 'hidden',
    'month' => $viewMode === 'month' ? '' : 'hidden',
    'range' => $viewMode === 'range' ? '' : 'hidden',
];
$rangeFieldDisabled = [
    'day' => $viewMode === 'day' ? '' : 'disabled',
    'week' => $viewMode === 'week' ? '' : 'disabled',
    'month' => $viewMode === 'month' ? '' : 'disabled',
    'range' => $viewMode === 'range' ? '' : 'disabled',
];
$rangeSummaryLabel = $rangeLabel;
if ($rangeDayCount > 1) {
    $rangeSummaryLabel .= sprintf(' • %d days', $rangeDayCount);
}

if ($pdo) {
    $allowedStatuses = ['Pending', 'Confirmed', 'Shipped', 'Delivered'];
    $statusList = implode(',', array_map([$pdo, 'quote'], $allowedStatuses));
    $startDateQuoted = $pdo->quote($startDateTime);
    $endDateQuoted = $pdo->quote($endDateTime);

    $productSql = "
        SELECT
            p.Product_ID,
            p.Name,
            p.Category,
            COALESCE(SUM(CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN oi.Quantity ELSE 0 END), 0) AS units_sold,
            COALESCE(SUM(CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN oi.Subtotal ELSE 0 END), 0) AS revenue,
            COUNT(DISTINCT CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN o.Order_ID END) AS order_count,
            MIN(CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN o.Order_Date END) AS first_sale,
            MAX(CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN o.Order_Date END) AS last_sale
        FROM product p
        LEFT JOIN order_item oi ON oi.Product_ID = p.Product_ID
        LEFT JOIN `order` o ON o.Order_ID = oi.Order_ID
        GROUP BY p.Product_ID, p.Name, p.Category
        ORDER BY revenue DESC, units_sold DESC, p.Name ASC
    ";
    $stmtProducts = $pdo->query($productSql);
    if ($stmtProducts) {
        $productSales = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
    }

    $ordersSql = "
        SELECT COUNT(DISTINCT CASE WHEN o.Status IN ($statusList) AND o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted THEN o.Order_ID END) AS order_count
        FROM order_item oi
        JOIN `order` o ON o.Order_ID = oi.Order_ID
        WHERE o.Order_Date >= $startDateQuoted AND o.Order_Date < $endDateQuoted
          AND o.Status IN ($statusList)
    ";
    $stmtOrders = $pdo->query($ordersSql);
    if ($stmtOrders) {
        $totalOrders = (int)($stmtOrders->fetchColumn() ?? 0);
    }

    $trendSql = "
        SELECT
            DATE_FORMAT(o.Order_Date, '%Y-%m-%d %H:00:00') AS sale_bucket,
            COALESCE(SUM(oi.Subtotal), 0) AS revenue,
            COALESCE(SUM(oi.Quantity), 0) AS units
        FROM order_item oi
        JOIN `order` o ON o.Order_ID = oi.Order_ID
        WHERE o.Status IN ($statusList)
          AND o.Order_Date IS NOT NULL
          AND o.Order_Date >= $startDateQuoted
          AND o.Order_Date < $endDateQuoted
        GROUP BY sale_bucket
        ORDER BY sale_bucket ASC
    ";
    $stmtTrend = $pdo->query($trendSql);
    $trendBuckets = [];
    if ($stmtTrend) {
        while ($row = $stmtTrend->fetch(PDO::FETCH_ASSOC)) {
            $bucketKey = $row['sale_bucket'] ?? null;
            if (is_string($bucketKey) && $bucketKey !== '') {
                $trendBuckets[$bucketKey] = [
                    'revenue' => (float)($row['revenue'] ?? 0),
                    'units' => (float)($row['units'] ?? 0),
                ];
            }
        }
    }
    $hourInterval = new DateInterval('PT1H');
    $period = new DatePeriod($rangeStart, $hourInterval, $rangeEndExclusive);
    foreach ($period as $point) {
        $bucketKey = $point->format('Y-m-d H:00:00');
        $hourlyLabels[] = $point->format($hourLabelFormat);
        $bucketData = $trendBuckets[$bucketKey] ?? ['revenue' => 0.0, 'units' => 0.0];
        $bucketRevenue = (float)($bucketData['revenue'] ?? 0.0);
        $bucketUnits = (float)($bucketData['units'] ?? 0.0);
        $hourlyRevenue[] = (int)round($bucketRevenue);
        $hourlyUnits[] = (int)round($bucketUnits);

        $dayKey = $point->format('Y-m-d');
        if (!isset($dailyTotals[$dayKey])) {
            $dailyTotals[$dayKey] = [
                'date' => $point->setTime(0, 0, 0),
                'revenue' => 0.0,
                'units' => 0.0,
            ];
        }
        $dailyTotals[$dayKey]['revenue'] += $bucketRevenue;
        $dailyTotals[$dayKey]['units'] += $bucketUnits;

        $weekYear = (int)$point->format('o');
        $weekNumber = (int)$point->format('W');
        $weekKey = sprintf('%d-W%02d', $weekYear, $weekNumber);
        if (!isset($weeklyTotals[$weekKey])) {
            $weekStartFull = (new DateTimeImmutable())->setISODate($weekYear, $weekNumber)->setTime(0, 0, 0);
            $weekStartForLabel = $weekStartFull;
            if ($weekStartFull < $rangeStart) {
                $weekStartForLabel = $rangeStart;
            }
            $weekEndFull = $weekStartFull->modify('+6 days');
            $weekEndForLabel = $weekEndFull;
            $lastRangeDay = $rangeEndExclusive->modify('-1 day');
            if ($weekEndForLabel > $lastRangeDay) {
                $weekEndForLabel = $lastRangeDay;
            }
            $weeklyTotals[$weekKey] = [
                'start' => $weekStartForLabel,
                'end' => $weekEndForLabel,
                'revenue' => 0.0,
                'units' => 0.0,
            ];
        }
        $weeklyTotals[$weekKey]['revenue'] += $bucketRevenue;
        $weeklyTotals[$weekKey]['units'] += $bucketUnits;
    }

    ksort($dailyTotals);
    if (!empty($dailyTotals)) {
        $dailyYears = [];
        foreach ($dailyTotals as $dailyData) {
            $dailyDate = $dailyData['date'] ?? null;
            if ($dailyDate instanceof DateTimeInterface) {
                $dailyYears[$dailyDate->format('Y')] = true;
            }
        }
        $includeDailyYear = count($dailyYears) > 1;
        foreach ($dailyTotals as $dailyData) {
            $dailyDate = $dailyData['date'] ?? null;
            $label = '';
            if ($dailyDate instanceof DateTimeInterface) {
                $label = $dailyDate->format($includeDailyYear ? 'M d, Y' : 'M d');
            }
            $dailyLabels[] = $label;
            $dailyRevenue[] = (int)round((float)($dailyData['revenue'] ?? 0));
            $dailyUnits[] = (int)round((float)($dailyData['units'] ?? 0));
        }
    }

    ksort($weeklyTotals);
    if (!empty($weeklyTotals)) {
        $weekYears = [];
        foreach ($weeklyTotals as $weeklyData) {
            $startDate = $weeklyData['start'] ?? null;
            $endDate = $weeklyData['end'] ?? null;
            if ($startDate instanceof DateTimeInterface) {
                $weekYears[$startDate->format('Y')] = true;
            }
            if ($endDate instanceof DateTimeInterface) {
                $weekYears[$endDate->format('Y')] = true;
            }
        }
        $includeWeekYear = count($weekYears) > 1;
        foreach ($weeklyTotals as $weeklyData) {
            $startDate = $weeklyData['start'] ?? null;
            $endDate = $weeklyData['end'] ?? null;
            $startLabel = '';
            $endLabel = '';
            if ($startDate instanceof DateTimeInterface) {
                $startLabel = $startDate->format($includeWeekYear ? 'M d, Y' : 'M d');
            }
            if ($endDate instanceof DateTimeInterface) {
                $endFormat = $includeWeekYear;
                if ($startDate instanceof DateTimeInterface && $startDate->format('Y') !== $endDate->format('Y')) {
                    $endFormat = true;
                }
                $endLabel = $endDate->format($endFormat ? 'M d, Y' : 'M d');
            }
            if ($startLabel !== '' && $endLabel !== '') {
                $weeklyLabels[] = sprintf('Week of %s – %s', $startLabel, $endLabel);
            } elseif ($startLabel !== '') {
                $weeklyLabels[] = sprintf('Week of %s', $startLabel);
            } else {
                $weeklyLabels[] = 'Week';
            }
            $weeklyRevenue[] = (int)round((float)($weeklyData['revenue'] ?? 0));
            $weeklyUnits[] = (int)round((float)($weeklyData['units'] ?? 0));
        }
    }

    $statusSql = "
        SELECT
            COALESCE(o.Status, 'Unknown') AS status,
            COALESCE(SUM(oi.Quantity), 0) AS units,
            COALESCE(SUM(oi.Subtotal), 0) AS revenue
        FROM order_item oi
        JOIN `order` o ON o.Order_ID = oi.Order_ID
        WHERE o.Status IN ($statusList)
          AND o.Order_Date >= $startDateQuoted
          AND o.Order_Date < $endDateQuoted
        GROUP BY status
        ORDER BY revenue DESC
    ";
    $stmtStatus = $pdo->query($statusSql);
    if ($stmtStatus) {
        $statusBreakdown = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
    }
}

$catalogSize = count($productSales);

foreach ($productSales as &$product) {
    $product['Name'] = $product['Name'] ?? 'Unnamed Product';
    $categoryRaw = $product['Category'] ?? '';
    $normalizedCategory = normalizeProductCategoryValue($categoryRaw);
    $categoryLabel = $normalizedCategory === '' ? 'Uncategorized' : $normalizedCategory;
    $product['Category'] = $categoryLabel;
    $product['revenue'] = (int)round((float)($product['revenue'] ?? 0));
    $product['units_sold'] = (int)($product['units_sold'] ?? 0);
    $product['order_count'] = (int)($product['order_count'] ?? 0);
    $product['first_sale'] = $product['first_sale'] ?? null;
    $product['last_sale'] = $product['last_sale'] ?? null;

    $totalRevenue += $product['revenue'];
    $totalUnits += $product['units_sold'];

    $category = $categoryLabel ?: 'Uncategorized';
    $categoryRevenue[$category] = ($categoryRevenue[$category] ?? 0) + $product['revenue'];
    $categoryUnits[$category] = ($categoryUnits[$category] ?? 0) + $product['units_sold'];

    if ($product['units_sold'] > 0) {
        $productsWithSales++;
    }

    if ($product['revenue'] > $topProductRevenue || ($product['revenue'] === $topProductRevenue && $product['units_sold'] > $topProductUnits)) {
        if ($product['revenue'] > 0 || $product['units_sold'] > 0) {
            $topProductRevenue = $product['revenue'];
            $topProductUnits = $product['units_sold'];
            $topProductName = $product['Name'];
        }
    }

    if ($product['last_sale']) {
        $timestamp = strtotime($product['last_sale']);
        if ($timestamp !== false && ($recentSaleTimestamp === null || $timestamp > $recentSaleTimestamp)) {
            $recentSaleTimestamp = $timestamp;
        }
    }
}
unset($product);

if ($recentSaleTimestamp) {
    $recentSaleDisplay = date($recentSaleDisplayDateFormat, $recentSaleTimestamp);
}

$sortedCategoryRevenue = $categoryRevenue;
arsort($sortedCategoryRevenue);
$filteredCategoryRevenue = array_map(static fn($value) => (int)round((float)$value), $sortedCategoryRevenue);
if (!empty($filteredCategoryRevenue)) {
    $topCategoryName = array_key_first($filteredCategoryRevenue) ?? 'No category sales yet';
    $topCategoryRevenue = $filteredCategoryRevenue[$topCategoryName] ?? 0;
}

$averageOrderValue = $totalOrders > 0 ? (int)round($totalRevenue / $totalOrders) : 0;
$averageUnitsPerOrder = $totalOrders > 0 ? $totalUnits / $totalOrders : 0.0;
$averageSellingPrice = $totalUnits > 0 ? (int)round($totalRevenue / max($totalUnits, 1)) : 0;
$hourlyRevenueRounded = array_map(static function ($value) {
    return (int)round($value);
}, $hourlyRevenue);

$topProductChartData = array_slice(array_values(array_filter($productSales, function ($product) {
    return ($product['revenue'] ?? 0) > 0;
})), 0, 5);
$topProductLabels = array_map(function ($product) {
    return $product['Name'];
}, $topProductChartData);
$topProductRevenueValues = array_map(function ($product) {
    return (int)round($product['revenue']);
}, $topProductChartData);
$topProductUnitValues = array_map(function ($product) {
    return (int)$product['units_sold'];
}, $topProductChartData);

$categoryLabels = [];
$categoryRevenueValues = [];
$categoryUnitValues = [];
foreach ($filteredCategoryRevenue as $category => $revenue) {
    if ($revenue <= 0) {
        continue;
    }
    $categoryLabels[] = $category;
    $categoryRevenueValues[] = (int)round($revenue);
    $categoryUnitValues[] = (int)($categoryUnits[$category] ?? 0);
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$salesTrendSeries = [
    'hourly' => [
        'labels' => $hourlyLabels,
        'revenue' => array_map('intval', $hourlyRevenueRounded),
        'units' => array_map('intval', $hourlyUnits),
    ],
    'daily' => [
        'labels' => $dailyLabels,
        'revenue' => array_map('intval', $dailyRevenue),
        'units' => array_map('intval', $dailyUnits),
    ],
    'weekly' => [
        'labels' => $weeklyLabels,
        'revenue' => array_map('intval', $weeklyRevenue),
        'units' => array_map('intval', $weeklyUnits),
    ],
];
$salesTrendSeriesJson = json_encode($salesTrendSeries, $jsonFlags) ?: '{}';
$topProductLabelsJson = json_encode($topProductLabels, $jsonFlags) ?: '[]';
$topProductRevenueJson = json_encode($topProductRevenueValues, $jsonFlags) ?: '[]';
$topProductUnitsJson = json_encode($topProductUnitValues, $jsonFlags) ?: '[]';
$categoryLabelsJson = json_encode($categoryLabels, $jsonFlags) ?: '[]';
$categoryRevenueJson = json_encode($categoryRevenueValues, $jsonFlags) ?: '[]';
$categoryUnitsJson = json_encode($categoryUnitValues, $jsonFlags) ?: '[]';

$extraHead = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
<style>
  .chart-interval-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }

  .chart-interval-button {
    padding: 6px 12px;
    border: 1px solid #dcdde1;
    border-radius: 6px;
    background: #ffffff;
    color: #2c3e50;
    font-size: 13px;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
  }

  .chart-interval-button.is-active {
    background: #e74c3c;
    color: #ffffff;
    border-color: #e74c3c;
  }

  .chart-interval-button:focus-visible {
    outline: 2px solid #e74c3c;
    outline-offset: 1px;
  }
</style>
HTML;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">
    <div style="display:flex;flex-direction:column;gap:6px;">
      <h1 style="margin:0;">Product Sales Report</h1>
      <span style="font-size:14px;color:#7f8c8d;">Last sale: <?= htmlspecialchars($recentSaleDisplay); ?></span>
    </div>
    <form method="get" id="reportRangeForm" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
        <label for="viewMode" style="font-size:14px;color:#2c3e50;">View:</label>
        <select id="viewMode" name="view" style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
          <option value="day" <?= $viewMode === 'day' ? 'selected' : ''; ?>>Day</option>
          <option value="week" <?= $viewMode === 'week' ? 'selected' : ''; ?>>Week</option>
          <option value="month" <?= $viewMode === 'month' ? 'selected' : ''; ?>>Month</option>
          <option value="range" <?= $viewMode === 'range' ? 'selected' : ''; ?>>Custom Range</option>
        </select>
      </div>
      <div class="range-field" data-range-mode="day"<?= $rangeFieldHidden['day'] !== '' ? ' hidden' : ''; ?> style="display:flex;align-items:center;gap:8px;">
        <label for="selected_date" style="font-size:14px;color:#2c3e50;">Date:</label>
        <input type="date" id="selected_date" name="selected_date" value="<?= htmlspecialchars($selectedDate); ?>" max="<?= htmlspecialchars($todayDate); ?>"<?= $rangeFieldDisabled['day'] !== '' ? ' disabled' : ''; ?> style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
      </div>
      <div class="range-field" data-range-mode="week"<?= $rangeFieldHidden['week'] !== '' ? ' hidden' : ''; ?> style="display:flex;align-items:center;gap:8px;">
        <label for="selected_week" style="font-size:14px;color:#2c3e50;">Week:</label>
        <input type="week" id="selected_week" name="selected_week" value="<?= htmlspecialchars($selectedWeek); ?>" max="<?= htmlspecialchars($todayWeek); ?>"<?= $rangeFieldDisabled['week'] !== '' ? ' disabled' : ''; ?> style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
      </div>
      <div class="range-field" data-range-mode="month"<?= $rangeFieldHidden['month'] !== '' ? ' hidden' : ''; ?> style="display:flex;align-items:center;gap:8px;">
        <label for="selected_month" style="font-size:14px;color:#2c3e50;">Month:</label>
        <input type="month" id="selected_month" name="selected_month" value="<?= htmlspecialchars($selectedMonth); ?>" max="<?= htmlspecialchars($todayMonth); ?>"<?= $rangeFieldDisabled['month'] !== '' ? ' disabled' : ''; ?> style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
      </div>
      <div class="range-field" data-range-mode="range"<?= $rangeFieldHidden['range'] !== '' ? ' hidden' : ''; ?> style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="range_start" style="font-size:14px;color:#2c3e50;">From:</label>
        <input type="date" id="range_start" name="range_start" value="<?= htmlspecialchars($rangeStartInput); ?>" max="<?= htmlspecialchars($todayDate); ?>"<?= $rangeFieldDisabled['range'] !== '' ? ' disabled' : ''; ?> style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
        <label for="range_end" style="font-size:14px;color:#2c3e50;">To:</label>
        <input type="date" id="range_end" name="range_end" value="<?= htmlspecialchars($rangeEndInput); ?>" max="<?= htmlspecialchars($todayDate); ?>"<?= $rangeFieldDisabled['range'] !== '' ? ' disabled' : ''; ?> style="padding:8px 12px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;">
      </div>
      <button type="submit" class="btn btn-primary" style="padding:8px 16px;">Apply</button>
      <a href="?view=day&amp;selected_date=<?= htmlspecialchars($todayDate); ?>" class="btn" style="padding:8px 16px;border:1px solid #dcdde1;border-radius:6px;font-size:14px;text-decoration:none;color:#2c3e50;background:#ecf0f1;">Today</a>
    </form>
  </div>

  <section class="stats-grid columns-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
    <div class="stat-card">
      <h3>Total Product Revenue</h3>
      <div class="value">₱<?= number_format((float)$totalRevenue, 2); ?></div>
      <div class="meta">
        <?php if ($topCategoryRevenue > 0): ?>
          Top category: <?= htmlspecialchars($topCategoryName); ?> (₱<?= number_format((float)$topCategoryRevenue, 2); ?>)
        <?php else: ?>
          No category sales yet
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-card">
      <h3>Units Sold</h3>
      <div class="value"><?= number_format($totalUnits); ?></div>
      <div class="meta">
        <?php if ($topProductRevenue > 0 || $topProductUnits > 0): ?>
          Top product: <?= htmlspecialchars($topProductName); ?> (<?= number_format($topProductUnits); ?> sold) &bull; Avg units/order: <?= number_format($averageUnitsPerOrder, 1); ?>
        <?php else: ?>
          No product sales yet (Avg units/order: <?= number_format($averageUnitsPerOrder, 1); ?>)
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-card">
      <h3>Orders with Sales</h3>
      <div class="value"><?= number_format($totalOrders); ?></div>
      <div class="meta">Average order value: ₱<?= number_format((float)$averageOrderValue, 2); ?></div>
    </div>
    <div class="stat-card">
      <h3>Products Sold</h3>
      <div class="value"><?= number_format($productsWithSales); ?></div>
      <div class="meta">Avg item price: ₱<?= number_format((float)$averageSellingPrice, 2); ?> &bull; Catalog: <?= number_format($catalogSize); ?> items</div>
    </div>
  </section>

  <div class="stats-grid columns-4" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));">
    <div class="card">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px;">
        <div>
          <h2 style="font-size:18px;margin:0;">Sales Trend</h2>
          <p id="salesTrendCaption" style="margin:4px 0 0;font-size:13px;color:#7f8c8d;">
            <?= htmlspecialchars($rangeSummaryLabel); ?>
          </p>
        </div>
        <div class="chart-interval-controls" role="group" aria-label="Sales trend interval">
          <button type="button" class="chart-interval-button is-active" data-sales-interval="hourly" aria-pressed="true">Hourly</button>
          <button type="button" class="chart-interval-button" data-sales-interval="daily" aria-pressed="false">Daily</button>
          <button type="button" class="chart-interval-button" data-sales-interval="weekly" aria-pressed="false">Weekly</button>
        </div>
      </div>
      <canvas id="salesChart" height="220"></canvas>
    </div>
    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Top Selling Products</h2>
      <canvas id="topProductChart" height="220"></canvas>
    </div>
    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Revenue by Category</h2>
      <canvas id="categoryChart" height="220"></canvas>
    </div>
  </div>

  <div class="card" style="margin-top:24px;">
    <div class="table-actions">
      <h2 style="font-size:18px;margin:0;">Sales by Order Status</h2>
      <input type="text" id="statusBreakdownSearch" placeholder="🔍 Filter order status...">
    </div>
    <div class="table-responsive">
      <?php if (empty($statusBreakdown)): ?>
        <p class="table-empty">No order activity recorded.</p>
      <?php else: ?>
        <table id="statusSalesTable">
          <thead>
            <tr>
              <th>Status</th>
              <th>Units Sold</th>
              <th>Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($statusBreakdown as $statusRow): ?>
              <?php
                $statusName = $statusRow['status'] ?? 'Unknown';
                $statusUnits = (int)($statusRow['units'] ?? 0);
                $statusRevenue = (int)round((float)($statusRow['revenue'] ?? 0));
                $statusClass = strtolower(str_replace(' ', '-', $statusName));
              ?>
              <tr>
                <td>
                  <span class="status-pill status-<?= htmlspecialchars($statusClass); ?>">
                    <?= htmlspecialchars($statusName); ?>
                  </span>
                </td>
                <td><?= number_format($statusUnits); ?></td>
                <td>₱<?= number_format((float)$statusRevenue, 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-container" style="margin-top:24px;">
    <div class="table-actions">
      <input type="text" id="productSalesSearch" placeholder="🔍 Search product or category...">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <button class="btn btn-primary" type="button" id="exportProductSales">Export Sales PDF</button>
      </div>
    </div>
    <?php if (empty($productSales)): ?>
      <p class="table-empty">No products found.</p>
    <?php else: ?>
      <table id="productSalesTable">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Units Sold</th>
            <th>Revenue</th>
            <th>Orders</th>
            <th>First Sale</th>
            <th>Last Sale</th>
            <th>Avg Item Price</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productSales as $product): ?>
            <?php
              $unitsSold = $product['units_sold'];
              $revenue = $product['revenue'];
              $avgPrice = $unitsSold > 0 ? (int)round($revenue / max($unitsSold, 1)) : 0;
              $firstSaleDisplay = formatAdminDateTime($product['first_sale'] ?? null, $recentSaleDisplayDateFormat, '—');
              $lastSaleDisplay = formatAdminDateTime($product['last_sale'] ?? null, $recentSaleDisplayDateFormat, '—');
            ?>
            <tr>
              <td><?= htmlspecialchars($product['Name']); ?></td>
              <td><?= htmlspecialchars($product['Category']); ?></td>
              <td><?= number_format($unitsSold); ?></td>
              <td>₱<?= number_format((float)$revenue, 2); ?></td>
              <td><?= number_format($product['order_count']); ?></td>
              <td><?= htmlspecialchars($firstSaleDisplay); ?></td>
              <td><?= htmlspecialchars($lastSaleDisplay); ?></td>
              <td><?= $unitsSold > 0 ? '₱' . number_format((float)$avgPrice, 2) : '—'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php
$recentSaleLabelJson = json_encode($recentSaleDisplay, $jsonFlags);
if ($recentSaleLabelJson === false) {
    $recentSaleLabelJson = '""';
}
$rangeSummaryJson = json_encode($rangeSummaryLabel, $jsonFlags);
if ($rangeSummaryJson === false) {
    $rangeSummaryJson = '""';
}
$extraScripts = <<<JS
<script>
  const salesTrendSeries = $salesTrendSeriesJson;
  const topProductLabels = $topProductLabelsJson;
  const topProductRevenue = $topProductRevenueJson;
  const topProductUnits = $topProductUnitsJson;
  const categoryLabels = $categoryLabelsJson;
  const categoryRevenue = $categoryRevenueJson;
  const categoryUnits = $categoryUnitsJson;
  const lastSaleLabel = $recentSaleLabelJson;
  const reportRangeLabel = $rangeSummaryJson;
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

  const rangeForm = document.getElementById('reportRangeForm');
  const viewModeSelect = document.getElementById('viewMode');
  if (rangeForm && viewModeSelect) {
    const rangeFields = rangeForm.querySelectorAll('.range-field');
    const updateRangeInputs = () => {
      const activeMode = viewModeSelect.value;
      rangeFields.forEach(field => {
        const fieldMode = field.getAttribute('data-range-mode');
        const inputs = field.querySelectorAll('input');
        const isActive = fieldMode === activeMode;
        if (isActive) {
          field.removeAttribute('hidden');
        } else {
          field.setAttribute('hidden', '');
        }
        inputs.forEach(input => {
          if (isActive) {
            input.removeAttribute('disabled');
          } else {
            input.setAttribute('disabled', 'disabled');
          }
        });
      });
    };
    updateRangeInputs();
    viewModeSelect.addEventListener('change', () => {
      updateRangeInputs();
      const selector = '.range-field[data-range-mode="' + viewModeSelect.value + '"] input:not([disabled])';
      const activeInput = rangeForm.querySelector(selector);
      if (activeInput) {
        activeInput.focus();
      }
    });
  }

  const salesChartCanvas = document.getElementById('salesChart');
  const salesTrendCaption = document.getElementById('salesTrendCaption');
  const intervalButtons = Array.from(document.querySelectorAll('[data-sales-interval]'));

  const intervalLabels = {
    hourly: 'Hourly',
    daily: 'Daily',
    weekly: 'Weekly',
  };

  const axisLabels = {
    hourly: 'Hour of Day',
    daily: 'Day',
    weekly: 'Week',
  };

  const hasInterval = interval => Object.prototype.hasOwnProperty.call(intervalLabels, interval);

  const getSeriesForInterval = interval => {
    if (!salesTrendSeries || typeof salesTrendSeries !== 'object') {
      return { labels: [], revenue: [], units: [] };
    }
    const candidate = hasInterval(interval) ? salesTrendSeries[interval] : null;
    if (!candidate || typeof candidate !== 'object') {
      return { labels: [], revenue: [], units: [] };
    }
    const labels = Array.isArray(candidate.labels) ? candidate.labels.slice() : [];
    const revenue = Array.isArray(candidate.revenue)
      ? candidate.revenue.map(value => {
          const numeric = Number.parseFloat(value);
          return Number.isFinite(numeric) ? Math.round(numeric) : 0;
        })
      : [];
    const units = Array.isArray(candidate.units)
      ? candidate.units.map(value => {
          const numeric = Number.parseFloat(value);
          return Number.isFinite(numeric) ? Math.round(numeric) : 0;
        })
      : [];
    return { labels, revenue, units };
  };

  const computePointRadius = labels => (Array.isArray(labels) && labels.length > 16 ? 3 : 6);
  const computeHoverRadius = radius => (radius <= 3 ? 5 : 8);

  const updateIntervalButtons = interval => {
    if (!intervalButtons.length) {
      return;
    }
    intervalButtons.forEach(button => {
      const buttonInterval = button.getAttribute('data-sales-interval');
      const isActive = buttonInterval === interval;
      if (isActive) {
        button.classList.add('is-active');
        button.setAttribute('aria-pressed', 'true');
      } else {
        button.classList.remove('is-active');
        button.setAttribute('aria-pressed', 'false');
      }
    });
  };

  const updateCaption = (series, interval) => {
    if (!salesTrendCaption) {
      return;
    }
    const captionParts = [];
    if (reportRangeLabel) {
      captionParts.push(reportRangeLabel);
    }
    const intervalLabel = hasInterval(interval) ? intervalLabels[interval] : intervalLabels.hourly;
    captionParts.push(intervalLabel + ' view');
    const hasRevenue = Array.isArray(series.revenue) && series.revenue.some(value => {
      const numeric = Number.parseFloat(value);
      return Number.isFinite(numeric) && Math.abs(numeric) > 0;
    });
    if (!hasRevenue) {
      captionParts.push('No sales recorded');
    }
    salesTrendCaption.textContent = captionParts.join(' • ');
  };

  let activeInterval = 'hourly';
  let salesChart = null;

  const applySalesTrendInterval = interval => {
    const normalizedInterval = hasInterval(interval) ? interval : 'hourly';
    const series = getSeriesForInterval(normalizedInterval);
    activeInterval = normalizedInterval;
    if (salesChart) {
      const dataset = salesChart.data.datasets[0];
      dataset.data = series.revenue.slice();
      dataset.units = series.units.slice();
      const pointRadius = computePointRadius(series.labels);
      dataset.pointRadius = pointRadius;
      dataset.pointHoverRadius = computeHoverRadius(pointRadius);
      salesChart.data.labels = series.labels.slice();
      if (
        salesChart.options &&
        salesChart.options.scales &&
        salesChart.options.scales.x &&
        salesChart.options.scales.x.title
      ) {
        salesChart.options.scales.x.title.text = axisLabels[normalizedInterval] || 'Period';
      }
      salesChart.update();
    }
    updateCaption(series, normalizedInterval);
    updateIntervalButtons(normalizedInterval);
  };

  if (salesChartCanvas) {
    const ctx = salesChartCanvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, salesChartCanvas.height || 400);
    gradient.addColorStop(0, 'rgba(231, 76, 60, 0.4)');
    gradient.addColorStop(1, 'rgba(231, 76, 60, 0)');

    const initialSeries = getSeriesForInterval(activeInterval);
    const initialPointRadius = computePointRadius(initialSeries.labels);
    const initialHoverRadius = computeHoverRadius(initialPointRadius);

    salesChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: initialSeries.labels.slice(),
        datasets: [{
          label: 'Revenue (₱)',
          data: initialSeries.revenue.slice(),
          units: initialSeries.units.slice(),
          borderColor: '#e74c3c',
          borderWidth: 3,
          backgroundColor: gradient,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#e74c3c',
          pointRadius: initialPointRadius,
          pointHoverRadius: initialHoverRadius,
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
                const revenueLabel = 'Revenue: ₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const unitsCollection = Array.isArray(context.dataset.units) ? context.dataset.units : [];
                const unitsValue = unitsCollection[context.dataIndex] ?? 0;
                const unitsLabel = 'Units sold: ' + Number(unitsValue).toLocaleString();
                return [revenueLabel, unitsLabel];
              }
            }
          }
        },
        scales: {
          x: {
            title: { display: true, text: axisLabels[activeInterval] },
            ticks: {
              autoSkip: true,
              maxRotation: 0,
              maxTicksLimit: 12,
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            }
          }
        }
      }
    });

    updateCaption(initialSeries, activeInterval);
    updateIntervalButtons(activeInterval);
  } else {
    updateCaption(getSeriesForInterval(activeInterval), activeInterval);
    updateIntervalButtons(activeInterval);
  }

  if (intervalButtons.length) {
    intervalButtons.forEach(button => {
      button.addEventListener('click', () => {
        const interval = button.getAttribute('data-sales-interval') || 'hourly';
        if (interval === activeInterval) {
          return;
        }
        applySalesTrendInterval(interval);
      });
    });
  }

  const topProductChartEl = document.getElementById('topProductChart');
  if (topProductChartEl) {
    const hasTopProducts = Array.isArray(topProductLabels) && topProductLabels.length > 0;
    const labels = hasTopProducts ? topProductLabels : ['No Sales'];
    const revenueData = hasTopProducts ? topProductRevenue : [0];
    const unitData = hasTopProducts ? topProductUnits : [0];

    new Chart(topProductChartEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Revenue (₱)',
            data: revenueData,
            backgroundColor: '#3498db',
            borderRadius: 6
          }
        ]
      },
      options: {
        plugins: {
          tooltip: {
            callbacks: {
              label: context => {
                const revenue = context.parsed.y ?? 0;
                const units = unitData[context.dataIndex] ?? 0;
                const revenueLabel = 'Revenue: ₱' + Number(revenue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const unitsLabel = 'Units sold: ' + Number(units).toLocaleString();
                return [revenueLabel, unitsLabel];
              }
            }
          },
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: value => '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            }
          }
        }
      }
    });
  }

  const categoryChartEl = document.getElementById('categoryChart');
  if (categoryChartEl) {
    const hasCategoryData = Array.isArray(categoryLabels) && categoryLabels.length > 0;
    const labels = hasCategoryData ? categoryLabels : ['No Sales'];
    const revenueData = hasCategoryData ? categoryRevenue : [0];
    const unitData = hasCategoryData ? categoryUnits : [0];

    new Chart(categoryChartEl, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [
          {
            data: revenueData,
            backgroundColor: ['#9b59b6', '#1abc9c', '#f1c40f', '#e74c3c', '#2ecc71', '#34495e']
          }
        ]
      },
      options: {
        plugins: {
          tooltip: {
            callbacks: {
              label: context => {
                const revenue = context.parsed ?? 0;
                const units = unitData[context.dataIndex] ?? 0;
                const revenueLabel = 'Revenue: ₱' + Number(revenue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const unitsLabel = 'Units sold: ' + Number(units).toLocaleString();
                return context.label + ': ' + revenueLabel + ' • ' + unitsLabel;
              }
            }
          },
          legend: { position: 'bottom' }
        }
      }
    });
  }

  const searchInput = document.getElementById('productSalesSearch');
  if (searchInput) {
    const rows = Array.from(document.querySelectorAll('#productSalesTable tbody tr'));
    const applySearchFilter = () => {
      const query = searchInput.value.trim().toLowerCase();
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    };
    searchInput.addEventListener('input', applySearchFilter);
    applySearchFilter();
  }

  const statusSearchInput = document.getElementById('statusBreakdownSearch');
  if (statusSearchInput) {
    const statusRows = Array.from(document.querySelectorAll('#statusSalesTable tbody tr'));
    const applyStatusFilter = () => {
      const query = statusSearchInput.value.trim().toLowerCase();
      statusRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    };
    statusSearchInput.addEventListener('input', applyStatusFilter);
    applyStatusFilter();
  }

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

  const showProductSalesPdfPreview = (pdfDoc, filename) => {
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

    const existingOverlay = document.getElementById('productSalesPdfPreviewOverlay');
    if (existingOverlay) {
      existingOverlay.remove();
    }

    if (!document.body) {
      pdfDoc.save(filename);
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
        blobUrl = null;
      }
      return;
    }

    const overlay = document.createElement('div');
    overlay.id = 'productSalesPdfPreviewOverlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Product sales report PDF preview');
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
    frame.title = 'Product sales report preview';
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
      'background:#e74c3c',
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
        blobUrl = null;
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

  const exportBtn = document.getElementById('exportProductSales');
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
        window.alert('PDF generator is not ready yet. Please try again in a moment.');
        return;
      }

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF({ orientation: 'landscape' });

      const rows = [];
      document.querySelectorAll('#productSalesTable tbody tr').forEach(tr => {
        if (rowIsHidden(tr)) {
          return;
        }
        const cells = Array.from(tr.cells).map(td => td.textContent.trim());
        rows.push(cells);
      });

      if (rows.length === 0) {
        window.alert('No visible rows to export. Please adjust your filters and try again.');
        return;
      }

      doc.setFontSize(16);
      doc.text('Product Sales Report', 14, 18);
      doc.setFontSize(11);
      doc.setTextColor(80);

      const exportNow = addEightHours(new Date()) || new Date();
      const generatedLabel = new Intl.DateTimeFormat(undefined, {
        timeZone: MANILA_TIME_ZONE,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      }).format(exportNow);
      let nextLineY = 26;
      doc.text('Generated on: ' + generatedLabel, 14, nextLineY);
      nextLineY += 8;
      if (typeof reportRangeLabel === 'string' && reportRangeLabel.trim().length > 0) {
        doc.text('Range: ' + reportRangeLabel, 14, nextLineY);
        nextLineY += 8;
      }
      if (typeof lastSaleLabel === 'string' && lastSaleLabel.trim().length > 0) {
        doc.text('Last sale recorded: ' + lastSaleLabel, 14, nextLineY);
        nextLineY += 8;
      }
      doc.setTextColor(0);

      doc.autoTable({
        startY: nextLineY + 4,
        head: [['Product', 'Category', 'Units Sold', 'Revenue', 'Orders', 'First Sale', 'Last Sale', 'Avg Item Price']],
        body: rows,
        theme: 'grid',
        styles: { fontSize: 9 }
      });

      showProductSalesPdfPreview(doc, 'product-sales-report.pdf');
    });
  }
</script>
JS;
include 'includes/footer.php';
