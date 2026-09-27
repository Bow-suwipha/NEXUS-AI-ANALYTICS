<?php
require 'db.php';

$q = $_GET['q'] ?? '';

if (empty($q)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT product_code, product_name, category, unit_price, stock_quantity as stock, status 
                       FROM products 
                       WHERE product_code LIKE ? OR product_name LIKE ?");
$likeQ = "%" . $q . "%";
$stmt->execute([$likeQ, $likeQ]);
$results = $stmt->fetchAll();

$formatted = [];
foreach ($results as $r) {
    $formatted[] = [
        "product_code" => $r['product_code'],
        "product_name" => $r['product_name'],
        "category" => $r['category'],
        "unit_price" => (float)$r['unit_price'],
        "stock" => (int)$r['stock'],
        "status" => (int)$r['status']
    ];
}

echo json_encode($formatted);
?>
