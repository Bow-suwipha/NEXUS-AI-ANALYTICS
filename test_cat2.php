<?php
require 'api/db.php';
$stmt = $pdo->query("SELECT DISTINCT category FROM sales_data");
$cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('cats.txt', implode("\n", $cats));
echo "Done";
