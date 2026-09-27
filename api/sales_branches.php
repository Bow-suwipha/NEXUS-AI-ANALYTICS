<?php
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

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
        b.branch_code, b.branch_name, 
        COALESCE(SUM(s.total_price), 0) as total_sales
    FROM branches b 
    LEFT JOIN sales_data s ON b.branch_id = s.branch_id AND s.sale_date BETWEEN ? AND ?
    GROUP BY b.branch_code, b.branch_name
    ORDER BY b.branch_code ASC");

$stmt->execute([$startDate, $endDate]);
$results = $stmt->fetchAll();

// Find the maximum sales among all branches to use as the baseline target
$max_sales = 0;
foreach ($results as $r) {
    $s = (float)$r['total_sales'];
    if ($s > $max_sales) {
        $max_sales = $s;
    }
}

// If all sales are 0, set a default fallback target
$global_target = $max_sales > 0 ? $max_sales : 10000;

$formatted = [];
foreach ($results as $r) {
    $sales = (float)$r['total_sales'];
    $target = $global_target;
    
    $diff_percent = $target > 0 ? (($sales - $target) / $target) * 100 : 0;
    
    $formatted[] = [
        "branch_code" => $r['branch_code'],
        "branch_name" => str_replace('สาขา', '', $r['branch_name']), // Remove 'สาขา' prefix for UI
        "sales" => $sales,
        "target" => $target,
        "diff_percent" => round($diff_percent)
    ];
}

echo json_encode($formatted);
?>
