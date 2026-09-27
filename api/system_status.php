<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM server_metrics ORDER BY recorded_at DESC LIMIT 1");
$metric = $stmt->fetch();

if ($metric) {
    echo json_encode([
        "tokens_used" => (int)$metric['tokens_used'],
        "tokens_limit" => (int)$metric['tokens_limit'],
        "success_rate" => (float)$metric['success_rate'],
        "active_agents" => (int)$metric['active_agents']
    ]);
} else {
    echo json_encode([
        "tokens_used" => 0,
        "tokens_limit" => 2000000,
        "success_rate" => 0.0,
        "active_agents" => 0
    ]);
}
?>
