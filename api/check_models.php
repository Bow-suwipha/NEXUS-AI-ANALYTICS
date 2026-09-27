<?php
require_once 'db.php';

// ดึง API Key ของคุณมา
$stmt = $pdo->query("SELECT groq_api_key FROM system_settings LIMIT 1");
$api_key = $stmt->fetchColumn();

// ยิงไปถาม Groq ว่า Key นี้ใช้โมเดลอะไรได้บ้าง
$ch = curl_init('https://api.groq.com/openai/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
