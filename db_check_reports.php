<?php
require 'api/db.php';
$stmt = $pdo->query("DESCRIBE reports;");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query("SELECT * FROM reports LIMIT 5;");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
?>
