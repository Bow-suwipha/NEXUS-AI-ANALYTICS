<?php
require 'db.php';

$stmt = $pdo->query("SELECT id, report_name, report_type, file_path, created_at FROM reports ORDER BY created_at DESC");
$reports = $stmt->fetchAll();

echo json_encode($reports);
?>
