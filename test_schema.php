<?php
require 'api/db.php';
$stmt = $pdo->query('SHOW CREATE TABLE products');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
?>
