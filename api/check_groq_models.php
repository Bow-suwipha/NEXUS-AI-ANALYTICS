<?php
require 'db.php';

for ($i = 1; $i <= 4; $i++) {
    $stmt = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = $i LIMIT 1");
    $key = $stmt->fetchColumn();
    if (!$key) continue;
    
    echo "Testing User ID: $i (Key: " . substr($key, 0, 10) . "...)\n";
    
    $ch = curl_init('https://api.groq.com/openai/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode == 200) {
        $res = json_decode($response, true);
        $modelIds = array_column($res['data'] ?? [], 'id');
        echo "  [SUCCESS] Available Models: " . implode(', ', $modelIds) . "\n";
    } else {
        $res = json_decode($response, true);
        $err = isset($res['error']['message']) ? $res['error']['message'] : "HTTP $httpcode";
        echo "  [FAIL] $err\n";
    }
    echo "--------------------------\n";
}
?>
