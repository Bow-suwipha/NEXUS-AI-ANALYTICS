<?php
require 'db.php';

$models = ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'llama3-8b-8192'];

for ($i = 1; $i <= 4; $i++) {
    $stmt = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = $i LIMIT 1");
    $key = $stmt->fetchColumn();
    if (!$key) continue;
    
    echo "Testing User ID: $i (Key: " . substr($key, 0, 10) . "...)\n";
    
    foreach ($models as $model) {
        $payload = [
            "model" => $model,
            "messages" => [["role" => "user", "content" => "hi"]]
        ];
        
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key, 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $res = json_decode($response, true);
        if ($httpcode == 200) {
            echo "  [SUCCESS] Model: $model\n";
            break; // found a working model for this key
        } else {
            $err = isset($res['error']['message']) ? $res['error']['message'] : "HTTP $httpcode";
            echo "  [FAIL] Model: $model -> $err\n";
        }
    }
    echo "--------------------------\n";
}
?>
