<?php
require 'db.php';

$period = $_GET['period'] ?? 'month';
$endDate = date('Y-m-d');

if ($period === 'today') {
    $startDate = $endDate;
} elseif ($period === 'week') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
} elseif ($period === 'quarter') {
    $startDate = date('Y-m-d', strtotime('-3 months'));
} else {
    // month
    $startDate = date('Y-m-01');
}

// 1. Total Sales and Orders
$stmt = $pdo->prepare("SELECT SUM(total_price) as total_sales, COUNT(*) as total_orders FROM sales_data WHERE sale_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totals = $stmt->fetch();
$totalSales = $totals['total_sales'] ?? 0;
$totalOrders = $totals['total_orders'] ?? 0;

// 2. Best Product
$stmt = $pdo->prepare("SELECT p.product_name, SUM(s.quantity) as total_qty 
                       FROM sales_data s 
                       JOIN products p ON s.product_id = p.product_id 
                       WHERE s.sale_date BETWEEN ? AND ? 
                       GROUP BY p.product_name 
                       ORDER BY total_qty DESC LIMIT 1");
$stmt->execute([$startDate, $endDate]);
$bestProduct = $stmt->fetch();
$bestProductName = $bestProduct ? $bestProduct['product_name'] : "-";
$bestProductQty = $bestProduct ? $bestProduct['total_qty'] : 0;

// 3. Daily Sales for Chart
$stmt = $pdo->prepare("SELECT sale_date, SUM(total_price) as daily_total 
                       FROM sales_data 
                       WHERE sale_date BETWEEN ? AND ? 
                       GROUP BY sale_date 
                       ORDER BY sale_date ASC");
$stmt->execute([$startDate, $endDate]);
$dailySales = $stmt->fetchAll();

$labels = [];
$values = [];
foreach ($dailySales as $ds) {
    $labels[] = date('d M', strtotime($ds['sale_date']));
    $values[] = (float)$ds['daily_total'];
}

// Calculate previous period dates for growth calculation
$prevStartDate = '';
$prevEndDate = '';
if ($period === 'today') {
    $prevStartDate = date('Y-m-d', strtotime('-1 day', strtotime($startDate)));
    $prevEndDate = $prevStartDate;
} elseif ($period === 'week') {
    $prevStartDate = date('Y-m-d', strtotime('-14 days'));
    $prevEndDate = date('Y-m-d', strtotime('-8 days'));
} elseif ($period === 'quarter') {
    $prevStartDate = date('Y-m-d', strtotime('-6 months'));
    $prevEndDate = date('Y-m-d', strtotime('-3 months - 1 day'));
} else {
    // month
    $prevStartDate = date('Y-m-d', strtotime('-1 month', strtotime($startDate)));
    $prevEndDate = date('Y-m-t', strtotime($prevStartDate)); // Last day of previous month
}

// 4. Previous Sales for Growth
$stmt = $pdo->prepare("SELECT SUM(total_price) as prev_sales FROM sales_data WHERE sale_date BETWEEN ? AND ?");
$stmt->execute([$prevStartDate, $prevEndDate]);
$prevTotals = $stmt->fetch();
$prevSales = $prevTotals['prev_sales'] ?? 0;

$growthPercent = 0;
if ($prevSales > 0) {
    $growthPercent = (($totalSales - $prevSales) / $prevSales) * 100;
} elseif ($totalSales > 0) {
    $growthPercent = 100; // From 0 to something is 100% growth
}

echo json_encode([
    "total_sales" => (float)$totalSales,
    "total_orders" => (int)$totalOrders,
    "growth_percent" => round($growthPercent, 1),
    "best_product" => [
        "name" => $bestProductName,
        "quantity" => (int)$bestProductQty
    ],
    "chart" => [
        "labels" => $labels,
        "values" => $values
    ]
]);
?>
