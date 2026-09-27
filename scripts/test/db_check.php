<?php
require 'api/db.php';
$stmt = $pdo->query("DESCRIBE sales_data;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
