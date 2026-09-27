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

// กำหนดเป้าหมายคงที่ตามช่วงเวลา (ตามที่คุณลูกค้าต้องการ)
$global_target = 20000; // ค่าเริ่มต้น (เดือน)
if ($period === 'today') {
    $global_target = 5000;
} elseif ($period === 'week') {
    $global_target = 12000;
} elseif ($period === 'month') {
    $global_target = 20000;
} elseif ($period === 'quarter') {
    $global_target = 60000;
} elseif ($period === 'year') {
    $global_target = 240000;
}

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
