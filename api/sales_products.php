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
} elseif ($period === 'year') {
    $startDate = date('Y-m-d', strtotime('-1 year'));
} else {
    // month
    $startDate = date('Y-m-01');
}

$stmt = $pdo->prepare("SELECT 
        p.product_code, p.product_name, p.category, 
        SUM(s.quantity) as total_quantity, 
        SUM(s.total_price) as total_sales,
        p.stock_quantity as stock
    FROM sales_data s 
    JOIN products p ON s.product_id = p.product_id 
    WHERE s.sale_date BETWEEN ? AND ? 
    GROUP BY p.product_code, p.product_name, p.category, p.stock_quantity 
    ORDER BY total_sales DESC");

$stmt->execute([$startDate, $endDate]);
$results = $stmt->fetchAll();

$formatted = [];
$totalAllSales = 0;
foreach ($results as $r) {
    $totalAllSales += (float)$r['total_sales'];
}

foreach ($results as $r) {
    $sales = (float)$r['total_sales'];
    $share = $totalAllSales > 0 ? round(($sales / $totalAllSales) * 100, 1) : 0;
    
    // Fake trend for now
    $trend = rand(1, 20);
    $trendDir = rand(0, 1) ? 'up' : 'down';
    
    $formatted[] = [
        "sku" => $r['product_code'],
        "name" => $r['product_name'],
        "category" => $r['category'],
        "units" => (int)$r['total_quantity'],
        "sales" => $sales,
        "share_percent" => $share,
        "trend_direction" => $trendDir,
        "trend" => $trend,
        "stock_quantity" => (int)$r['stock']
    ];
}

echo json_encode($formatted);
?>
