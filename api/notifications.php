<?php
require 'db.php';

// In a real app, use user_id from session/JWT. Hardcoded to 1 for now.
$user_id = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark all as read
    $pdo->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND is_read = 0");
    echo json_encode(['success' => true]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_unread->execute([$user_id]);
$unread_count = $stmt_unread->fetchColumn();

echo json_encode([
    'unread_count' => (int)$unread_count,
    'notifications' => $notifications
]);
?>
