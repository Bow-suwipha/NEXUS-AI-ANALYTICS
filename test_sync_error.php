<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require 'api/sync_csv.php';
} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile();
}
?>
