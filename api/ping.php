<?php
header('Content-Type: application/json');

// Simulate ping to POS database
// In a real scenario, this would attempt a database connection
$latency = rand(10, 45);

echo json_encode([
    "status" => "online",
    "latency_ms" => $latency
]);
?>
