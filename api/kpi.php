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

echo json_encode([
    "total_sales" => (float)$totalSales,
    "total_orders" => (int)$totalOrders,
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
