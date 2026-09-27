<?php
require 'db.php';
$stmt = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = 4 LIMIT 1");
$key = $stmt->fetchColumn();
if (!$key) die('No key found');

$ch = curl_init('https://api.groq.com/openai/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>
