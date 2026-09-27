<?php
require 'db.php';

$stmt = $pdo->query("SELECT id, insight_type, title, description, severity, related_branch FROM ai_insights WHERE is_active = 1 ORDER BY created_at DESC LIMIT 4");
$insights = $stmt->fetchAll();

echo json_encode($insights);
?>
