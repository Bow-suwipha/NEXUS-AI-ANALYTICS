<?php
require 'api/db.php';
$stmt = $pdo->query("SELECT DISTINCT category FROM sales_data");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
