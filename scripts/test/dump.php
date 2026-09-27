<?php
require 'api/db.php';
$products = $pdo->query("SELECT * FROM products LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$insights = $pdo->query("SELECT * FROM ai_insights LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "PRODUCTS:\n";
print_r($products);
echo "\nINSIGHTS:\n";
print_r($insights);
?>
