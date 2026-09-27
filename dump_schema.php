<?php
require 'api/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "Table: $table\n";
    $stmt2 = $pdo->query("SHOW CREATE TABLE `$table`");
    $create = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo $create['Create Table'] . "\n\n";
}
?>
