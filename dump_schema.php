<?php
require 'api/db.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$result = [];
foreach($tables as $t) {
    $result[$t] = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
}
file_put_contents('schema.json', json_encode($result));
echo "Done";
