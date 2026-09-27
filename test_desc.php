<?php
require 'db.php';
$stmt = $pdo->query("DESCRIBE reports");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
