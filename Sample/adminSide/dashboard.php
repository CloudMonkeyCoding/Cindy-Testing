<?php
require_once __DIR__ . '/includes/require_admin_login.php';

require_once '../PHP/db_connect.php';
require_once '../PHP/order_functions.php';
require_once '../PHP/order_item_functions.php';
require_once '../PHP/transaction_functions.php';
require_once '../PHP/inventory_functions.php';
require_once '../PHP/user_functions.php';
require_once '../PHP/product_functions.php';

$activePage = 'dashboard';
$pageTitle = "Dashboard - Cindy's Bakeshop";
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

$timeframeOptions = [
    'last_7_days' => 'Last 7 Days',
    'last_30_days' => 'Last 30 Days',
    'last_90_days' => 'Last 90 Days',
    'last_year' => 'Last 1 Year',
    'all_time' => 'All Time',
    'custom' => 'Custom Range'
];

function sanitizeDateValue($value)
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return ($date && $date->format('Y-m-d') === $value) ? $value : null;
}

function getDashboardAllTimeBounds(PDO $pdo)
{
    $start = null;
    $end = null;

    $updateBoundary = function (?DateTimeImmutable $current, ?string $candidate, bool $preferLower) {
        if (!$candidate || $candidate === '0000-00-00' || $candidate === '0000-00-00 00:00:00') {
            return $current;
        }

        try {
            $date = new DateTimeImmutable($candidate);
        } catch (\Exception $e) {
            return $current;
        }

        $date = $date->setTime(0, 0, 0);

        if ($current === null) {
            return $date;
        }

        $currentTimestamp = $current->getTimestamp();
        $candidateTimestamp = $date->getTimestamp();

        if ($preferLower) {
            return ($candidateTimestamp < $currentTimestamp) ? $date : $current;
        }

        return ($candidateTimestamp > $currentTimestamp) ? $date : $current;
    };

    $rangeQueries = [
        "SELECT MIN(Order_Date) AS min_date, MAX(Order_Date) AS max_date FROM `order`",
        "SELECT MIN(Payment_Date) AS min_date, MAX(Payment_Date) AS max_date FROM transaction WHERE Payment_Date IS NOT NULL",
    ];

    foreach ($rangeQueries as $sql) {
        try {
            $stmt = $pdo->query($sql);
        } catch (\PDOException $e) {
            $stmt = false;
        }

        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $start = $updateBoundary($start, $row['min_date'] ?? null, true);
            $end = $updateBoundary($end, $row['max_date'] ?? null, false);
        }
    }

    $userDateColumn = getDateColumnIfExists($pdo, 'user', [
        'Created_At',
        'CreatedAt',
        'Registration_Date',
        'Registered_On',
        'Date_Created',
        'DateCreated',
        'Joined_At',
    ]);

    if ($userDateColumn) {
        $sql = sprintf(
            'SELECT MIN(`%1$s`) AS min_date, MAX(`%1$s`) AS max_date FROM user WHERE `%1$s` IS NOT NULL',
            $userDateColumn
        );

        try {
            $stmt = $pdo->query($sql);
        } catch (\PDOException $e) {
            $stmt = false;
        }

        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $start = $updateBoundary($start, $row['min_date'] ?? null, true);
            $end = $updateBoundary($end, $row['max_date'] ?? null, false);
        }
    }

    $inventoryDateColumn = getDateColumnIfExists($pdo, 'inventory', [
        'Updated_At',
        'UpdatedAt',
        'Last_Updated',
        'LastUpdated',
        'Created_At',
        'CreatedAt',
        'Date_Added',
        'DateAdded',
    ]);

    if ($inventoryDateColumn) {
        $sql = sprintf(
            'SELECT MIN(`%1$s`) AS min_date, MAX(`%1$s`) AS max_date FROM inventory WHERE `%1$s` IS NOT NULL',
            $inventoryDateColumn
        );

        try {
            $stmt = $pdo->query($sql);
        } catch (\PDOException $e) {
            $stmt = false;
        }

        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $start = $updateBoundary($start, $row['min_date'] ?? null, true);
            $end = $updateBoundary($end, $row['max_date'] ?? null, false);
        }
    }

    if ($start === null && $end === null) {
        return null;
    }

    if ($start === null) {
        $start = $end;
    } elseif ($end === null) {
        $end = $start;
    }

    if ($start && $end && $start->getTimestamp() > $end->getTimestamp()) {
        [$start, $end] = [$end, $start];
    }

    $today = new DateTimeImmutable('today');
    if ($end && $end->getTimestamp() < $today->getTimestamp()) {
        $end = $today;
    }

    return [
        'start' => $start,
        'end' => $end,
    ];
}

function resolveDashboardDateRange($timeframe, $customStart, $customEnd, ?PDO $pdo = null)
{
    $today = new DateTimeImmutable('today');
    $lastThirtyStart = $today->modify('-29 days');

    switch ($timeframe) {
        case 'last_7_days':
            return [
                'start' => $today->modify('-6 days'),
                'end' => $today,
                'timeframe' => 'last_7_days',
                'customStart' => null,
                'customEnd' => null,
            ];
        case 'last_30_days':
            return [
                'start' => $lastThirtyStart,
                'end' => $today,
                'timeframe' => 'last_30_days',
                'customStart' => null,
                'customEnd' => null,
            ];
        case 'last_90_days':
            return [
                'start' => $today->modify('-89 days'),
                'end' => $today,
                'timeframe' => 'last_90_days',
                'customStart' => null,
                'customEnd' => null,
            ];
        case 'last_year':
            return [
                'start' => $today->modify('-364 days'),
                'end' => $today,
                'timeframe' => 'last_year',
                'customStart' => null,
                'customEnd' => null,
            ];
        case 'all_time':
            if ($pdo) {
                $bounds = getDashboardAllTimeBounds($pdo);
                if (is_array($bounds) && isset($bounds['start'], $bounds['end'])) {
                    return [
                        'start' => $bounds['start'],
                        'end' => $bounds['end'],
                        'timeframe' => 'all_time',
                        'customStart' => null,
                        'customEnd' => null,
                    ];
                }
            }

            return [
                'start' => $lastThirtyStart,
                'end' => $today,
                'timeframe' => 'all_time',
                'customStart' => null,
                'customEnd' => null,
            ];
        case 'custom':
            $start = $customStart ? DateTimeImmutable::createFromFormat('!Y-m-d', $customStart) : null;
            $end = $customEnd ? DateTimeImmutable::createFromFormat('!Y-m-d', $customEnd) : null;

            if ($start && $end) {
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                return [
                    'start' => $start,
                    'end' => $end,
                    'timeframe' => 'custom',
                    'customStart' => $start->format('Y-m-d'),
                    'customEnd' => $end->format('Y-m-d'),
                ];
            }
            break;
    }

    return [
        'start' => $lastThirtyStart,
        'end' => $today,
        'timeframe' => 'last_30_days',
        'customStart' => null,
        'customEnd' => null,
    ];
}

function getDateColumnIfExists(PDO $pdo, $table, array $candidates)
{
    static $cache = [];

    $cacheKey = $table . '|' . implode(',', $candidates);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (empty($candidates)) {
        $cache[$cacheKey] = null;
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($candidates), '?'));
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME IN ($placeholders) LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$table], $candidates));
    $column = $stmt->fetchColumn() ?: null;

    $cache[$cacheKey] = $column;

    return $column;
}

$selectedTimeframe = $_SESSION['dashboard_timeframe'] ?? 'last_30_days';
if (isset($_GET['timeframe']) && is_string($_GET['timeframe']) && isset($timeframeOptions[$_GET['timeframe']])) {
    $selectedTimeframe = $_GET['timeframe'];
}

$requestedCustomStart = isset($_GET['start_date']) ? sanitizeDateValue($_GET['start_date']) : null;
$requestedCustomEnd = isset($_GET['end_date']) ? sanitizeDateValue($_GET['end_date']) : null;

$sessionCustomStart = isset($_SESSION['dashboard_custom_start']) ? sanitizeDateValue($_SESSION['dashboard_custom_start']) : null;
$sessionCustomEnd = isset($_SESSION['dashboard_custom_end']) ? sanitizeDateValue($_SESSION['dashboard_custom_end']) : null;

$customStartInput = $requestedCustomStart ?? $sessionCustomStart;
$customEndInput = $requestedCustomEnd ?? $sessionCustomEnd;

$range = resolveDashboardDateRange($selectedTimeframe, $customStartInput, $customEndInput, $pdo);

$rangeStart = $range['start'];
$rangeEnd = $range['end'];
$selectedTimeframe = $range['timeframe'];
$customRangeStart = $range['customStart'];
$customRangeEnd = $range['customEnd'];

$_SESSION['dashboard_timeframe'] = $selectedTimeframe;
$_SESSION['dashboard_range_start'] = $rangeStart->format('Y-m-d');
$_SESSION['dashboard_range_end'] = $rangeEnd->format('Y-m-d');
if ($selectedTimeframe === 'custom') {
    $_SESSION['dashboard_custom_start'] = $customRangeStart;
    $_SESSION['dashboard_custom_end'] = $customRangeEnd;
}

$rangeStartFormatted = $rangeStart->format('Y-m-d');
$rangeEndFormatted = $rangeEnd->format('Y-m-d');
$rangeDisplay = $rangeStart->format('F j, Y') . ' – ' . $rangeEnd->format('F j, Y');
$rangeDays = (int)$rangeStart->diff($rangeEnd)->format('%a') + 1;
$salesGranularity = $rangeDays <= 31 ? 'daily' : 'monthly';
$salesGranularityLabel = $salesGranularity === 'daily' ? 'Daily' : 'Monthly';
$salesChartTitle = $salesGranularity === 'daily' ? 'Sales Trend (Daily)' : 'Sales Trend (Monthly)';
$timeframeLabel = $timeframeOptions[$selectedTimeframe] ?? 'Custom Range';
$customStartValue = $customRangeStart ?? ($sessionCustomStart ?? '');
$customEndValue = $customRangeEnd ?? ($sessionCustomEnd ?? '');
$showCustomRange = $selectedTimeframe === 'custom';

$totalOrders = 0;
$pendingOrders = 0;
$deliveredOrders = 0;
$totalRevenue = 0.0;
$totalUsers = 0;
$lowStockCount = 0;
$lowStockProducts = [];
$topProduct = null;
$topProductName = null;
$topProductQty = null;
$salesTrendSeries = [];
$categoryBreakdown = [];
$categoryHasData = false;
$recentOrders = [];
$userFilterApplied = false;
$inventoryFilterApplied = false;

if ($pdo) {
    $totalOrders = countOrders($pdo, $rangeStartFormatted, $rangeEndFormatted);
    $pendingOrders = count(getOrdersByStatus($pdo, 'Pending', $rangeStartFormatted, $rangeEndFormatted));
    $deliveredOrders = count(getOrdersByStatus($pdo, 'Delivered', $rangeStartFormatted, $rangeEndFormatted));

    $stmtRevenue = $pdo->prepare("SELECT COALESCE(SUM(Amount_Paid), 0) FROM transaction WHERE Payment_Date IS NOT NULL AND Payment_Date BETWEEN :start_date AND :end_date");
    $stmtRevenue->execute([
        ':start_date' => $rangeStartFormatted,
        ':end_date' => $rangeEndFormatted,
    ]);
    $totalRevenue = (float)$stmtRevenue->fetchColumn();

    $userDateColumn = $pdo ? getDateColumnIfExists($pdo, 'user', [
        'Created_At',
        'CreatedAt',
        'Registration_Date',
        'Registered_On',
        'Date_Created',
        'DateCreated',
        'Joined_At',
    ]) : null;
    $totalUsers = (int)countUsers($pdo, $rangeStartFormatted, $rangeEndFormatted, $userDateColumn);
    $userFilterApplied = (bool)$userDateColumn;

    $inventoryThreshold = 'i.Stock_Quantity IS NULL OR i.Stock_Quantity <= 10';
    $inventoryDateColumn = $pdo ? getDateColumnIfExists($pdo, 'inventory', [
        'Updated_At',
        'UpdatedAt',
        'Last_Updated',
        'LastUpdated',
        'Created_At',
        'CreatedAt',
        'Date_Added',
        'DateAdded',
    ]) : null;

    if ($inventoryDateColumn) {
        $inventoryDateClause = sprintf('i.`%s` BETWEEN :start_date AND :end_date', $inventoryDateColumn);
        $stmtLowStockCount = $pdo->prepare("SELECT COUNT(*) FROM inventory i WHERE ($inventoryThreshold) AND $inventoryDateClause");
        $stmtLowStockCount->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $lowStockCount = (int)$stmtLowStockCount->fetchColumn();

        $stmtLowStock = $pdo->prepare("
            SELECT p.Name, i.Stock_Quantity
            FROM inventory i
            JOIN product p ON i.Product_ID = p.Product_ID
            WHERE ($inventoryThreshold) AND $inventoryDateClause
            ORDER BY i.Stock_Quantity ASC, p.Name ASC
            LIMIT 6
        ");
        $stmtLowStock->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $lowStockProducts = $stmtLowStock->fetchAll(PDO::FETCH_ASSOC);
        $inventoryFilterApplied = true;
    } else {
        $stmtLowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory i WHERE $inventoryThreshold");
        $lowStockCount = (int)($stmtLowStockCount ? $stmtLowStockCount->fetchColumn() : 0);

        $stmtLowStock = $pdo->query("
            SELECT p.Name, i.Stock_Quantity
            FROM inventory i
            JOIN product p ON i.Product_ID = p.Product_ID
            WHERE $inventoryThreshold
            ORDER BY i.Stock_Quantity ASC, p.Name ASC
            LIMIT 6
        ");
        $lowStockProducts = $stmtLowStock ? $stmtLowStock->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    $stmtTopProduct = $pdo->prepare("
        SELECT p.Name, SUM(oi.Quantity) AS total_qty
        FROM order_item oi
        JOIN product p ON oi.Product_ID = p.Product_ID
        JOIN `order` o ON oi.Order_ID = o.Order_ID
        WHERE o.Order_Date BETWEEN :start_date AND :end_date
        GROUP BY p.Product_ID, p.Name
        ORDER BY total_qty DESC
        LIMIT 1
    ");
    $stmtTopProduct->execute([
        ':start_date' => $rangeStartFormatted,
        ':end_date' => $rangeEndFormatted,
    ]);
    $topProduct = $stmtTopProduct->fetch(PDO::FETCH_ASSOC) ?: null;
    $topProductName = is_array($topProduct) ? ($topProduct['Name'] ?? null) : null;
    $topProductQty = is_array($topProduct) && isset($topProduct['total_qty']) ? (int)$topProduct['total_qty'] : null;

    if ($salesGranularity === 'daily') {
        $stmtDailyRevenue = $pdo->prepare("
            SELECT DATE(Payment_Date) AS period, COALESCE(SUM(Amount_Paid), 0) AS total
            FROM transaction
            WHERE Payment_Date IS NOT NULL
              AND Payment_Date BETWEEN :start_date AND :end_date
            GROUP BY DATE(Payment_Date)
            ORDER BY period ASC
        ");
        $stmtDailyRevenue->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $dailyRevenueTotals = [];
        foreach ($stmtDailyRevenue->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dailyRevenueTotals[$row['period']] = (float)($row['total'] ?? 0);
        }

        $stmtDailyOrders = $pdo->prepare("
            SELECT DATE(Order_Date) AS period, COUNT(*) AS total
            FROM `order`
            WHERE Order_Date BETWEEN :start_date AND :end_date
            GROUP BY DATE(Order_Date)
            ORDER BY period ASC
        ");
        $stmtDailyOrders->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $dailyOrderTotals = [];
        foreach ($stmtDailyOrders->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dailyOrderTotals[$row['period']] = (int)($row['total'] ?? 0);
        }

        for ($date = $rangeStart; $date <= $rangeEnd; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $revenue = $dailyRevenueTotals[$key] ?? 0.0;
            $orders = $dailyOrderTotals[$key] ?? 0;
            $salesTrendSeries[] = [
                'label' => $date->format('M j'),
                'revenue' => round($revenue, 2),
                'orders' => $orders,
            ];
        }
    } else {
        $stmtMonthlyRevenue = $pdo->prepare("
            SELECT DATE_FORMAT(Payment_Date, '%Y-%m') AS period, COALESCE(SUM(Amount_Paid), 0) AS total
            FROM transaction
            WHERE Payment_Date IS NOT NULL
              AND Payment_Date BETWEEN :start_date AND :end_date
            GROUP BY DATE_FORMAT(Payment_Date, '%Y-%m')
            ORDER BY period ASC
        ");
        $stmtMonthlyRevenue->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $monthlyRevenueTotals = [];
        foreach ($stmtMonthlyRevenue->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthlyRevenueTotals[$row['period']] = (float)($row['total'] ?? 0);
        }

        $stmtMonthlyOrders = $pdo->prepare("
            SELECT DATE_FORMAT(Order_Date, '%Y-%m') AS period, COUNT(*) AS total
            FROM `order`
            WHERE Order_Date BETWEEN :start_date AND :end_date
            GROUP BY DATE_FORMAT(Order_Date, '%Y-%m')
            ORDER BY period ASC
        ");
        $stmtMonthlyOrders->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $monthlyOrderTotals = [];
        foreach ($stmtMonthlyOrders->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthlyOrderTotals[$row['period']] = (int)($row['total'] ?? 0);
        }

        $monthCursor = new DateTimeImmutable($rangeStart->format('Y-m-01'));
        $monthEndCursor = new DateTimeImmutable($rangeEnd->format('Y-m-01'));

        while ($monthCursor <= $monthEndCursor) {
            $key = $monthCursor->format('Y-m');
            $revenue = $monthlyRevenueTotals[$key] ?? 0.0;
            $orders = $monthlyOrderTotals[$key] ?? 0;
            $salesTrendSeries[] = [
                'label' => $monthCursor->format('M Y'),
                'revenue' => round($revenue, 2),
                'orders' => $orders,
            ];
            $monthCursor = $monthCursor->modify('+1 month');
        }
    }

    $categoryExpression = "COALESCE(NULLIF(TRIM(p.Category), ''), 'Uncategorized')";
    $statusFilter = ['Delivered', 'Completed'];
    $statusPlaceholders = [];
    $categoryParams = [
        ':start_date' => $rangeStartFormatted,
        ':end_date' => $rangeEndFormatted,
    ];

    foreach ($statusFilter as $index => $status) {
        $placeholder = ':status_' . $index;
        $statusPlaceholders[] = $placeholder;
        $categoryParams[$placeholder] = $status;
    }

    $categoryQuery = "
        SELECT
            $categoryExpression AS category_name,
            COALESCE(SUM(oi.Subtotal), 0) AS total_revenue,
            COALESCE(SUM(oi.Quantity), 0) AS total_units
        FROM order_item oi
        JOIN product p ON oi.Product_ID = p.Product_ID
        JOIN `order` o ON o.Order_ID = oi.Order_ID
        WHERE o.Order_Date BETWEEN :start_date AND :end_date
    ";
    if ($statusPlaceholders) {
        $categoryQuery .= ' AND o.Status IN (' . implode(',', $statusPlaceholders) . ')';
    }
    $categoryQuery .= "
        GROUP BY $categoryExpression
        ORDER BY total_revenue DESC
        LIMIT 5
    ";

    $stmtCategory = $pdo->prepare($categoryQuery);
    $stmtCategory->execute($categoryParams);
    $categoryRows = $stmtCategory->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $categoryRows = array_values(array_filter($categoryRows, static function ($row) {
        $revenue = isset($row['total_revenue']) ? (float)$row['total_revenue'] : 0.0;
        $units = isset($row['total_units']) ? (float)$row['total_units'] : 0.0;
        return $revenue > 0 || $units > 0;
    }));

    if (empty($categoryRows)) {
        $fallbackQuery = "
            SELECT
                $categoryExpression AS category_name,
                COALESCE(SUM(oi.Subtotal), 0) AS total_revenue,
                COALESCE(SUM(oi.Quantity), 0) AS total_units
            FROM order_item oi
            JOIN product p ON oi.Product_ID = p.Product_ID
            JOIN `order` o ON o.Order_ID = oi.Order_ID
            WHERE o.Order_Date BETWEEN :start_date AND :end_date
            GROUP BY $categoryExpression
            ORDER BY total_revenue DESC
            LIMIT 5
        ";
        $stmtFallback = $pdo->prepare($fallbackQuery);
        $stmtFallback->execute([
            ':start_date' => $rangeStartFormatted,
            ':end_date' => $rangeEndFormatted,
        ]);
        $categoryRows = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $categoryRows = array_values(array_filter($categoryRows, static function ($row) {
            $revenue = isset($row['total_revenue']) ? (float)$row['total_revenue'] : 0.0;
            $units = isset($row['total_units']) ? (float)$row['total_units'] : 0.0;
            return $revenue > 0 || $units > 0;
        }));
    }

    $categoryBreakdown = array_map(static function ($row) {
        $categoryName = $row['category_name'] ?? 'Uncategorized';
        $normalized = normalizeProductCategoryValue($categoryName);
        return [
            'label' => $normalized === '' ? 'Uncategorized' : $normalized,
            'revenue' => round((float)($row['total_revenue'] ?? 0), 2),
            'units' => (int)round((float)($row['total_units'] ?? 0)),
        ];
    }, $categoryRows);

    $categoryHasData = !empty($categoryBreakdown);

    $stmtRecent = $pdo->prepare("
        SELECT o.Order_ID, o.Order_Date, o.Status, u.Name, COALESCE(SUM(oi.Subtotal), 0) AS Total
        FROM `order` o
        LEFT JOIN user u ON o.User_ID = u.User_ID
        LEFT JOIN order_item oi ON oi.Order_ID = o.Order_ID
        WHERE o.Order_Date BETWEEN :start_date AND :end_date
        GROUP BY o.Order_ID, o.Order_Date, o.Status, u.Name
        ORDER BY o.Order_Date DESC, o.Order_ID DESC
        LIMIT 6
    ");
    $stmtRecent->execute([
        ':start_date' => $rangeStartFormatted,
        ':end_date' => $rangeEndFormatted,
    ]);
    $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($recentOrders) {
        $recentOrders = array_map(static function (array $order): array {
            $rawDate = $order['Order_Date'] ?? null;
            if ($rawDate) {
                $formatted = formatAdminDateTime($rawDate, 'F j, Y');
                $order['Order_Date_Formatted'] = $formatted !== '' ? $formatted : $rawDate;
            } else {
                $order['Order_Date_Formatted'] = '';
            }

            return $order;
        }, $recentOrders);
    }
}

if (empty($salesTrendSeries)) {
    $salesTrendSeries[] = [
        'label' => $salesGranularity === 'daily' ? $rangeStart->format('M j') : $rangeStart->format('M Y'),
        'revenue' => 0.0,
        'orders' => 0,
    ];
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

$salesSeriesJson = json_encode($salesTrendSeries, $jsonFlags);
if ($salesSeriesJson === false) {
    $salesSeriesJson = '[]';
}

$categorySeries = $categoryHasData ? $categoryBreakdown : [[
    'label' => 'No Data',
    'revenue' => 0.0,
    'units' => 0,
]];

$categorySeriesJson = json_encode($categorySeries, $jsonFlags);
if ($categorySeriesJson === false) {
    $categorySeriesJson = '[]';
}

$ordersCardSubtitle = 'Orders placed in range';
$pendingCardSubtitle = 'Pending orders in range';
$deliveredCardSubtitle = 'Delivered orders in range';
$revenueCardSubtitle = 'Revenue collected in range';
$userCardSubtitle = $userFilterApplied ? 'Customers added in range' : 'Registered customers';
$inventoryCardSubtitle = $inventoryFilterApplied ? 'Low stock flagged in range' : 'Items at or below threshold';
$topProductSubtitle = $topProductQty !== null ? 'Sold ' . number_format($topProductQty) . ' pcs in range' : 'No product sales in range';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <div class="header-controls">
      <h1>Welcome back!</h1>
      <form id="dashboard-timeframe-form" class="timeframe-form" method="get" action="">
        <select name="timeframe" id="dashboard-timeframe" aria-label="Select timeframe">
          <?php foreach ($timeframeOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key); ?>" <?= $selectedTimeframe === $key ? 'selected' : ''; ?>>
              <?= htmlspecialchars($label); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div id="dashboard-custom-range" class="timeframe-custom-fields" style="<?= $showCustomRange ? '' : 'display:none;'; ?>">
          <input type="date" name="start_date" id="dashboard-start-date" value="<?= htmlspecialchars($customStartValue); ?>" <?= $showCustomRange ? '' : 'disabled'; ?> aria-label="Custom range start">
          <span class="range-separator">to</span>
          <input type="date" name="end_date" id="dashboard-end-date" value="<?= htmlspecialchars($customEndValue); ?>" <?= $showCustomRange ? '' : 'disabled'; ?> aria-label="Custom range end">
          <button type="submit" class="btn btn-primary">Apply</button>
        </div>
      </form>
      <p class="timeframe-summary">Showing <?= htmlspecialchars($timeframeLabel); ?> &middot; <?= htmlspecialchars($rangeDisplay); ?></p>
    </div>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <section class="cards">
    <a class="card" href="orders.php" aria-label="View all orders">
      <div class="card-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= number_format($totalOrders); ?></h3>
        <p><?= htmlspecialchars($ordersCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="orders.php?status=Pending" aria-label="View pending orders">
      <div class="card-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= number_format($pendingOrders); ?></h3>
        <p><?= htmlspecialchars($pendingCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="orders.php?status=Delivered" aria-label="View delivered orders">
      <div class="card-icon"><i class="fa-solid fa-truck" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= number_format($deliveredOrders); ?></h3>
        <p><?= htmlspecialchars($deliveredCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="financial-report.php" aria-label="View financial reports">
      <div class="card-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3>₱<?= number_format($totalRevenue, 2); ?></h3>
        <p><?= htmlspecialchars($revenueCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="users.php" aria-label="Manage users">
      <div class="card-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= number_format($totalUsers); ?></h3>
        <p><?= htmlspecialchars($userCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="products.php" aria-label="Review product inventory">
      <div class="card-icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= number_format($lowStockCount); ?></h3>
        <p><?= htmlspecialchars($inventoryCardSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="product-sales-report.php" aria-label="View top product details">
      <div class="card-icon"><i class="fa-solid fa-crown" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3><?= $topProductName ? htmlspecialchars($topProductName) : 'No data'; ?></h3>
        <p><?= htmlspecialchars($topProductSubtitle); ?></p>
      </div>
    </a>
    <a class="card" href="report.php" aria-label="Generate PDF reports">
      <div class="card-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></div>
      <div class="card-info">
        <h3>Reports</h3>
        <p>Generate PDF summaries</p>
        <span class="card-link">View reports <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
      </div>
    </a>
  </section>

  <div class="stats-grid columns-4" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
    <div class="card">
      <div class="chart-card-header">
        <div>
          <h2 class="chart-title" style="margin:0;">
            <?= htmlspecialchars($salesChartTitle); ?>
          </h2>
          <p class="chart-caption">Viewing <span id="salesMetricLabel">Revenue</span> totals &bull; <?= htmlspecialchars($rangeDisplay); ?></p>
        </div>
        <div class="chart-filter-group" role="group" aria-label="Sales metric">
          <button type="button" class="chart-filter-button is-active" data-sales-metric="revenue" aria-pressed="true">Revenue</button>
          <button type="button" class="chart-filter-button" data-sales-metric="orders" aria-pressed="false">Orders</button>
        </div>
      </div>
      <canvas id="salesChart" height="220"></canvas>
    </div>
    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Low Stock Alerts</h2>
      <?php if (empty($lowStockProducts)): ?>
        <p class="table-empty">All items have sufficient stock.</p>
      <?php else: ?>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($lowStockProducts as $item): ?>
            <li style="display:flex;justify-content:space-between;align-items:center;">
              <span><?= htmlspecialchars($item['Name']); ?></span>
              <span class="badge badge-danger">Stock: <?= is_null($item['Stock_Quantity']) ? 'Pre-order' : number_format($item['Stock_Quantity']); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="stats-grid columns-4" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
    <div class="card">
      <h2 style="font-size:18px;margin-bottom:16px;">Recent Orders</h2>
      <?php if (empty($recentOrders)): ?>
        <p class="table-empty">No recent orders found.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td>#<?= str_pad($order['Order_ID'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?= htmlspecialchars($order['Name'] ?? 'Walk-in'); ?></td>
                <td>₱<?= number_format($order['Total'] ?? 0, 2); ?></td>
                <td>
                  <span class="status-pill status-<?= strtolower($order['Status']); ?>">
                    <?= htmlspecialchars($order['Status']); ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($order['Order_Date_Formatted'] ?? $order['Order_Date'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="chart-card-header">
        <div>
          <h2 style="font-size:18px;margin:0;">Top Category Performance</h2>
          <p class="chart-caption">Viewing <span id="categoryMetricLabel">Revenue</span> share &bull; <?= htmlspecialchars($rangeDisplay); ?></p>
        </div>
        <?php if ($categoryHasData): ?>
          <div class="chart-filter-group" role="group" aria-label="Category metric">
            <button type="button" class="chart-filter-button is-active" data-category-metric="revenue" aria-pressed="true">Revenue</button>
            <button type="button" class="chart-filter-button" data-category-metric="units" aria-pressed="false">Units</button>
          </div>
        <?php endif; ?>
      </div>
      <?php if (!$categoryHasData): ?>
        <p class="table-empty">No category performance recorded yet.</p>
      <?php else: ?>
        <canvas id="categoryChart" height="220"></canvas>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$extraScripts = <<<JS
<script>
  const timeframeForm = document.getElementById('dashboard-timeframe-form');
  if (timeframeForm) {
    const timeframeSelect = document.getElementById('dashboard-timeframe');
    const customContainer = document.getElementById('dashboard-custom-range');
    const startInput = document.getElementById('dashboard-start-date');
    const endInput = document.getElementById('dashboard-end-date');

    const toggleCustomFields = () => {
      const isCustom = timeframeSelect && timeframeSelect.value === 'custom';
      if (customContainer) {
        customContainer.style.display = isCustom ? 'flex' : 'none';
      }
      if (startInput) {
        startInput.disabled = !isCustom;
      }
      if (endInput) {
        endInput.disabled = !isCustom;
      }
    };

    if (timeframeSelect) {
      timeframeSelect.addEventListener('change', () => {
        const isCustom = timeframeSelect.value === 'custom';
        toggleCustomFields();
        if (!isCustom) {
          timeframeForm.submit();
        }
      });
      toggleCustomFields();
    }
  }

  const salesSeries = $salesSeriesJson;
  const categorySeries = $categorySeriesJson;

  const formatCurrency = value => '₱' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const formatCurrencyAxis = value => '₱' + Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
  const formatInteger = value => Number(value || 0).toLocaleString();

  const updatePressedState = (buttons, activeValue) => {
    if (!buttons || !buttons.length) {
      return;
    }
    buttons.forEach(button => {
      const value = button.dataset.salesMetric || button.dataset.categoryMetric;
      const isActive = value === activeValue;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  };

  const salesChartElement = document.getElementById('salesChart');
  if (salesChartElement) {
    const seriesData = Array.isArray(salesSeries) && salesSeries.length ? salesSeries : [{ label: 'No Data', revenue: 0, orders: 0 }];
    const salesLabels = seriesData.map(item => typeof item.label === 'string' ? item.label : '');
    const salesCtx = salesChartElement.getContext('2d');
    const gradient = salesCtx.createLinearGradient(0, 0, 0, salesChartElement.height || 400);
    gradient.addColorStop(0, 'rgba(231, 76, 60, 0.9)');
    gradient.addColorStop(1, 'rgba(241, 196, 15, 0.9)');

    let currentSalesMetric = 'revenue';
    const salesMetricLabel = document.getElementById('salesMetricLabel');
    const salesMetricButtons = document.querySelectorAll('[data-sales-metric]');

    const salesChart = new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: salesLabels,
        datasets: [{
          label: 'Revenue (₱)',
          data: seriesData.map(item => Number(item.revenue || 0)),
          backgroundColor: gradient,
          borderColor: '#e74c3c',
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#e74c3c',
          pointRadius: 5,
          pointHoverRadius: 7
        }]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            labels: {
              color: '#2c3e50',
              font: { size: 14 }
            }
          },
          tooltip: {
            callbacks: {
              label: context => {
                const entry = seriesData[context.dataIndex] || {};
                const revenueText = 'Revenue: ' + formatCurrency(entry.revenue);
                const ordersText = 'Orders: ' + formatInteger(entry.orders);
                return currentSalesMetric === 'orders'
                  ? [ordersText, revenueText]
                  : [revenueText, ordersText];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#2c3e50' },
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#2c3e50',
              callback: value => currentSalesMetric === 'orders'
                ? formatInteger(value)
                : formatCurrencyAxis(value)
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          }
        }
      }
    });

    const updateSalesMetric = (metric, updateButtons = true) => {
      currentSalesMetric = metric === 'orders' ? 'orders' : 'revenue';
      const datasetValues = currentSalesMetric === 'orders'
        ? seriesData.map(item => Number(item.orders || 0))
        : seriesData.map(item => Number(item.revenue || 0));
      salesChart.data.datasets[0].data = datasetValues;
      salesChart.data.datasets[0].label = currentSalesMetric === 'orders' ? 'Orders' : 'Revenue (₱)';
      if (salesMetricLabel) {
        salesMetricLabel.textContent = currentSalesMetric === 'orders' ? 'Order count' : 'Revenue';
      }
      if (updateButtons) {
        updatePressedState(salesMetricButtons, currentSalesMetric);
      }
      salesChart.update();
    };

    salesMetricButtons.forEach(button => {
      button.addEventListener('click', () => {
        const metric = button.dataset.salesMetric;
        if (!metric || metric === currentSalesMetric) {
          return;
        }
        updateSalesMetric(metric);
      });
    });

    updateSalesMetric('revenue', false);
    updatePressedState(salesMetricButtons, 'revenue');
  }

  const categoryChartElement = document.getElementById('categoryChart');
  if (categoryChartElement) {
    const seriesData = Array.isArray(categorySeries) && categorySeries.length ? categorySeries : [{ label: 'No Data', revenue: 0, units: 0 }];
    const categoryLabels = seriesData.map(item => typeof item.label === 'string' ? item.label : '');
    const palette = ['#e74c3c', '#f39c12', '#3498db', '#2ecc71', '#9b59b6', '#16a085', '#e67e22'];
    const barColors = categoryLabels.map((_, index) => palette[index % palette.length]);
    const categoryCtx = categoryChartElement.getContext('2d');

    let currentCategoryMetric = 'revenue';
    const categoryMetricLabel = document.getElementById('categoryMetricLabel');
    const categoryMetricButtons = document.querySelectorAll('[data-category-metric]');

    const categoryChart = new Chart(categoryCtx, {
      type: 'bar',
      data: {
        labels: categoryLabels,
        datasets: [{
          label: 'Revenue (₱)',
          data: seriesData.map(item => Number(item.revenue || 0)),
          backgroundColor: barColors,
          borderRadius: 8,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => {
                const entry = seriesData[context.dataIndex] || {};
                const revenueText = formatCurrency(entry.revenue);
                const unitsText = formatInteger(entry.units);
                const revenueLabel = 'Revenue: ' + revenueText;
                const unitsLabel = 'Units sold: ' + unitsText;
                if (currentCategoryMetric === 'units') {
                  return [context.label + ': ' + unitsText + ' units', revenueLabel];
                }
                return [context.label + ': ' + revenueText, unitsLabel];
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#2c3e50' },
            grid: { display: false }
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#2c3e50',
              callback: value => currentCategoryMetric === 'units'
                ? formatInteger(value)
                : formatCurrencyAxis(value)
            },
            grid: { color: 'rgba(0,0,0,0.05)' }
          }
        }
      }
    });

    const updateCategoryMetric = (metric, updateButtons = true) => {
      currentCategoryMetric = metric === 'units' ? 'units' : 'revenue';
      const datasetValues = currentCategoryMetric === 'units'
        ? seriesData.map(item => Number(item.units || 0))
        : seriesData.map(item => Number(item.revenue || 0));
      categoryChart.data.datasets[0].data = datasetValues;
      categoryChart.data.datasets[0].label = currentCategoryMetric === 'units' ? 'Units Sold' : 'Revenue (₱)';
      if (categoryMetricLabel) {
        categoryMetricLabel.textContent = currentCategoryMetric === 'units' ? 'Units sold' : 'Revenue';
      }
      if (updateButtons) {
        updatePressedState(categoryMetricButtons, currentCategoryMetric);
      }
      categoryChart.update();
    };

    categoryMetricButtons.forEach(button => {
      button.addEventListener('click', () => {
        const metric = button.dataset.categoryMetric;
        if (!metric || metric === currentCategoryMetric) {
          return;
        }
        updateCategoryMetric(metric);
      });
    });

    updateCategoryMetric('revenue', false);
    updatePressedState(categoryMetricButtons, 'revenue');
  }
</script>
JS;
include 'includes/footer.php';
