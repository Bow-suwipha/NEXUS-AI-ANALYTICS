<?php
require 'db.php';
$stmt = $pdo->query("SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, SUM(total_price) as total FROM sales_data GROUP BY month ORDER BY month;");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
