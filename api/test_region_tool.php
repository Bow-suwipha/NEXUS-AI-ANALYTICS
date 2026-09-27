<?php
require 'db.php';
define('GROQ_MODEL', 'openai/gpt-oss-20b');

$stmt = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = 1 LIMIT 1");
$api_key = $stmt->fetchColumn();

// Simulate Region Tool Data
$region = 'กรุงเทพฯ';
$result = [];
$stmt = $pdo->prepare("SELECT branch, SUM(total_price) as branch_total FROM sales_data WHERE region = ? GROUP BY branch ORDER BY branch_total DESC");
$stmt->execute([$region]);
$result['region_branches_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT SUM(total_price) as region_total FROM sales_data WHERE region = ?");
$stmt2->execute([$region]);
$result['region_total'] = $stmt2->fetch(PDO::FETCH_ASSOC)['region_total'] ?? 0;

$stmt3 = $pdo->prepare("SELECT product_name, SUM(quantity) as qty, SUM(total_price) as total FROM sales_data WHERE region = ? GROUP BY product_name ORDER BY total DESC LIMIT 3");
$stmt3->execute([$region]);
$result['region_top_products'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Construct Messages
$messages = [
    [
        "role" => "system",
        "content" => "You are an AI Sales Analyst."
    ],
    [
        "role" => "user",
        "content" => "วิเคราะห์ยอดขายพื้นที่ กรุงเทพฯ"
    ],
    [
        "role" => "assistant",
        "content" => null,
        "tool_calls" => [
            [
                "id" => "call_12345",
                "type" => "function",
                "function" => [
                    "name" => "get_sales_data",
                    "arguments" => "{\"region\":\"กรุงเทพฯ\"}"
                ]
            ]
        ]
    ],
    [
        "role" => "tool",
        "tool_call_id" => "call_12345",
        "name" => "get_sales_data",
        "content" => json_encode($result, JSON_UNESCAPED_UNICODE)
    ]
];

$payload2 = [
    "model" => GROQ_MODEL,
    "messages" => $messages,
    "temperature" => 0.5
];

$ch2 = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload2));
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$response2 = curl_exec($ch2);
curl_close($ch2);

echo $response2;
?>
